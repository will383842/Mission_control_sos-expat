<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Contract tests for the public Knowledge Base endpoint.
 *
 * Downstream consumers (Blog_sos-expat_frontend, Social Multi-Platform,
 * Backlink Engine) will depend on this endpoint. These tests guard the
 * public contract: scope, headers, conditional GET, sensitive exclusions.
 */
class KnowledgeBasePublicEndpointTest extends TestCase
{
    public function test_endpoint_returns_200_with_public_scope(): void
    {
        $response = $this->get('/api/public/knowledge-base');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('X-KB-Version', '2.1.0');
        $response->assertHeader('X-KB-Updated-At', '2026-04-20');
    }

    public function test_endpoint_returns_etag_for_conditional_get(): void
    {
        $response = $this->get('/api/public/knowledge-base');
        $etag = $response->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // Conditional GET with the same ETag should return 304
        $response2 = $this->withHeaders(['If-None-Match' => $etag])
            ->get('/api/public/knowledge-base');
        $response2->assertStatus(304);
    }

    public function test_public_payload_excludes_sensitive_sections(): void
    {
        $response = $this->get('/api/public/knowledge-base');
        $data = $response->json();

        $this->assertArrayNotHasKey('anti_fraud', $data, 'public must exclude anti_fraud');
        $this->assertArrayNotHasKey('infrastructure', $data, 'public must exclude infrastructure');
        $this->assertArrayNotHasKey('backlink_engine', $data, 'public must exclude backlink_engine');
        $this->assertArrayNotHasKey('cloudflare_edge_cache', $data, 'public must exclude cloudflare_edge_cache');

        // Telegram bot handles stripped but notifications structure kept
        $this->assertArrayHasKey('notifications', $data);
        if (isset($data['notifications']['telegram'])) {
            $this->assertArrayNotHasKey('bot_names', $data['notifications']['telegram']);
            $this->assertArrayNotHasKey('bots', $data['notifications']['telegram']);
        }

        // Scope marker
        $this->assertSame('public', $data['_scope']);
    }

    public function test_public_payload_includes_core_content_data(): void
    {
        $response = $this->get('/api/public/knowledge-base');
        $data = $response->json();

        // What downstream services actually need
        $this->assertArrayHasKey('identity', $data);
        $this->assertArrayHasKey('services', $data);
        $this->assertArrayHasKey('programs', $data);
        $this->assertArrayHasKey('brand_voice', $data);
        $this->assertArrayHasKey('content_rules', $data);
        $this->assertArrayHasKey('seo_rules', $data);
        $this->assertArrayHasKey('coverage', $data);
        $this->assertArrayHasKey('tools', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_public_pricing_matches_single_source_of_truth(): void
    {
        $response = $this->get('/api/public/knowledge-base');
        $data = $response->json();

        // Hardcoded from pricingService.ts DEFAULT_PRICING_CONFIG.
        // If these drift, downstream-generated content will mis-promise.
        $this->assertSame(49, $data['services']['lawyer']['price_eur']);
        $this->assertSame(55, $data['services']['lawyer']['price_usd']);
        $this->assertSame(19, $data['services']['expat']['price_eur']);
        $this->assertSame(25, $data['services']['expat']['price_usd']);
    }

    public function test_public_commissions_match_defaultplans_ts(): void
    {
        $response = $this->get('/api/public/knowledge-base');
        $data = $response->json();

        // Hardcoded from defaultPlans.ts CHATTER_V1.
        $this->assertSame(500, $data['programs']['chatter']['client_lawyer_call']);
        $this->assertSame(300, $data['programs']['chatter']['client_expat_call']);
        $this->assertSame(100, $data['programs']['chatter']['n1_call_commission']);
        $this->assertSame(50, $data['programs']['chatter']['n2_call_commission']);

        // Influencer $5 fixed discount (post-audit alignment)
        $this->assertSame(500, $data['programs']['influencer']['client_discount']);

        // Milestones explicit in KB for these roles. Captain inherits via the
        // 'inherits' field, not a duplicated milestones array.
        foreach (['chatter', 'influencer', 'blogger', 'group_admin'] as $role) {
            $this->assertArrayHasKey(
                'milestones',
                $data['programs'][$role],
                "programs.{$role} must expose milestones in the public payload"
            );
            $this->assertSame(
                400000,
                $data['programs'][$role]['milestones'][500],
                "programs.{$role} top milestone must be \$4000 at 500 filleuls"
            );
        }
        $this->assertStringContainsString(
            'Chatter',
            $data['programs']['captain_chatter']['inherits'] ?? '',
            'captain_chatter.inherits points to the chatter milestones'
        );
    }

    public function test_meta_endpoint_is_small_and_cacheable(): void
    {
        $response = $this->get('/api/public/knowledge-base/meta');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertSame('2.1.0', $data['kb_version']);
        $this->assertSame('2026-04-20', $data['kb_updated_at']);
        $this->assertSame('public', $data['scope']);
        $this->assertArrayHasKey('endpoints', $data);
        $this->assertArrayHasKey('full', $data['endpoints']);
        $this->assertArrayHasKey('meta', $data['endpoints']);

        // Meta should be much smaller than full payload
        $this->assertLessThan(1000, strlen($response->getContent()));
    }

    public function test_no_route_conflict_with_web_catchall(): void
    {
        // The .json extension variant intentionally does NOT exist — it
        // collided with a web catch-all serving the SPA. Consumers must use
        // the extensionless canonical route.
        $response = $this->get('/api/public/knowledge-base.json');
        // Either 404 (not registered) or HTML catch-all (wrong). What we
        // check is that we did NOT register this alias, so the route list
        // at /api/public/knowledge-base stays authoritative.
        $this->assertNotSame(200, $response->getStatusCode(), '.json alias must NOT return 200 JSON (would collide with SPA)');
    }

    public function test_service_layer_separates_public_and_full(): void
    {
        $svc = new \App\Services\Content\KnowledgeBaseService();
        $public = $svc->toPublicArray();
        $full = $svc->toFullArray();

        $this->assertLessThan(strlen(json_encode($full)), strlen(json_encode($public)));
        $this->assertArrayNotHasKey('anti_fraud', $public);
        $this->assertArrayHasKey('anti_fraud', $full);
        $this->assertSame('public', $public['_scope']);
        $this->assertSame('full', $full['_scope']);
    }
}
