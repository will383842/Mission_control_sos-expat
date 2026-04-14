<?php

namespace App\Services\Social;

use App\Models\LinkedInPost;
use App\Models\LinkedInToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn REST API v202401 — publish posts, upload images, post first comments.
 *
 * Endpoints (Share on LinkedIn product, approved 2026-04-14):
 *   POST /rest/posts                           — create text or image post
 *   POST /rest/images?action=initializeUpload  — register image upload slot
 *   PUT  {uploadUrl}                           — binary upload of image
 *   POST /rest/socialActions/{urn}/comments    — post first comment
 *   GET  /v2/userinfo                          — get personal profile ID (OpenID)
 *   GET  /v2/organizationAcls?q=roleAssignee   — get managed org IDs
 *
 * account_type: 'personal' → urn:li:person:{id}
 *               'page'     → urn:li:organization:{id}
 *
 * Required header on all REST v202401 calls: LinkedIn-Version: 202401
 */
class LinkedInApiService
{
    private const BASE    = 'https://api.linkedin.com';
    private const REST    = 'https://api.linkedin.com/rest';
    private const V2      = 'https://api.linkedin.com/v2';
    private const VERSION = '202401';

    // ── Public interface ───────────────────────────────────────────────

    public function isConfigured(string $accountType = 'personal'): bool
    {
        $token = LinkedInToken::where('account_type', $accountType)->first();
        return $token && $token->isValid();
    }

    public function getTokenStatus(): array
    {
        $personal = LinkedInToken::forPersonal()->first();
        $page     = LinkedInToken::forPage()->first();

        return [
            'personal' => [
                'connected'       => $personal?->isValid() ?? false,
                'name'            => $personal?->linkedin_name,
                'expires_in_days' => $personal?->expiresInDays() ?? 0,
            ],
            'page' => [
                'connected'       => $page?->isValid() ?? false,
                'name'            => $page?->linkedin_name,
                'expires_in_days' => $page?->expiresInDays() ?? 0,
            ],
        ];
    }

    /**
     * Publish a LinkedIn post to the given account (personal | page).
     * Returns the LinkedIn post URN on success, null on failure.
     */
    public function publish(LinkedInPost $post, string $accountType): ?string
    {
        $token = $this->resolveToken($accountType);
        if (!$token) {
            Log::error('LinkedInApiService: no valid token', ['account_type' => $accountType]);
            return null;
        }

        try {
            $authorUrn = $this->authorUrn($accountType, $token->linkedin_id);
            $fullText  = $post->hook . "\n\n" . $post->body . "\n\n" . implode(' ', array_map(fn($h) => "#{$h}", $post->hashtags ?? []));

            if ($post->featured_image_url) {
                $urn = $this->publishWithImage($fullText, $post->featured_image_url, $authorUrn, $token->access_token);
            } else {
                $urn = $this->publishText($fullText, $authorUrn, $token->access_token);
            }

            Log::info('LinkedInApiService: published', [
                'post_id'      => $post->id,
                'account_type' => $accountType,
                'urn'          => $urn,
            ]);

            return $urn;

        } catch (\Throwable $e) {
            Log::error('LinkedInApiService: publish failed', [
                'post_id'      => $post->id,
                'account_type' => $accountType,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fetch comments on a published post.
     * Returns array of ['urn', 'author_name', 'author_urn', 'text', 'commented_at'].
     */
    public function getComments(string $postUrn, string $accountType): array
    {
        $token = $this->resolveToken($accountType);
        if (!$token) return [];

        try {
            $r = Http::withHeaders($this->headers($token->access_token))
                ->get(self::REST . '/socialActions/' . urlencode($postUrn) . '/comments', [
                    'count' => 50,
                ]);

            if (!$r->successful()) {
                Log::warning('LinkedInApiService: getComments failed', [
                    'urn'    => $postUrn,
                    'status' => $r->status(),
                    'body'   => mb_substr($r->body(), 0, 300),
                ]);
                return [];
            }

            $elements = $r->json()['elements'] ?? [];
            $result   = [];

            foreach ($elements as $el) {
                $urn        = $el['$URN'] ?? $el['id'] ?? null;
                $authorUrn  = $el['actor'] ?? null;
                $text       = $el['message']['text'] ?? '';
                $createdMs  = $el['created']['time'] ?? null;

                // Try to extract author name from commenter field (v202401 format)
                $name = null;
                $commenter = $el['commenter'] ?? [];
                if (!empty($commenter['member'])) {
                    $first = $commenter['member']['firstName']['localized'] ?? [];
                    $last  = $commenter['member']['lastName']['localized'] ?? [];
                    $name  = trim(implode(' ', [reset($first) ?: '', reset($last) ?: '']));
                }

                if ($urn && $text) {
                    $result[] = [
                        'urn'          => $urn,
                        'author_name'  => $name ?: 'Inconnu',
                        'author_urn'   => $authorUrn,
                        'text'         => $text,
                        'commented_at' => $createdMs ? \Carbon\Carbon::createFromTimestampMs($createdMs) : now(),
                    ];
                }
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error('LinkedInApiService: getComments exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Post a reply comment on a LinkedIn post.
     * We post as a regular comment (mentioning the author in text).
     * Returns true on success.
     */
    public function postReply(string $postUrn, string $replyText, string $accountType): bool
    {
        $token = $this->resolveToken($accountType);
        if (!$token) return false;

        try {
            $response = Http::withHeaders($this->headers($token->access_token))
                ->post(self::REST . '/socialActions/' . urlencode($postUrn) . '/comments', [
                    'actor'   => $this->authorUrn($accountType, $token->linkedin_id),
                    'message' => ['text' => $replyText],
                ]);

            if ($response->successful()) {
                Log::info('LinkedInApiService: reply posted', ['urn' => $postUrn]);
                return true;
            }

            Log::warning('LinkedInApiService: reply failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;

        } catch (\Throwable $e) {
            Log::error('LinkedInApiService: postReply exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Post the first comment 3 minutes after publication.
     * Returns true on success.
     */
    public function postFirstComment(string $postUrn, string $commentText, string $accountType): bool
    {
        $token = $this->resolveToken($accountType);
        if (!$token) return false;

        try {
            $response = Http::withHeaders($this->headers($token->access_token))
                ->post(self::REST . '/socialActions/' . urlencode($postUrn) . '/comments', [
                    'actor'   => $this->authorUrn($accountType, $token->linkedin_id),
                    'message' => ['text' => $commentText],
                ]);

            if ($response->successful()) {
                Log::info('LinkedInApiService: first comment posted', ['urn' => $postUrn]);
                return true;
            }

            Log::warning('LinkedInApiService: first comment failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;

        } catch (\Throwable $e) {
            Log::error('LinkedInApiService: first comment exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Fetch personal profile ID via OpenID userinfo endpoint.
     * Called once during OAuth callback to populate linkedin_id.
     */
    public function fetchPersonalId(string $accessToken): ?array
    {
        try {
            $r = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get(self::V2 . '/userinfo');

            if ($r->successful()) {
                $data = $r->json();
                $name = trim(($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? ''));
                if (!$name) {
                    $name = $data['name'] ?? '';
                }
                return [
                    'id'   => $data['sub'] ?? null,
                    'name' => $name,
                ];
            }

            // Fallback: try legacy /v2/me
            $r2 = Http::withHeaders([
                'Authorization'              => 'Bearer ' . $accessToken,
                'X-Restli-Protocol-Version'  => '2.0.0',
            ])->get(self::V2 . '/me', [
                'projection' => '(id,localizedFirstName,localizedLastName)',
            ]);

            if ($r2->successful()) {
                $data = $r2->json();
                return [
                    'id'   => $data['id'] ?? null,
                    'name' => trim(($data['localizedFirstName'] ?? '') . ' ' . ($data['localizedLastName'] ?? '')),
                ];
            }

        } catch (\Throwable $e) {
            Log::error('LinkedInApiService: fetchPersonalId failed', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Fetch organization IDs managed by the authenticated user.
     */
    public function fetchManagedOrgs(string $accessToken): array
    {
        try {
            $r = Http::withHeaders([
                'Authorization'              => 'Bearer ' . $accessToken,
                'LinkedIn-Version'           => self::VERSION,
                'X-Restli-Protocol-Version'  => '2.0.0',
            ])->get(self::V2 . '/organizationAcls', [
                'q'          => 'roleAssignee',
                'role'       => 'ADMINISTRATOR',
                'projection' => '(elements*(organization~(id,localizedName)))',
            ]);

            if ($r->successful()) {
                $elements = $r->json()['elements'] ?? [];
                return array_map(fn($e) => [
                    'id'   => $e['organization~']['id'] ?? null,
                    'name' => $e['organization~']['localizedName'] ?? '',
                ], $elements);
            }
        } catch (\Throwable $e) {
            Log::error('LinkedInApiService: fetchManagedOrgs failed', ['error' => $e->getMessage()]);
        }
        return [];
    }

    // ── Private helpers ────────────────────────────────────────────────

    private function resolveToken(string $accountType): ?LinkedInToken
    {
        $token = LinkedInToken::where('account_type', $accountType)->first();
        if (!$token || !$token->isValid()) return null;
        return $token;
    }

    private function authorUrn(string $accountType, string $id): string
    {
        return $accountType === 'page'
            ? "urn:li:organization:{$id}"
            : "urn:li:person:{$id}";
    }

    /** Common headers for REST v202401 API calls */
    private function headers(string $accessToken): array
    {
        return [
            'Authorization'             => 'Bearer ' . $accessToken,
            'Content-Type'              => 'application/json',
            'LinkedIn-Version'          => self::VERSION,
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }

    /**
     * Publish a text-only post via REST v202401 API.
     * Returns post URN.
     */
    private function publishText(string $text, string $authorUrn, string $accessToken): string
    {
        $response = Http::withHeaders($this->headers($accessToken))
            ->post(self::REST . '/posts', [
                'author'          => $authorUrn,
                'commentary'      => $text,
                'visibility'      => 'PUBLIC',
                'distribution'    => [
                    'feedDistribution'             => 'MAIN_FEED',
                    'targetEntities'               => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState'             => 'PUBLISHED',
                'isReshareDisabledByAuthor'  => false,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("POST /rest/posts error {$response->status()}: {$response->body()}");
        }

        // New REST API returns post URN in X-RestLi-Id header
        return $response->header('X-RestLi-Id')
            ?? $response->header('x-restli-id')
            ?? $response->json()['id']
            ?? '';
    }

    /**
     * Upload image to LinkedIn then publish post with media.
     * Falls back to text-only if any upload step fails.
     * Returns post URN.
     */
    private function publishWithImage(string $text, string $imageUrl, string $authorUrn, string $accessToken): string
    {
        // 1. Initialize image upload
        $initResp = Http::withHeaders($this->headers($accessToken))
            ->post(self::REST . '/images?action=initializeUpload', [
                'initializeUploadRequest' => [
                    'owner' => $authorUrn,
                ],
            ]);

        if (!$initResp->successful()) {
            Log::warning('LinkedInApiService: image initializeUpload failed, falling back to text', [
                'status' => $initResp->status(),
                'body'   => $initResp->body(),
            ]);
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        $value     = $initResp->json()['value'] ?? [];
        $uploadUrl = $value['uploadUrl'] ?? null;
        $imageUrn  = $value['image'] ?? null;  // urn:li:image:{id}

        if (!$uploadUrl || !$imageUrn) {
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        // 2. Download image and upload binary to LinkedIn
        try {
            $imageContent = Http::timeout(30)->get($imageUrl)->body();
        } catch (\Throwable) {
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        $uploadResp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->withBody($imageContent, 'image/jpeg')->put($uploadUrl);

        if (!$uploadResp->successful() && $uploadResp->status() !== 201) {
            Log::warning('LinkedInApiService: image binary upload failed', ['status' => $uploadResp->status()]);
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        // 3. Publish post with image media reference
        $postResp = Http::withHeaders($this->headers($accessToken))
            ->post(self::REST . '/posts', [
                'author'       => $authorUrn,
                'commentary'   => $text,
                'visibility'   => 'PUBLIC',
                'distribution' => [
                    'feedDistribution'               => 'MAIN_FEED',
                    'targetEntities'                 => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'content' => [
                    'media' => [
                        'id' => $imageUrn,
                    ],
                ],
                'lifecycleState'            => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ]);

        if (!$postResp->successful()) {
            Log::warning('LinkedInApiService: image post failed, falling back to text', [
                'status' => $postResp->status(),
                'body'   => $postResp->body(),
            ]);
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        return $postResp->header('X-RestLi-Id')
            ?? $postResp->header('x-restli-id')
            ?? $postResp->json()['id']
            ?? '';
    }
}
