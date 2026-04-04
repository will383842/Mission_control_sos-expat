<?php

namespace App\Services\News;

use App\Models\RssFeedItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RelevanceFilterService
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu évalues si un article de presse est DIRECTEMENT utile à quelqu'un qui vit, travaille ou voyage HORS de son pays d'origine.

Score ÉLEVÉ (≥65) uniquement si l'article traite concrètement de :
- Visa, immigration, permis de résidence ou de travail dans un pays étranger
- Logement à l'étranger, location internationale, coût de la vie comparé
- Santé à l'étranger : assurance internationale, remboursements, accès aux soins
- Fiscalité internationale, double imposition, obligations fiscales des non-résidents
- Banque internationale, transferts d'argent, compte bancaire à l'étranger
- Emploi international, contrat local, télétravail depuis l'étranger
- Retraite à l'étranger, pension internationale
- Transport international : vols, aéroports, liaisons intercontinentales
- Alerte sécurité voyage (zone de guerre, catastrophe naturelle, épidémie) impactant les voyageurs
- Nouvelles réglementations douanières, frontalières ou d'entrée sur un territoire
- Droits spécifiques des étrangers ou non-résidents dans un pays

Score FAIBLE (<65) — NON PERTINENT si l'article traite de :
- Politique nationale, élections, gouvernement local sans impact sur les étrangers
- Manifestations, mouvements sociaux, grèves locales
- Sport, résultats sportifs, compétitions nationales
- People, célébrités, divertissement, culture pop locale
- Économie nationale (PIB, budget, inflation) sans lien avec les expatriés
- Justice locale, faits divers, criminalité sans alerte voyageur
- Racisme, discrimination, débats sociaux internes à un pays
- Urbanisme, immobilier local pour résidents nationaux

Réponds UNIQUEMENT en JSON valide (sans markdown):
{"score": 85, "relevant": true, "category": "visa", "reason": "Nouvelles règles visa Schengen pour les non-européens"}
PROMPT;

    /**
     * Evaluate the relevance of a feed item using Claude Haiku.
     * Updates the item in place (status, relevance_score, relevance_category, relevance_reason).
     */
    public function evaluate(RssFeedItem $item): void
    {
        $anthropicKey = config('services.anthropic.api_key') ?: config('services.claude.api_key');

        if (! $anthropicKey) {
            Log::warning('RelevanceFilterService: ANTHROPIC_API_KEY manquant');
            return;
        }

        $text    = $item->original_title ?? $item->title;
        $excerpt = mb_substr(strip_tags($item->original_excerpt ?? ''), 0, 500);

        $userPrompt = "Titre: {$text}\nRésumé: {$excerpt}";

        $result = $this->callClaude($userPrompt, $anthropicKey);

        if (! $result) {
            // En cas d'échec API, on laisse le status à 'pending'
            Log::warning("RelevanceFilterService: échec appel Claude pour item #{$item->id}");
            return;
        }

        $json = $this->extractJson($result);

        if (! $json) {
            Log::warning("RelevanceFilterService: JSON invalide pour item #{$item->id}", ['raw' => $result]);
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
    // CLAUDE API
    // ─────────────────────────────────────────

    private function callClaude(string $userPrompt, string $key): ?string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model'      => self::MODEL,
            'max_tokens' => 200,
            'system'     => self::SYSTEM_PROMPT,
            'messages'   => [['role' => 'user', 'content' => $userPrompt]],
        ]);

        if (! $response->successful()) {
            Log::error('RelevanceFilterService: Claude API error', ['status' => $response->status()]);
            return null;
        }

        return $response->json('content.0.text');
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
