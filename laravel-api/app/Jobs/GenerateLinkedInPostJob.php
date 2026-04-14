<?php

namespace App\Jobs;

use App\Models\GeneratedArticle;
use App\Models\LinkedInPost;
use App\Models\QaEntry;
use App\Models\Sondage;
use App\Services\AI\ClaudeService;
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

    public function handle(ClaudeService $claude, KnowledgeBaseService $kb): void
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
            $kbContext       = $kb->getLightPrompt('linkedin', null, $lang);
            $audienceContext = AudienceContextService::getContextFor($lang);
            $dayInstructions = $this->getDayInstructions($dayType, $post->source_type, $lang);
            $angleInstructions = $this->getAngleInstructions($post->source_type, $lang);
            $langLabel       = $lang === 'en' ? 'English' : 'français';

            $systemPrompt = <<<SYSTEM
{$kbContext}

{$audienceContext}

Tu es un expert LinkedIn de niveau international (top 1% des créateurs LinkedIn 2026).
Tu crées du contenu LinkedIn pour SOS-Expat.com — mise en relation expatriés × avocats/experts dans 197 pays.

RÈGLES ABSOLUES LINKEDIN 2026 :
- Hook IRRÉSISTIBLE sur 2-3 lignes (avant "Voir plus" — MAX 140 caractères)
- Corps total (hook + body) : 1 200–1 800 caractères
- JAMAIS de lien dans le post (le lien va en 1er commentaire, JAMAIS dans le post)
- 3-5 hashtags de niche pertinents — PAS de hashtags génériques
- CTA doux : question ouverte finale pour générer les commentaires
- Style : humain, empathique, conversationnel, pratique
- Texte brut LinkedIn (INTERDIT : Markdown, **, ##, *, _)
- Ligne vide entre chaque paragraphe (lisibilité mobile)
- Toujours mentionner SOS-Expat.com comme ressource (avec le .com)
- Le pays est un CONTEXTE dans le corps, jamais le sujet principal
SYSTEM;

            $userPrompt = <<<USER
Génère un post LinkedIn en {$langLabel} pour SOS-Expat.com.

JOUR : {$dayType}
FORMAT : {$dayInstructions}
ANGLE : {$angleInstructions}

SOURCE :
Titre : {$source['title']}
Contenu : {$source['content']}
Mots-clés : {$source['keywords']}
URL blog (pour le 1er commentaire) : {$source['url']}

Retourne UNIQUEMENT un objet JSON valide avec exactement ces 4 clés :
{
  "hook": "2-3 lignes d'accroche avant Voir plus (max 140 chars, aucun saut de ligne)",
  "body": "Corps complet sans le hook (900-1500 chars, \\n entre paragraphes)",
  "hashtags": ["expatriation", "expat", "sosexpat"],
  "first_comment": "Commentaire à poster 3 min après le post : question ouverte à la communauté + lien blog si disponible + révèle la suite non dite dans le post. 150-300 chars."
}
USER;

            // ── 3. Generate with Claude Haiku ────────────────────────
            $result = $claude->complete($systemPrompt, $userPrompt, [
                'model'       => 'claude-haiku-4-5-20251001',
                'max_tokens'  => 1800,
                'temperature' => 0.78,
                'json_mode'   => true,
            ]);

            if (!($result['success'] ?? false)) {
                throw new \RuntimeException($result['error'] ?? 'Claude API error');
            }

            $data = json_decode($result['content'] ?? '', true) ?? [];

            // ── 4. Hashtags: sanitize + fallback from keywords ───────
            $hashtags = $this->buildHashtags($data['hashtags'] ?? [], $source['hashtag_seeds']);

            // ── 5. First comment fallback ────────────────────────────
            $firstComment = $data['first_comment'] ?? $this->defaultFirstComment($post->source_type, $source['url'], $lang);

            // ── 6. Image: use source article featured image ──────────
            $featuredImage = $source['image_url'] ?? null;

            // ── 7. Auto-schedule to next free optimal slot ──────────
            $controller   = new \App\Http\Controllers\LinkedInController();
            $nextSlot     = $controller->nextFreeSlot($lang);

            // ── 8. Update post record (scheduled, not just draft) ────
            $post->update([
                'hook'                  => $data['hook']  ?? $this->defaultHook($dayType, $lang),
                'body'                  => $data['body']  ?? $this->defaultBody($lang),
                'hashtags'              => $hashtags,
                'first_comment'         => $firstComment,
                'featured_image_url'    => $featuredImage,
                'first_comment_status'  => $firstComment ? 'pending' : null,
                'status'                => 'scheduled',
                'scheduled_at'          => $nextSlot,
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

    // ── Day & angle instructions ───────────────────────────────────────

    private function getDayInstructions(string $day, string $sourceType, string $lang): string
    {
        $fr = [
            'monday'    => 'Format carrousel/liste : "Les X erreurs / conseils pour les expats". Commencer par un chiffre choc. Style pratique, liste numérotée dans le corps.',
            'tuesday'   => 'Story fictive : un personnage type ("Marie voulait s\'installer au Vietnam..."). Hook émotionnel fort. Situation → problème → résolution via SOS-Expat.',
            'wednesday' => 'Actu légale/visa OU opinion tranchée. Format "🚨 N changements" ou affirmation provocante. Concis, factuel, chiffres si possible.',
            'thursday'  => 'Q&A ou statistique choc. Commencer par une question ou un chiffre surprenant. Réponse structurée avec valeur max. Stats sondage si disponibles.',
            'friday'    => 'Témoignage / tip / story partenaire. Ton inspirant et positif. Finir sur fierté ou espoir. Ou story d\'un avocat/helper partenaire.',
        ];

        $en = [
            'monday'    => '"X mistakes / tips for expats" carousel format. Start with a shocking stat. Practical style, numbered list.',
            'tuesday'   => 'Fictional story: a typical character ("Sarah wanted to move to Thailand..."). Strong emotional hook. Situation → problem → resolution via SOS-Expat.',
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
            'milestone'         => 'Célébrer un chiffre ou une étape de SOS-Expat. Ex: "1000 expatriés aidés". Humaniser avec des données concrètes. Ton humble et fier.',
            'partner_story'     => 'Story d\'un avocat ou helper partenaire. Fictif ou inspiré. Montrer l\'impact humain du réseau SOS-Expat. Humanise la plateforme.',
            'counter_intuition' => 'Affirmation contre-intuitive sur l\'expatriation. Commence par l\'idée reçue, puis la surprise. Curiosité garantit le "voir plus".',
            'tip'               => 'Conseil pratique rapide et actionnable pour les expatriés. Concis, direct, immédiatement utile.',
            'news'              => 'Actualité récente liée à l\'expatriation ou aux changements légaux/visa. Factuel, chiffres, importance pour les expats.',
            'case_study'        => 'Cas client (fictif ou anonymisé) : problème rencontré → solution SOS-Expat → résultat. Format story avec données concrètes.',
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
            'milestone'         => 'Celebrate a milestone for SOS-Expat. Humanize with concrete data. Humble and proud tone.',
            'partner_story'     => 'A partner lawyer or helper story. Fictional or inspired. Show the human impact of the SOS-Expat network.',
            'counter_intuition' => 'Counter-intuitive statement about expat life. Start with the assumption, then the surprise. Curiosity ensures "see more".',
            'tip'               => 'Quick, practical, actionable tip for expats. Concise, direct, immediately useful.',
            'news'              => 'Recent news related to expat life or legal/visa changes. Factual, figures, relevance for expats.',
            'case_study'        => 'Client case (fictional or anonymized): problem → SOS-Expat solution → result. Story format with concrete data.',
        ];

        $map = ($lang === 'en') ? $en : $fr;
        return $map[$sourceType] ?? 'Post LinkedIn professionnel pour SOS-Expat.com.';
    }

    // ── Hashtag builder ────────────────────────────────────────────────

    private function buildHashtags(array $aiHashtags, array $seeds): array
    {
        // Base tags always included
        $base = ['expatriation', 'expat', 'sosexpat'];

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
            ? "SOS-Expat helps expats navigate legal and administrative challenges abroad.\n\nOur network of partner lawyers and experienced expat helpers is available to guide you step by step.\n\n→ Visa questions, tax issues, finding the right contact: we cover it all in 197 countries.\n\nHave you faced this situation? Share your experience in the comments 👇\n\n(Link in first comment)"
            : "SOS-Expat aide les expatriés à naviguer dans les défis juridiques et administratifs à l'étranger.\n\nNotre réseau d'avocats partenaires et d'expats aidants est disponible pour vous guider pas à pas.\n\n→ Questions de visa, fiscalité, trouver le bon interlocuteur dans un nouveau pays : on couvre tout dans 197 pays.\n\nVous avez vécu cette situation ? Partagez votre expérience en commentaire 👇\n\n(Lien en premier commentaire)";
    }
}
