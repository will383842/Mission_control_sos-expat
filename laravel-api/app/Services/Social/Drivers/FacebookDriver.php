<?php

namespace App\Services\Social\Drivers;

use App\Models\SocialPost;
use App\Models\SocialToken;
use App\Services\Social\AbstractSocialDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Pages driver — publishes to ONE Pro Page (services.facebook.page_id).
 *
 * Required env:
 *   FACEBOOK_CLIENT_ID, FACEBOOK_CLIENT_SECRET, FACEBOOK_REDIRECT_URI, FACEBOOK_PAGE_ID
 *
 * OAuth scopes requested (Meta App Review needed for production):
 *   pages_show_list, pages_read_engagement, pages_manage_posts,
 *   pages_manage_engagement, pages_read_user_content
 *
 * Token model:
 *   - User logs in → short-lived USER token
 *   - Exchange to long-lived USER token (~60 days)
 *   - GET /me/accounts → list of managed Pages, each with its own PAGE access_token
 *     (page access_token is non-expiring as long as the user token is valid)
 *   - We store the PAGE access_token in social_tokens(platform=facebook, account_type=page)
 */
class FacebookDriver extends AbstractSocialDriver
{
    private const OAUTH_DIALOG = 'https://www.facebook.com/{v}/dialog/oauth';
    private const GRAPH        = 'https://graph.facebook.com';
    private const SCOPES       = 'pages_show_list,pages_read_engagement,pages_manage_posts,pages_manage_engagement,pages_read_user_content';

    public function platform(): string { return 'facebook'; }

    public function supportedAccountTypes(): array { return ['page']; }
    public function supportsFirstComment(): bool   { return true; }
    public function supportsHashtags(): bool       { return false; } // weak signal on FB
    public function requiresImage(): bool          { return false; }
    public function maxContentLength(): int        { return 63206; }

    private function v(): string { return config('services.facebook.graph_version', 'v19.0'); }

    // ── Publishing ─────────────────────────────────────────────────────

    public function publish(SocialPost $post, ?string $accountType = null): ?string
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) {
            $this->logError('publish: no valid token');
            return null;
        }

        $pageId = $token->platform_user_id; // we store the FB page id here
        $hashtags = array_map(fn($h) => "#{$h}", $post->hashtags ?? []);
        $text     = $post->hook . "\n\n" . $post->body
                  . ($hashtags ? "\n\n" . implode(' ', $hashtags) : '');

        try {
            $endpoint = $post->featured_image_url
                ? self::GRAPH . '/' . $this->v() . "/{$pageId}/photos"
                : self::GRAPH . '/' . $this->v() . "/{$pageId}/feed";

            $payload = $post->featured_image_url
                ? ['url' => $post->featured_image_url, 'caption' => $text, 'access_token' => $token->access_token]
                : ['message' => $text, 'access_token' => $token->access_token];

            $r = Http::asForm()->post($endpoint, $payload);

            if (!$r->successful()) {
                $this->handleApiError($r, $token);
                throw new \RuntimeException("Facebook publish failed: HTTP {$r->status()} — " . mb_substr($r->body(), 0, 300));
            }

            $data = $r->json();
            // /feed returns ['id' => '{page-id}_{post-id}']
            // /photos returns ['id' => '{photo-id}', 'post_id' => '{page-id}_{post-id}']
            $postId = $data['post_id'] ?? $data['id'] ?? null;

            Log::info('FacebookDriver: published', ['post_id' => $post->id, 'fb_id' => $postId]);
            return $postId;

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
                'fields'       => 'id,from,message,created_time',
                'order'        => 'reverse_chronological',
                'limit'        => 50,
                'access_token' => $token->access_token,
            ]);

            if (!$r->successful()) {
                $this->logError('getComments failed', ['status' => $r->status(), 'body' => mb_substr($r->body(), 0, 300)]);
                return [];
            }

            $out = [];
            foreach (($r->json()['data'] ?? []) as $c) {
                $out[] = [
                    'platform_comment_id' => $c['id'] ?? '',
                    'author_name'         => $c['from']['name'] ?? 'Inconnu',
                    'author_platform_id'  => $c['from']['id'] ?? null,
                    'text'                => $c['message'] ?? '',
                    'commented_at'        => isset($c['created_time']) ? \Carbon\Carbon::parse($c['created_time']) : now(),
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

    private function postCommentInternal(string $postId, string $text, ?string $accountType, string $context): bool
    {
        $token = $this->resolveToken($this->resolveAccountType($accountType));
        if (!$token) return false;

        try {
            $r = Http::asForm()->post(self::GRAPH . '/' . $this->v() . "/{$postId}/comments", [
                'message'      => $text,
                'access_token' => $token->access_token,
            ]);

            if ($r->successful()) {
                Log::info("FacebookDriver: {$context} posted", ['post_id' => $postId]);
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
                'metric'       => 'post_impressions,post_engaged_users,post_clicks,post_reactions_by_type_total',
                'access_token' => $token->access_token,
            ]);

            if (!$r->successful()) return [];

            $out = ['reach' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'clicks' => 0];
            foreach (($r->json()['data'] ?? []) as $m) {
                $name  = $m['name'] ?? '';
                $value = $m['values'][0]['value'] ?? 0;

                if ($name === 'post_impressions')          $out['reach']  = (int) $value;
                if ($name === 'post_engaged_users')        $out['comments'] = (int) $value; // approximate
                if ($name === 'post_clicks')               $out['clicks'] = (int) $value;
                if ($name === 'post_reactions_by_type_total' && is_array($value)) {
                    $out['likes'] = array_sum($value);
                }
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
            'client_id'     => config('services.facebook.client_id'),
            'redirect_uri'  => config('services.facebook.redirect_uri'),
            'state'         => $state,
            'scope'         => self::SCOPES,
            'response_type' => 'code',
        ]);

        return str_replace('{v}', $this->v(), self::OAUTH_DIALOG) . '?' . $params;
    }

    public function handleOAuthCallback(string $code, string $accountType): ?SocialToken
    {
        try {
            // 1. Exchange code → short-lived USER token
            $r = Http::get(self::GRAPH . '/' . $this->v() . '/oauth/access_token', [
                'client_id'     => config('services.facebook.client_id'),
                'client_secret' => config('services.facebook.client_secret'),
                'redirect_uri'  => config('services.facebook.redirect_uri'),
                'code'          => $code,
            ]);

            if (!$r->successful()) {
                $this->logError('OAuth code exchange failed', ['body' => mb_substr($r->body(), 0, 300)]);
                return null;
            }

            $shortToken = $r->json()['access_token'] ?? null;
            if (!$shortToken) return null;

            // 2. Exchange short-lived → long-lived USER token (~60 days)
            $r2 = Http::get(self::GRAPH . '/' . $this->v() . '/oauth/access_token', [
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => config('services.facebook.client_id'),
                'client_secret'     => config('services.facebook.client_secret'),
                'fb_exchange_token' => $shortToken,
            ]);

            $longToken = $r2->json()['access_token'] ?? $shortToken;
            $expiresIn = (int) ($r2->json()['expires_in'] ?? 5184000);

            // 3. Fetch managed Pages and pick the configured FACEBOOK_PAGE_ID
            $r3 = Http::get(self::GRAPH . '/' . $this->v() . '/me/accounts', [
                'access_token' => $longToken,
                'fields'       => 'id,name,access_token',
            ]);

            if (!$r3->successful()) {
                $this->logError('OAuth /me/accounts failed', ['body' => mb_substr($r3->body(), 0, 300)]);
                return null;
            }

            $targetPageId = config('services.facebook.page_id');
            $page = null;
            foreach (($r3->json()['data'] ?? []) as $p) {
                if (!$targetPageId || $p['id'] === $targetPageId) { $page = $p; break; }
            }

            if (!$page) {
                $this->logError('OAuth: configured FACEBOOK_PAGE_ID not found in user pages', [
                    'wanted' => $targetPageId,
                    'available' => array_column($r3->json()['data'] ?? [], 'id'),
                ]);
                return null;
            }

            // 4. Store the PAGE access_token (not the user token)
            return SocialToken::updateOrCreate(
                ['platform' => 'facebook', 'account_type' => 'page'],
                [
                    'access_token'       => $page['access_token'],
                    'refresh_token'      => null, // FB page tokens don't have a refresh token
                    'expires_at'         => now()->addSeconds($expiresIn),
                    'platform_user_id'   => $page['id'],
                    'platform_user_name' => $page['name'] ?? 'Page Pro',
                    'scope'              => self::SCOPES,
                    'metadata'           => ['user_token_expires_at' => now()->addSeconds($expiresIn)->toIso8601String()],
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
        // Meta returns errors with shape: {error: {code, type, message, error_subcode}}
        $err = $r->json()['error'] ?? null;
        if (!$err) return;

        // 190 = OAuthException token invalid/expired → notify for reconnect
        if (($err['code'] ?? 0) === 190) {
            $this->notifyTokenExpired($token->account_type, $r->status());
        }

        Log::warning('FacebookDriver: API error', [
            'code'    => $err['code'] ?? null,
            'subcode' => $err['error_subcode'] ?? null,
            'message' => $err['message'] ?? '',
        ]);
    }
}
