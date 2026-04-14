<?php

namespace App\Http\Controllers;

use App\Jobs\PostLinkedInFirstCommentJob;
use App\Models\LinkedInPost;
use App\Models\LinkedInPostComment;
use App\Services\Social\LinkedInApiService;
use App\Services\Social\TelegramAlertService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Telegram webhook receiver for two LinkedIn flows:
 *
 * ─ Flow 1: Post confirmation (1-tap publish) ──────────────────────────
 *   Triggered when LINKEDIN_TELEGRAM_CONFIRM=true and a post reaches its
 *   scheduled_at time. AutoPublishLinkedInCommand sends a preview with
 *   inline buttons instead of publishing directly.
 *
 *   Callbacks:
 *     li_confirm_{postId}  → publish now
 *     li_skip_{postId}     → postpone +1 day
 *
 * ─ Flow 2: Comment reply ──────────────────────────────────────────────
 *   Triggered when CheckLinkedInCommentsCommand detects a new comment
 *   and sends a Telegram notification with reply options.
 *
 *   Callbacks:
 *     li_replyv_{commentId}_{variantIdx}  → post pre-generated variant
 *     li_custm_{commentId}                → enter custom reply mode
 *     li_ignr_{commentId}                 → ignore, mark as seen
 *
 *   Custom reply mode:
 *     - Redis key `li_pending_reply:{chatId}` set to commentId (TTL 10 min)
 *     - Next text message from that chat → posted as LinkedIn reply
 *     - Allows full personalisation before posting
 *
 * Security: X-Telegram-Bot-Api-Secret-Token header (TELEGRAM_LINKEDIN_WEBHOOK_SECRET).
 */
class LinkedInTelegramController extends Controller
{
    private const PENDING_TTL = 600; // 10 minutes

    public function __construct(
        private LinkedInApiService   $api,
        private TelegramAlertService $telegram,
    ) {}

    // ── Entry point ────────────────────────────────────────────────────

    public function webhook(Request $request): Response
    {
        // Security check
        $secret = config('services.linkedin.telegram_webhook_secret', '');
        if ($secret) {
            $incoming = $request->header('X-Telegram-Bot-Api-Secret-Token', '');
            if (!hash_equals($secret, $incoming)) {
                Log::warning('LinkedInTelegramController: invalid secret token');
                return response('Forbidden', 403);
            }
        }

        $body = $request->json()->all();

        // ── callback_query: button tap ────────────────────────────────
        if (isset($body['callback_query'])) {
            $cb        = $body['callback_query'];
            $cbId      = $cb['id'] ?? '';
            $data      = $cb['data'] ?? '';
            $messageId = $cb['message']['message_id'] ?? null;
            $chatId    = (string) ($cb['message']['chat']['id'] ?? '');

            Log::info('LinkedInTelegramController: callback', ['data' => $data]);

            if (str_starts_with($data, 'li_confirm_')) {
                $postId = (int) str_replace('li_confirm_', '', $data);
                $this->handleConfirm($postId, $cbId, $messageId);

            } elseif (str_starts_with($data, 'li_skip_')) {
                $postId = (int) str_replace('li_skip_', '', $data);
                $this->handleSkip($postId, $cbId, $messageId);

            } elseif (str_starts_with($data, 'li_replyv_')) {
                // li_replyv_{commentId}_{variantIdx}
                $parts     = explode('_', str_replace('li_replyv_', '', $data), 2);
                $commentId = (int) ($parts[0] ?? 0);
                $varIdx    = (int) ($parts[1] ?? 0);
                $this->handleReplyVariant($commentId, $varIdx, $cbId, $messageId);

            } elseif (str_starts_with($data, 'li_custm_')) {
                $commentId = (int) str_replace('li_custm_', '', $data);
                $this->handleCustomReplyStart($commentId, $chatId, $cbId, $messageId);

            } elseif (str_starts_with($data, 'li_ignr_')) {
                $commentId = (int) str_replace('li_ignr_', '', $data);
                $this->handleIgnore($commentId, $cbId, $messageId);

            } else {
                $this->telegram->answerCallback($cbId, '❓ Action inconnue');
            }

            return response('ok', 200);
        }

        // ── text message: check for pending custom reply ──────────────
        if (isset($body['message']['text']) && isset($body['message']['chat']['id'])) {
            $chatId  = (string) $body['message']['chat']['id'];
            $text    = $body['message']['text'];
            $this->handlePendingCustomReply($chatId, $text);
        }

        return response('ok', 200);
    }

    // ── Flow 1: Post confirmation ──────────────────────────────────────

    private function handleConfirm(int $postId, string $cbId, ?int $messageId): void
    {
        $post = LinkedInPost::find($postId);

        if (!$post) {
            $this->telegram->answerCallback($cbId, '❌ Post introuvable', true);
            return;
        }

        if (!in_array($post->status, ['scheduled', 'pending_confirm'], true)) {
            $this->telegram->answerCallback($cbId, "⚠️ Statut : {$post->status}", true);
            return;
        }

        $this->telegram->answerCallback($cbId, '⏳ Publication en cours...');

        try {
            $this->publishPost($post);

            if ($messageId) {
                $slot = $post->published_at?->format('H:i') ?? now()->format('H:i');
                $this->telegram->editMessageText(
                    $messageId,
                    "✅ <b>Publié sur LinkedIn !</b>\n\nPost #{$postId} · {$slot}\n\n" . mb_substr($post->hook ?? '', 0, 100) . '...'
                );
            }
        } catch (\Throwable $e) {
            Log::error('LinkedInTelegramController: confirm failed', ['post_id' => $postId, 'error' => $e->getMessage()]);
            $this->telegram->answerCallback($cbId, '❌ Erreur : ' . mb_substr($e->getMessage(), 0, 100), true);
            if ($messageId) {
                $this->telegram->editMessageText(
                    $messageId,
                    "❌ <b>Échec</b> Post #{$postId}\n" . mb_substr($e->getMessage(), 0, 200)
                );
            }
        }
    }

    private function handleSkip(int $postId, string $cbId, ?int $messageId): void
    {
        $post = LinkedInPost::find($postId);

        if (!$post) {
            $this->telegram->answerCallback($cbId, '❌ Post introuvable', true);
            return;
        }

        $newTime = ($post->scheduled_at ?? now())->addDay();
        $post->update(['status' => 'scheduled', 'scheduled_at' => $newTime]);

        $this->telegram->answerCallback($cbId, '📅 Repoussé à ' . $newTime->format('D d/m H:i'));

        if ($messageId) {
            $this->telegram->editMessageText(
                $messageId,
                "📅 <b>Post #{$postId} repoussé</b>\nNouvelle date : " . $newTime->format('D d/m à H:i') . "\n\n" . mb_substr($post->hook ?? '', 0, 100) . '...'
            );
        }
    }

    // ── Flow 2: Comment replies ────────────────────────────────────────

    /** User tapped one of the 3 pre-generated variant buttons */
    private function handleReplyVariant(int $commentId, int $variantIdx, string $cbId, ?int $messageId): void
    {
        $comment = LinkedInPostComment::with('linkedinPost')->find($commentId);

        if (!$comment || $comment->replied_at) {
            $this->telegram->answerCallback($cbId, $comment?->replied_at ? '✅ Déjà répondu' : '❌ Commentaire introuvable', true);
            return;
        }

        // Retrieve stored variants from Redis (set at notification time)
        $variantsKey = "li_variants_{$commentId}";
        $variantsJson = $this->redisGet($variantsKey);
        $variants = $variantsJson ? json_decode($variantsJson, true) : [];

        $replyText = $variants[$variantIdx] ?? null;

        if (!$replyText) {
            $this->telegram->answerCallback($cbId, '❌ Variante introuvable', true);
            return;
        }

        $this->telegram->answerCallback($cbId, '⏳ Publication de la réponse...');
        $this->postCommentReply($comment, $replyText, 'variant', $cbId, $messageId);
    }

    /** User tapped "✏️ Personnaliser" — start custom reply mode */
    private function handleCustomReplyStart(int $commentId, string $chatId, string $cbId, ?int $messageId): void
    {
        $comment = LinkedInPostComment::find($commentId);

        if (!$comment) {
            $this->telegram->answerCallback($cbId, '❌ Commentaire introuvable', true);
            return;
        }

        if ($comment->replied_at) {
            $this->telegram->answerCallback($cbId, '✅ Déjà répondu', true);
            return;
        }

        // Store pending state in Redis (TTL 10 min)
        $this->redisSet("li_pending_reply:{$chatId}", (string) $commentId, self::PENDING_TTL);

        $this->telegram->answerCallback($cbId, '✏️ Tape ta réponse ci-dessous');

        $author = $comment->author_name ?? 'cet utilisateur';
        $this->telegram->sendMessage(
            "✏️ <b>Réponse personnalisée</b>\n\nRéponds à <b>{$author}</b> :\n\n<i>\"" . mb_substr($comment->comment_text, 0, 150) . "\"</i>\n\n→ Tape ta réponse maintenant (dans les 10 min) :"
        );
    }

    /** User typed a text message while in custom reply mode */
    private function handlePendingCustomReply(string $chatId, string $text): void
    {
        $commentId = $this->redisGet("li_pending_reply:{$chatId}");
        if (!$commentId) return;

        // Commands (e.g. /start) — ignore
        if (str_starts_with(trim($text), '/')) return;

        $comment = LinkedInPostComment::with('linkedinPost')->find((int) $commentId);

        if (!$comment) {
            $this->redisDelete("li_pending_reply:{$chatId}");
            return;
        }

        // Clear pending state
        $this->redisDelete("li_pending_reply:{$chatId}");

        $this->postCommentReply($comment, $text, 'custom', null, null);
    }

    /** User tapped "🔇 Ignorer" */
    private function handleIgnore(int $commentId, string $cbId, ?int $messageId): void
    {
        $comment = LinkedInPostComment::find($commentId);

        if (!$comment) {
            $this->telegram->answerCallback($cbId, '❌ Introuvable', true);
            return;
        }

        $this->telegram->answerCallback($cbId, '🔇 Ignoré');

        if ($messageId) {
            $author = $comment->author_name ?? 'Inconnu';
            $this->telegram->editMessageText(
                $messageId,
                "🔇 <b>Ignoré</b>\n\nCommentaire de {$author} — aucune réponse"
            );
        }
    }

    // ── Shared: post a reply to LinkedIn and update DB ─────────────────

    private function postCommentReply(
        LinkedInPostComment $comment,
        string              $replyText,
        string              $source,
        ?string             $cbId,
        ?int                $messageId,
    ): void {
        $post        = $comment->linkedinPost;
        $postUrn     = $post?->li_post_id_personal ?? $post?->li_post_id_page;
        $accountType = $post?->li_post_id_personal ? 'personal' : 'page';

        if (!$postUrn) {
            if ($cbId) $this->telegram->answerCallback($cbId, '❌ URN du post introuvable', true);
            return;
        }

        // Prefix reply with author mention for context
        $author    = $comment->author_name ?? '';
        $firstName = explode(' ', $author)[0] ?? '';
        $fullReply = $firstName ? "@{$firstName} {$replyText}" : $replyText;

        $success = $this->api->postReply($postUrn, $fullReply, $accountType);

        if ($success) {
            $comment->update([
                'reply_text'   => $fullReply,
                'replied_at'   => now(),
                'reply_source' => $source,
            ]);

            if ($cbId) $this->telegram->answerCallback($cbId, '✅ Réponse publiée !');

            if ($messageId) {
                $this->telegram->editMessageText(
                    $messageId,
                    "✅ <b>Réponse publiée !</b>\n\n↩️ <i>\"{$replyText}\"</i>\n\nPosté par <b>@{$firstName}</b>"
                );
            } else {
                // Custom reply — send confirmation message
                $this->telegram->sendMessage("✅ <b>Réponse publiée sur LinkedIn !</b>\n\n↩️ \"{$replyText}\"");
            }

            Log::info('LinkedInTelegramController: reply posted', [
                'comment_id' => $comment->id,
                'source'     => $source,
            ]);
        } else {
            if ($cbId) $this->telegram->answerCallback($cbId, '❌ Échec LinkedIn API', true);
            if ($messageId) {
                $this->telegram->editMessageText($messageId, "❌ <b>Échec</b>\n\nImpossible de poster la réponse. Vérifie le token LinkedIn.");
            } else {
                $this->telegram->sendMessage('❌ Impossible de poster la réponse. Vérifie le token LinkedIn.');
            }
        }
    }

    // ── Post publish helper ────────────────────────────────────────────

    private function publishPost(LinkedInPost $post): void
    {
        if ($post->account === 'page') {
            $urn = $this->api->publish($post, 'page');
            if (!$urn) throw new \RuntimeException('LinkedIn page API returned no URN');
            $post->update(['li_post_id_page' => $urn, 'status' => 'published', 'published_at' => now()]);
            $this->scheduleFirstComment($post, $urn, 'page');

        } elseif ($post->account === 'personal') {
            $urn = $this->api->publish($post, 'personal');
            if (!$urn) throw new \RuntimeException('LinkedIn personal API returned no URN');
            $post->update(['li_post_id_personal' => $urn, 'status' => 'published', 'published_at' => now()]);
            $this->scheduleFirstComment($post, $urn, 'personal');

        } elseif ($post->account === 'both') {
            $urn = $this->api->publish($post, 'personal');
            if (!$urn) throw new \RuntimeException('LinkedIn personal API returned no URN (both)');
            $post->update([
                'li_post_id_personal' => $urn,
                'page_publish_after'  => now()->addHours(4)->addMinutes(30),
                'status'              => 'scheduled',
                'published_at'        => now(),
            ]);
            $this->scheduleFirstComment($post, $urn, 'personal');
        }
    }

    private function scheduleFirstComment(LinkedInPost $post, string $postUrn, string $accountType): void
    {
        if (!$post->first_comment) return;
        PostLinkedInFirstCommentJob::dispatch($post->id, $postUrn, $accountType)->delay(now()->addMinutes(3));
    }

    // ── Redis helpers (graceful fallback if Redis not available) ──────

    private function redisGet(string $key): ?string
    {
        try {
            return Redis::get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private function redisSet(string $key, string $value, int $ttl): void
    {
        try {
            Redis::setex($key, $ttl, $value);
        } catch (\Throwable $e) {
            Log::warning('LinkedInTelegramController: Redis set failed', ['error' => $e->getMessage()]);
        }
    }

    private function redisDelete(string $key): void
    {
        try {
            Redis::del($key);
        } catch (\Throwable) {}
    }
}
