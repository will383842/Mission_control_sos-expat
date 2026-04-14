<?php

namespace App\Services\Social;

use App\Models\LinkedInPost;
use App\Models\LinkedInToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LinkedIn API v2 — publish posts, upload images, post first comments.
 *
 * Endpoints used:
 *   POST /v2/ugcPosts           — create text or image post
 *   POST /v2/assets?action=registerUpload — register image upload
 *   PUT  {uploadUrl}            — binary upload of image
 *   POST /v2/socialActions/{urn}/comments — post first comment
 *   GET  /v2/me                 — get personal profile ID
 *   GET  /v2/organizationAcls?q=roleAssignee — get managed org IDs
 *
 * account_type: 'personal' → urn:li:person:{id}
 *               'page'     → urn:li:organization:{id}
 */
class LinkedInApiService
{
    private const API = 'https://api.linkedin.com/v2';

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
                'connected'      => $personal?->isValid() ?? false,
                'name'           => $personal?->linkedin_name,
                'expires_in_days' => $personal?->expiresInDays() ?? 0,
            ],
            'page' => [
                'connected'      => $page?->isValid() ?? false,
                'name'           => $page?->linkedin_name,
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
     * Post the first comment 3 minutes after publication.
     * Returns true on success.
     */
    public function postFirstComment(string $postUrn, string $commentText, string $accountType): bool
    {
        $token = $this->resolveToken($accountType);
        if (!$token) return false;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token->access_token,
                'Content-Type'  => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->post(self::API . '/socialActions/' . urlencode($postUrn) . '/comments', [
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
     * Fetch personal profile ID from LinkedIn API.
     * Called once during OAuth callback to populate linkedin_id.
     */
    public function fetchPersonalId(string $accessToken): ?array
    {
        try {
            $r = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get(self::API . '/me', [
                'projection' => '(id,localizedFirstName,localizedLastName)',
            ]);

            if ($r->successful()) {
                $data = $r->json();
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
                'Authorization' => 'Bearer ' . $accessToken,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])->get(self::API . '/organizationAcls', [
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

    /** Publish a text-only post. Returns post URN. */
    private function publishText(string $text, string $authorUrn, string $accessToken): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->post(self::API . '/ugcPosts', [
            'author'         => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => ['text' => $text],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("ugcPosts error {$response->status()}: {$response->body()}");
        }

        return $response->header('X-RestLi-Id') ?? $response->json()['id'] ?? '';
    }

    /** Upload image to LinkedIn Assets, then publish post. Returns post URN. */
    private function publishWithImage(string $text, string $imageUrl, string $authorUrn, string $accessToken): string
    {
        // 1. Register upload
        $regResp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->post(self::API . '/assets?action=registerUpload', [
            'registerUploadRequest' => [
                'recipes'              => ['urn:li:digitalmediaRecipe:feedshare-image'],
                'owner'                => $authorUrn,
                'serviceRelationships' => [[
                    'relationshipType' => 'OWNER',
                    'identifier'       => 'urn:li:userGeneratedContent',
                ]],
            ],
        ]);

        if (!$regResp->successful()) {
            // Fall back to text-only if image upload fails
            Log::warning('LinkedInApiService: image register failed, falling back to text', [
                'status' => $regResp->status(),
            ]);
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        $uploadData = $regResp->json()['value'] ?? [];
        $uploadUrl  = $uploadData['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? null;
        $assetUrn   = $uploadData['asset'] ?? null;

        if (!$uploadUrl || !$assetUrn) {
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        // 2. Download image and upload binary to LinkedIn
        $imageContent = Http::timeout(30)->get($imageUrl)->body();
        $uploadResp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->withBody($imageContent, 'image/jpeg')->put($uploadUrl);

        if (!$uploadResp->successful() && $uploadResp->status() !== 201) {
            Log::warning('LinkedInApiService: image binary upload failed', ['status' => $uploadResp->status()]);
            return $this->publishText($text, $authorUrn, $accessToken);
        }

        // 3. Publish post with image asset
        $postResp = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->post(self::API . '/ugcPosts', [
            'author'         => $authorUrn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => ['text' => $text],
                    'shareMediaCategory' => 'IMAGE',
                    'media'              => [[
                        'status'      => 'READY',
                        'media'       => $assetUrn,
                        'description' => ['text' => 'SOS-Expat.com'],
                        'title'       => ['text' => 'SOS-Expat'],
                    ]],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ]);

        if (!$postResp->successful()) {
            throw new \RuntimeException("ugcPosts image error {$postResp->status()}: {$postResp->body()}");
        }

        return $postResp->header('X-RestLi-Id') ?? $postResp->json()['id'] ?? '';
    }
}
