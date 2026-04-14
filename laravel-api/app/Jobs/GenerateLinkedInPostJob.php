<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\LinkedInPost;
use App\Models\QaEntry;
use App\Models\Sondage;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAiService;
use App\Services\AI\UnsplashService;
use App\Services\Content\AudienceContextService;
use App\Services\Content\KnowledgeBaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates a LinkedIn post (hook + body + hashtags + first_comment) asynchronously.
 *
 * 14 source types supported:
 *   - article, faq, sondage → pull from DB (best score, dedup)
 *   - hot_take, myth, poll, serie, reactive, milestone, partner_story,
 *     counter_intuition, tip, news, case_study → free generation
 *
 * 5-day rhythm mapping:
 *   Monday    → article (carrousel/liste)
 *   Tuesday   → faq (story fictive)
 *   Wednesday → reactive/hot_take (actu/opinion)
 *   Thursday  → faq / sondage (Q&A / stats)
 *   Friday    → tip / milestone / partner_story
 */
class GenerateLinkedInPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 2;

    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public int $postId)
    {
        $this->onQueue('linkedin');
    }

    public function handle(OpenAiService $openai, KnowledgeBaseService $kb, UnsplashService $unsplash): void
    {
        $post = LinkedInPost::find($this->postId);
        if (!$post) {
            Log::warning('GenerateLinkedInPostJob: post not found', ['id' => $this->postId]);
            return;
        }

        try {
            $lang    = $post->lang === 'both' ? 'fr' : $post->lang;
            $dayType = $post->day_type;

            // ── 1. Fetch source content ──────────────────────────────
            $source = $this->fetchSource($post->source_type, $post->source_id, $lang);

            // ── 2. Build system prompt with full KB + audience ───────
            $kbContext         = $kb->getLightPrompt('linkedin', null, $lang);
            $audienceContext   = AudienceContextService::getContextFor($lang);
            $dayInstructions   = $this->getDayInstructions($dayType, $post->source_type, $lang);
            $angleInstructions = $this->getAngleInstructions($post->source_type, $lang);
            $langLabel         = $lang === 'en' ? 'English' : 'français';

            $systemPrompt = <<<SYSTEM
{$kbContext}

{$audienceContext}

═══════════════════════════════════════════════════════
TU ES : le ghostwriter LinkedIn #1 Europe francophone.
Tes clients : fondateurs de scale-ups, dirigeants expatriés, experts indépendants.
Référence de style : voix de fondateur authentique, pas de marque.
Tes posts génèrent 200+ commentaires. Les posts "commerciaux" génèrent 0.
═══════════════════════════════════════════════════════

MISSION ABSOLUE :
Tu écris pour le FONDATEUR de SOS-Expat.com — sa voix personnelle, son expertise,
ses observations de terrain. PAS pour la marque. PAS une pub.
La marque SOS-Expat n'apparaît JAMAIS dans le post (uniquement dans le 1er commentaire).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
HOOK — MAX 140 CARACTÈRES ABSOLUS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Crée une friction irrésistible avec UN de ces patterns éprouvés :
→ CONFESSION     : "J'ai fait une erreur qui a coûté X à un expatrié. Voici ce que j'ai appris."
→ PARADOXE       : "[Vérité contre-intuitive sur l'expatriation] — personne ne vous le dira."
→ CHIFFRE CHOC   : "X% des expatriés ignorent [problème précis]. Les conséquences sont lourdes."
→ PROVOCATION    : "Le conseil que tout le monde donne sur [sujet] est faux."
→ TENSION BRUTE  : "[Situation dramatique en 1 phrase courte et coupante.]"
JAMAIS de "Quand Marc/Sophie/Ahmed..." — les prénoms inventés sonnent faux.
JAMAIS de question dans le hook — l'affirmation forte performe 3× mieux.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CORPS — OBLIGATOIREMENT 1000-1500 CARACTÈRES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Structure narrative en 4 actes (paragraphes de 1-3 lignes, ligne vide entre chaque) :

ACTE 1 — LA SCÈNE (2-3 lignes) :
Contexte précis et ancré : pays, situation réelle, moment précis.
Donne au lecteur l'impression d'être là.

ACTE 2 — LE PROBLÈME (3-4 lignes) :
La douleur concrète. Ce que ça coûte vraiment (temps, argent, stress, famille).
Pas de généralités — des faits, des chiffres, des détails.

ACTE 3 — L'INSIGHT RARE (4-5 lignes) :
Ce que peu de gens savent. La solution contre-intuitive.
Le truc que seul quelqu'un avec 10 ans d'expérience terrain connaît.
Peut inclure 3-4 points courts avec "→" si et seulement si < 4 items.

ACTE 4 — LA LEÇON + CTA (2-3 lignes) :
1 principe universel que le lecteur peut appliquer aujourd'hui.
Puis UNE question ULTRA-SPÉCIFIQUE (jamais "partagez votre expérience") :
  ✓ "Dans quel pays avez-vous eu le plus de mal à régulariser votre statut ?"
  ✓ "Quel document vous a pris le plus longtemps à obtenir à l'étranger ?"
  ✗ "Et vous, avez-vous déjà vécu ça ?" (trop vague, 0 engagement)
  ✗ "Partagez en commentaire !" (cliché mortel)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INTERDITS ABSOLUS (chaque violation détruit la portée)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✗ "SOS-Expat" dans le post — va dans le 1er commentaire UNIQUEMENT
✗ Tout mot de pub : "découvrez", "notre service", "notre plateforme", "solution"
✗ Histoires en 3ème personne avec prénom inventé ("Marc a découvert...")
✗ CTA génériques : "partagez", "n'hésitez pas", "likez si vous êtes d'accord"
✗ "Résultat ?" seul sur une ligne — cliché 2018
✗ "Le secret de..." — idem
✗ Markdown, **, ##, *, _ — LinkedIn rend tout en texte brut
✗ Plus de 2 émojis dans tout le post
✗ URL ou liens dans le post (UNIQUEMENT dans first_comment)
✗ Listes à puces si > 4 items — listicle = portée divisée par 5

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
HASHTAGS — 3 À 5, ULTRA-NICHE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PAS #expat #expatriation (trop génériques, 0 trafic ciblé).
OUI : #visa #droitdutravail #fiscaliteinternationale #mobiliteinternationale #expatrie
Adapte aux mots-clés de la source.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1ER COMMENTAIRE (posté 3 min après)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
C'est ICI que SOS-Expat.com apparaît — naturellement, pas comme pub :
"Pour ceux qui veulent aller plus loin sur ce sujet → [URL ou SOS-Expat.com]"
+ 1 question de rebond pour relancer les commentaires.
150-250 caractères.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PROFIL PERSONNEL — ALGORITHME LINKEDIN 2026
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Tu écris pour un PROFIL PERSONNEL, pas une page entreprise.
→ Les profils personnels ont 5-8× plus de portée que les pages.
→ La voix FONDATEUR authentique surperforme systématiquement le contenu corporate.

GOLDEN HOUR (90 min décisives) :
Les 90 premières minutes après publication = 70% de la portée finale.
L'algorithme mesure : vitesse des premiers commentaires + dwell time + taux "voir plus".
→ Le hook doit forcer à cliquer "voir plus" (jamais finir le hook sur une idée complète).
→ Un post qui génère 5 commentaires dans les 15 min → distribution ×10.
→ Termine sur une question précise = engagement = reach.

DWELL TIME (métrique cachée 2026) :
LinkedIn mesure le temps passé sur le post, pas les clics.
→ Paragraphes de 1-3 lignes max → l'œil descend → le temps de lecture augmente.
→ Ligne vide obligatoire entre chaque bloc → respiration visuelle (80% de lecture mobile).
→ 1200 chars lus en 45s >>> 400 chars lus en 8s.

POSTS EN ANGLAIS (si lang = EN) :
L'audience LinkedIn anglophone est internationale, plus senior et plus saturée.
→ Angle encore plus counter-intuitive, chiffres encore plus précis.
→ Les expats anglophones : professionnels 30-50 ans, visas de travail, mobilité internationale.
→ Ton expert-to-expert, jamais condescendant ou "conseils de base".
SYSTEM;

            $userPrompt = <<<USER
Génère un post LinkedIn en {$langLabel} — voix de fondateur expert, ZÉRO commercial.

JOUR/FORMAT : {$dayType} — {$dayInstructions}
ANGLE ÉDITORIAL : {$angleInstructions}

SOURCE (inspire-toi, ne recopie pas) :
Titre : {$source['title']}
Contenu : {$source['content']}
Mots-clés : {$source['keywords']}
URL (pour le 1er commentaire UNIQUEMENT) : {$source['url']}

RAPPEL CRITIQUE :
- Hook ≤ 140 chars, première personne "Je", tension immédiate
- SOS-Expat n'apparaît JAMAIS dans hook ni body — uniquement dans first_comment
- Corps 1000-1500 chars, 4 actes, paragraphes courts
- CTA = question précise sur une situation réelle vécue
- Max 2 émojis dans tout le post

Retourne UNIQUEMENT un JSON valide :
{
  "hook": "accroche ≤140 chars, sans saut de ligne, en {$langLabel}",
  "body": "corps 1000-1500 chars, \\n entre paragraphes, en {$langLabel}",
  "hashtags": ["mot1", "mot2", "mot3"],
  "first_comment": "question rebond + lien discret vers SOS-Expat.com ou URL source, 150-250 chars, en {$langLabel}"
}
USER;

            // ── 3. Generate with QA loop (max 3 attempts) ───────────
            $data = $this->generateWithQualityLoop($openai, $systemPrompt, $userPrompt, $post, $source, $lang, $dayType);

            // ── 4. Hashtags: sanitize + fallback from keywords ───────
            $hashtags = $this->buildHashtags($data['hashtags'] ?? [], $source['hashtag_seeds']);

            // ── 5. First comment fallback ────────────────────────────
            $firstComment = $data['first_comment'] ?? $this->defaultFirstComment($post->source_type, $source['url'], $lang);

            // ── 6. Image: article image OR Unsplash search ──────────
            $featuredImage      = $source['image_url'] ?? null;
            $imageAttribution   = null;

            if (!$featuredImage && $unsplash->isConfigured()) {
                $imgQuery  = $this->buildUnsplashQuery($post->source_type, $source['keywords'], $lang);
                $imgResult = $unsplash->searchUnique($imgQuery, 1, 'landscape');
                if ($imgResult['success'] && !empty($imgResult['images'])) {
                    $img              = $imgResult['images'][0];
                    $featuredImage    = $img['url'];
                    $imageAttribution = $img['attribution']; // "Photo by X on Unsplash"
                    Log::info('GenerateLinkedInPostJob: Unsplash image found', [
                        'query' => $imgQuery,
                        'attribution' => $imageAttribution,
                    ]);
                }
            }

            // Append Unsplash attribution to first comment (API requirement)
            if ($imageAttribution) {
                $firstComment .= "\n\n📸 " . $imageAttribution;
            }

            // ── 7. Use pre-assigned slot (set at creation) ──────────
            // scheduled_at is already set by LinkedInController::createAndDispatch()
            // Only recalculate if somehow null (shouldn't happen)
            if (!$post->scheduled_at) {
                $controller = new \App\Http\Controllers\LinkedInController();
                $post->scheduled_at = $controller->nextFreeSlot($lang);
            }

            // ── 8. Update post record → directly scheduled ───────────
            $post->update([
                'hook'                  => $data['hook']  ?? $this->defaultHook($dayType, $lang),
                'body'                  => $data['body']  ?? $this->defaultBody($lang),
                'hashtags'              => $hashtags,
                'first_comment'         => $firstComment,
                'featured_image_url'    => $featuredImage,
                'first_comment_status'  => $firstComment ? 'pending' : null,
                'status'                => 'scheduled',
                'scheduled_at'          => $post->scheduled_at,
                'auto_scheduled'        => true,
                'error_message'         => null,
            ]);

            Log::info('GenerateLinkedInPostJob: done', [
                'post_id'     => $post->id,
                'day'         => $dayType,
                'source_type' => $post->source_type,
                'lang'        => $lang,
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateLinkedInPostJob: failed', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);
            $post->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }

    // ── AI generation with fallback chain ─────────────────────────────

    /**
     * Try GPT-4o → Claude Sonnet → built-in template (3 levels).
     * Returns parsed array with keys: hook, body, hashtags, first_comment.
     * Never throws — worst case returns pre-built template so the post
     * always ends up as 'scheduled' (never 'failed') due to a quota issue.
     */
    private function generateWithFallback(
        OpenAiService $openai,
        string        $systemPrompt,
        string        $userPrompt,
        LinkedInPost  $post,
        array         $source,
        string        $lang,
        string        $dayType,
    ): array {
        // ── Level 1: GPT-4o ────────────────────────────────────────────
        if ($openai->isConfigured()) {
            $r = $openai->complete($systemPrompt, $userPrompt, [
                'model'       => 'gpt-4o',
                'max_tokens'  => 1800,
                'temperature' => 0.78,
                'json_mode'   => true,
            ]);

            if ($r['success'] ?? false) {
                $data = json_decode($r['content'] ?? '', true) ?? [];
                if (!empty($data['hook']) && !empty($data['body'])) {
                    Log::info('GenerateLinkedInPostJob: generated via GPT-4o', ['post_id' => $post->id]);
                    return $data;
                }
            }

            // Check if it's a quota/billing error (don't retry with Claude for transient errors)
            $err = $r['error'] ?? '';
            $isQuota = str_contains($err, '429') || str_contains($err, 'quota')
                    || str_contains($err, 'billing') || str_contains($err, 'budget');

            Log::warning('GenerateLinkedInPostJob: GPT-4o failed', [
                'post_id' => $post->id,
                'error'   => mb_substr($err, 0, 200),
                'is_quota' => $isQuota,
            ]);
        }

        // ── Level 2: Claude Sonnet fallback ────────────────────────────
        try {
            $claude = app(ClaudeService::class);
            if ($claude->isConfigured()) {
                $r2 = $claude->complete($systemPrompt, $userPrompt, [
                    'model'      => 'claude-haiku-4-5-20251001', // fast + cheap for fallback
                    'max_tokens' => 1800,
                    'json_mode'  => true,
                ]);

                if ($r2['success'] ?? false) {
                    $data2 = json_decode($r2['content'] ?? '', true) ?? [];
                    if (!empty($data2['hook']) && !empty($data2['body'])) {
                        Log::info('GenerateLinkedInPostJob: generated via Claude (GPT fallback)', ['post_id' => $post->id]);

                        // Notify Telegram that we had to use the fallback
                        $this->notifyFallback($post, 'GPT-4o quota — Claude utilisé à la place');

                        return $data2;
                    }
                }

                Log::warning('GenerateLinkedInPostJob: Claude fallback also failed', [
                    'post_id' => $post->id,
                    'error'   => mb_substr($r2['error'] ?? '', 0, 200),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('GenerateLinkedInPostJob: Claude fallback exception', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // ── Level 3: Built-in template (no AI required) ────────────────
        Log::warning('GenerateLinkedInPostJob: both AI services failed — using template', ['post_id' => $post->id]);
        $this->notifyFallback($post, '⚠️ GPT + Claude indisponibles — post généré depuis template. Vérifiez vos crédits API.');

        return $this->buildTemplatePost($source, $lang, $dayType, $post->source_type);
    }

    // ── Quality loop (auto-improve until score ≥ 80) ──────────────────────

    /**
     * Up to 3 generation attempts. Each attempt is scored (0-100).
     * If score < 80, the next prompt includes the critique so the AI
     * corrects its own mistakes. Best attempt is always returned.
     */
    private function generateWithQualityLoop(
        OpenAiService $openai,
        string        $systemPrompt,
        string        $userPrompt,
        LinkedInPost  $post,
        array         $source,
        string        $lang,
        string        $dayType,
    ): array {
        $bestData  = null;
        $bestScore = 0;
        $maxRounds = 3;
        $threshold = 80;
        $currentPrompt = $userPrompt;

        for ($round = 1; $round <= $maxRounds; $round++) {
            $data = $this->generateWithFallback($openai, $systemPrompt, $currentPrompt, $post, $source, $lang, $dayType);

            $score    = $this->scorePost($data);
            $critique = $this->buildCritique($data, $lang);

            Log::info('GenerateLinkedInPostJob: QA score', [
                'post_id' => $post->id,
                'round'   => $round,
                'score'   => $score,
                'issues'  => $critique ?: 'none',
            ]);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestData  = $data;
            }

            if ($score >= $threshold || $round === $maxRounds) break;

            // Build corrective prompt for next round
            $langLabel      = $lang === 'en' ? 'English' : 'français';
            $currentPrompt  = $userPrompt . "\n\n" . <<<CRITIQUE
CRITIQUE DU POST PRÉCÉDENT (score: {$score}/100) :
{$critique}

Régénère le post en {$langLabel} en corrigeant UNIQUEMENT ces points.
Garde le même sujet et la même structure JSON.
CRITIQUE;
        }

        if ($bestScore < $threshold) {
            Log::warning('GenerateLinkedInPostJob: QA below threshold after retries', [
                'post_id'    => $post->id,
                'best_score' => $bestScore,
            ]);
        }

        return $bestData;
    }

    /**
     * Score a generated post 0-100 based on LinkedIn TOP 1% criteria.
     *
     * Hook ≤ 140 chars                    → 20 pts
     * No brand/URL in body               → 20 pts  ← KEY: SOS-Expat must NOT appear
     * Body 1000-1600 chars total         → 20 pts
     * First-person voice (je/j'/I )      → 15 pts
     * 3-5 hashtags                       → 10 pts
     * First comment ≥ 100 chars          → 10 pts
     * No commercial clichés in body      →  5 pts
     */
    private function scorePost(array $data): int
    {
        $score = 0;
        $hook         = $data['hook']          ?? '';
        $body         = $data['body']          ?? '';
        $hashtags     = $data['hashtags']       ?? [];
        $firstComment = $data['first_comment'] ?? '';
        $fullBody     = $hook . "\n\n" . $body;

        // 1. Hook ≤ 140 chars
        $hookLen = mb_strlen($hook);
        if ($hookLen > 0 && $hookLen <= 140) $score += 20;
        elseif ($hookLen <= 155)             $score += 10;

        // 2. No brand / URL in post body (SOS-Expat must be in first_comment only)
        $hasBrand = preg_match('/sos.?expat|notre (service|plateforme|solution)|découvrez notre/i', $body);
        $hasUrl   = preg_match('#https?://|www\.#i', $body);
        if (!$hasBrand && !$hasUrl) $score += 20;
        elseif (!$hasUrl)           $score += 8; // URL penalty harsher than brand

        // 3. Body length 1000-1600 chars total
        $totalLen = mb_strlen($fullBody);
        if ($totalLen >= 1000 && $totalLen <= 1600)  $score += 20;
        elseif ($totalLen >= 800 && $totalLen <= 1900) $score += 10;

        // 4. First-person voice
        if (preg_match('/\bje\b|\bj\'|\bI /i', $hook . ' ' . $body)) $score += 15;
        elseif (preg_match('/\bnous\b|\bnotre\b/i', $hook . ' ' . $body)) $score += 5;

        // 5. Hashtag count 3-5
        $hashCount = count(is_array($hashtags) ? $hashtags : []);
        if ($hashCount >= 3 && $hashCount <= 5) $score += 10;
        elseif ($hashCount >= 2 && $hashCount <= 6) $score += 5;

        // 6. First comment substantive (≥ 100 chars)
        if (mb_strlen($firstComment) >= 100) $score += 10;
        elseif (mb_strlen($firstComment) >= 50) $score += 5;

        // 7. No commercial clichés
        $clichés = preg_match('/résultat \?|le secret de|partagez votre expérience|n\'hésitez pas|likez si/i', $body);
        if (!$clichés) $score += 5;

        return min(100, $score);
    }

    /**
     * Return a human-readable critique for the regeneration loop.
     */
    private function buildCritique(array $data, string $lang): string
    {
        $issues = [];
        $hook         = $data['hook']          ?? '';
        $body         = $data['body']          ?? '';
        $hashtags     = $data['hashtags']       ?? [];
        $firstComment = $data['first_comment'] ?? '';
        $hookLen      = mb_strlen($hook);
        $totalLen     = mb_strlen($hook . "\n\n" . $body);
        $hashCount    = count(is_array($hashtags) ? $hashtags : []);

        if ($hookLen > 140)
            $issues[] = "HOOK TROP LONG ({$hookLen} chars, max 140 absolus). Raccourcis-le radicalement — chaque mot compte.";
        if ($hookLen === 0)
            $issues[] = "HOOK MANQUANT. Crée une accroche en première personne, tension immédiate, ≤140 chars.";
        if (!preg_match('/\bje\b|\bj\'|\bI /i', $hook . ' ' . $body))
            $issues[] = "VOIX MANQUANTE. Écris en première personne (Je/J'). Un post à la 3ème personne ou impersonnel ne performe pas.";
        if (preg_match('/sos.?expat|notre (service|plateforme|solution)|découvrez notre/i', $body))
            $issues[] = "CONTENU COMMERCIAL DÉTECTÉ. 'SOS-Expat', 'notre service' etc. sont INTERDITS dans le post. Mets-les UNIQUEMENT dans first_comment. Le post doit être 100% éducatif/expert.";
        if (preg_match('#https?://|www\.#i', $body))
            $issues[] = "URL DANS LE CORPS. Retire tout lien — LinkedIn pénalise algorithmiquement. Le lien va UNIQUEMENT dans first_comment.";
        if ($totalLen < 1000)
            $issues[] = "CORPS TROP COURT ({$totalLen} chars, minimum 1000). Développe l'acte 2 (le problème réel) et l'acte 3 (l'insight rare) avec des détails concrets.";
        if ($totalLen > 1700)
            $issues[] = "CORPS TROP LONG ({$totalLen} chars, max 1600). Coupe les transitions inutiles et les listes trop longues.";
        if ($hashCount < 3)
            $issues[] = "HASHTAGS INSUFFISANTS ({$hashCount}). Ajoute 3-5 hashtags de niche (visa, droitdutravail, mobiliteinternationale...).";
        if ($hashCount > 5)
            $issues[] = "TROP DE HASHTAGS ({$hashCount}, max 5). Garde uniquement les 5 plus ciblés.";
        if (mb_strlen($firstComment) < 100)
            $issues[] = "PREMIER COMMENTAIRE TROP COURT. Doit inclure : question de rebond + lien SOS-Expat.com + accroche vers l'article. Minimum 100 chars.";
        if (preg_match('/résultat \?|le secret de|partagez votre expérience|n\'hésitez pas/i', $body))
            $issues[] = "CLICHÉS LINKEDIN DÉTECTÉS. Supprime 'Résultat ?', 'Le secret de', 'Partagez votre expérience' — ce sont des marqueurs de contenu amateur 2018.";

        return empty($issues) ? '' : "CORRECTIONS OBLIGATOIRES pour le prochain essai :\n" . implode("\n", $issues);
    }

    /** Build a decent post from the source content without any AI */
    private function buildTemplatePost(array $source, string $lang, string $day, string $sourceType): array
    {
        $title    = mb_substr($source['title'] ?? 'SOS-Expat', 0, 80);
        $url      = $source['url'] ?? '';
        $keywords = array_slice(
            array_filter(array_map('trim', explode(',', $source['keywords'] ?? ''))),
            0, 3
        );

        $hooks = [
            'fr' => [
                'monday'    => "5 conseils essentiels pour les expatriés sur : {$title} 👇",
                'tuesday'   => "Ce que personne ne vous dit sur : {$title}",
                'wednesday' => "🚨 Ce que tout expatrié doit savoir : {$title}",
                'thursday'  => "La question du jour : {$title}",
                'friday'    => "Retour d'expérience sur : {$title} ✈️",
            ],
            'en' => [
                'monday'    => "5 essential tips for expats on: {$title} 👇",
                'tuesday'   => "What nobody tells you about: {$title}",
                'wednesday' => "🚨 Every expat needs to know: {$title}",
                'thursday'  => "Question of the day: {$title}",
                'friday'    => "Real expat experience on: {$title} ✈️",
            ],
        ];

        $bodies = [
            'fr' => "J'accompagne des expatriés dans leurs démarches administratives et juridiques depuis plusieurs années.\n\nUn sujet revient souvent : {$title}.\n\nCe que j'ai appris sur le terrain :\n→ Les démarches varient énormément selon le pays et la situation personnelle.\n→ L'erreur la plus courante : sous-estimer les délais administratifs.\n→ Le bon interlocuteur au bon moment change tout.\n\nVotre plus grand défi d'expatrié à ce stade ? Partagez en commentaire 👇\n\n(Ressources complètes en 1er commentaire)",
            'en' => "I've been helping expats navigate administrative and legal challenges for years.\n\nOne topic keeps coming up: {$title}.\n\nWhat I've learned from experience:\n→ Processes vary dramatically by country and personal situation.\n→ The most common mistake: underestimating administrative timelines.\n→ The right contact at the right time changes everything.\n\nWhat's your biggest expat challenge right now? Drop it in the comments 👇\n\n(Full resources in first comment)",
        ];

        $l    = ($lang === 'en') ? 'en' : 'fr';
        $hook = $hooks[$l][$day] ?? $hooks[$l]['monday'];
        $body = $bodies[$l];

        $firstCommentText = $url
            ? ($lang === 'en' ? "Read the full article: {$url}" : "Lire l'article complet : {$url}")
            : ($lang === 'en' ? 'Find all our resources at SOS-Expat.com' : 'Retrouvez toutes nos ressources sur SOS-Expat.com');

        return [
            'hook'          => $hook,
            'body'          => $body,
            'hashtags'      => array_merge(['expatriation', 'expat', 'sosexpat'], $keywords),
            'first_comment' => $firstCommentText,
        ];
    }

    /** Send Telegram alert when falling back from AI */
    private function notifyFallback(LinkedInPost $post, string $reason): void
    {
        try {
            $telegram = app(\App\Services\Social\TelegramAlertService::class);
            if ($telegram->isConfigured()) {
                $telegram->sendMessage(
                    "⚠️ <b>LinkedIn post #{$post->id}</b> — fallback activé\n\n{$reason}\n\nPost programmé avec contenu template."
                );
            }
        } catch (\Throwable) {}
    }

    // ── Source resolution ──────────────────────────────────────────────

    /** Free-generation source types (no DB source needed) */
    private const FREE_TYPES = ['hot_take', 'myth', 'poll', 'serie', 'reactive', 'milestone',
                                'partner_story', 'counter_intuition', 'tip', 'news', 'case_study'];

    private function fetchSource(string $type, ?int $id, string $lang): array
    {
        $empty = [
            'title'         => 'SOS-Expat.com',
            'content'       => '',
            'keywords'      => 'expatriation, expat, visa, étranger, avocat',
            'hashtag_seeds' => ['expatriation', 'expat', 'sosexpat'],
            'url'           => '',
            'image_url'     => null,
        ];

        // Free types — no DB source
        if (in_array($type, self::FREE_TYPES, true)) {
            return $empty;
        }

        // Auto-select if no explicit ID
        if (!$id) {
            return match ($type) {
                'article' => $this->bestArticle($lang) ?? $empty,
                'faq'     => $this->bestFaq($lang) ?? $empty,
                'sondage' => $this->bestSondage($lang) ?? $empty,
                default   => $empty,
            };
        }

        return match ($type) {
            'article' => $this->fetchArticle($id) ?? $empty,
            'faq'     => $this->fetchFaq($id) ?? $empty,
            'sondage' => $this->fetchSondage($id) ?? $empty,
            default   => $empty,
        };
    }

    // ── Smart auto-selection (best score, dedup) ───────────────────────

    private function bestArticle(string $lang): ?array
    {
        $usedIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'article')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $article = GeneratedArticle::published()
            ->where('language', $lang)
            ->whereNotIn('id', $usedIds)
            ->orderByDesc('editorial_score')
            ->first();

        return $article ? $this->articleToSource($article) : null;
    }

    private function bestFaq(string $lang): ?array
    {
        $usedIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'faq')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $faq = QaEntry::published()
            ->where('language', $lang)
            ->whereNotIn('id', $usedIds)
            ->orderByDesc('seo_score')
            ->first();

        return $faq ? $this->faqToSource($faq) : null;
    }

    private function bestSondage(string $lang): ?array
    {
        $usedIds = LinkedInPost::whereIn('status', ['draft', 'scheduled', 'published', 'generating'])
            ->where('source_type', 'sondage')
            ->whereNotNull('source_id')
            ->pluck('source_id');

        $sondage = Sondage::whereIn('status', ['active', 'closed'])
            ->where('language', $lang)
            ->whereNotIn('id', $usedIds)
            ->latest()
            ->first();

        return $sondage ? $this->sondageToSource($sondage) : null;
    }

    private function fetchArticle(int $id): ?array
    {
        try {
            $a = GeneratedArticle::find($id);
            return $a ? $this->articleToSource($a) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchFaq(int $id): ?array
    {
        try {
            $f = QaEntry::find($id);
            return $f ? $this->faqToSource($f) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchSondage(int $id): ?array
    {
        try {
            $s = Sondage::with('questions')->find($id);
            return $s ? $this->sondageToSource($s) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Source → array converters ──────────────────────────────────────

    private function articleToSource(GeneratedArticle $a): array
    {
        $plain = strip_tags($a->content_html ?? '');
        $plain = trim(preg_replace('/\s+/', ' ', $plain));
        $plain = substr($plain, 0, 800);

        $primary   = $a->keywords_primary ?? '';
        $secondary = is_array($a->keywords_secondary)
            ? implode(', ', array_slice($a->keywords_secondary, 0, 5)) : '';
        $allKeys   = trim($primary . ($secondary ? ', ' . $secondary : ''));
        $seeds     = array_values(array_filter(array_map('trim', explode(',', $allKeys))));

        return [
            'title'         => $a->title ?? '',
            'content'       => $plain,
            'keywords'      => $allKeys,
            'hashtag_seeds' => array_slice($seeds, 0, 5),
            'url'           => $a->external_url ?? $a->canonical_url ?? '',
            'image_url'     => $a->featured_image_url ?? null,
        ];
    }

    private function faqToSource(QaEntry $f): array
    {
        $answer  = ($f->answer_short ?? '') . ' ' . strip_tags($f->answer_detailed_html ?? '');
        $answer  = trim(preg_replace('/\s+/', ' ', $answer));
        $answer  = substr($answer, 0, 800);

        $primary   = $f->keywords_primary ?? '';
        $secondary = is_array($f->keywords_secondary)
            ? implode(', ', array_slice($f->keywords_secondary, 0, 4)) : '';
        $allKeys   = trim($primary . ($secondary ? ', ' . $secondary : ''));
        $seeds     = array_values(array_filter(array_map('trim', explode(',', $allKeys))));

        return [
            'title'         => $f->question ?? '',
            'content'       => $answer,
            'keywords'      => $allKeys,
            'hashtag_seeds' => array_slice($seeds, 0, 4),
            'url'           => $f->external_url ?? $f->canonical_url ?? '',
            'image_url'     => null,
        ];
    }

    private function sondageToSource(Sondage $s): array
    {
        $questions = $s->questions ?? collect();
        $qText = $questions->map(function ($q) {
            $opts = is_array($q->options) ? ' → Options: ' . implode(' / ', array_slice($q->options, 0, 4)) : '';
            return $q->text . $opts;
        })->take(5)->implode("\n");

        $content = "Sondage : {$s->title}\nStatut : {$s->status}\n\nQuestions :\n{$qText}";

        return [
            'title'         => $s->title ?? 'Sondage SOS-Expat',
            'content'       => substr($content, 0, 800),
            'keywords'      => 'sondage, statistiques, expatriés, données, expat',
            'hashtag_seeds' => ['expatriation', 'sondage', 'expat', 'statistiques', 'vieinternational'],
            'url'           => '',
            'image_url'     => null,
        ];
    }

    // ── Unsplash query builder ─────────────────────────────────────────

    /**
     * Build a relevant Unsplash search query from source type + keywords.
     * Keeps the query generic enough to get good results (Unsplash isn't
     * specialized in expat topics; abstract/lifestyle photos work best).
     */
    private function buildUnsplashQuery(string $sourceType, string $keywords, string $lang): string
    {
        // Type-specific visual themes
        $typeThemes = [
            'article'           => 'expat travel city passport',
            'faq'               => 'professional meeting consulting office',
            'sondage'           => 'data chart survey people diverse',
            'hot_take'          => 'bold statement speaking microphone',
            'myth'              => 'magnifying glass truth discovery',
            'poll'              => 'voting choice decision crossroads',
            'serie'             => 'learning education book study',
            'reactive'          => 'breaking news newspaper alert',
            'milestone'         => 'celebration success achievement trophy',
            'partner_story'     => 'collaboration handshake partnership',
            'counter_intuition' => 'surprise twist unexpected arrow',
            'tip'               => 'lightbulb idea solution tips',
            'news'              => 'newspaper world news globe',
            'case_study'        => 'success story result growth chart',
        ];

        $theme = $typeThemes[$sourceType] ?? 'travel abroad international';

        // Extract up to 2 meaningful keywords from the source
        $kwList = array_filter(array_map('trim', explode(',', $keywords)));
        $kw1    = $kwList[0] ?? '';
        $kw2    = $kwList[1] ?? '';

        // Prefer theme + first keyword for relevance; fallback to theme alone
        $query = $kw1 ? "{$theme} {$kw1}" : $theme;

        return mb_substr($query, 0, 80); // Unsplash query max
    }

    // ── Day & angle instructions ───────────────────────────────────────

    private function getDayInstructions(string $day, string $sourceType, string $lang): string
    {
        $fr = [
            'monday'    => 'Format carrousel/liste : "Les X erreurs / conseils pour les expats". Commencer par un chiffre choc. Style pratique, liste numérotée dans le corps.',
            'tuesday'   => 'Story à la première personne ou d\'un type d\'expat (sans prénom inventé). Hook émotionnel fort. Structure : Situation → Problème → Insight rare → Leçon actionnable. Aucune mention de service ou plateforme — la valeur vient de l\'expérience racontée.',
            'wednesday' => 'Actu légale/visa OU opinion tranchée. Format "🚨 N changements" ou affirmation provocante. Concis, factuel, chiffres si possible.',
            'thursday'  => 'Q&A ou statistique choc. Commencer par une question ou un chiffre surprenant. Réponse structurée avec valeur max. Stats sondage si disponibles.',
            'friday'    => 'Témoignage / tip / story partenaire. Ton inspirant et positif. Finir sur fierté ou espoir. Ou story d\'un avocat/helper partenaire.',
        ];

        $en = [
            'monday'    => '"X mistakes / tips for expats" carousel format. Start with a shocking stat. Practical style, numbered list.',
            'Founder\'s own story or a relatable expat archetype (no invented first names). Strong emotional hook. Structure: Situation → Problem → Rare insight → Actionable lesson. No mention of any service or platform — value comes from the experience itself.',
            'wednesday' => 'Legal/visa news OR strong opinion. "🚨 N important changes" or provocative statement. Concise, factual, figures when possible.',
            'thursday'  => 'Q&A or shocking statistic. Start with a question or surprising figure. Structured answer with maximum value. Survey stats if available.',
            'friday'    => 'Testimonial / tip / partner story. Inspiring, positive tone. End on pride or hope. Or a lawyer/helper partner story.',
        ];

        $map = ($lang === 'en') ? $en : $fr;
        return $map[$day] ?? 'Post LinkedIn professionnel pour SOS-Expat.com.';
    }

    private function getAngleInstructions(string $sourceType, string $lang): string
    {
        $fr = [
            'article'           => 'Adapte l\'article en conseils pratiques actionnables pour des expatriés.',
            'faq'               => 'Transforme la FAQ en contenu engageant. La question de l\'expat doit être dans le hook.',
            'sondage'           => 'Utilise les données du sondage pour créer un post statistique choc. Les chiffres surprennent et créent le partage. Ex: "X% des expats ont..."',
            'hot_take'          => 'Opinion tranchée et controversée sur l\'expatriation. Commencer par une affirmation forte que 50% désaccord. Objectif : générer le débat.',
            'myth'              => 'Casser un mythe courant sur l\'expatriation. Format : "Non, [mythe]. La vérité : [réalité]". Utiliser des exemples concrets.',
            'poll'              => 'Créer un post avec sondage LinkedIn natif intégré. Formuler 4 options concises. La question doit être universelle pour les expats.',
            'serie'             => 'Post numéroté d\'une série éducative. Format "Expat tip #[N]" ou "Le guide de l\'expat #[N]". Crée l\'habitude de revenir.',
            'reactive'          => 'Réagir à une actualité ou tendance. Lier l\'événement à l\'expérience expat. Être parmi les premiers à commenter = visibilité × 5.',
            'milestone'         => 'Célébrer une étape personnelle du fondateur ou un chiffre marquant. Ex: "J\'ai aidé 1000 expatriés cette année." Humaniser avec des détails concrets. Ton humble et fier, jamais publicitaire.',
            'partner_story'     => 'Story d\'un type d\'avocat ou d\'expert expat (fictif ou inspiré de vrais cas). Montrer l\'impact humain d\'un conseil juridique au bon moment. Aucune mention de plateforme — la valeur vient de l\'histoire.',
            'counter_intuition' => 'Affirmation contre-intuitive sur l\'expatriation. Commence par l\'idée reçue, puis la surprise. Curiosité garantit le "voir plus".',
            'tip'               => 'Conseil pratique rapide et actionnable pour les expatriés. Concis, direct, immédiatement utile.',
            'news'              => 'Actualité récente liée à l\'expatriation ou aux changements légaux/visa. Factuel, chiffres, importance pour les expats.',
            'case_study'        => 'Cas client (fictif ou anonymisé) : problème rencontré → démarche effectuée → résultat chiffré. Format story avec données concrètes. JAMAIS de mention de plateforme dans le corps — la solution vient du SAVOIR, pas d\'un service.',
        ];

        $en = [
            'article'           => 'Adapt the article into practical, actionable tips for expats.',
            'faq'               => 'Transform the FAQ into engaging content. The expat question must be in the hook.',
            'sondage'           => 'Use survey data to create a shocking stat post. Figures surprise and drive sharing. Ex: "X% of expats have..."',
            'hot_take'          => 'Controversial opinion on expatriation. Start with a strong statement that 50% disagree with. Goal: generate debate.',
            'myth'              => 'Bust a common expat myth. Format: "No, [myth]. The truth: [reality]". Use concrete examples.',
            'poll'              => 'Create a post with integrated LinkedIn native poll. 4 concise options. The question must be universal for expats.',
            'serie'             => 'Numbered post from an educational series. "Expat tip #[N]" format. Builds habit of coming back.',
            'reactive'          => 'React to breaking news or trend. Link the event to the expat experience. Being among the first to comment = 5× visibility.',
            'milestone'         => 'Celebrate a personal founder milestone or a meaningful number. Ex: "I helped 1,000 expats this year." Humanize with concrete details. Humble and proud tone, never promotional.',
            'partner_story'     => 'Story of a lawyer or expat expert type (fictional or inspired by real cases). Show the human impact of expert legal advice at the right moment. No platform mention — the value comes from the story.',
            'counter_intuition' => 'Counter-intuitive statement about expat life. Start with the assumption, then the surprise. Curiosity ensures "see more".',
            'tip'               => 'Quick, practical, actionable tip for expats. Concise, direct, immediately useful.',
            'news'              => 'Recent news related to expat life or legal/visa changes. Factual, figures, relevance for expats.',
            'case_study'        => 'Client case (fictional or anonymized): problem encountered → steps taken → measurable result. Story format with concrete data. NEVER mention any platform in the body — the solution comes from KNOWLEDGE, not a service.',
        ];

        $map = ($lang === 'en') ? $en : $fr;
        return $map[$sourceType] ?? 'Post LinkedIn professionnel pour SOS-Expat.com.';
    }

    // ── Hashtag builder ────────────────────────────────────────────────

    private function buildHashtags(array $aiHashtags, array $seeds): array
    {
        // Base niche tags (no brand hashtag — avoid appearing promotional)
        $base = ['expatriation', 'expat'];

        // Merge AI tags + seeds + base
        $all = array_merge($aiHashtags, $seeds, $base);

        // Sanitize: strip #, lowercase, alphanumeric only
        $clean = array_unique(array_filter(array_map(function (string $h) {
            $h = strtolower(ltrim(trim($h), '#'));
            return preg_match('/^[\w]+$/', $h) ? $h : null;
        }, $all)));

        return array_values(array_slice($clean, 0, 5));
    }

    // ── Fallbacks ──────────────────────────────────────────────────────

    private function defaultFirstComment(string $sourceType, string $url, string $lang): string
    {
        $templates = [
            'fr' => [
                'article'           => "Vous avez déjà vécu une situation similaire en tant qu'expatrié ? Partagez votre expérience en commentaire 👇\n\n" . ($url ? "→ Guide complet : {$url}" : '→ Plus d\'infos sur SOS-Expat.com'),
                'faq'               => "Et vous ? Comment avez-vous géré cette situation à l'étranger ? 👇\n\n" . ($url ? "→ Réponse complète : {$url}" : '→ Toutes nos FAQs sur SOS-Expat.com'),
                'sondage'           => "Ces chiffres vous surprennent ? Qu'est-ce qui vous a le plus étonné dans ces données ? 👇\n\n→ Participez à notre sondage complet sur SOS-Expat.com",
                'hot_take'          => "Vous êtes d'accord ? En désaccord ? Je veux votre avis honnête 👇",
                'poll'              => "Votez ci-dessus et dites-moi en commentaire ce qui vous a le plus surpris dans votre expérience expat 👇",
                'milestone'         => "Merci à tous ceux qui font confiance à SOS-Expat.com ! Quelle a été votre expérience avec nous ? 🙏",
                'partner_story'     => "Vous êtes avocat ou expert expatrié ? Rejoignez notre réseau partenaire → SOS-Expat.com",
                'default'           => "Question pour vous : quel est votre plus grand défi en tant qu'expatrié ? 👇\n\n→ Ressources sur SOS-Expat.com",
            ],
            'en' => [
                'article'           => "Have you faced a similar situation as an expat? Share your experience in the comments 👇\n\n" . ($url ? "→ Full guide: {$url}" : '→ More resources at SOS-Expat.com'),
                'faq'               => "How did you handle this situation abroad? I'd love to hear your experience 👇\n\n" . ($url ? "→ Full answer: {$url}" : '→ All our FAQs at SOS-Expat.com'),
                'sondage'           => "Surprised by these numbers? What surprised you the most? 👇\n\n→ Take our full survey at SOS-Expat.com",
                'hot_take'          => "Agree or disagree? I want your honest take 👇",
                'poll'              => "Vote above and tell me in the comments what surprised you most about your expat experience 👇",
                'milestone'         => "Thank you to everyone who trusts SOS-Expat.com! What has your experience been? 🙏",
                'partner_story'     => "Are you a lawyer or expat expert? Join our partner network → SOS-Expat.com",
                'default'           => "Question for you: what's your biggest challenge as an expat? 👇\n\n→ Resources at SOS-Expat.com",
            ],
        ];

        $langTemplates = $templates[$lang === 'en' ? 'en' : 'fr'];
        return $langTemplates[$sourceType] ?? $langTemplates['default'];
    }

    private function defaultHook(string $day, string $lang): string
    {
        $hooks = [
            'fr' => [
                'monday'    => "5 erreurs que font 90% des expatriés à leur arrivée (et comment les éviter) 👇",
                'tuesday'   => "Elle voulait tout quitter pour s'installer au Vietnam. Voici ce que personne ne lui a dit.",
                'wednesday' => "🚨 Ce que la plupart des expats ne savent pas sur leurs droits à l'étranger",
                'thursday'  => "La question la plus posée cette semaine : comment ouvrir un compte bancaire à l'étranger ?",
                'friday'    => "Il y a 2 ans, il avait peur de tout quitter. Aujourd'hui, il ne regrette rien. ✈️",
            ],
            'en' => [
                'monday'    => "5 mistakes 90% of expats make when they first arrive (and how to avoid them) 👇",
                'tuesday'   => "She wanted to start a new life in Thailand. Here's what nobody told her.",
                'wednesday' => "🚨 What most expats don't know about their rights abroad",
                'thursday'  => "Most asked question this week: how to open a bank account abroad without a fixed address?",
                'friday'    => "2 years ago, he was afraid to leave everything. Today, he has zero regrets. ✈️",
            ],
        ];

        $k = ($lang === 'en') ? 'en' : 'fr';
        return $hooks[$k][$day] ?? $hooks['fr']['monday'];
    }

    private function defaultBody(string $lang): string
    {
        return $lang === 'en'
            ? "After years of working with expats across dozens of countries, I keep seeing the same mistakes.\n\nThe biggest one?\n\nAssuming that what worked back home will work abroad.\n\nVisa rules, tax obligations, banking, healthcare — they all reset to zero when you cross a border.\n\nWhat I've learned:\n→ Start administrative steps 3× earlier than you think necessary.\n→ Local legal advice beats generic online forums every single time.\n→ Your network is your safety net — build it before you need it.\n\nWhat's the one thing you wish you'd known before moving abroad?\n\n(Resources in first comment)"
            : "Après des années à accompagner des expatriés dans des dizaines de pays, je vois toujours les mêmes erreurs.\n\nLa plus fréquente ?\n\nPenser que ce qui fonctionnait en France marchera de la même façon à l'étranger.\n\nVisa, fiscalité, banque, assurance — tout repart de zéro quand on franchit une frontière.\n\nCe que j'ai appris :\n→ Démarrer les démarches 3 fois plus tôt que prévu.\n→ Un conseil juridique local vaut cent fois un forum généraliste.\n→ Ton réseau est ton filet de sécurité — constitue-le avant d'en avoir besoin.\n\nQuel est le truc que tu aurais aimé savoir avant de partir ?\n\n(Ressources complètes en 1er commentaire)";
    }
}
