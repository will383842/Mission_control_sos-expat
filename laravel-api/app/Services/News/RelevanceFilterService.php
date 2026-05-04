<?php

namespace App\Services\News;

use App\Models\RssFeedItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RelevanceFilterService
{
    private const MODEL = 'gpt-4o-mini';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu évalues si un article de presse est utile à quelqu'un qui vit, travaille ou voyage HORS de son pays d'origine. Sois GÉNÉREUX dès qu'un sujet a un IMPACT PLAUSIBLE sur expatriés ou voyageurs internationaux, même sans mention explicite.

────────────────────────────────────────
ÉCHELLE DE SCORE (calibre toi sur ces ancres)
────────────────────────────────────────
85-100 — IMPACT DIRECT et concret pour expatriés/voyageurs
  Ex: "Nouvelles règles visa Schengen 2026", "La Thaïlande lance le visa retraité", "Double imposition: nouvelle convention France-USA"
70-84 — IMPACT FORT mais sectoriel ou indirect
  Ex: "Pénurie de carburant menace les vols d'été" (transport international), "L'Iran ferme partiellement le détroit d'Hormuz" (alerte voyage), "Cinq morts dans une croisière, l'OMS enquête" (santé voyageurs)
55-69 — SUJET PERTINENT par projection: voyageurs ou expats du pays sont concernés
  Ex: "Les compagnies aériennes indiennes au bord de la cessation" (impact vols), "Régularisation 500 000 sans-papiers en Espagne", "Inflation au Cambodge en 2026", "Manifestations bloquent l'aéroport de Bangkok"
40-54 — INTÉRÊT GÉNÉRAL mais peu actionnable pour un expat
  Ex: "Élection présidentielle au Brésil" (sauf si visa/règles changent)
0-39 — HORS SUJET pour cette audience
  Ex: sport national, people locale, faits divers locaux sans alerte voyage

────────────────────────────────────────
THÈMES PERTINENTS (typiquement 55-100)
────────────────────────────────────────
- Visa, immigration, permis de résidence ou de travail à l'étranger
- Logement à l'étranger, coût de la vie, location internationale
- Santé internationale: assurance, accès aux soins, épidémies, alertes sanitaires voyage
- Fiscalité internationale, double imposition, comptes étrangers, obligations non-résidents
- Banque internationale, transferts d'argent, change, fluctuation monétaire d'un pays-cible
- Emploi international, contrat local, télétravail depuis l'étranger, marchés du travail par pays
- Retraite à l'étranger, pension internationale
- Transport international: vols (annulations, pénuries carburant, grèves aéroports), trains transfrontaliers, croisières
- ALERTE VOYAGE: guerre, crise géopolitique d'un pays-cible, catastrophe naturelle, épidémie, attentats, manifestations bloquantes
- Réglementations douanières, frontalières, contrôles d'entrée, pass sanitaire, ETIAS, EES
- Droits des étrangers ou non-résidents dans un pays (logement, travail, justice)
- Économie d'un pays-cible (inflation, change, prix immobilier) IMPACTANT le pouvoir d'achat des expats sur place
- Sécurité d'un pays touristique ou d'expatriation (criminalité, arnaques, zones à éviter)

────────────────────────────────────────
THÈMES NON PERTINENTS (typiquement 0-40)
────────────────────────────────────────
- Politique purement nationale, élections, débats internes sans impact sur étrangers
- Sport, résultats sportifs, compétitions
- People, célébrités, divertissement, culture pop locale
- Faits divers locaux sans alerte voyageur
- Urbanisme purement local pour résidents nationaux
- Économie macro abstraite (PIB national) sans lien expats / pouvoir d'achat sur place

RÈGLE CLÉ: si tu hésites entre "pertinent par projection" et "non pertinent", choisis 60. La transversalité (transport, santé, sécurité, économie d'un pays-cible) compte.

Réponds UNIQUEMENT en JSON valide (sans markdown):
{"score": 85, "relevant": true, "category": "visa", "reason": "Nouvelles règles visa Schengen pour les non-européens"}
PROMPT;

    /**
     * Evaluate the relevance of a feed item using GPT-4o-mini (cost-optimized).
     * Updates the item in place (status, relevance_score, relevance_category, relevance_reason).
     */
    public function evaluate(RssFeedItem $item): void
    {
        $openaiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY');

        if (! $openaiKey) {
            Log::warning('RelevanceFilterService: OPENAI_API_KEY manquant');
            return;
        }

        $text    = $item->original_title ?? $item->title;
        $excerpt = mb_substr(strip_tags($item->original_excerpt ?? ''), 0, 500);

        $userPrompt = "Titre: {$text}\nRésumé: {$excerpt}";

        $result = $this->callOpenAi($userPrompt, $openaiKey);

        if (! $result) {
            Log::warning("RelevanceFilterService: échec appel OpenAI pour item #{$item->id}");
            $item->update(['error_message' => 'Relevance API call failed — ' . now()->toDateTimeString()]);
            return;
        }

        $json = $this->extractJson($result);

        if (! $json) {
            Log::warning("RelevanceFilterService: JSON invalide pour item #{$item->id}", ['raw' => mb_substr($result, 0, 500)]);
            $item->update(['error_message' => 'Relevance JSON parse failed — ' . now()->toDateTimeString()]);
            return;
        }

        $score    = (int) ($json['score'] ?? 0);
        $relevant = (bool) ($json['relevant'] ?? false);
        $category = $json['category'] ?? null;
        $reason   = isset($json['reason']) ? mb_substr($json['reason'], 0, 500) : null;

        $threshold = $item->feed ? $item->feed->relevance_threshold : 65;

        if ($relevant && $score >= $threshold) {
            $item->update([
                'status'              => 'pending',
                'relevance_score'     => $score,
                'relevance_category'  => $category,
                'relevance_reason'    => $reason,
            ]);
        } else {
            $item->update([
                'status'              => 'irrelevant',
                'relevance_score'     => $score,
                'relevance_category'  => $category,
                'relevance_reason'    => $reason,
            ]);
        }
    }

    // ─────────────────────────────────────────
    // OPENAI API (GPT-4o-mini — cost-optimized)
    // ─────────────────────────────────────────

    private function callOpenAi(string $userPrompt, string $key): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$key}",
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model'           => self::MODEL,
                'max_tokens'      => 200,
                'temperature'     => 0.3,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
            ]);

            if (! $response->successful()) {
                Log::error('RelevanceFilterService: OpenAI API error', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 500),
                ]);
                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('RelevanceFilterService: timeout/connexion', ['error' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('RelevanceFilterService: exception inattendue', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractJson(string $text): ?array
    {
        $text  = trim(preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/i'], '', trim($text)));
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) return null;

        try {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }
}
