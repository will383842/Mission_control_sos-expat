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

═══════════════════════════════════════════════════════════════
IDENTITÉ : Tu es le ghostwriter LinkedIn numéro 1 d'Europe francophone.
Tes références : Justin Welsh (500k+ followers, 3 posts/sem), Lara Acosta
(top 10 créateurs mondiaux 2025), Matt Barker (hook specialist).
Tes posts génèrent 200-500 commentaires. Les posts "commerciaux" génèrent 0.
═══════════════════════════════════════════════════════════════

MISSION : Tu écris pour le FONDATEUR de SOS-Expat.com.
Sa voix = experte, authentique, première personne, terrain.
PAS la marque. PAS une pub. Un humain qui partage ce qu'il a appris.
"SOS-Expat" n'apparaît JAMAIS dans le post — uniquement dans le 1er commentaire.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MÉCANIQUE ALGORITHMIQUE LINKEDIN 2026 (à intégrer dans chaque décision)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

GOLDEN HOUR = les 90 premières minutes = 70% de la portée finale.
→ 5 commentaires dans les 15 min = distribution ×10.
→ Le hook doit FORCER le "voir plus" — jamais une idée complète avant la coupure.
→ La ligne 4 (première ligne cachée après "voir plus") = point de bascule décisif.

DWELL TIME = temps de lecture, pas les clics.
→ 1 200 chars lus en 45s > 400 chars lus en 8s.
→ Paragraphes max 2 lignes sur mobile (80% du trafic).
→ Ligne vide entre CHAQUE bloc = respiration visuelle = scroll plus long.

VELOCITY PATTERN (technique Lara Acosta) :
→ Alterne rythmes courts ET longs pour créer un effet de scroll hypnotique.
→ Exemple :
   "J'ai failli perdre mon visa. [courte]
   C'était en 2021, au Maroc, à cause d'une erreur administrative de 3 jours. [longue]
   3 jours. [ultra-courte — choc]
   Voici ce que j'aurais dû faire dès le départ." [révélation]

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
HOOK — MAX 140 CARACTÈRES ABSOLUS
(les 3 premières lignes visibles avant "voir plus")
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

SCIENCE DU HOOK 2026 : L'affirmation spécifique bat la question 3×.
Le chiffre précis bat le chiffre arrondi 2×. La confession bat le conseil 4×.

PATTERNS PROUVÉS (choisis UN seul, adapte au contenu) :
→ CONFESSION+CHIFFRE : "En 7 ans, j'ai vu 300+ expatriés faire la même erreur coûteuse."
→ PARADOXE PRÉCIS    : "Le document le plus important pour s'expatrier n'est pas le visa."
→ CHIFFRE CHOC       : "73% des expatriés ratent leur intégration bancaire au pays d'arrivée."
→ TENSION BRUTE      : "J'ai reçu un email ce matin. Mon client allait perdre son titre de séjour."
→ PRÉDICTION         : "Dans 18 mois, les règles fiscales pour les nomades vont changer radicalement."
→ CONTRE-SENS        : "Tout le monde vous dit de préparer votre visa en premier. C'est faux."

INTERDITS HOOK (chacun coupe le reach de 50%) :
✗ Commencer par "Quand", "Si vous", "Lorsque", "En tant que", "Dans cet article"
✗ Commencer par une question — l'affirmation forte performe 3× mieux
✗ Prénoms inventés : "Quand Pierre a voulu partir au Japon..."
✗ Idée complète dans le hook — coupe le "voir plus", donc coupe le reach
✗ Plus de 140 caractères — LinkedIn tronque, contexte perdu

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CORPS — 900-1400 CARACTÈRES
(structure narrative en 4 actes, mobile-first)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

LA LIGNE 4 (première après "voir plus") = TA MEILLEURE LIGNE.
C'est elle qui décide si le lecteur lit la suite. Mets-y ton fait le plus surprenant.

ACTE 1 — ANCRAGE (2 lignes max) :
Contexte précis : pays + situation + moment. Pas de généralité.
✓ "En Thaïlande, en 2023, j'ai rencontré un ingénieur français bloqué depuis 40 jours."
✗ "L'expatriation peut être difficile parfois."

ACTE 2 — LA DOULEUR RÉELLE (3-4 lignes) :
Coût concret : temps + argent + stress + famille. Chiffres quand possible.
Une seule idée par paragraphe. Max 2 lignes par bloc.

ACTE 3 — L'INSIGHT RARE (3-5 lignes) :
Ce que 95% des gens ne savent pas. Contre-intuitif. Terrain.
Peut utiliser "→" pour 3-4 points max (jamais 5+, listicle = portée ÷5).

ACTE 4 — RÉSOLUTION + CTA (2-3 lignes) :
1 principe que le lecteur peut appliquer AUJOURD'HUI.
UNE question ultra-spécifique (jamais générique) :
✓ "Dans quel pays avez-vous eu le plus de mal avec l'ouverture de compte ?"
✓ "Quelle démarche vous a pris le plus longtemps ? Je compile les réponses."
✗ "Et vous, qu'en pensez-vous ?" (trop vague, 0 engagement)
✗ "Partagez si vous êtes d'accord !" (cliché 2018, pénalisé par l'algo)

RÈGLES MOBILE-FIRST ABSOLUES :
→ Maximum 2 lignes par paragraphe (sur mobile, 3 lignes = un mur de texte)
→ Ligne vide entre CHAQUE paragraphe — OBLIGATOIRE
→ Émojis : max 2 dans tout le post, uniquement en début de ligne, jamais en milieu de phrase
→ "→" (flèche) libre d'utilisation pour les listes courtes
→ Jamais de liste > 4 items (listicle = signal spam pour l'algo)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
INTERDITS ABSOLUS (violation = portée détruite)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✗ "SOS-Expat", "notre service", "notre plateforme", "découvrez", "solution"
✗ Histoires à la 3ème personne avec prénom inventé
✗ CTA génériques : "partagez", "n'hésitez pas", "likez", "réagissez"
✗ "Résultat ?" seul sur une ligne — cliché mortel
✗ "Le secret de..." ou "Voici comment..." en hook
✗ Markdown : **, ##, *, _ (LinkedIn affiche tout en texte brut)
✗ URL dans le post (UNIQUEMENT dans first_comment)
✗ "En conclusion", "Pour résumer", "J'espère que..." — ton scolaire
✗ Rimes ou allitérations forcées — sonnent faux, signal IA

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
HASHTAGS — 3 À 5, ULTRA-NICHE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
NON : #expat #expatriation #travel (trop larges, noient dans le bruit)
OUI : #visa #droitdutravail #fiscaliteinternationale #mobiliteinternationale
Dérive des keywords_primary de la source. Jamais de hashtag de marque.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1ER COMMENTAIRE — STRATÉGIE 3 MIN APRÈS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Le 1er commentaire posté par toi-même dans les 3 min = boost algorithmique +15%.
Structure OBLIGATOIRE :
1. Valeur ajoutée (ce que le post n'a pas dit : chiffre, anecdote, nuance)
2. Lien discret vers la ressource (si disponible)
3. Question de rebond pour relancer (différente de celle du post)
Longueur : 150-250 caractères. Ton naturel, pas copié-collé du post.
SYSTEM;

            // Country context line (only when the source has a known country)
            $countryLine = !empty($source['country'])
                ? "Pays de contexte (à mentionner naturellement dans l'acte 1, jamais comme sujet principal) : " . $source['country']
                : '';

            // URL line for first comment — differs by source type
            $isSondage = $post->source_type === 'sondage';
            $isFreeType = in_array($post->source_type, self::FREE_TYPES, true);
            if (!empty($source['url'])) {
                if ($isSondage) {
                    $urlLine = "URL des résultats complets du sondage (OBLIGATOIRE dans first_comment) : {$source['url']}";
                } elseif ($isFreeType) {
                    $urlLine = "URL d'un article lié en ressource complémentaire (mettre dans first_comment, PAS dans le corps) : {$source['url']}";
                } else {
                    $urlLine = "URL de l'article source (OBLIGATOIRE dans first_comment) : {$source['url']}";
                }
            } else {
                $urlLine = "Pas d'URL source — mets uniquement → SOS-Expat.com dans first_comment";
            }

            $userPrompt = <<<USER
Génère un post LinkedIn en {$langLabel} — voix de fondateur expert, ZÉRO commercial.

JOUR/FORMAT : {$dayType} — {$dayInstructions}
ANGLE ÉDITORIAL : {$angleInstructions}

SOURCE (inspire-toi, ne recopie pas) :
Titre : {$source['title']}
Contenu : {$source['content']}
Mots-clés : {$source['keywords']}
{$countryLine}
{$urlLine}

RAPPEL CRITIQUE :
- Hook ≤ 140 chars, première personne "Je", tension immédiate
- SOS-Expat n'apparaît JAMAIS dans hook ni body — uniquement dans first_comment
- Corps 1000-1500 chars, 4 actes, paragraphes courts
- CTA = question précise sur une situation réelle vécue
- Max 2 émojis dans tout le post
- first_comment DOIT contenir l'URL fournie (jamais dans le corps du post)
- Pour le sondage : utilise les pourcentages RÉELS fournis dans le contenu source — pas de chiffres inventés

Retourne UNIQUEMENT un JSON valide :
{
  "hook": "accroche ≤140 chars, sans saut de ligne, en {$langLabel}",
  "body": "corps 1000-1500 chars, \\n entre paragraphes, en {$langLabel}",
  "hashtags": ["mot1", "mot2", "mot3"],
  "first_comment": "question rebond + URL fournie obligatoire + lien SOS-Expat.com, 150-300 chars, en {$langLabel}"
}
USER;

            // ── 3. Alert if source is empty (DB type with no articles left) ─
            if (empty($source['content']) && in_array($post->source_type, ['article', 'faq', 'sondage'], true)) {
                $this->notifyFallback(
                    $post,
                    "⚠️ <b>Source épuisée</b> — plus d'<code>{$post->source_type}</code> disponible en {$lang}.\n"
                    . "Post #{$post->id} généré en libre (hot_take) à la place.\n"
                    . "→ Publiez plus d'articles/FAQs pour alimenter le pipeline."
                );
                // Override to free generation so the post still has value
                $post->source_type = 'hot_take';
            }

            // ── 4. Generate with QA loop (max 3 attempts) ───────────
            $data = $this->generateWithQualityLoop($openai, $systemPrompt, $userPrompt, $post, $source, $lang, $dayType);

            // If QA loop placed post in 'draft' (score too low), abort here — no further update
            if ($post->fresh()->status === 'draft') return;

            // ── 4. Hashtags: sanitize + fallback from keywords ───────
            $hashtags = $this->buildHashtags($data['hashtags'] ?? [], $source['hashtag_seeds']);

            // ── 5. First comment — ensure source URL is always present ──
            $firstComment = $data['first_comment'] ?? $this->defaultFirstComment($post->source_type, $source['url'], $lang);

            // Post-processing: if source has a valid URL but AI forgot to include it, append it
            if (!empty($source['url'])
                && filter_var($source['url'], FILTER_VALIDATE_URL)
                && !str_contains($firstComment, $source['url'])
            ) {
                if ($post->source_type === 'sondage') {
                    $arrow = ($lang === 'en') ? '→ Full survey results: ' : '→ Résultats complets : ';
                } elseif (in_array($post->source_type, self::FREE_TYPES, true)) {
                    $arrow = ($lang === 'en') ? '→ Related article: ' : '→ Article lié : ';
                } else {
                    $arrow = ($lang === 'en') ? '→ Full article: ' : '→ Article complet : ';
                }
                $firstComment = rtrim($firstComment) . "\n\n" . $arrow . $source['url'];
            }

            // ── 6. Image: article image OR Unsplash search ──────────
            $featuredImage      = $source['image_url'] ?? null;
            $imageAttribution   = null;

            if (!$featuredImage && $unsplash->isConfigured()) {
                $imgQuery  = $this->buildUnsplashQuery($post->source_type, $source['keywords'], $lang, $source['country'] ?? '', $post->id);
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

            // Append Unsplash attribution to first comment (API requirement — only if not already present)
            if ($imageAttribution && !str_contains($firstComment, 'Unsplash')) {
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
                $data = json_decode($r['content'] ?? '', true);
                if (is_array($data)
                    && isset($data['hook'], $data['body'], $data['hashtags'])
                    && is_string($data['hook']) && mb_strlen(trim($data['hook'])) > 0
                    && is_string($data['body']) && mb_strlen(trim($data['body'])) > 50
                ) {
                    Log::info('GenerateLinkedInPostJob: generated via GPT-4o', ['post_id' => $post->id]);
                    return $data;
                }
                Log::warning('GenerateLinkedInPostJob: GPT-4o returned invalid JSON structure', [
                    'post_id' => $post->id,
                    'raw'     => mb_substr($r['content'] ?? '', 0, 200),
                ]);
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
                    $data2 = json_decode($r2['content'] ?? '', true);
                    if (is_array($data2)
                        && isset($data2['hook'], $data2['body'])
                        && is_string($data2['hook']) && mb_strlen(trim($data2['hook'])) > 0
                        && is_string($data2['body']) && mb_strlen(trim($data2['body'])) > 50
                    ) {
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
    ): ?array {
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
            $langLabel     = $lang === 'en' ? 'English' : 'français';
            $currentPrompt = $userPrompt
                . "\n\nCRITIQUE DU POST PRÉCÉDENT (score: {$score}/100) :\n"
                . $critique
                . "\n\nRégénère le post en {$langLabel} en corrigeant UNIQUEMENT ces points.\n"
                . "Garde le même sujet et la même structure JSON.";
        }

        if ($bestScore < $threshold) {
            Log::warning('GenerateLinkedInPostJob: QA below threshold after retries', [
                'post_id'    => $post->id,
                'best_score' => $bestScore,
            ]);

            // Absolute minimum: if score < 50, put in draft for manual review
            if ($bestScore < 50) {
                $post->update([
                    'status'        => 'draft',
                    'error_message' => "QA score {$bestScore}/100 après 3 tentatives — révision manuelle requise.",
                ]);
                $this->notifyFallback(
                    $post,
                    "🔴 <b>LinkedIn post #{$post->id} en DRAFT</b>\n\nScore QA trop bas : {$bestScore}/100 après 3 tentatives.\n"
                    . "→ Vérifier et corriger manuellement dans Mission Control > LinkedIn > File d'attente"
                );
                return null; // signal to handle() to abort remaining updates
            }
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

        // ── 1. Hook ≤ 140 chars (20 pts) ──────────────────────────────────
        $hookLen = mb_strlen($hook);
        if ($hookLen > 0 && $hookLen <= 140) $score += 20;
        elseif ($hookLen <= 155)             $score += 10;

        // ── 2. No brand / URL in body (20 pts) ────────────────────────────
        $hasBrand = preg_match('/sos.?expat|notre (service|plateforme|solution)|découvrez notre/i', $body);
        $hasUrl   = preg_match('#https?://|www\.#i', $body);
        if (!$hasBrand && !$hasUrl) $score += 20;
        elseif (!$hasUrl)           $score += 8;

        // ── 3. Body 900-1600 chars total (15 pts) ─────────────────────────
        $totalLen = mb_strlen($fullBody);
        if ($totalLen >= 900 && $totalLen <= 1600)  $score += 15;
        elseif ($totalLen >= 700 && $totalLen <= 1900) $score += 8;

        // ── 4. First-person voice (15 pts) ────────────────────────────────
        if (preg_match('/\bje\b|\bj\'|\bI /i', $hook . ' ' . $body)) $score += 15;
        elseif (preg_match('/\bnous\b|\bnotre\b/i', $hook . ' ' . $body)) $score += 5;

        // ── 5. Hashtag count 3-5 (10 pts) ─────────────────────────────────
        $hashCount = count(is_array($hashtags) ? $hashtags : []);
        if ($hashCount >= 3 && $hashCount <= 5) $score += 10;
        elseif ($hashCount >= 2 && $hashCount <= 6) $score += 5;

        // ── 6. First comment substantive ≥ 100 chars (8 pts) ─────────────
        if (mb_strlen($firstComment) >= 100) $score += 8;
        elseif (mb_strlen($firstComment) >= 50) $score += 4;

        // ── 7. No commercial clichés (5 pts) ──────────────────────────────
        $clichés = preg_match('/résultat \?|le secret de|partagez votre expérience|n\'hésitez pas|likez si/i', $body);
        if (!$clichés) $score += 5;

        // ── 8. Hook does NOT start with weak openers (-10 penalty) ────────
        $weakOpeners = '/^(Quand |Si vous |Lorsque |En tant que |Dans cet article|J\'aimerais|Je pense que|Aujourd\'hui,? je|Comment |Pourquoi )/iu';
        if (preg_match($weakOpeners, $hook)) $score -= 10;

        // ── 9. No paragraph > 3 lines in body (5 pts) ─────────────────────
        // Detect blocks with > 3 consecutive non-empty lines (wall of text)
        $blocks = preg_split('/\n{2,}/', $body);
        $hasLongBlock = false;
        foreach (($blocks ?: []) as $block) {
            if (substr_count($block, "\n") > 2) { $hasLongBlock = true; break; }
        }
        if (!$hasLongBlock) $score += 5;

        // ── 10. No listicle > 4 items (2 pts) ──────────────────────────────
        $listItemCount = preg_match_all('/^[→✓✗•\-]\s/m', $body);
        if ($listItemCount <= 4) $score += 2;

        return max(0, min(100, $score));
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

        // Weak hook opener
        $weakOpeners = '/^(Quand |Si vous |Lorsque |En tant que |Dans cet article|J\'aimerais|Je pense que|Aujourd\'hui,? je|Comment |Pourquoi )/iu';
        if (preg_match($weakOpeners, $hook))
            $issues[] = "OPENER FAIBLE. Le hook commence par un mot interdit (" . mb_substr($hook, 0, 30) . "...). Commence par un chiffre, une confession, ou une affirmation choc. L'affirmation directe performe 3× mieux.";

        // Long paragraphs (wall of text)
        $blocks = preg_split('/\n{2,}/', $body);
        foreach (($blocks ?: []) as $block) {
            if (substr_count($block, "\n") > 2) {
                $issues[] = "MUR DE TEXTE DÉTECTÉ. Un bloc dépasse 3 lignes. Sur mobile (80% du trafic), c'est illisible. Coupe chaque bloc à 2 lignes max et ajoute une ligne vide entre chaque.";
                break;
            }
        }

        // Listicle > 4 items
        $listItemCount = preg_match_all('/^[→✓✗•\-]\s/m', $body);
        if ($listItemCount > 4)
            $issues[] = "LISTE TROP LONGUE ({$listItemCount} items). LinkedIn pénalise les listicles > 4 items. Garde 3-4 points forts, supprime le reste.";

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
            'hashtags'      => array_merge(['expatriation', 'expat'], $keywords),
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
            'hashtag_seeds' => ['expatriation', 'expat'],
            'url'           => '',
            'image_url'     => null,
            'country'       => '',
        ];

        // Free types — no DB source, but always attach a related article URL as supporting link
        if (in_array($type, self::FREE_TYPES, true)) {
            $related = $this->relatedArticleForFreeType($type, $lang);
            return array_merge($empty, $related ? [
                'url'           => $related['url'],
                'keywords'      => $related['keywords'] ?: $empty['keywords'],
                'hashtag_seeds' => $related['hashtag_seeds'] ?: $empty['hashtag_seeds'],
                'image_url'     => $related['image_url'],
            ] : []);
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
            'country'       => $a->country ?? '',
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
            'country'       => $f->country ?? '',
        ];
    }

    private function sondageToSource(Sondage $s): array
    {
        // ── Try to pull real stats + slug from blog DB ────────────────────
        $blogStats = $this->fetchBlogSondageStats($s->external_id, 'fr');

        // Build question+stats text from blog data (if available), else fall back to MC options
        if (!empty($blogStats['questions'])) {
            $statsLines = [];
            foreach ($blogStats['questions'] as $qText => $results) {
                $statsLines[] = "Q : {$qText}";
                foreach (array_slice($results, 0, 3) as $opt => $pct) {
                    $statsLines[] = "  → {$opt} : {$pct}%";
                }
            }
            $statsBlock = implode("\n", $statsLines);
            $totalLabel = number_format($blogStats['total_responses'], 0, ',', ' ');
            $content    = "Sondage : {$blogStats['title']}\n"
                        . "Répondants réels : {$totalLabel}\n\n"
                        . "Résultats (données réelles SOS-Expat) :\n{$statsBlock}";
            $url        = $blogStats['url'];
        } else {
            // Fallback: MC questions without real stats
            $questions = $s->questions ?? collect();
            $qText     = $questions->map(function ($q) {
                $opts = is_array($q->options) ? ' → Options: ' . implode(' / ', array_slice($q->options, 0, 4)) : '';
                return $q->text . $opts;
            })->take(5)->implode("\n");
            $content = "Sondage : {$s->title}\n\nQuestions :\n{$qText}";
            $url     = '';
        }

        return [
            'title'         => $blogStats['title'] ?? $s->title ?? 'Sondage SOS-Expat',
            'content'       => substr($content, 0, 1200),
            'keywords'      => 'sondage, statistiques, expatriés, données, expat',
            'hashtag_seeds' => ['expatriation', 'sondage', 'expat', 'statistiques', 'vieinternational'],
            'url'           => $url,
            'image_url'     => null,
        ];
    }

    /**
     * Fetch real sondage stats from the blog PostgreSQL database.
     * Uses the 'blog_pgsql' secondary connection (blog-postgres on shared-network).
     * Cached 6 hours — stats don't change by the minute.
     *
     * Returns: ['title', 'slug', 'url', 'total_responses', 'questions' => [q_text => [opt => pct]]]
     */
    private function fetchBlogSondageStats(string $externalId, string $lang): array
    {
        $cacheKey = "sondage_blog_stats_{$externalId}_{$lang}";
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 21_600, function () use ($externalId, $lang) {
            try {
                $db = \Illuminate\Support\Facades\DB::connection('blog_pgsql');

                // Get sondage ID + slug + responses_count via external_id
                $row = $db->table('sondages as s')
                    ->join('sondage_translations as st', 'st.sondage_id', '=', 's.id')
                    ->where('s.external_id', $externalId)
                    ->where('st.language_code', $lang)
                    ->whereNull('s.deleted_at')
                    ->select(['s.id', 'st.title', 'st.slug', 's.responses_count'])
                    ->first();

                if (!$row) return [];

                $sondageId      = $row->id;
                $slug           = $row->slug;
                $totalResponses = (int) $row->responses_count;

                // Build blog URL: /fr/sondages/{slug}/resultats
                $blogUrl = "https://sos-expat.com/{$lang}/sondages/{$slug}/resultats";

                // Fetch questions (skip open/scale types — no useful percentages)
                $questions = $db->table('sondage_questions')
                    ->where('sondage_id', $sondageId)
                    ->where('language_code', $lang)
                    ->whereIn('type', ['single', 'multiple'])
                    ->orderBy('sort_order')
                    ->take(6)
                    ->get(['id', 'text', 'type']);

                $questionStats = [];
                foreach ($questions as $q) {
                    // Skip country/language questions (too many values, no insight)
                    if (str_contains(strtolower($q->text), 'nationalit') ||
                        str_contains(strtolower($q->text), 'pays vivez') ||
                        str_contains(strtolower($q->text), 'langue maternelle')) {
                        continue;
                    }

                    // Aggregate top answers
                    $rows = $db->select("
                        SELECT elem->>'value' AS v, COUNT(*) AS cnt
                        FROM sondage_responses sr,
                             jsonb_array_elements(sr.answers::jsonb) AS elem
                        WHERE sr.sondage_id = ? AND sr.completed = true
                          AND (elem->>'question_id')::int = ?
                        GROUP BY v
                        ORDER BY cnt DESC
                        LIMIT 6
                    ", [$sondageId, $q->id]);

                    if (empty($rows)) continue;

                    // For multiple-choice, the value is often a JSON array like ["opt1","opt2"]
                    // Flatten: count individual option mentions
                    $optCounts = [];
                    $totalVotes = 0;
                    foreach ($rows as $r) {
                        $val = $r->v;
                        // Decode JSON array values (multiple-choice combinations)
                        $decoded = json_decode($val, true);
                        $opts = is_array($decoded) ? $decoded : [$val];
                        foreach ($opts as $opt) {
                            $opt = preg_replace('/^[^\w\s]+\s*/', '', trim($opt)); // strip leading emoji
                            if (!$opt) continue;
                            $optCounts[$opt] = ($optCounts[$opt] ?? 0) + (int) $r->cnt;
                            $totalVotes += (int) $r->cnt;
                        }
                    }

                    if ($totalVotes === 0) continue;

                    // Sort by count DESC, take top 4, compute percentages
                    arsort($optCounts);
                    $top = array_slice($optCounts, 0, 4, true);
                    $percents = [];
                    foreach ($top as $opt => $cnt) {
                        $percents[$opt] = round($cnt * 100 / $totalVotes, 1);
                    }

                    $questionStats[$q->text] = $percents;
                }

                return [
                    'title'            => $row->title,
                    'slug'             => $slug,
                    'url'              => $blogUrl,
                    'total_responses'  => $totalResponses,
                    'questions'        => $questionStats,
                ];

            } catch (\Throwable $e) {
                Log::warning('GenerateLinkedInPostJob: fetchBlogSondageStats failed', [
                    'external_id' => $externalId,
                    'error'       => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    /**
     * For free-type posts, find the best-scored published article to use as a supporting link.
     * This ensures every post — even opinion/tip/myth ones — links to real blog content.
     * Uses a rotating selection (not the most recent, to vary links across posts).
     */
    private function relatedArticleForFreeType(string $type, string $lang): ?array
    {
        // Pick from top-10 articles by editorial_score, rotating by type hash for variety
        $top = GeneratedArticle::published()
            ->where('language', $lang)
            ->orderByDesc('editorial_score')
            ->take(10)
            ->get();

        if ($top->isEmpty()) return null;

        // Deterministic but varied: rotate by source type name hash
        $pick = $top->values()[(abs(crc32($type)) % $top->count())];

        return $this->articleToSource($pick);
    }

    // ── Unsplash query builder ─────────────────────────────────────────

    /**
     * Build a relevant Unsplash search query from source type + keywords.
     * Keeps the query generic enough to get good results (Unsplash isn't
     * specialized in expat topics; abstract/lifestyle photos work best).
     */
    private function buildUnsplashQuery(string $sourceType, string $keywords, string $lang, string $country = '', int $postId = 0): string
    {
        // Type-specific visual themes — varied pool to reduce repetition
        $typeThemes = [
            'article'           => ['expat abroad city life', 'travel documents passport visa', 'international move boxes', 'city skyline abroad urban', 'airport departure international'],
            'faq'               => ['professional advice consultation', 'desk laptop working abroad', 'office meeting business', 'person thinking decision', 'document paperwork administration'],
            'sondage'           => ['data analytics people diverse', 'survey statistics chart', 'world map global data', 'people voting choice', 'research analysis numbers'],
            'hot_take'          => ['confident speaker podium', 'bold statement contrast', 'discussion debate diverse', 'microphone speaking crowd', 'strong opinion newspaper'],
            'myth'              => ['magnifying glass discovery truth', 'question mark confusion', 'myth versus reality split', 'detective investigation clue', 'reveal surprise unveil'],
            'poll'              => ['voting choice crossroads', 'hands raised decision', 'ballot choice select', 'diverse group opinion', 'decision fork road'],
            'serie'             => ['open book learning study', 'education growth steps', 'library knowledge books', 'notebook pen writing tips', 'learning path journey'],
            'reactive'          => ['newspaper breaking news', 'phone alert notification', 'news headline world', 'urgent update flash', 'current events media'],
            'milestone'         => ['celebration achievement success', 'trophy award milestone', 'team clapping success', 'anniversary number milestone', 'goal completed checkmark'],
            'partner_story'     => ['handshake partnership collaboration', 'two people meeting coffee', 'lawyer professional meeting', 'business people agreement', 'team working together'],
            'counter_intuition' => ['surprise twist unexpected', 'arrow opposite direction', 'upside down reverse', 'paradox contrast unexpected', 'mind blown realization'],
            'tip'               => ['lightbulb idea creative', 'checklist practical tips', 'sticky note reminder advice', 'notebook writing ideas', 'simple solution clear'],
            'news'              => ['globe world map international', 'newspaper legislation law', 'parliament government official', 'legal gavel law book', 'world news today'],
            'case_study'        => ['growth chart result success', 'before after transformation', 'client meeting solution', 'problem solved whiteboard', 'success story data result'],
        ];

        $themePool = $typeThemes[$sourceType] ?? ['travel international abroad city'];

        // Use postId + keywords for deterministic but unique selection across posts
        // Same post always gets same theme; different posts with same keywords get different themes
        $hash  = abs(crc32($postId . '_' . $keywords . '_' . $country));
        $theme = $themePool[$hash % count($themePool)];

        // Extract 1 meaningful keyword from the source (first non-trivial word)
        $kwList = array_values(array_filter(array_map('trim', explode(',', $keywords))));
        $kw1    = !empty($kwList[0]) ? $kwList[0] : '';

        // Include country as a specific geographic anchor when available
        $parts = array_filter([$theme, $kw1, $country]);
        $query = implode(' ', array_slice(array_values($parts), 0, 3));

        return mb_substr($query, 0, 80);
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
            'saturday'  => 'Post weekend : ton plus personnel et inspirant. Corps plus court (700-900 chars). Bilan de semaine ou tip actionnable immédiatement. Finir sur une question lifestyle ou émotionnelle ("Qu\'est-ce qui vous a le plus surpris dans votre vie d\'expatrié cette semaine ?"). Pas d\'actu légale un samedi.',
        ];

        $en = [
            'monday'    => '"X mistakes / tips for expats" carousel format. Start with a shocking stat. Practical style, numbered list.',
            'tuesday'   => 'Founder\'s own story or a relatable expat archetype (no invented first names). Strong emotional hook. Structure: Situation → Problem → Rare insight → Actionable lesson. No mention of any service or platform — value comes from the experience itself.',
            'wednesday' => 'Legal/visa news OR strong opinion. "🚨 N important changes" or provocative statement. Concise, factual, figures when possible.',
            'thursday'  => 'Q&A or shocking statistic. Start with a question or surprising figure. Structured answer with maximum value. Survey stats if available.',
            'friday'    => 'Testimonial / tip / partner story. Inspiring, positive tone. End on pride or hope. Or a lawyer/helper partner story.',
            'saturday'  => 'Weekend post: more personal and inspiring tone. Shorter body (700-900 chars). Weekly reflection or immediately actionable tip. End on a lifestyle or emotional question. No legal updates on a Saturday.',
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

        // Sanitize: strip #, lowercase, ASCII alphanumeric only (no accents — LinkedIn hashtag rules)
        $clean = array_unique(array_filter(array_map(function (string $h) {
            $h = strtolower(ltrim(trim($h), '#'));
            // Strip accents → transliterate to ASCII
            $h = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $h) ?: $h;
            // Keep only alphanumeric (no spaces, dashes, underscores in hashtags)
            $h = preg_replace('/[^a-z0-9]/', '', $h);
            return ($h && strlen($h) >= 2) ? $h : null;
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
                'sondage'           => "Ces chiffres vous surprennent ? Qu'est-ce qui vous a le plus étonné dans ces données ? 👇\n\n" . ($url ? "→ Résultats complets : {$url}" : "→ Sondage complet sur SOS-Expat.com"),
                'hot_take'          => "Vous êtes d'accord ? En désaccord ? Je veux votre avis honnête 👇",
                'poll'              => "Votez ci-dessus et dites-moi en commentaire ce qui vous a le plus surpris dans votre expérience expat 👇",
                'milestone'         => "Merci à tous ceux qui font confiance à SOS-Expat.com ! Quelle a été votre expérience avec nous ? 🙏",
                'partner_story'     => "Vous êtes avocat ou expert expatrié ? Rejoignez notre réseau partenaire → SOS-Expat.com",
                'default'           => "Question pour vous : quel est votre plus grand défi en tant qu'expatrié ? 👇\n\n→ Ressources sur SOS-Expat.com",
            ],
            'en' => [
                'article'           => "Have you faced a similar situation as an expat? Share your experience in the comments 👇\n\n" . ($url ? "→ Full guide: {$url}" : '→ More resources at SOS-Expat.com'),
                'faq'               => "How did you handle this situation abroad? I'd love to hear your experience 👇\n\n" . ($url ? "→ Full answer: {$url}" : '→ All our FAQs at SOS-Expat.com'),
                'sondage'           => "Surprised by these numbers? What surprised you the most? 👇\n\n" . ($url ? "→ Full survey results: {$url}" : "→ Full survey at SOS-Expat.com"),
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
                'saturday'  => "Ce que 3 ans à l'étranger m'ont appris sur moi-même (que je n'attendais pas). ✈️",
            ],
            'en' => [
                'monday'    => "5 mistakes 90% of expats make when they first arrive (and how to avoid them) 👇",
                'tuesday'   => "She wanted to start a new life in Thailand. Here's what nobody told her.",
                'wednesday' => "🚨 What most expats don't know about their rights abroad",
                'thursday'  => "Most asked question this week: how to open a bank account abroad without a fixed address?",
                'friday'    => "2 years ago, he was afraid to leave everything. Today, he has zero regrets. ✈️",
                'saturday'  => "3 years abroad taught me something I never expected about myself. ✈️",
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
