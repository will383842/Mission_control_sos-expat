<?php

namespace App\Services\Social\Drivers;

use App\Models\SocialPost;
use App\Models\SocialToken;
use App\Services\Social\AbstractSocialDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn driver (REST API v202601).
 *
 * account_type:
 *   'personal' → urn:li:person:{id}, scope w_member_social
 *   'page'     → urn:li:organization:{id}, scope rw_organization_social
 *
 * Port of the former App\Services\Social\LinkedInApiService, adapted to
 * the multi-platform SocialPublishingServiceInterface.
 */
class LinkedInDriver extends AbstractSocialDriver
{
    private const REST    = 'https://api.linkedin.com/rest';
    private const V2      = 'https://api.linkedin.com/v2';
    private const OAUTH   = 'https://www.linkedin.com/oauth/v2';
    private const VERSION = '202601';

    public function platform(): string { return 'linkedin'; }

    public function supportedAccountTypes(): array { return ['personal', 'page']; }

    public function supportsFirstComment(): bool { return true; }
    public function supportsHashtags(): bool     { return true; }
    public function requiresImage(): bool        { return false; }
    public function maxContentLength(): int      { return 3000; }

    // ── Publishing ─────────────────────────────────────────────────────

    public function publish(SocialPost $post, ?string $accountType = null): ?string
    {
        $accountType = $this->resolveAccountType($accountType);
        $token = $this->resolveToken($accountType);
        if (!$token) {
            $this->logError('publish: no valid token', ['account_type' => $accountType]);
            return null;
        }

        try {
            $authorUrn = $this->authorUrn($accountType, $token->platform_user_id);
            $hashtags  = array_map(fn($h) => "#{$h}", $post->hashtags ?? []);
            $fullText  = $post->hook . "\n\n" . $post->body . "\n\n" . implode(' ', $hashtags);

            $urn = $post->featured_image_url
                ? $this->publishWithImage($fullText, $post->featured_image_url, $authorUrn, $token->access_token)
                : $this->publishText($fullText, $authorUrn, $token->access_token);

            Log::info('LinkedInDriver: published', [
                'post_id'      => $post->id,
                'account_type' => $accountType,
                'urn'          => $urn,
            ]);

            return $urn;

        } catch (\Throwable $e) {
            $this->logError('publish failed', [
                'post_id'      => $post->id,
                'account_type' => $accountType,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getComments(string $platformPostId, ?string $accountType = null): array
    {
        $accountType = $this->resolveAccountType($accountType);
        $token = $this->resolveToken($accountType);
        if (!$token) return [];

        try {
            $r = Http::withHeaders($this->headers($token->access_token))
                ->get(self::REST . '/socialActions/' . urlencode($platformPostId) . '/comments', [
                    'count' => 50,
                ]);

            if (!$r->successful()) {
                Log::warning('LinkedInDriver: getComments failed', [
                    'urn'    => $platformPostId,
                    'status' => $r->status(),
                    'body'   => mb_substr($r->body(), 0, 300),
                ]);
                return [];
            }

            $result = [];
            foreach (($r->json()['elements'] ?? []) as $el) {
                $urn        = $el['$URN'] ?? $el['id'] ?? null;
                $authorUrn  = $el['actor'] ?? null;
                $text       = $el['message']['text'] ?? '';
                $createdMs  = $el['created']['time'] ?? null;

                $name = null;
                $commenter = $el['commenter'] ?? [];
                if (!empty($commenter['member'])) {
                    $first = $commenter['member']['firstName']['localized'] ?? [];
                    $last  = $commenter['member']['lastName']['localized'] ?? [];
                    $name  = trim(implode(' ', [reset($first) ?: '', reset($last) ?: '']));
                }

                if ($urn && $text) {
                    $result[] = [
                        'platform_comment_id' => $urn,
                        'author_name'         => $name ?: 'Inconnu',
                        'author_platform_id'  => $authorUrn,
                        'text'                => $text,
                        'commented_at'        => $createdMs ? \Carbon\Carbon::createFromTimestampMs($createdMs) : now(),
                    ];
                }
            }
            return $result;

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

    private function postCommentInternal(string $postUrn, string $text, ?string $accountType, string $context): bool
    {
        $accountType = $this->resolveAccountType($accountType);
        $token = $this->resolveToken($accountType);
        if (!$token) return false;

        try {
            $r = Http::withHeaders($this->headers($token->access_token))
                ->post(self::REST . '/socialActions/' . urlencode($postUrn) . '/comments', [
                    'actor'   => $this->authorUrn($accountType, $token->platform_user_id),
                    'message' => ['text' => $text],
                ]);

            if ($r->successful()) {
                Log::info("LinkedInDriver: {$context} posted", ['urn' => $postUrn]);
                return true;
            }

            Log::warning("LinkedInDriver: {$context} failed", [
                'status' => $r->status(),
                'body'   => $r->body(),
            ]);
            return false;

        } catch (\Throwable $e) {
            $this->logError("{$context} exception", ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function fetchAnalytics(string $platformPostId, ?string $accountType = null): array
    {
        // Analytics pulled from the social_posts row itself (engagement counters
        // updated by CheckSocialCommentsCommand). A dedicated insights endpoint
        // call can be added here later if needed.
        return [];
    }

    // ── OAuth ──────────────────────────────────────────────────────────

    public function getOAuthUrl(string $accountType, string $state): string
    {
        $scope = $accountType === 'page'
            ? 'r_organization_social w_organization_social rw_organization_admin'
            : 'openid profile w_member_social';

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => config('services.linkedin.client_id'),
            'redirect_uri'  => config('services.linkedin.redirect_uri'),
            'state'         => $state,
            'scope'         => $scope,
        ]);

        return self::OAUTH . '/authorization?' . $params;
    }

    public function handleOAuthCallback(string $code, string $accountType): ?SocialToken
    {
        try {
            $r = Http::asForm()->post(self::OAUTH . '/accessToken', [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => config('services.linkedin.redirect_uri'),
                'client_id'     => config('services.linkedin.client_id'),
                'client_secret' => config('services.linkedin.client_secret'),
            ]);

            if (!$r->successful()) {
                $this->logError('OAuth code exchange failed', [
                    'status' => $r->status(),
                    'body'   => mb_substr($r->body(), 0, 300),
                ]);
                return null;
            }

            $data         = $r->json();
            $accessToken  = $data['access_token'] ?? null;
            $expiresIn    = (int) ($data['expires_in'] ?? 5184000);
            $refreshToken = $data['refresh_token'] ?? null;
            $refreshExpIn = $data['refresh_token_expires_in'] ?? null;
            $scope        = $data['scope'] ?? null;

            if (!$accessToken) return null;

            // Resolve platform_user_id / name depending on account_type
            if ($accountType === 'personal') {
                $profile = $this->fetchPersonalId($accessToken);
            } else {
                // For pages, the user is expected to pick an org afterwards (see fetchManagedOrgs).
                // At this point we store the user token; the org id is set later via setPage.
                $profile = $this->fetchPersonalId($accessToken);
            }

            if (!$profile || empty($profile['id'])) {
                $this->logError('OAuth: could not fetch platform_user_id');
                return null;
            }

            return SocialToken::updateOrCreate(
                ['platform' => 'linkedin', 'account_type' => $accountType],
                [
                    'access_token'             => $accessToken,
                    'refresh_token'            => $refreshToken,
                    'expires_at'               => now()->addSeconds($expiresIn),
                    'refresh_token_expires_at' => $refreshExpIn ? now()->addSeconds((int) $refreshExpIn) : null,
                    'platform_user_id'         => $profile['id'],
                    'platform_user_name'       => $profile['name'] ?? null,
                    'scope'                    => $scope,
                ]
            );

        } catch (\Throwable $e) {
            $this->logError('handleOAuthCallback exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /** Public helper used by the multi-org page selector. */
    public function fetchManagedOrgs(string $accessToken): array
    {
        try {
            $r = Http::withHeaders([
                'Authorization'             => 'Bearer ' . $accessToken,
                'LinkedIn-Version'          => self::VERSION,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->get(self::V2 . '/organizationAcls', [
                'q'          => 'roleAssignee',
                'role'       => 'ADMINISTRATOR',
                'projection' => '(elements*(organization~(id,localizedName)))',
            ]);

            if ($r->successful()) {
                return array_map(fn($e) => [
                    'id'   => $e['organization~']['id'] ?? null,
                    'name' => $e['organization~']['localizedName'] ?? '',
                ], $r->json()['elements'] ?? []);
            }
        } catch (\Throwable $e) {
            $this->logError('fetchManagedOrgs failed', ['error' => $e->getMessage()]);
        }
        return [];
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function fetchPersonalId(string $accessToken): ?array
    {
        try {
            $r = Http::withHeaders(['Authorization' => 'Bearer ' . $accessToken])
                ->get(self::V2 . '/userinfo');

            if ($r->successful()) {
                $d = $r->json();
                $name = trim(($d['given_name'] ?? '') . ' ' . ($d['family_name'] ?? '')) ?: ($d['name'] ?? '');
                return ['id' => $d['sub'] ?? null, 'name' => $name];
            }

            $r2 = Http::withHeaders([
                'Authorization'             => 'Bearer ' . $accessToken,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->get(self::V2 . '/me', [
                'projection' => '(id,localizedFirstName,localizedLastName)',
            ]);

            if ($r2->successful()) {
                $d = $r2->json();
                return [
                    'id'   => $d['id'] ?? null,
                    'name' => trim(($d['localizedFirstName'] ?? '') . ' ' . ($d['localizedLastName'] ?? '')),
                ];
            }
        } catch (\Throwable $e) {
            $this->logError('fetchPersonalId failed', ['error' => $e->getMessage()]);
        }
        return null;
    }

    private function resolveToken(string $accountType): ?SocialToken
    {
        $token = $this->findToken($accountType);
        if (!$token) return null;

        // Auto-refresh if expiring within 7 days and we have a refresh token
        if ($token->expires_at
            && $token->expires_at->diffInDays(now(), false) >= -7
            && $token->refresh_token) {
            $refreshed = $this->refreshAccessToken($token);
            if ($refreshed) $token = $refreshed;
        }

        return $token->isValid() ? $token : null;
    }

    private function refreshAccessToken(SocialToken $token): ?SocialToken
    {
        try {
            $r = Http::asForm()->post(self::OAUTH . '/accessToken', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $token->refresh_token,
                'client_id'     => config('services.linkedin.client_id'),
                'client_secret' => config('services.linkedin.client_secret'),
            ]);

            if (!$r->successful()) {
                Log::warning('LinkedInDriver: refresh_token failed', [
                    'account_type' => $token->account_type,
                    'status'       => $r->status(),
                    'body'         => mb_substr($r->body(), 0, 300),
                ]);
                $this->notifyTokenExpired($token->account_type, $r->status());
                return null;
            }

            $d = $r->json();
            $token->access_token  = $d['access_token'] ?? $token->access_token;
            $token->refresh_token = $d['refresh_token'] ?? $token->refresh_token;
            $token->expires_at    = now()->addSeconds((int) ($d['expires_in'] ?? 5184000));
            if (!empty($d['refresh_token_expires_in'])) {
                $token->refresh_token_expires_at = now()->addSeconds((int) $d['refresh_token_expires_in']);
            }
            $token->save();

            return $token->fresh();

        } catch (\Throwable $e) {
            $this->logError('refreshAccessToken exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function authorUrn(string $accountType, string $id): string
    {
        return $accountType === 'page'
            ? "urn:li:organization:{$id}"
            : "urn:li:person:{$id}";
    }

    private function headers(string $accessToken): array
    {
        return [
            'Authorization'             => 'Bearer ' . $accessToken,
            'Content-Type'              => 'application/json',
            'LinkedIn-Version'          => self::VERSION,
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }

    private function publishText(string $text, string $authorUrn, string $accessToken): string
    {
        $r = Http::withHeaders($this->headers($accessToken))
            ->post(self::REST . '/posts', [
                'author'                    => $authorUrn,
                'commentary'                => $text,
                'visibility'                => 'PUBLIC',
                'distribution'              => [
                    'feedDistribution'               => 'MAIN_FEED',
                    'targetEntities'                 => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState'            => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ]);

        if (!$r->successful()) {
            throw new \RuntimeException("POST /rest/posts error {$r->status()}: {$r->body()}");
        }

        return $r->header('X-RestLi-Id') ?? $r->header('x-restli-id') ?? $r->json()['id'] ?? '';
    }

    private function publishWithImage(string $text, string $imageUrl, string $authorUrn, string $accessToken): string
    {
        $init = Http::withHeaders($this->headers($accessToken))
            ->post(self::REST . '/images?action=initializeUpload', [
                'initializeUploadRequest' => ['owner' => $authorUrn],
            ]);

        if (!$init->successful()) {
            Log::warning('LinkedInDriver: initializeUpload failed, falling back to text', [
                'status' => $init->status(), 'body' => $init->body(),
            ]);
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        $value     = $init->json()['value'] ?? [];
        $uploadUrl = $value['uploadUrl'] ?? null;
        $imageUrn  = $value['image'] ?? null;

        if (!$uploadUrl || !$imageUrn) {
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        try {
            $imageContent = Http::timeout(30)->get($imageUrl)->body();
        } catch (\Throwable) {
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        $upload = Http::withHeaders(['Authorization' => 'Bearer ' . $accessToken])
            ->withBody($imageContent, 'image/jpeg')
            ->put($uploadUrl);

        if (!$upload->successful() && $upload->status() !== 201) {
            Log::warning('LinkedInDriver: binary upload failed', ['status' => $upload->status()]);
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        $r = Http::withHeaders($this->headers($accessToken))
            ->post(self::REST . '/posts', [
                'author'                    => $authorUrn,
                'commentary'                => $text,
                'visibility'                => 'PUBLIC',
                'distribution'              => [
                    'feedDistribution'               => 'MAIN_FEED',
                    'targetEntities'                 => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'content'                   => ['media' => ['id' => $imageUrn]],
                'lifecycleState'            => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ]);

        if (!$r->successful()) {
            Log::warning('LinkedInDriver: image post failed, falling back to text', [
                'status' => $r->status(), 'body' => $r->body(),
            ]);
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        return $r->header('X-RestLi-Id') ?? $r->header('x-restli-id') ?? $r->json()['id'] ?? '';
    }
}
