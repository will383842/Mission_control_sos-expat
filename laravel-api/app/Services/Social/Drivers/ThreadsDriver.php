<?php

namespace App\Services\Social\Drivers;

use App\Models\SocialPost;
use App\Models\SocialToken;
use App\Services\Social\AbstractSocialDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta Threads driver (public API since 2024-06).
 *
 * Required env:
 *   THREADS_CLIENT_ID, THREADS_CLIENT_SECRET, THREADS_REDIRECT_URI
 *
 * OAuth scopes:
 *   threads_basic, threads_content_publish, threads_manage_replies,
 *   threads_read_replies
 *
 * Token model:
 *   - Short-lived (1h) → exchange to long-lived (60d)
 *   - Long-lived can be refreshed via threads_refresh_access_token
 *
 * Publishing is a 2-step container dance:
 *   1. POST /v1.0/me/threads        (create container, returns creation_id)
 *   2. POST /v1.0/me/threads_publish (publish the container, returns thread_id)
 *
 * Rate limit: 250 posts / 24h / user — enforced before publish.
 */
class ThreadsDriver extends AbstractSocialDriver
{
    private const GRAPH         = 'https://graph.threads.net';
    private const OAUTH         = 'https://threads.net/oauth/authorize';
    private const TOKEN_URL     = 'https://graph.threads.net/oauth/access_token';
    private const LL_TOKEN_URL  = 'https://graph.threads.net/access_token';
    private const REFRESH_URL   = 'https://graph.threads.net/refresh_access_token';
    private const SCOPES        = 'threads_basic,threads_content_publish,threads_manage_replies,threads_read_replies';
    private const RATE_LIMIT    = 250;  // posts per 24h per user

    public function platform(): string { return 'threads'; }

    public function supportedAccountTypes(): array { return ['personal']; }
    public function supportsFirstComment(): bool   { return false; } // no native concept
    public function supportsHashtags(): bool       { return true; }   // searchable, not clickable
    public function requiresImage(): bool          { return false; }
    public function maxContentLength(): int        { return 500; }

    private function v(): string { return config('services.threads.api_version', 'v1.0'); }

    // ── Publishing (2-step container) ──────────────────────────────────

    public function publish(SocialPost $post, ?string $accountType = null): ?string
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) {
            $this->logError('publish: no valid token');
            return null;
        }

        // Enforce 250 posts / 24h rate limit
        $count24h = SocialPost::forPlatform('threads')
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subDay())
            ->count();
        if ($count24h >= self::RATE_LIMIT) {
            $this->logError('publish: rate limit reached', [
                'count_24h' => $count24h,
                'limit'     => self::RATE_LIMIT,
            ]);
            return null;
        }

        $hashtags = array_map(fn($h) => "#{$h}", $post->hashtags ?? []);
        $text     = $post->hook . "\n\n" . $post->body
                  . ($hashtags ? "\n\n" . implode(' ', $hashtags) : '');
        $text     = mb_substr($text, 0, $this->maxContentLength());

        $userId = $token->platform_user_id;

        try {
            // Step 1: create container
            $payload = [
                'media_type'   => $post->featured_image_url ? 'IMAGE' : 'TEXT',
                'text'         => $text,
                'access_token' => $token->access_token,
            ];
            if ($post->featured_image_url) {
                $payload['image_url'] = $post->featured_image_url;
            }

            $create = Http::asForm()->post(self::GRAPH . '/' . $this->v() . "/{$userId}/threads", $payload);
            if (!$create->successful()) {
                $this->handleApiError($create, $token);
                throw new \RuntimeException("Threads create container failed: HTTP {$create->status()} — " . mb_substr($create->body(), 0, 300));
            }

            $creationId = $create->json()['id'] ?? null;
            if (!$creationId) throw new \RuntimeException('Threads container missing id');

            // Threads docs recommend a brief wait (~30s) for media containers
            if ($post->featured_image_url) sleep(2);

            // Step 2: publish container
            $publish = Http::asForm()->post(self::GRAPH . '/' . $this->v() . "/{$userId}/threads_publish", [
                'creation_id'  => $creationId,
                'access_token' => $token->access_token,
            ]);
            if (!$publish->successful()) {
                $this->handleApiError($publish, $token);
                throw new \RuntimeException("Threads publish failed: HTTP {$publish->status()} — " . mb_substr($publish->body(), 0, 300));
            }

            $threadId = $publish->json()['id'] ?? null;
            Log::info('ThreadsDriver: published', ['post_id' => $post->id, 'thread_id' => $threadId]);
            return $threadId;

        } catch (\Throwable $e) {
            $this->logError('publish failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function getComments(string $platformPostId, ?string $accountType = null): array
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) return [];

        try {
            $r = Http::get(self::GRAPH . '/' . $this->v() . "/{$platformPostId}/replies", [
                'fields'       => 'id,username,text,timestamp,from',
                'access_token' => $token->access_token,
            ]);
            if (!$r->successful()) {
                $this->logError('getComments failed', ['status' => $r->status()]);
                return [];
            }

            $out = [];
            foreach (($r->json()['data'] ?? []) as $c) {
                $out[] = [
                    'platform_comment_id' => $c['id'] ?? '',
                    'author_name'         => $c['username'] ?? ($c['from']['username'] ?? 'Inconnu'),
                    'author_platform_id'  => $c['from']['id'] ?? null,
                    'text'                => $c['text'] ?? '',
                    'commented_at'        => isset($c['timestamp']) ? \Carbon\Carbon::parse($c['timestamp']) : now(),
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logError('getComments exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function postReply(string $platformPostId, string $text, ?string $accountType = null): bool
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) return false;

        $userId = $token->platform_user_id;

        try {
            // Reply = 2-step container with reply_to_id
            $create = Http::asForm()->post(self::GRAPH . '/' . $this->v() . "/{$userId}/threads", [
                'media_type'   => 'TEXT',
                'text'         => mb_substr($text, 0, $this->maxContentLength()),
                'reply_to_id'  => $platformPostId,
                'access_token' => $token->access_token,
            ]);
            if (!$create->successful()) {
                $this->logError('postReply create failed', ['status' => $create->status(), 'body' => mb_substr($create->body(), 0, 300)]);
                return false;
            }
            $creationId = $create->json()['id'] ?? null;
            if (!$creationId) return false;

            $publish = Http::asForm()->post(self::GRAPH . '/' . $this->v() . "/{$userId}/threads_publish", [
                'creation_id'  => $creationId,
                'access_token' => $token->access_token,
            ]);
            return $publish->successful();
        } catch (\Throwable $e) {
            $this->logError('postReply exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function postFirstComment(string $platformPostId, string $text, ?string $accountType = null): bool
    {
        // Threads has no first-comment concept — skip cleanly
        return false;
    }

    public function fetchAnalytics(string $platformPostId, ?string $accountType = null): array
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) return [];

        try {
            $r = Http::get(self::GRAPH . '/' . $this->v() . "/{$platformPostId}/insights", [
                'metric'       => 'views,likes,replies,reposts,quotes',
                'access_token' => $token->access_token,
            ]);
            if (!$r->successful()) return [];

            $out = ['reach' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'clicks' => 0];
            foreach (($r->json()['data'] ?? []) as $m) {
                $name = $m['name'] ?? '';
                $val  = $m['values'][0]['value'] ?? 0;
                match ($name) {
                    'views'   => $out['reach']    = (int) $val,
                    'likes'   => $out['likes']    = (int) $val,
                    'replies' => $out['comments'] = (int) $val,
                    'reposts',
                    'quotes'  => $out['shares']  += (int) $val,
                    default   => null,
                };
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logError('fetchAnalytics exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── OAuth ──────────────────────────────────────────────────────────

    public function getOAuthUrl(string $accountType, string $state): string
    {
        $params = http_build_query([
            'client_id'     => config('services.threads.client_id'),
            'redirect_uri'  => config('services.threads.redirect_uri'),
            'state'         => $state,
            'scope'         => self::SCOPES,
            'response_type' => 'code',
        ]);
        return self::OAUTH . '?' . $params;
    }

    public function handleOAuthCallback(string $code, string $accountType): ?SocialToken
    {
        try {
            // 1. code → short-lived token (1h)
            $r = Http::asForm()->post(self::TOKEN_URL, [
                'client_id'     => config('services.threads.client_id'),
                'client_secret' => config('services.threads.client_secret'),
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => config('services.threads.redirect_uri'),
                'code'          => $code,
            ]);
            if (!$r->successful()) {
                $this->logError('OAuth code exchange failed', ['body' => mb_substr($r->body(), 0, 300)]);
                return null;
            }
            $data       = $r->json();
            $shortToken = $data['access_token'] ?? null;
            $userId     = (string) ($data['user_id'] ?? '');
            if (!$shortToken || !$userId) return null;

            // 2. short-lived → long-lived (60d)
            $r2 = Http::get(self::LL_TOKEN_URL, [
                'grant_type'    => 'th_exchange_token',
                'client_secret' => config('services.threads.client_secret'),
                'access_token'  => $shortToken,
            ]);
            $data2     = $r2->json();
            $longToken = $data2['access_token'] ?? $shortToken;
            $expiresIn = (int) ($data2['expires_in'] ?? 5184000);

            // 3. fetch profile name
            $name = null;
            $r3 = Http::get(self::GRAPH . '/' . $this->v() . "/{$userId}", [
                'fields'       => 'id,username,name',
                'access_token' => $longToken,
            ]);
            if ($r3->successful()) {
                $j = $r3->json();
                $name = $j['name'] ?? $j['username'] ?? null;
            }

            return SocialToken::updateOrCreate(
                ['platform' => 'threads', 'account_type' => 'personal'],
                [
                    'access_token'       => $longToken,
                    'refresh_token'      => null, // refreshed via /refresh_access_token, not via grant
                    'expires_at'         => now()->addSeconds($expiresIn),
                    'platform_user_id'   => $userId,
                    'platform_user_name' => $name,
                    'scope'              => self::SCOPES,
                ]
            );

        } catch (\Throwable $e) {
            $this->logError('handleOAuthCallback exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function resolveToken(string $accountType): ?SocialToken
    {
        $token = $this->findToken($accountType);
        if (!$token) return null;

        // Auto-refresh if expiring within 7 days
        if ($token->expires_at && $token->expires_at->diffInDays(now(), false) >= -7) {
            $refreshed = $this->refreshToken($token);
            if ($refreshed) $token = $refreshed;
        }

        return $token->isValid() ? $token : null;
    }

    private function refreshToken(SocialToken $token): ?SocialToken
    {
        try {
            $r = Http::get(self::REFRESH_URL, [
                'grant_type'   => 'th_refresh_token',
                'access_token' => $token->access_token,
            ]);
            if (!$r->successful()) {
                $this->notifyTokenExpired($token->account_type, $r->status());
                return null;
            }
            $d = $r->json();
            $token->access_token = $d['access_token'] ?? $token->access_token;
            $token->expires_at   = now()->addSeconds((int) ($d['expires_in'] ?? 5184000));
            $token->save();
            return $token->fresh();
        } catch (\Throwable $e) {
            $this->logError('refreshToken exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function handleApiError(\Illuminate\Http\Client\Response $r, SocialToken $token): void
    {
        $err = $r->json()['error'] ?? null;
        if (!$err) return;

        // OAuth token errors → notify for reconnect
        if (in_array($err['code'] ?? 0, [190, 102, 463], true)) {
            $this->notifyTokenExpired($token->account_type, $r->status());
        }

        Log::warning('ThreadsDriver: API error', [
            'code'    => $err['code'] ?? null,
            'message' => $err['message'] ?? '',
        ]);
    }
}
