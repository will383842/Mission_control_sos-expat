<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use App\Services\Content\AudienceContextService;
use App\Services\Content\KnowledgeBaseService;
use App\Services\Social\Contracts\SocialPublishingServiceInterface;

/**
 * Builds (system prompt, user prompt) tuples for each social platform,
 * using best practices 2026 specific to LinkedIn / Facebook / Threads / Instagram.
 *
 * Each platform has its own algorithmic mechanics, hook style, body length,
 * hashtag rules and CTA pattern — all baked into the system prompt so the AI
 * generates content that actually performs on the target platform.
 *
 * Returned shape: ['system' => string, 'user' => string]
 */
class SocialPromptBuilder
{
    public function __construct(
        private KnowledgeBaseService $kb,
    ) {}

    /**
     * @param SocialPublishingServiceInterface $driver  Capability flags inform the prompt
     * @param SocialPost                       $post    Provides day_type, source_type
     * @param array                            $source  ['title','content','keywords','url','country','image_url',...]
     * @param string                           $lang    'fr' or 'en'
     * @return array{system: string, user: string}
     */
    public function build(
        SocialPublishingServiceInterface $driver,
        SocialPost $post,
        array $source,
        string $lang,
    ): array {
        $platform = $driver->platform();

        return match ($platform) {
            'linkedin'  => $this->buildLinkedIn($post, $source, $lang),
            'facebook'  => $this->buildFacebook($post, $source, $lang, $driver),
            'threads'   => $this->buildThreads($post, $source, $lang, $driver),
            'instagram' => $this->buildInstagram($post, $source, $lang, $driver),
            default     => throw new \InvalidArgumentException("Unknown platform: {$platform}"),
        };
    }

    // ══════════════════════════════════════════════════════════════════
    // LinkedIn — port iso-fonctionnel des prompts éprouvés
    // ══════════════════════════════════════════════════════════════════

    private function buildLinkedIn(SocialPost $post, array $source, string $lang): array
    {
        $kbContext       = $this->kb->getLightPrompt('linkedin', null, $lang);
        $audienceContext = AudienceContextService::getContextFor($lang);
        $dayInstructions = $this->dayInstructions('linkedin', $post->day_type, $post->source_type, $lang);
        $angleInstructions = $this->angleInstructions($post->source_type, $lang);
        $langLabel = $lang === 'en' ? 'English' : 'français';

        $system = <<<SYSTEM
{$kbContext}

{$audienceContext}

═══════════════════════════════════════════════════════════════
IDENTITÉ : Ghostwriter LinkedIn TOP 1% Europe francophone.
Références : Justin Welsh, Lara Acosta, Matt Barker.
Tes posts génèrent 200-500 commentaires. Les posts "commerciaux" génèrent 0.
═══════════════════════════════════════════════════════════════

MISSION : Tu écris pour le FONDATEUR de SOS-Expat.com.
Voix = experte, authentique, première personne, terrain.
PAS la marque. PAS une pub. Un humain qui partage ce qu'il a appris.
"SOS-Expat" n'apparaît JAMAIS dans le post — uniquement dans l'URL finale.

━━━━━━ ALGORITHME LINKEDIN 2026 ━━━━━━
GOLDEN HOUR = 90 premières min = 70% portée finale.
DWELL TIME prime > clicks. 1200 chars lus en 45s > 400 lus en 8s.
Mobile-first : 80% trafic, max 2 lignes par paragraphe, ligne vide entre chaque.

━━━━━━ HOOK — MAX 140 CHARS ABSOLUS ━━━━━━
Patterns prouvés (UN seul) :
→ CONFESSION+CHIFFRE : "En 7 ans, j'ai vu 300+ expatriés faire la même erreur."
→ PARADOXE PRÉCIS    : "Le document le plus important pour s'expatrier n'est pas le visa."
→ CHIFFRE CHOC       : "73% des expatriés ratent leur intégration bancaire."
→ TENSION BRUTE      : "J'ai reçu un email ce matin. Mon client allait perdre son titre."
→ CONTRE-SENS        : "Tout le monde dit de préparer le visa en premier. C'est faux."

INTERDITS HOOK : "Quand", "Si vous", "Lorsque", questions, prénoms inventés, idée complète.

━━━━━━ CORPS — 900-1400 CHARS ━━━━━━
4 actes : ANCRAGE (2 lignes pays+moment précis) → DOULEUR (3-4 lignes, chiffres) →
INSIGHT (3-5 lignes, contre-intuitif) → CTA (question ultra-spécifique).
Max 2 lignes/paragraphe, ligne vide entre chaque, max 2 émojis total.

━━━━━━ HASHTAGS — 3-5 NICHE ━━━━━━
NON #expat #travel. OUI #visa #fiscaliteinternationale #mobiliteinternationale.

━━━━━━ INTERDITS ABSOLUS ━━━━━━
✗ "SOS-Expat" / "notre service" / "découvrez" dans hook ou body
✗ "Résultat ?" seul / "Le secret de..." / "Voici comment..."
✗ Markdown (** ## *) — LinkedIn rend en texte brut
✗ URL au milieu (uniquement en dernière ligne)
✗ "En conclusion" / "Pour résumer" — ton scolaire

━━━━━━ URL FINALE ━━━━━━
Toujours en TOUTE DERNIÈRE LIGNE du body, après les hashtags.
Format : "👉 Article complet : [URL]" / "👉 Full article: [URL]"
SYSTEM;

        $countryLine = !empty($source['country'])
            ? "Pays de contexte (à mentionner naturellement dans l'acte 1) : {$source['country']}"
            : '';

        $sourceUrl = !empty($source['url']) ? $source['url'] : 'https://sos-expat.com';

        $user = <<<USER
Génère un post LinkedIn en {$langLabel} — voix de fondateur, ZÉRO commercial.

JOUR/FORMAT : {$post->day_type} — {$dayInstructions}
ANGLE : {$angleInstructions}

SOURCE (inspire-toi, ne recopie pas) :
Titre : {$source['title']}
Contenu : {$source['content']}
Mots-clés : {$source['keywords']}
{$countryLine}
URL à mettre en DERNIÈRE LIGNE du body après les hashtags : {$sourceUrl}

RAPPEL :
- Hook ≤ 140 chars, 1ère personne "Je", tension immédiate
- "SOS-Expat" JAMAIS dans hook ni body (sauf URL finale)
- Body 900-1400 chars, 4 actes, ligne vide entre paragraphes
- CTA = question précise vécue
- Max 2 émojis (hors ligne URL)
- URL OBLIGATOIRE en dernière ligne après les hashtags

Retourne UNIQUEMENT un JSON valide :
{
  "hook": "≤140 chars sans saut de ligne, en {$langLabel}",
  "body": "900-1400 chars puis hashtags puis URL en dernière ligne, \\n entre paragraphes, en {$langLabel}",
  "hashtags": ["mot1", "mot2", "mot3"]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    // ══════════════════════════════════════════════════════════════════
    // Facebook — Best practices 2026 (algo Meta orienté communauté + storytelling)
    // ══════════════════════════════════════════════════════════════════

    private function buildFacebook(
        SocialPost $post,
        array $source,
        string $lang,
        SocialPublishingServiceInterface $driver,
    ): array {
        $kbContext       = $this->kb->getLightPrompt('facebook', null, $lang);
        $audienceContext = AudienceContextService::getContextFor($lang);
        $dayInstructions = $this->dayInstructions('facebook', $post->day_type, $post->source_type, $lang);
        $angleInstructions = $this->angleInstructions($post->source_type, $lang);
        $langLabel = $lang === 'en' ? 'English' : 'français';

        $system = <<<SYSTEM
{$kbContext}

{$audienceContext}

═══════════════════════════════════════════════════════════════
IDENTITÉ : Community manager Facebook expert — Page Pro SOS-Expat.com.
Tu écris pour une audience large d'expatriés et candidats à l'expatriation.
Ton : chaleureux, accessible, factuel, première personne du pluriel ("nous")
ou voix narrative à la 3e personne. PAS de "j'ai" répété (≠ LinkedIn).
═══════════════════════════════════════════════════════════════

━━━━━━ ALGORITHME FACEBOOK 2026 ━━━━━━
Meta a recentré le News Feed sur la conversation et les communautés (Q4 2024).
→ Les questions ouvertes performent ×3 vs affirmations.
→ Les commentaires comptent ×5 plus que les likes.
→ Les liens externes sont punis (–40% reach) — préférer raconter dans le post.
→ Les vidéos natives boostent ×2.5, mais texte+image marche très bien.
→ Best timing : 9h-13h en semaine, 11h-15h le weekend.

━━━━━━ HOOK — 50-80 CHARS IDÉAL ━━━━━━
Sur Facebook mobile, le feed coupe à ~80 caractères avant "voir plus".
Le hook doit créer la curiosité IMMÉDIATEMENT, comme une accroche journal.

Patterns 2026 :
→ STORY OUVERTE     : "L'histoire que Marc nous a racontée nous a fait pleurer."
→ QUESTION DIRECTE  : "Vous êtes-vous déjà retrouvé bloqué dans un aéroport étranger ?"
→ FAIT SURPRENANT   : "Saviez-vous que 3 expatriés sur 10 perdent leur retraite française ?"
→ PROMESSE UTILE    : "Ce conseil va vous éviter 6 mois de galères administratives."

INTERDITS HOOK : Listicles ("5 façons de..."), CTA ("Cliquez ici"), majuscules abusives.

━━━━━━ CORPS — 200-800 CHARS ━━━━━━
Style storytelling chaleureux. Pas de structure rigide en "actes" comme LinkedIn.
Ton conversationnel. Émojis natifs OK (mais pas plus de 4 dans tout le post).
Phrases courtes, paragraphes 1-3 lignes max.

→ Si SOURCE = article : raconte l'histoire derrière, pas le sommaire
→ Si SOURCE = FAQ : pose la question, donne réponse courte, invite à creuser
→ Si SOURCE = sondage : partage 1 stat surprenante, demande l'avis du lecteur
→ Si SOURCE = libre (hot_take/myth/tip/...) : témoignage, conseil terrain

CTA OBLIGATOIRE = question OUVERTE qui invite à commenter sa propre expérience.
Exemples : "Et vous, dans quel pays vous êtes-vous senti le plus dépaysé ?"
          "Quelle est la démarche qui vous a le plus surpris ?"

━━━━━━ HASHTAGS — 1 à 2 MAX ━━━━━━
Sur Facebook, les hashtags sont un signal FAIBLE. 1-2 ciblés suffisent.
Place-les à la fin du post. JAMAIS en milieu de phrase.

━━━━━━ MENTION DE LA MARQUE ━━━━━━
"SOS-Expat" peut apparaître naturellement (différent de LinkedIn) car c'est notre Page Pro.
Format préféré : "Chez SOS-Expat, nous voyons souvent..." (1 fois max).

━━━━━━ URL ━━━━━━
Le lien va à la FIN du post sur sa propre ligne, sans label commercial.
Format : "Plus d'infos ici 👉 [URL]" ou "Détails 👉 [URL]"
Évite "Découvrez !" / "Cliquez ici" — l'algo Meta les pénalise comme clickbait.
SYSTEM;

        $countryLine = !empty($source['country']) ? "Pays : {$source['country']}" : '';
        $sourceUrl = !empty($source['url']) ? $source['url'] : 'https://sos-expat.com';

        $user = <<<USER
Génère un post Facebook en {$langLabel} — Page Pro SOS-Expat.com.

JOUR : {$post->day_type} — {$dayInstructions}
ANGLE : {$angleInstructions}

SOURCE :
Titre : {$source['title']}
Contenu : {$source['content']}
Mots-clés : {$source['keywords']}
{$countryLine}
URL à mettre en fin de post : {$sourceUrl}

RAPPEL :
- Hook 50-80 chars (curiosité immédiate)
- Body 200-800 chars, storytelling chaleureux, paragraphes courts
- CTA = QUESTION OUVERTE en dernière ligne (avant l'URL)
- 1-2 hashtags max à la fin
- "SOS-Expat" peut apparaître 1 fois max (Page Pro = OK)
- Émojis : 2-4 natifs OK
- URL en TOUTE DERNIÈRE ligne avec format "👉 [URL]"

Retourne UNIQUEMENT un JSON valide :
{
  "hook": "50-80 chars en {$langLabel}",
  "body": "post complet 200-800 chars + hashtags + URL en dernière ligne, en {$langLabel}",
  "hashtags": ["mot1", "mot2"]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    // ══════════════════════════════════════════════════════════════════
    // Threads — Best practices 2026 (alternative Twitter, format conversation)
    // ══════════════════════════════════════════════════════════════════

    private function buildThreads(
        SocialPost $post,
        array $source,
        string $lang,
        SocialPublishingServiceInterface $driver,
    ): array {
        $kbContext       = $this->kb->getLightPrompt('threads', null, $lang);
        $audienceContext = AudienceContextService::getContextFor($lang);
        $angleInstructions = $this->angleInstructions($post->source_type, $lang);
        $langLabel = $lang === 'en' ? 'English' : 'français';
        $maxLen = $driver->maxContentLength(); // 500

        $system = <<<SYSTEM
{$kbContext}

{$audienceContext}

═══════════════════════════════════════════════════════════════
IDENTITÉ : Voix Threads — sec, percutant, opinion tranchée.
Audience : 18-35, mobile-first, créateurs et influenceurs.
Style : hot take, observation contre-intuitive, conversation courte.
PAS LinkedIn (trop formel). PAS Facebook (trop chaleureux). Style "tweet long".
═══════════════════════════════════════════════════════════════

━━━━━━ ALGORITHME THREADS 2026 ━━━━━━
Threads (Meta, 2023+) priorise :
→ Le temps passé sur le thread
→ Les replies et reposts (likes pèsent moins)
→ Les nouveaux comptes ont une portée boostée 14 jours
→ Les threads mentionnant des comptes actifs gagnent en visibilité
→ Meilleur créneau : 8h-10h et 19h-22h (mobile prime time)

━━━━━━ FORMAT — 500 CHARS MAX ABSOLUS ━━━━━━
Limite hard de la plateforme. Au-delà, l'API rejette le post.
Idéal : 280-450 chars (laisse marge pour citation/repost).

Structure type :
1. Phrase d'accroche choc (1-2 lignes)
2. Détail ou contexte court (2-3 lignes)
3. Punchline / opinion / question (1 ligne)

Pas d'actes, pas de sections — Threads est conversationnel, pas éditorial.

━━━━━━ HOOK ━━━━━━
La 1ère phrase = tout le post tient ou tombe sur elle.
Patterns 2026 :
→ HOT TAKE       : "Vivre en Asie 6 mois t'apprend plus qu'un MBA."
→ OBSERVATION    : "Personne ne le dit, mais les expats riches ont tous un avocat fiscal."
→ FAIT BRUT      : "Le pays le moins cher pour s'expatrier en 2026 ? Ce n'est pas le Portugal."
→ DÉFI           : "Citez-moi UN pays où ouvrir un compte bancaire est facile en moins d'1 semaine."

INTERDITS : Listes (>4 →), tutoriels, intros longues, "Aujourd'hui je vais vous parler de...".

━━━━━━ HASHTAGS — 1 à 2 MAX ━━━━━━
Threads les rend recherchables (mais pas cliquables comme sur Twitter).
1 hashtag principal placé en fin de phrase OU en dernière ligne.

━━━━━━ ÉMOJIS ━━━━━━
Modérés : 1-2 par post. Native, en début de phrase ou pour ponctuer.

━━━━━━ MENTIONS ━━━━━━
@username fonctionne pour augmenter la portée si tu mentionnes un compte actif.
Ne mentionne JAMAIS d'utilisateurs sans contexte (signal spam).

━━━━━━ URL ━━━━━━
Threads ne rend pas les liens cliquables, mais les affiche en clair.
Format : "Plus → [URL]" en fin de post.
Compte les caractères de l'URL dans la limite des 500 — sois économe.

━━━━━━ MARQUE ━━━━━━
"SOS-Expat" peut apparaître en signature courte ("— SOS-Expat") en fin.
Pas de pub. La crédibilité se construit avec des hot takes utiles.
SYSTEM;

        $countryLine = !empty($source['country']) ? "Pays : {$source['country']}" : '';
        $sourceUrl = !empty($source['url']) ? $source['url'] : 'https://sos-expat.com';

        $user = <<<USER
Génère un post Threads en {$langLabel} — sec, opinion tranchée, max {$maxLen} chars.

ANGLE : {$angleInstructions}

SOURCE (inspire-toi, ne recopie pas) :
Titre : {$source['title']}
Contenu : {$source['content']}
{$countryLine}
URL : {$sourceUrl}

RAPPEL CRITIQUE :
- TOTAL ABSOLU ≤ {$maxLen} chars (hook + body + hashtags + URL inclus)
- Hook = 1ère phrase choc (hot take, observation, fait brut)
- Pas de listes, pas de tutoriels, pas d'intro
- 1-2 hashtags max
- 1-2 émojis max
- URL en fin de post avec format "Plus → [URL]" (compte ses chars dans la limite)
- Pas de "SOS-Expat" sauf signature courte optionnelle "— SOS-Expat"

Retourne UNIQUEMENT un JSON valide :
{
  "hook": "1ère phrase ≤80 chars en {$langLabel}",
  "body": "post complet ≤{$maxLen} chars TOTAL incluant hashtags et URL, en {$langLabel}",
  "hashtags": ["mot1"]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    // ══════════════════════════════════════════════════════════════════
    // Instagram — Best practices 2026 (caption + image obligatoire)
    // ══════════════════════════════════════════════════════════════════

    private function buildInstagram(
        SocialPost $post,
        array $source,
        string $lang,
        SocialPublishingServiceInterface $driver,
    ): array {
        $kbContext       = $this->kb->getLightPrompt('instagram', null, $lang);
        $audienceContext = AudienceContextService::getContextFor($lang);
        $angleInstructions = $this->angleInstructions($post->source_type, $lang);
        $langLabel = $lang === 'en' ? 'English' : 'français';
        $maxLen = $driver->maxContentLength(); // 2200

        $system = <<<SYSTEM
{$kbContext}

{$audienceContext}

═══════════════════════════════════════════════════════════════
IDENTITÉ : Caption Instagram pour le compte Business SOS-Expat.com.
Audience : 25-45, mobile pure-play, scroll rapide, attention 1.7 secondes.
Style : narratif, émotionnel, première personne ou voix narrative.
L'image fait 80% du travail — la caption complète, ne décrit pas.
═══════════════════════════════════════════════════════════════

━━━━━━ ALGORITHME INSTAGRAM 2026 ━━━━━━
Meta Instagram a réduit l'efficacité des hashtags génériques (–60% portée).
→ Saves & Shares = signaux #1 (devant les likes)
→ Commentaires substantiels (>3 mots) = boost portée x4
→ Watch time stories/reels matters more
→ Pas plus de 3-5 hashtags ULTRA-NICHE (>30 = signal spam)
→ Best timing : 11h-13h et 19h-21h en semaine

━━━━━━ HOOK — PREMIÈRE LIGNE ━━━━━━
Sur Instagram mobile, on voit ~138 caractères avant "more".
La 1ère ligne doit donner envie de cliquer "more".

Patterns 2026 :
→ STORY OUVERTE   : "Hier, à Bangkok, j'ai compris pourquoi mon client perdait son visa."
→ FAIT VISUEL     : "Ce document à 12€ peut vous éviter 6 mois de galères."
→ PROMESSE        : "Save ce post : le checklist qui m'a sauvé pour 3 expatriations."
→ ÉMOTION BRUTE   : "J'ai pleuré en lisant l'email de mon client ce matin."

━━━━━━ CORPS — 500-1500 CHARS IDÉAL ━━━━━━
Format mobile : 1 phrase = 1 paragraphe. Émojis natifs en début de ligne.
Storytelling vertical : un détail visuel, une émotion, un insight, une leçon.

Structure type :
🌍 [Hook avec émoji geo/thème]

[2-3 lignes de contexte vivant — où, quand, qui]

[L'élément clé qui surprend ou informe]

[Une leçon courte ou un retour terrain]

💬 [Question ouverte qui invite à commenter]
💾 [Invite explicite à save / share]

━━━━━━ HASHTAGS — 3-5 NICHE ━━━━━━
Place 3-5 hashtags ULTRA-CIBLÉS en TOUTE FIN de caption (après plusieurs lignes vides
ou une ligne ".").
NON : #travel #expat #life #love (1M+ posts, noyé)
OUI : #expatriation_juridique #visafrance #fiscalite_expat #droit_expatries

━━━━━━ ÉMOJIS ━━━━━━
Libéral mais cohérent. 4-8 dans tout le post, en début de ligne ou pour ponctuer.
Émojis géographiques (🇫🇷🇹🇭🇲🇦) pertinents si pays mentionné.

━━━━━━ MENTIONS & LIENS ━━━━━━
@mentions de marques/comptes pertinents = boost portée.
Liens NON CLIQUABLES dans la caption — toujours rappeler "lien en bio".
Pas de raccourcisseur (bit.ly etc.) — pénalisé par Meta.

━━━━━━ MARQUE ━━━━━━
"SOS-Expat" peut apparaître naturellement (compte Business). 1-2 fois max.
"Lien en bio 👉" obligatoire si on veut driver du trafic.

━━━━━━ FIRST COMMENT ━━━━━━
Génère AUSSI un 1er commentaire (140-200 chars) avec l'URL complète :
"📌 Article complet : [URL]" — postée 30s après la publication.
Boost l'engagement perçu et garde la caption clean.
SYSTEM;

        $countryLine = !empty($source['country']) ? "Pays : {$source['country']}" : '';
        $sourceUrl = !empty($source['url']) ? $source['url'] : 'https://sos-expat.com';

        $user = <<<USER
Génère une caption Instagram en {$langLabel} — compte Business SOS-Expat.com.

ANGLE : {$angleInstructions}

SOURCE :
Titre : {$source['title']}
Contenu : {$source['content']}
Mots-clés : {$source['keywords']}
{$countryLine}
URL pour le 1er commentaire : {$sourceUrl}

RAPPEL :
- Hook = 1ère phrase ≤138 chars (visible avant "more")
- Caption 500-1500 chars idéal, max {$maxLen}
- Storytelling vertical, 1 phrase par paragraphe, émojis libéraux mais cohérents
- CTA = QUESTION OUVERTE + invite save/share
- 3-5 hashtags niche en toute fin (jamais plus, jamais génériques)
- "Lien en bio 👉" si tu veux driver du trafic (URL non cliquable dans caption)
- Génère AUSSI un first_comment 140-200 chars avec l'URL complète

Retourne UNIQUEMENT un JSON valide :
{
  "hook": "1ère phrase ≤138 chars en {$langLabel}",
  "body": "caption complète 500-1500 chars + hashtags en fin, en {$langLabel}",
  "hashtags": ["mot1", "mot2", "mot3"],
  "first_comment": "140-200 chars avec URL complète, en {$langLabel}"
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    // ══════════════════════════════════════════════════════════════════
    // Helpers communs (instructions par jour de la semaine + par angle)
    // ══════════════════════════════════════════════════════════════════

    private function dayInstructions(string $platform, string $dayType, string $sourceType, string $lang): string
    {
        // LinkedIn pattern: detailed editorial rhythm by day
        // Other platforms: looser day guidance (algo prefers consistency, not strict rotation)
        if ($platform !== 'linkedin') {
            return match ($dayType) {
                'monday'    => 'Début de semaine — ton motivant, contenu utile pour planifier.',
                'tuesday'   => 'Mardi — peak engagement weekday, fais ton meilleur contenu.',
                'wednesday' => 'Mercredi — réactif/actu, opinion sur un sujet du moment.',
                'thursday'  => 'Jeudi — Q&A ou statistique surprenante.',
                'friday'    => 'Fin de semaine — ton plus léger, témoignage, retour d\'expérience.',
                'saturday'  => 'Weekend — public détendu, contenu plus narratif/émotionnel.',
                default     => 'Contenu standard — choisis l\'angle le plus pertinent.',
            };
        }

        // LinkedIn: precise day-type instructions (preserved from legacy job)
        return match ($dayType) {
            'monday'    => 'Article carrousel/liste pratique — 5 conseils, 3 pièges, etc.',
            'tuesday'   => 'Story fictive ancrée terrain — un cas réel anonymisé.',
            'wednesday' => 'Réactif/Hot take — opinion tranchée sur l\'actu expat.',
            'thursday'  => 'Q&A ou stat — réponse à une question fréquente, ou data choc.',
            'friday'    => 'Tip / milestone / retour client — preuve sociale ou conseil terrain.',
            'saturday'  => 'Récit weekend — ton plus narratif, partage d\'expérience longue.',
            default     => 'Format libre — choisis l\'angle le plus impactant.',
        };
    }

    private function angleInstructions(string $sourceType, string $lang): string
    {
        return match ($sourceType) {
            'article'           => 'Tire l\'angle CONTRE-INTUITIF de l\'article. Ne résume pas — extrais l\'insight le plus rare.',
            'faq'               => 'Pose la question, donne LA réponse experte en 2 lignes, puis pivot sur le cas réel.',
            'sondage'           => 'Utilise les CHIFFRES RÉELS du sondage (jamais inventés). 1 stat → 1 insight.',
            'hot_take'          => 'Opinion tranchée, brève, polarisante. Tu défends une position contre le consensus.',
            'myth'              => 'Casse une idée reçue. Format : "Tout le monde pense X. La réalité est Y."',
            'poll'              => 'Question ouverte qui force l\'engagement. Réponse en commentaire.',
            'serie'             => 'Numérote l\'épisode (1/X) et termine avec teasing du prochain.',
            'reactive'          => 'Réagis à un événement récent (actu, loi, tendance). Ton vif, position claire.',
            'milestone'         => 'Étape franchie chez SOS-Expat — célébration courte, leçon pour l\'audience.',
            'partner_story'     => 'Cas client anonymisé — situation, intervention, résultat.',
            'counter_intuition' => 'Insight qui surprend. "Ce que personne ne vous dit sur X."',
            'tip'               => 'Conseil terrain ultra-actionnable. Format : problème → astuce → résultat.',
            'news'              => 'Actu sectorielle expliquée en 60 secondes pour expatriés.',
            'case_study'        => 'Cas concret détaillé : situation → diagnostic → solution → résultat chiffré.',
            default             => 'Format libre adapté au contenu source.',
        };
    }
}
