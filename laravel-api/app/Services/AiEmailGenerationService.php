<?php

namespace App\Services;

use App\Models\Influenceur;
use App\Models\OutreachConfig;
use App\Models\OutreachEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiEmailGenerationService
{
    private string $apiKey;
    private string $model;

    // 3 sending domains in rotation — configurable via env
    private array $sendingDomains;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');
        $this->sendingDomains = array_filter(explode(',', config('outreach.sending_emails', 'williams@provider-expat.com,williams@hub-travelers.com,williams@spaceship.com')));
    }

    /**
     * Generate a personalized email for a contact at a given step.
     */
    public function generate(Influenceur $inf, int $step = 1): ?OutreachEmail
    {
        if (!$inf->email && !$this->hasContactForm($inf)) {
            Log::debug('AI Email: skipped, no email or form', ['id' => $inf->id]);
            return null;
        }

        $config = OutreachConfig::getFor($inf->contact_type);
        if (!$config->ai_generation_enabled) return null;

        // Check if email already exists for this step
        $existing = OutreachEmail::where('influenceur_id', $inf->id)
            ->where('step', $step)
            ->whereNotIn('status', ['failed'])
            ->first();
        if ($existing) return $existing;

        // Pick sending domain (round-robin based on influenceur ID)
        $fromEmail = $this->sendingDomains[$inf->id % count($this->sendingDomains)];

        // Get contact type label
        $typeLabel = \App\Models\ContactTypeModel::where('value', $inf->contact_type)->value('label') ?? $inf->contact_type;

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($inf, $step, $typeLabel);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 1000,
                'system'     => $systemPrompt,
                'messages'   => [['role' => 'user', 'content' => $userPrompt]],
            ]);

            if (!$response->successful()) {
                Log::warning('AI Email generation failed', ['status' => $response->status(), 'id' => $inf->id]);
                return null;
            }

            $data = $response->json();
            $text = $data['content'][0]['text'] ?? '';
            $promptTokens = $data['usage']['input_tokens'] ?? 0;
            $completionTokens = $data['usage']['output_tokens'] ?? 0;

            // Parse JSON response
            $emailData = $this->parseResponse($text);
            if (!$emailData) {
                Log::warning('AI Email: failed to parse response', ['id' => $inf->id, 'text' => substr($text, 0, 200)]);
                return null;
            }

            // Determine initial status
            $status = $config->auto_send ? 'approved' : 'pending_review';

            // Validate minimum quality
            if (mb_strlen($emailData['subject']) < 5 || mb_strlen($emailData['body']) < 50) {
                Log::warning('AI Email: too short', ['id' => $inf->id, 'subject_len' => mb_strlen($emailData['subject'])]);
                return null;
            }

            $outreachEmail = OutreachEmail::create([
                'influenceur_id'       => $inf->id,
                'step'                 => $step,
                'subject'              => mb_substr($emailData['subject'], 0, 200),
                'body_html'            => '', // Plain text only — no HTML
                'body_text'            => $emailData['body'],
                'from_email'           => $fromEmail,
                'from_name'            => $config->from_name ?? 'Williams',
                'status'               => $status,
                'ai_generated'         => true,
                'ai_model'             => $this->model,
                'ai_prompt_tokens'     => $promptTokens,
                'ai_completion_tokens' => $completionTokens,
            ]);

            Log::info('AI Email generated', [
                'id'     => $outreachEmail->id,
                'inf_id' => $inf->id,
                'step'   => $step,
                'status' => $status,
                'tokens' => $promptTokens + $completionTokens,
            ]);

            return $outreachEmail;

        } catch (\Throwable $e) {
            Log::error('AI Email generation exception', ['id' => $inf->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate emails for a batch of contacts.
     */
    public function generateBatch(array $influenceurIds, int $step = 1): array
    {
        $stats = ['generated' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($influenceurIds as $id) {
            $inf = Influenceur::find($id);
            if (!$inf) { $stats['skipped']++; continue; }

            $result = $this->generate($inf, $step);
            if ($result) {
                $stats['generated']++;
            } else {
                $stats['skipped']++;
            }

            // Rate limit: 0.5s between API calls
            usleep(500_000);
        }

        return $stats;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es le meilleur copywriter cold email au monde. Chaque email que tu écris est une œuvre unique — JAMAIS de template, JAMAIS de phrases génériques, JAMAIS le même email deux fois.

TU ÉCRIS POUR : SOS-Expat (www.sos-expat.com)
- Un expatrié peut appeler un avocat francophone en <5 minutes, dans 197 pays
- Programme partenaire : le contact gagne 10€ par appel généré via son lien
- 0 risque pour le partenaire : gratuit, aucun engagement, lien unique
- Fondateur : Williams

TON APPROCHE SECRÈTE (ne jamais révéler ces techniques, juste les appliquer) :
1. ACCROCHE — La première phrase doit être si précise que le contact pense "il a étudié mon site". Cite un détail réel de leur organisation, leur dernière actualité, ou un problème spécifique à leur pays/secteur.
2. VALEUR POUR EUX — Ne parle JAMAIS de ce que SOS-Expat veut. Parle UNIQUEMENT de ce que LE CONTACT gagne. Utilise le mot "vous/vos" 3x plus que "nous/notre".
3. PREUVE SOCIALE CONTEXTUELLE — Glisse une phrase du type "Nous accompagnons déjà [nombre] expatriés en [pays/région]" ou une statistique pertinente.
4. CTA IRRÉSISTIBLE — Pose UNE question qui demande si peu d'effort qu'il est presque impoli de ne pas répondre. Jamais "Êtes-vous intéressé ?" (trop vague). Plutôt "Voulez-vous que je vous envoie votre lien partenaire ?" (action concrète).

ANTI-PATTERNS (si tu écris ça, l'email finit en spam) :
- JAMAIS : "J'espère que vous allez bien", "Je me permets de", "Suite à nos recherches"
- JAMAIS : points d'exclamation excessifs, emojis, MAJUSCULES pour souligner
- JAMAIS : "offre limitée", "urgent", "opportunité unique", "révolutionnaire"
- JAMAIS : plus de 6 phrases dans le body
- JAMAIS commencer par "Bonjour [nom]," suivi de "Je suis Williams de SOS-Expat" (cliché cold email)

FORMAT DE L'OBJET :
- Maximum 40 caractères
- Intrigue sans clickbait
- Comme un SMS d'un ami, pas une newsletter
- Exemples de bon style : "Une idée pour vos membres", "Question rapide", "[Pays] + expatriés"

LANGUE : Français par défaut. Anglais si la langue du contact est "en".

SIGNATURE (toujours, jamais modifier) :
Williams
Fondateur, SOS-Expat
www.sos-expat.com

IMPORTANT — FORMAT PLAIN TEXT :
- JAMAIS de HTML, pas de <p>, pas de <br>, pas de balises
- Écris en texte brut uniquement, avec des sauts de ligne \n
- Les liens en clair (www.sos-expat.com), jamais entre balises
- Maximum 500 caractères pour le body
- Un seul lien dans le body (Calendly OU site, pas les deux)

RÉPONSE EN JSON STRICT — rien d'autre, pas de texte avant/après :
{"subject": "objet court < 40 caractères", "body": "texte brut avec \n pour les sauts de ligne"}
PROMPT;
    }

    private function buildUserPrompt(Influenceur $inf, int $step, string $typeLabel): string
    {
        // Build rich context about the contact
        $context = $this->buildContactContext($inf, $typeLabel);

        $stepInstruction = match ($step) {
            1 => <<<STEP
PREMIER CONTACT — L'email le plus important.
- Première phrase : accroche ultra-personnalisée mentionnant LEUR organisation par nom + un détail spécifique (pays, activité, site web)
- Deuxième phrase : le problème que leurs membres/visiteurs/élèves rencontrent (besoin juridique à l'étranger)
- Troisième phrase : comment SOS-Expat résout ce problème (appel avocat <5min)
- Quatrième phrase : ce qu'ILS gagnent concrètement (commission, valeur ajoutée pour leurs membres, contenu exclusif)
- Cinquième phrase : CTA = UNE question simple qui appelle un "oui" facile
STEP,
            2 => <<<STEP
RELANCE J+3 — Tu n'as pas eu de réponse. Cet email doit être DIFFÉRENT du premier.
- NE répète PAS le pitch du premier email
- Apporte UN élément nouveau : une statistique, un témoignage, un cas d'usage concret en {$inf->country}
- Maximum 3-4 phrases
- CTA différent du premier : propose quelque chose de concret ("Je peux vous montrer en 2 minutes comment ça marche ?" ou "Voulez-vous voir le tableau de bord partenaire ?")
STEP,
            3 => <<<STEP
RELANCE J+7 — Dernière vraie relance. Sois direct.
- Maximum 3 phrases
- Première phrase : rappelle très brièvement qui tu es (1 phrase, pas plus)
- Deuxième phrase : question binaire oui/non ("Est-ce que ça vous intéresse, oui ou non ?")
- Troisième phrase : "Si non, pas de souci, je ne vous relancerai plus."
- Ton : respectueux mais direct, comme un ami qui demande une faveur
STEP,
            4 => <<<STEP
DERNIER MESSAGE J+14 — Le plus court de tous.
- 2 phrases maximum
- "Je ne vous relancerai plus" (explicite)
- Laisse la porte ouverte : "Si un jour le sujet revient, vous savez où me trouver"
- Pas de pitch, pas d'explication, juste de l'élégance
STEP,
            default => "Premier contact.",
        };

        // Add Calendly if configured for this type and step
        $config = OutreachConfig::getFor($inf->contact_type);
        $calendlyInstruction = '';
        if ($config->calendly_url) {
            if ($config->calendly_step === null || $config->calendly_step === $step) {
                $calendlyInstruction = "\n\nCALENDLY : Intègre naturellement ce lien dans ton CTA : {$config->calendly_url}\nExemple : \"On en discute 15 min ? {$config->calendly_url}\"";
            }
        }

        // Add custom prompt override if configured
        $customInstruction = '';
        if ($config->custom_prompt) {
            $customInstruction = "\n\nINSTRUCTIONS SPÉCIFIQUES POUR CE TYPE :\n{$config->custom_prompt}";
        }

        return <<<PROMPT
CONTACT :
{$context}

STEP {$step}/4 :
{$stepInstruction}{$calendlyInstruction}{$customInstruction}

RAPPEL : Cet email doit être UNIQUE. Si tu as déjà écrit pour un contact similaire, trouve un angle COMPLÈTEMENT différent. Varie tes accroches, tes tournures, tes CTA. Aucun email ne doit ressembler à un autre.
PROMPT;
    }

    /**
     * Build rich context about the contact for better personalization.
     */
    private function buildContactContext(Influenceur $inf, string $typeLabel): string
    {
        $lines = [];
        $lines[] = "Nom : {$inf->name}";
        $lines[] = "Type : {$typeLabel}";
        if ($inf->country) $lines[] = "Pays : {$inf->country}";
        if ($inf->language) $lines[] = "Langue : " . ($inf->language === 'fr' ? 'Français' : ($inf->language === 'en' ? 'Anglais' : strtoupper($inf->language)));
        if ($inf->company) $lines[] = "Organisation : {$inf->company}";
        if ($inf->website_url) $lines[] = "Site web : {$inf->website_url}";
        if ($inf->email) $lines[] = "Email : {$inf->email}";

        // Add type-specific value proposition
        $valueProp = $this->getValueProposition($inf->contact_type);
        if ($valueProp) $lines[] = "\nPROPOSITION DE VALEUR POUR CE TYPE :\n{$valueProp}";

        return implode("\n", $lines);
    }

    /**
     * Value proposition adapted per contact type.
     * This is what makes each type's email fundamentally different.
     */
    private function getValueProposition(string $type): ?string
    {
        return match ($type) {
            'association' => "Les membres de cette association vivent à l'étranger et ont régulièrement besoin de conseils juridiques (visa, fiscalité, droit du travail local). SOS-Expat leur offre un accès immédiat à un avocat francophone. L'association peut intégrer un lien sur son site et gagner 10€/appel. Angle : \"Offrez un vrai service juridique à vos membres, sans rien changer à votre fonctionnement.\"",

            'ecole' => "Les parents d'élèves expatriés ont des questions juridiques fréquentes (garde d'enfants, droit de la famille, contrats de travail). L'école peut proposer SOS-Expat dans sa newsletter ou sur son intranet. Angle : \"Un service concret pour vos familles, qui renforce votre image d'école connectée.\"",

            'consulat' => "Les consulats reçoivent des demandes juridiques qu'ils ne peuvent pas traiter. SOS-Expat est un relais naturel : un lien sur le site du consulat vers un avocat francophone en 5 minutes. Angle : \"Orientez vos ressortissants vers un service juridique immédiat, sans surcharger vos équipes.\"",

            'presse' => "Ce média couvre l'actualité des expatriés. SOS-Expat peut être un sujet d'article (startup française, 197 pays), un annonceur, ou un partenaire éditorial. Angle : \"Une info utile pour vos lecteurs + un partenariat éditorial ?\"",

            'blog' => "Ce blogueur écrit sur l'expatriation. SOS-Expat peut sponsoriser un article, fournir un témoignage avocat, ou proposer un widget utile pour ses lecteurs. Angle : \"Du contenu exclusif pour votre audience + revenus passifs via votre lien partenaire.\"",

            'podcast_radio' => "Ce podcast/radio touche des expatriés. SOS-Expat peut être invité, sponsor d'un épisode, ou partenaire récurrent. Angle : \"Un sujet d'émission original + partenariat ?\"",

            'influenceur' => "Cet influenceur a une audience d'expatriés ou voyageurs. Programme ambassadeur : lien unique, 10€/appel, tableau de bord temps réel. Angle : \"Monétisez votre audience avec un service que vos abonnés utiliseront vraiment.\"",

            'avocat' => "Ce cabinet peut rejoindre le réseau de prestataires SOS-Expat et recevoir des appels de clients expatriés. Angle : \"Recevez des clients qualifiés, francophones, depuis 197 pays, sans prospection.\"",

            'immobilier' => "Les expatriés qui déménagent ont des questions juridiques. L'agence peut recommander SOS-Expat à ses clients. Angle : \"Complétez votre offre relocation avec un accès avocat immédiat pour vos clients.\"",

            'chambre_commerce' => "La CCI/chambre de commerce accompagne des entreprises à l'international. SOS-Expat complète leurs services. Angle : \"Un service juridique instantané pour vos entreprises membres.\"",

            'communaute_expat' => "Cette communauté rassemble des expatriés qui ont régulièrement des questions juridiques. Angle : \"Offrez à votre communauté un accès direct à un avocat francophone — gratuit pour vous, utile pour eux.\"",

            'coworking_coliving' => "Les coworkings/colivings accueillent des nomads et expats qui ont besoin de conseils (visa, fiscalité). Angle : \"Un QR code dans votre espace → vos membres accèdent à un avocat en 5 min.\"",

            'plateforme_nomad' => "Cette plateforme cible des digital nomads. SOS-Expat est LE service complémentaire (visa, fiscalité, droit local). Angle : \"Intégrez le service juridique que vos utilisateurs cherchent déjà.\"",

            default => null,
        };
    }

    private function parseResponse(string $text): ?array
    {
        $text = trim($text);

        // Remove markdown code blocks (```json ... ``` or ``` ... ```)
        $text = preg_replace('/^```(?:json)?\s*\n?/i', '', $text);
        $text = preg_replace('/\n?\s*```\s*$/', '', $text);
        $text = trim($text);

        // Attempt 1: Direct JSON decode
        $data = json_decode($text, true);

        // Attempt 2: Find JSON object in surrounding text
        if (!$data || !isset($data['subject'])) {
            // Match from first { to last } (handles nested braces in body)
            if (preg_match('/\{[\s\S]*\}/', $text, $match)) {
                $data = json_decode($match[0], true);
            }
        }

        // Attempt 3: Extract subject and body individually via regex
        if (!$data || !isset($data['subject'])) {
            $subject = null;
            $body = null;
            if (preg_match('/"subject"\s*:\s*"([^"]+)"/', $text, $m)) $subject = $m[1];
            if (preg_match('/"body"\s*:\s*"([\s\S]*?)(?:"\s*[,}])/', $text, $m)) $body = $m[1];

            if ($subject && $body) {
                $data = ['subject' => $subject, 'body' => str_replace('\n', "\n", $body)];
            }
        }

        if (!$data || !isset($data['subject']) || !isset($data['body'])) return null;

        // Clean the body: unescape \n, strip any HTML tags that slipped in
        $body = str_replace('\n', "\n", $data['body']);
        $body = strip_tags($body);

        return [
            'subject' => trim($data['subject']),
            'body'    => trim($body),
        ];
    }

    private function hasContactForm(Influenceur $inf): bool
    {
        $social = $inf->scraped_social;
        return is_array($social) && !empty($social['_contact_form_url']);
    }
}
