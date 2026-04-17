<?php

namespace App\Services\Social\Drivers;

use App\Models\SocialPost;
use App\Models\SocialToken;
use App\Services\Social\AbstractSocialDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Instagram Business driver (Meta Graph API).
 *
 * Required env:
 *   INSTAGRAM_CLIENT_ID, INSTAGRAM_CLIENT_SECRET, INSTAGRAM_REDIRECT_URI,
 *   INSTAGRAM_BUSINESS_ACCOUNT_ID
 *
 * Shares the same Meta App as Facebook — but the Business account must be
 * linked to a Facebook Page. The IG business account id is fetched via
 *   GET /{page-id}?fields=instagram_business_account
 *
 * OAuth scopes (Meta App Review needed):
 *   instagram_basic, instagram_content_publish, instagram_manage_comments,
 *   pages_show_list, pages_read_engagement
 *
 * Publishing requires an image_url (or video_url) — we throw if none.
 * Two-step container pattern (same as Threads).
 */
class InstagramDriver extends AbstractSocialDriver
{
    private const OAUTH_DIALOG = 'https://www.facebook.com/{v}/dialog/oauth';
    private const GRAPH        = 'https://graph.facebook.com';
    private const SCOPES       = 'instagram_basic,instagram_content_publish,instagram_manage_comments,pages_show_list,pages_read_engagement';

    public function platform(): string { return 'instagram'; }

    public function supportedAccountTypes(): array { return ['business']; }
    public function supportsFirstComment(): bool   { return true; }
    public function supportsHashtags(): bool       { return true; }
    public function requiresImage(): bool          { return true; }
    public function maxContentLength(): int        { return 2200; }

    private function v(): string { return config('services.instagram.graph_version', 'v19.0'); }

    // ── Publishing (2-step container, image required) ──────────────────

    public function publish(SocialPost $post, ?string $accountType = null): ?string
    {
        if (!$post->featured_image_url) {
            $this->logError('publish: featured_image_url required for Instagram');
            return null;
        }

        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) {
            $this->logError('publish: no valid token');
            return null;
        }

        $igUserId = $token->platform_user_id; // Instagram business account id
        $hashtags = array_map(fn($h) => "#{$h}", $post->hashtags ?? []);
        $caption  = $post->hook . "\n\n" . $post->body
                  . ($hashtags ? "\n\n" . implode(' ', $hashtags) : '');
        $caption  = mb_substr($caption, 0, $this->maxContentLength());

        try {
            // Step 1: create media container
            $create = Http::asForm()->post(self::GRAPH . '/' . $this->v() . "/{$igUserId}/media", [
                'image_url'    => $post->featured_image_url,
                'caption'      => $caption,
                'access_token' => $token->access_token,
            ]);
            if (!$create->successful()) {
                $this->handleApiError($create, $token);
                throw new \RuntimeException("Instagram create container failed: HTTP {$create->status()} — " . mb_substr($create->body(), 0, 300));
            }

            $creationId = $create->json()['id'] ?? null;
            if (!$creationId) throw new \RuntimeException('Instagram container missing id');

            // Brief wait — IG sometimes needs ~3s to process the image
            sleep(3);

            // Step 2: publish container
            $publish = Http::asForm()->post(self::GRAPH . '/' . $this->v() . "/{$igUserId}/media_publish", [
                'creation_id'  => $creationId,
                'access_token' => $token->access_token,
            ]);
            if (!$publish->successful()) {
                $this->handleApiError($publish, $token);
                throw new \RuntimeException("Instagram publish failed: HTTP {$publish->status()} — " . mb_substr($publish->body(), 0, 300));
            }

            $mediaId = $publish->json()['id'] ?? null;
            Log::info('InstagramDriver: published', ['post_id' => $post->id, 'media_id' => $mediaId]);
            return $mediaId;

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
            $r = Http::get(self::GRAPH . '/' . $this->v() . "/{$platformPostId}/comments", [
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
        return $this->postCommentInternal($platformPostId, $text, $accountType, 'reply');
    }

    public function postFirstComment(string $platformPostId, string $text, ?string $accountType = null): bool
    {
        return $this->postCommentInternal($platformPostId, $text, $accountType, 'first_comment');
    }

    private function postCommentInternal(string $mediaId, string $text, ?string $accountType, string $context): bool
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) return false;

        try {
            $r = Http::asForm()->post(self::GRAPH . '/' . $this->v() . "/{$mediaId}/comments", [
                'message'      => $text,
                'access_token' => $token->access_token,
            ]);
            if ($r->successful()) {
                Log::info("InstagramDriver: {$context} posted", ['media_id' => $mediaId]);
                return true;
            }
            $this->logError("{$context} failed", ['status' => $r->status(), 'body' => mb_substr($r->body(), 0, 300)]);
            return false;
        } catch (\Throwable $e) {
            $this->logError("{$context} exception", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function fetchAnalytics(string $platformPostId, ?string $accountType = null): array
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) return [];

        try {
            $r = Http::get(self::GRAPH . '/' . $this->v() . "/{$platformPostId}/insights", [
                'metric'       => 'impressions,reach,engagement,likes,comments,shares,saved',
                'access_token' => $token->access_token,
            ]);
            if (!$r->successful()) return [];

            $out = ['reach' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'clicks' => 0];
            foreach (($r->json()['data'] ?? []) as $m) {
                $name = $m['name'] ?? '';
                $val  = (int) ($m['values'][0]['value'] ?? 0);
                match ($name) {
                    'reach'        => $out['reach']    = $val,
                    'impressions'  => $out['reach']    = max($out['reach'], $val),
                    'likes'        => $out['likes']    = $val,
                    'comments'     => $out['comments'] = $val,
                    'shares'       => $out['shares']   = $val,
                    'saved'        => $out['clicks']   = $val, // proxy: saves are an engagement signal
                    default        => null,
                };
            }
            return $out;
        } catch (\Throwable $e) {
            $this->logError('fetchAnalytics exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── OAuth (Meta unified — same dialog as Facebook) ─────────────────

    public function getOAuthUrl(string $accountType, string $state): string
    {
        $params = http_build_query([
            'client_id'     => config('services.instagram.client_id'),
            'redirect_uri'  => config('services.instagram.redirect_uri'),
            'state'         => $state,
            'scope'         => self::SCOPES,
            'response_type' => 'code',
        ]);
        return str_replace('{v}', $this->v(), self::OAUTH_DIALOG) . '?' . $params;
    }

    public function handleOAuthCallback(string $code, string $accountType): ?SocialToken
    {
        try {
            // 1. code → short-lived USER token
            $r = Http::get(self::GRAPH . '/' . $this->v() . '/oauth/access_token', [
                'client_id'     => config('services.instagram.client_id'),
                'client_secret' => config('services.instagram.client_secret'),
                'redirect_uri'  => config('services.instagram.redirect_uri'),
                'code'          => $code,
            ]);
            if (!$r->successful()) {
                $this->logError('OAuth code exchange failed', ['body' => mb_substr($r->body(), 0, 300)]);
                return null;
            }
            $shortToken = $r->json()['access_token'] ?? null;
            if (!$shortToken) return null;

            // 2. short-lived → long-lived USER token (~60 days)
            $r2 = Http::get(self::GRAPH . '/' . $this->v() . '/oauth/access_token', [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => config('services.instagram.client_id'),
                'client_secret'     => config('services.instagram.client_secret'),
                'fb_exchange_token' => $shortToken,
            ]);
            $longToken = $r2->json()['access_token'] ?? $shortToken;
            $expiresIn = (int) ($r2->json()['expires_in'] ?? 5184000);

            // 3. Find IG business account ID
            //    Path: /me/accounts → pick page → /{page-id}?fields=instagram_business_account
            $configuredIgId = config('services.instagram.business_account_id');
            $igUserId = $configuredIgId;
            $igName   = null;
            $pageAccessToken = $longToken; // fall back to user token

            $accounts = Http::get(self::GRAPH . '/' . $this->v() . '/me/accounts', [
                'access_token' => $longToken,
                'fields'       => 'id,name,access_token,instagram_business_account',
            ])->json()['data'] ?? [];

            foreach ($accounts as $acc) {
                $linkedIg = $acc['instagram_business_account']['id'] ?? null;
                if ($linkedIg && (!$configuredIgId || $linkedIg === $configuredIgId)) {
                    $igUserId        = $linkedIg;
                    $pageAccessToken = $acc['access_token'] ?? $longToken;
                    break;
                }
            }

            if (!$igUserId) {
                $this->logError('OAuth: no IG business account found in user pages');
                return null;
            }

            // Fetch IG account name
            $profile = Http::get(self::GRAPH . '/' . $this->v() . "/{$igUserId}", [
                'fields'       => 'id,username,name',
                'access_token' => $pageAccessToken,
            ])->json();
            $igName = $profile['username'] ?? $profile['name'] ?? null;

            return SocialToken::updateOrCreate(
                ['platform' => 'instagram', 'account_type' => 'business'],
                [
                    'access_token'       => $pageAccessToken, // page token works for IG endpoints
                    'refresh_token'      => null,
                    'expires_at'         => now()->addSeconds($expiresIn),
                    'platform_user_id'   => $igUserId,
                    'platform_user_name' => $igName,
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
        return ($token && $token->isValid()) ? $token : null;
    }

    private function handleApiError(\Illuminate\Http\Client\Response $r, SocialToken $token): void
    {
        $err = $r->json()['error'] ?? null;
        if (!$err) return;

        if (($err['code'] ?? 0) === 190) {
            $this->notifyTokenExpired($token->account_type, $r->status());
        }

        Log::warning('InstagramDriver: API error', [
            'code'    => $err['code'] ?? null,
            'subcode' => $err['error_subcode'] ?? null,
            'message' => $err['message'] ?? '',
        ]);
    }
}
