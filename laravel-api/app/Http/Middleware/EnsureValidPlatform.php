<?php

namespace App\Http\Middleware;

use App\Services\Social\SocialDriverManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests to /social/{platform}/* whose platform is unknown or disabled
 * (via config/social.php → drivers.{platform}.enabled).
 *
 * Alias: 'social.platform' (registered in bootstrap/app.php).
 */
class EnsureValidPlatform
{
    public function __construct(private SocialDriverManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $platform = $request->route('platform');

        if (!$platform || !in_array($platform, $this->manager->allPlatforms(), true)) {
            return response()->json([
                'error'    => "Unknown social platform: '{$platform}'",
                'platforms' => $this->manager->allPlatforms(),
            ], 404);
        }

        // OAuth routes (authorize + callback) must remain accessible even when
        // the platform is disabled — otherwise users can never connect for the
        // first time and Meta App Review reviewers can't test the OAuth flow.
        // Only the publish / queue / stats routes are gated by `enabled`.
        $uri = $request->getRequestUri();
        $isOAuthRoute = str_contains($uri, '/oauth/authorize')
                     || str_contains($uri, '/oauth/callback');

        if (!$isOAuthRoute && !$this->manager->isEnabled($platform)) {
            return response()->json([
                'error'    => "Platform '{$platform}' is disabled",
                'hint'     => "Set SOCIAL_" . strtoupper($platform) . "_ENABLED=true in .env",
            ], 403);
        }

        return $next($request);
    }
}
