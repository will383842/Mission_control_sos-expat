<?php

namespace App\Http\Controllers;

use App\Models\LinkedInToken;
use App\Services\Social\LinkedInApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LinkedIn OAuth 2.0 flow.
 *
 * Step 1: GET  /content-gen/linkedin/oauth/authorize?account_type=personal|page
 *         → redirects to LinkedIn authorization page
 *
 * Step 2: GET  /content-gen/linkedin/oauth/callback?code=...&state=...
 *         → exchanges code for token, stores in linkedin_tokens, redirects to Mission Control
 *
 * Other:  GET  /content-gen/linkedin/oauth/status
 *         DEL  /content-gen/linkedin/oauth/disconnect?account_type=personal|page
 *         GET  /content-gen/linkedin/oauth/orgs — list managed org pages (for page setup)
 *         PUT  /content-gen/linkedin/oauth/set-page — save chosen org ID as 'page' token
 */
class LinkedInOAuthController extends Controller
{
    // Scopes needed:
    // personal → w_member_social (post), r_liteprofile (read id/name)
    // page     → rw_organization_social (post to page), r_organization_social (read)
    // OpenID for profile name fetch
    // Personal only: w_member_social = post, openid+profile = read name/id
    // Page scopes (rw_organization_social) removed — Community Management API not approved
    // offline_access requires explicit LinkedIn app approval — not requested here.
    // Token auto-refresh via refresh_token is handled in LinkedInApiService if LinkedIn
    // returns one, but we don't force-request it to avoid OAuth errors.
    private const SCOPES = 'openid,profile,w_member_social';

    public function __construct(private LinkedInApiService $api) {}

    // ── 1. Redirect to LinkedIn ────────────────────────────────────────

    public function authorize(Request $request): RedirectResponse
    {
        $accountType = $request->get('account_type', 'personal');
        $state       = Str::random(32) . '|' . $accountType;

        // API routes are stateless — use Cache instead of session
        Cache::put('li_oauth_state', $state, now()->addMinutes(10));

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => config('services.linkedin.client_id'),
            'redirect_uri'  => config('services.linkedin.redirect_uri'),
            'state'         => $state,
            'scope'         => self::SCOPES,
        ]);

        return redirect('https://www.linkedin.com/oauth/v2/authorization?' . $params);
    }

    // ── 2. Callback — exchange code for token ─────────────────────────

    public function callback(Request $request): RedirectResponse
    {
        $dashboardUrl = config('services.linkedin.dashboard_url', '/');

        // CSRF check — use Cache (API routes are stateless)
        $savedState = Cache::get('li_oauth_state');
        if (!$savedState || $request->state !== $savedState) {
            Log::error('LinkedIn OAuth: state mismatch');
            return redirect($dashboardUrl . '?li_error=state_mismatch');
        }

        if ($request->has('error')) {
            Log::warning('LinkedIn OAuth: user denied', ['error' => $request->error]);
            return redirect($dashboardUrl . '?li_error=' . $request->error);
        }

        // Parse account type from state
        [, $accountType] = explode('|', $savedState, 2) + ['', 'personal'];

        try {
            // Exchange code → access token
            $tokenResp = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type'    => 'authorization_code',
                'code'          => $request->code,
                'redirect_uri'  => config('services.linkedin.redirect_uri'),
                'client_id'     => config('services.linkedin.client_id'),
                'client_secret' => config('services.linkedin.client_secret'),
            ]);

            if (!$tokenResp->successful()) {
                Log::error('LinkedIn OAuth: token exchange failed', ['body' => $tokenResp->body()]);
                return redirect($dashboardUrl . '?li_error=token_exchange_failed');
            }

            $tokenData    = $tokenResp->json();
            $accessToken  = $tokenData['access_token'];
            $expiresIn    = $tokenData['expires_in'] ?? 5184000; // 60 days default
            $refreshToken = $tokenData['refresh_token'] ?? null;

            // Fetch LinkedIn profile ID
            $profile = $this->api->fetchPersonalId($accessToken);
            if (!$profile) {
                return redirect($dashboardUrl . '?li_error=profile_fetch_failed');
            }

            $linkedinId   = $profile['id'];
            $linkedinName = $profile['name'];

            // For 'page' account type: we store the personal token first, then
            // the admin picks which org to use via /oauth/set-page
            // (We reuse the same token for org posting if user is admin of that org)

            LinkedInToken::updateOrCreate(
                ['account_type' => $accountType],
                [
                    'access_token'  => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_at'    => now()->addSeconds($expiresIn),
                    'linkedin_id'   => $linkedinId,
                    'linkedin_name' => $linkedinName,
                    'scope'         => implode(',', (array) ($tokenData['scope'] ?? [])),
                ]
            );

            Log::info('LinkedIn OAuth: token stored', [
                'account_type' => $accountType,
                'name'         => $linkedinName,
            ]);

            return redirect($dashboardUrl . '?li_connected=' . $accountType);

        } catch (\Throwable $e) {
            Log::error('LinkedIn OAuth: callback exception', ['error' => $e->getMessage()]);
            return redirect($dashboardUrl . '?li_error=exception');
        }
    }

    // ── Status ─────────────────────────────────────────────────────────

    public function status(): JsonResponse
    {
        return response()->json($this->api->getTokenStatus());
    }

    // ── List managed org pages ─────────────────────────────────────────

    public function orgs(): JsonResponse
    {
        $token = LinkedInToken::forPersonal()->first();
        if (!$token || !$token->isValid()) {
            return response()->json(['error' => 'Connect personal account first'], 400);
        }

        $orgs = $this->api->fetchManagedOrgs($token->access_token);
        return response()->json(['orgs' => $orgs]);
    }

    /**
     * Once the user has chosen which org to use for the company page,
     * set the 'page' token by copying the personal token but with the chosen org ID.
     * (LinkedIn uses the same access token for both personal + page publishing,
     * only the `author` URN in the post body differs.)
     */
    public function setPage(Request $request): JsonResponse
    {
        $request->validate([
            'org_id'   => 'required|string',
            'org_name' => 'nullable|string|max:255',
        ]);

        $personalToken = LinkedInToken::forPersonal()->first();
        if (!$personalToken || !$personalToken->isValid()) {
            return response()->json(['error' => 'Connect personal account first'], 400);
        }

        LinkedInToken::updateOrCreate(
            ['account_type' => 'page'],
            [
                'access_token'  => $personalToken->access_token,
                'refresh_token' => $personalToken->refresh_token,
                'expires_at'    => $personalToken->expires_at,
                'linkedin_id'   => $request->org_id,
                'linkedin_name' => $request->org_name ?? 'SOS-Expat',
                'scope'         => $personalToken->scope,
            ]
        );

        return response()->json(['message' => 'Page token set', 'org' => $request->org_id]);
    }

    // ── Disconnect ─────────────────────────────────────────────────────

    public function disconnect(Request $request): JsonResponse
    {
        $accountType = $request->get('account_type', 'personal');
        LinkedInToken::where('account_type', $accountType)->delete();
        return response()->json(['message' => "LinkedIn {$accountType} disconnected"]);
    }
}
