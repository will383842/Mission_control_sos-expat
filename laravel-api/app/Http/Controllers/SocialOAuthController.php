<?php

namespace App\Http\Controllers;

use App\Models\SocialToken;
use App\Services\Social\Drivers\LinkedInDriver;
use App\Services\Social\SocialDriverManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Multi-platform OAuth flow.
 *
 * Routes:
 *   GET  /social/{platform}/oauth/authorize  — redirect to platform authorize URL
 *   GET  /social/{platform}/oauth/callback   — exchange code, persist SocialToken
 *   GET  /social/{platform}/oauth/status     — token connection status
 *   DEL  /social/{platform}/oauth/disconnect — delete SocialToken
 *
 * LinkedIn-only (returns 404 on other platforms):
 *   GET  /social/linkedin/oauth/orgs         — list managed organizations
 *   POST /social/linkedin/oauth/set-page     — save chosen org id as the 'page' token
 */
class SocialOAuthController extends Controller
{
    public function __construct(private SocialDriverManager $manager) {}

    // ── 1. Redirect to platform authorize URL ──────────────────────────

    public function authorize(Request $request, string $platform): RedirectResponse
    {
        $driver      = $this->manager->driver($platform);
        $accountType = $request->get('account_type', $driver->supportedAccountTypes()[0]);

        if (!in_array($accountType, $driver->supportedAccountTypes(), true)) {
            $fallback = config("services.{$platform}.dashboard_url", '/');
            return redirect($fallback . '?error=invalid_account_type');
        }

        $state = Str::random(32) . '|' . $platform . '|' . $accountType;
        Cache::put($this->stateCacheKey($platform), $state, now()->addMinutes(10));

        return redirect($driver->getOAuthUrl($accountType, $state));
    }

    // ── 2. Callback — exchange code, persist token ────────────────────

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $driver       = $this->manager->driver($platform);
        $dashboardUrl = config("services.{$platform}.dashboard_url",
                        config('services.linkedin.dashboard_url', '/'));

        $savedState = Cache::get($this->stateCacheKey($platform));
        if (!$savedState || $request->state !== $savedState) {
            Log::error("{$platform} OAuth: state mismatch");
            return redirect($dashboardUrl . "?social_error=state_mismatch&platform={$platform}");
        }

        if ($request->has('error')) {
            Log::warning("{$platform} OAuth: user denied", ['error' => $request->error]);
            return redirect($dashboardUrl . "?social_error={$request->error}&platform={$platform}");
        }

        // state = random|platform|accountType
        $parts = explode('|', $savedState);
        $accountType = $parts[2] ?? $driver->supportedAccountTypes()[0];

        try {
            $token = $driver->handleOAuthCallback($request->code, $accountType);

            if (!$token) {
                return redirect($dashboardUrl . "?social_error=token_exchange_failed&platform={$platform}");
            }

            Log::info("{$platform} OAuth: token stored", [
                'platform'     => $platform,
                'account_type' => $accountType,
                'user_name'    => $token->platform_user_name,
            ]);

            return redirect($dashboardUrl . "?social_connected={$platform}:{$accountType}");

        } catch (\Throwable $e) {
            Log::error("{$platform} OAuth: callback exception", ['error' => $e->getMessage()]);
            return redirect($dashboardUrl . "?social_error=exception&platform={$platform}");
        }
    }

    // ── Status ─────────────────────────────────────────────────────────

    public function status(string $platform): JsonResponse
    {
        return response()->json([
            'platform' => $platform,
            'tokens'   => $this->manager->driver($platform)->getTokenStatus(),
        ]);
    }

    // ── Disconnect ─────────────────────────────────────────────────────

    public function disconnect(Request $request, string $platform): JsonResponse
    {
        $driver      = $this->manager->driver($platform);
        $accountType = $request->get('account_type', $driver->supportedAccountTypes()[0]);

        SocialToken::where('platform', $platform)
            ->where('account_type', $accountType)
            ->delete();

        return response()->json([
            'platform'     => $platform,
            'account_type' => $accountType,
            'message'      => "{$platform} {$accountType} disconnected",
        ]);
    }

    // ── LinkedIn-only: list managed orgs ──────────────────────────────

    public function orgs(string $platform): JsonResponse
    {
        if ($platform !== 'linkedin') {
            return response()->json(['error' => "/oauth/orgs is only supported on linkedin"], 404);
        }

        $token = SocialToken::lookup('linkedin', 'personal');
        if (!$token || !$token->isValid()) {
            return response()->json(['error' => 'Connect personal account first'], 400);
        }

        /** @var LinkedInDriver $driver */
        $driver = $this->manager->driver('linkedin');
        return response()->json(['orgs' => $driver->fetchManagedOrgs($token->access_token)]);
    }

    // ── LinkedIn-only: copy personal token to page slot with chosen org id ──

    public function setPage(Request $request, string $platform): JsonResponse
    {
        if ($platform !== 'linkedin') {
            return response()->json(['error' => '/oauth/set-page is only supported on linkedin'], 404);
        }

        $request->validate([
            'org_id'   => 'required|string',
            'org_name' => 'nullable|string|max:255',
        ]);

        $personal = SocialToken::lookup('linkedin', 'personal');
        if (!$personal || !$personal->isValid()) {
            return response()->json(['error' => 'Connect personal account first'], 400);
        }

        SocialToken::updateOrCreate(
            ['platform' => 'linkedin', 'account_type' => 'page'],
            [
                'access_token'             => $personal->access_token,
                'refresh_token'            => $personal->refresh_token,
                'expires_at'               => $personal->expires_at,
                'refresh_token_expires_at' => $personal->refresh_token_expires_at,
                'platform_user_id'         => $request->org_id,
                'platform_user_name'       => $request->org_name ?? 'SOS-Expat',
                'scope'                    => $personal->scope,
            ]
        );

        return response()->json(['message' => 'Page token set', 'org' => $request->org_id]);
    }

    private function stateCacheKey(string $platform): string
    {
        return "social_oauth_state:{$platform}";
    }
}
