<?php

namespace App\Console\Commands;

use App\Models\GeneratedArticle;
use App\Services\AI\ClaudeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-discover long-tail keywords from existing content.
 *
 * Analyzes published articles and uses AI to generate related
 * "People Also Ask" style queries, classified by search intent.
 * Checks for duplicates before inserting.
 *
 * Self-feeding loop: articles → new keywords → new articles → more keywords
 *
 * Usage:
 *   php artisan keywords:discover              # Discover from latest 20 articles
 *   php artisan keywords:discover --limit=50   # Discover from latest 50 articles
 *   php artisan keywords:discover --country=PT # Focus on Portugal articles
 */
class DiscoverKeywordsCommand extends Command
{
    protected $signature = 'keywords:discover
        {--limit=20 : Number of articles to analyze}
        {--country= : Focus on a specific country code}
        {--dry-run : Show discovered keywords without saving}';

    protected $description = 'Auto-discover long-tail keywords from existing articles using AI';

    private const INTENTS = ['informational', 'commercial_investigation', 'transactional', 'local', 'urgency'];

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $country = $this->option('country');
        $dryRun = $this->option('dry-run');

        $claude = app(ClaudeService::class);
        $apiKey = config('services.anthropic.api_key') ?: config('services.claude.api_key');

        if (!$apiKey) {
            $this->error('ANTHROPIC_API_KEY missing.');
            return 1;
        }

        $this->info("=== KEYWORD DISCOVERY ===");

        // Fetch published articles
        $query = GeneratedArticle::where('status', 'published')
            ->whereNull('parent_article_id')
            ->where('language', 'fr')
            ->orderByDesc('published_at');

        if ($country) {
            $query->where('country', strtoupper($country));
            $this->info("Focus country: {$country}");
        }

        $articles = $query->limit($limit)->get(['id', 'title', 'keywords_primary', 'content_type', 'country']);

        if ($articles->isEmpty()) {
            $this->warn("No published articles found.");
            return 0;
        }

        $this->info("Analyzing {$articles->count()} articles...");

        $totalDiscovered = 0;
        $totalDuplicates = 0;
        $totalInserted = 0;

        foreach ($articles as $article) {
            $this->line("  Analyzing: " . mb_substr($article->title, 0, 60) . "...");

            $prompt = $this->buildDiscoveryPrompt($article);

            try {
                $result = $claude->complete(
                    "Tu es un expert SEO specialise en recherche de mots-cles longue traine pour le marche des expatries et voyageurs internationaux.",
                    $prompt,
                    ['model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 2000, 'temperature' => 0.7]
                );

                $text = $result['content'] ?? $result['text'] ?? '';
                $keywords = $this->parseKeywords($text);

                foreach ($keywords as $kw) {
                    $totalDiscovered++;

                    // Check duplicate
                    $exists = DB::table('keyword_tracking')
                        ->where('keyword', $kw['keyword'])
                        ->where('language', 'fr')
                        ->exists();

                    if ($exists) {
                        $totalDuplicates++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("    [{$kw['intent']}] {$kw['keyword']}");
                        continue;
                    }

                    DB::table('keyword_tracking')->insert([
                        'keyword' => $kw['keyword'],
                        'type' => 'long_tail',
                        'search_intent' => $kw['intent'],
                        'language' => 'fr',
                        'country' => $article->country,
                        'category' => $kw['category'] ?? $article->content_type,
                        'search_volume_estimate' => 'medium',
                        'difficulty_estimate' => 'low',
                        'trend' => 'stable',
                        'articles_using_count' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $totalInserted++;
                }
            } catch (\Throwable $e) {
                $this->warn("    Error: " . $e->getMessage());
                Log::warning("keywords:discover error", ['article_id' => $article->id, 'error' => $e->getMessage()]);
            }

            usleep(500000); // 500ms between API calls
        }

        $this->newLine();
        $this->info("=== RESULTS ===");
        $this->info("Discovered: {$totalDiscovered}");
        $this->info("Duplicates: {$totalDuplicates}");
        $this->info("Inserted:   {$totalInserted}");

        if ($dryRun) {
            $this->comment("Dry run — nothing saved. Use without --dry-run to insert.");
        }

        return 0;
    }

    private function buildDiscoveryPrompt(GeneratedArticle $article): string
    {
        $countryCtx = $article->country ? "Pays concerne: {$article->country}" : "Pas de pays specifique";

        return <<<PROMPT
Analyse cet article et genere 10 requetes longue traine (4+ mots) que des expatries/voyageurs taperaient dans Google en rapport avec ce sujet.

Article: "{$article->title}"
Mots-cles: {$article->keywords_primary}
Type: {$article->content_type}
{$countryCtx}

Pour CHAQUE requete, classifie l'intention :
- informational = veut apprendre/comprendre
- commercial_investigation = veut comparer avant de choisir
- transactional = veut agir/acheter maintenant
- local = cherche un service dans un lieu precis
- urgency = a un probleme urgent maintenant

Retourne en JSON UNIQUEMENT (sans markdown) :
[
  {"keyword": "requete longue traine naturelle", "intent": "informational", "category": "visa"},
  {"keyword": "autre requete 4+ mots", "intent": "commercial_investigation", "category": "assurance"}
]

Regles :
- Requetes NATURELLES comme un vrai utilisateur les taperait
- 4+ mots minimum par requete
- Pas de mots-cles generiques (trop courts = trop de concurrence)
- Varier les intentions (pas que informational)
- Si pays specifique, inclure le nom du pays dans certaines requetes
- Categories possibles: visa, fiscalite, assurance, finance, logement, sante, education, juridique, expatriation, retraite, urgence, entrepreneuriat, transport, quotidien, telecom, patrimoine, succession, immobilier, relocation, digital_nomad
PROMPT;
    }

    private function parseKeywords(string $text): array
    {
        $text = trim(preg_replace(['/^```(?:json)?\s*/i', '/\s*```$/i'], '', trim($text)));
        $start = strpos($text, '[');
        $end = strrpos($text, ']');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        try {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) return [];

            return array_filter($decoded, function ($kw) {
                return isset($kw['keyword'])
                    && isset($kw['intent'])
                    && mb_strlen($kw['keyword']) >= 15
                    && in_array($kw['intent'], self::INTENTS, true);
            });
        } catch (\JsonException) {
            return [];
        }
    }
}
