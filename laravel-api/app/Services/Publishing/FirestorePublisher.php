<?php

namespace App\Services\Publishing;

use Illuminate\Database\Eloquent\Model;
use App\Models\PublishingEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirestorePublisher
{
    public function publish(Model $content, PublishingEndpoint $endpoint): array
    {
        $config = $endpoint->config;
        $projectId = $config['project_id'] ?? config('services.firebase.project_id');
        $collection = $config['collection'] ?? 'blog_articles';

        if (empty($projectId)) {
            throw new \RuntimeException('Firebase project_id not configured');
        }

        // Build the document data from the content model
        $data = [
            'fields' => $this->toFirestoreFields([
                'title' => $content->title,
                'slug' => $content->slug,
                'content_html' => $content->content_html ?? '',
                'excerpt' => $content->excerpt ?? '',
                'meta_title' => $content->meta_title ?? '',
                'meta_description' => $content->meta_description ?? '',
                'language' => $content->language ?? 'fr',
                'country' => $content->country ?? '',
                'status' => 'published',
                'seo_score' => $content->seo_score ?? 0,
                'word_count' => $content->word_count ?? 0,
                'published_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ]),
        ];

        // Add JSON-LD if available
        if ($content->json_ld) {
            $data['fields']['json_ld'] = ['stringValue' => json_encode($content->json_ld)];
        }
        if ($content->hreflang_map) {
            $data['fields']['hreflang_map'] = ['stringValue' => json_encode($content->hreflang_map)];
        }

        // Add FAQs if available
        if (method_exists($content, 'faqs') && $content->faqs) {
            $faqsData = $content->faqs->map(fn ($faq) => [
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])->toArray();
            $data['fields']['faqs'] = ['stringValue' => json_encode($faqsData)];
        }

        // Add featured image if available
        if ($content->featured_image_url) {
            $data['fields']['featured_image'] = ['stringValue' => json_encode([
                'url' => $content->featured_image_url,
                'alt' => $content->featured_image_alt ?? '',
                'attribution' => $content->featured_image_attribution ?? '',
            ])];
        }

        // Add reading time if available
        if ($content->reading_time_minutes) {
            $data['fields']['reading_time_minutes'] = ['integerValue' => (string) $content->reading_time_minutes];
        }

        // Add keywords if available
        if ($content->keywords_primary) {
            $data['fields']['keywords_primary'] = ['stringValue' => $content->keywords_primary];
        }
        if ($content->keywords_secondary) {
            $data['fields']['keywords_secondary'] = ['stringValue' => json_encode($content->keywords_secondary)];
        }

        // Use Firebase REST API
        $documentId = $content->uuid ?? $content->id;

        try {
            $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";

            $token = $this->getAccessToken();
            $request = Http::timeout(30);
            if ($token) {
                $request = $request->withToken($token);
            }
            $response = $request->patch($url, $data);

            if ($response->successful()) {
                Log::info('FirestorePublisher: published', ['id' => $documentId, 'collection' => $collection]);

                // Build public article URL
                $siteUrl = config('services.site.url', 'https://sos-expat.com');
                $publicUrl = rtrim($siteUrl, '/') . '/' . ($content->language ?? 'fr') . '/blog/' . ($content->slug ?? $documentId);

                return [
                    'external_id' => $documentId,
                    'external_url' => $publicUrl,
                ];
            }

            Log::error('FirestorePublisher: failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Firestore publish failed: HTTP ' . $response->status());
        } catch (\Throwable $e) {
            Log::error('FirestorePublisher: exception', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get a Google access token using the Firebase service account key.
     */
    private function getAccessToken(): string
    {
        $keyPath = config('services.firebase.service_account_key');
        if (!$keyPath || !file_exists(base_path($keyPath))) {
            // Fallback: try without auth (works for public Firestore rules or emulator)
            return '';
        }

        $serviceAccount = json_decode(file_get_contents(base_path($keyPath)), true);

        // Build JWT
        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signature = '';
        openssl_sign("$header.$payload", $signature, $serviceAccount['private_key'], 'SHA256');
        $jwt = "$header.$payload." . base64_encode($signature);

        // Exchange JWT for access token
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->successful()) {
            return $response->json('access_token');
        }

        throw new \RuntimeException('Failed to get Firebase access token: ' . $response->body());
    }

    private function toFirestoreFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            if (is_int($value)) {
                $fields[$key] = ['integerValue' => (string) $value];
            } elseif (is_bool($value)) {
                $fields[$key] = ['booleanValue' => $value];
            } elseif (is_float($value)) {
                $fields[$key] = ['doubleValue' => $value];
            } else {
                $fields[$key] = ['stringValue' => (string) ($value ?? '')];
            }
        }
        return $fields;
    }
}
