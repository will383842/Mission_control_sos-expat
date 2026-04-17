<?php

namespace App\Services\Social\Contracts;

use App\Models\SocialPost;

/**
 * Every social platform driver implements this contract.
 *
 * Capability flags (supports*) let the generic controller / jobs skip
 * or adapt behavior per platform (e.g. skip first-comment job on Threads,
 * validate that Instagram posts have an image, etc.).
 */
interface SocialPublishingServiceInterface
{
    /** The platform slug: linkedin | facebook | threads | instagram */
    public function platform(): string;

    /**
     * Whether a valid token exists for the given account_type.
     * If accountType is null, check any account connected to the platform.
     */
    public function isConfigured(?string $accountType = null): bool;

    /**
     * Return token connection status for every account_type supported.
     * Shape:
     *   [ 'personal' => ['connected' => bool, 'name' => string|null, 'expires_in_days' => int|null, ...], ... ]
     */
    public function getTokenStatus(): array;

    /**
     * Publish a post to the platform. Returns the platform post id/URN on success.
     * Throws \RuntimeException on unrecoverable failure.
     */
    public function publish(SocialPost $post, ?string $accountType = null): ?string;

    /**
     * Fetch recent comments on a published post.
     * Each comment: ['platform_comment_id', 'author_name', 'author_platform_id', 'text', 'commented_at'].
     */
    public function getComments(string $platformPostId, ?string $accountType = null): array;

    /** Post a reply to a comment. Returns true on success. */
    public function postReply(string $platformPostId, string $text, ?string $accountType = null): bool;

    /** Post a comment on a published post (used for the "first comment" engagement trick). */
    public function postFirstComment(string $platformPostId, string $text, ?string $accountType = null): bool;

    /**
     * Fetch analytics for a published post.
     * Shape: ['reach' => int, 'likes' => int, 'comments' => int, 'shares' => int, 'clicks' => int].
     * Missing metrics should be omitted or set to 0 — the controller does not assume a fixed shape.
     */
    public function fetchAnalytics(string $platformPostId, ?string $accountType = null): array;

    /** Build the OAuth authorize URL for the given account_type. */
    public function getOAuthUrl(string $accountType, string $state): string;

    /**
     * Handle the OAuth callback: exchange code for tokens, persist to social_tokens.
     * Returns the freshly-saved \App\Models\SocialToken (or null on failure).
     */
    public function handleOAuthCallback(string $code, string $accountType): ?\App\Models\SocialToken;

    // ── Capability flags ───────────────────────────────────────────────

    /** Does the platform support a "first comment" posted by the author? */
    public function supportsFirstComment(): bool;

    /** Does the platform use hashtags meaningfully? */
    public function supportsHashtags(): bool;

    /** Is an image REQUIRED for a post (Instagram feed) or only optional? */
    public function requiresImage(): bool;

    /** Max characters for a post body. */
    public function maxContentLength(): int;

    /** Supported account_types for this platform (e.g. ['personal','page'] for LinkedIn). */
    public function supportedAccountTypes(): array;
}
