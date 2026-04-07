<?php

namespace App\Jobs;

use App\Models\StatisticsDataset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResearchStatisticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        private string $theme,
        private string $countryCode,
        private string $countryName,
        private string $language = 'en',
    ) {}

    public function handle(): void
    {
        $themeLabels = [
            'expatries'      => 'expatriates',
            'voyageurs'      => 'travelers',
            'nomades'        => 'digital nomads',
            'etudiants'      => 'international students',
            'investisseurs'  => 'foreign investors',
        ];

        $themeLabel = $themeLabels[$this->theme] ?? $this->theme;
        $apiKey = config('services.perplexity.api_key');

        if (empty($apiKey)) {
            Log::warning('ResearchStatisticsJob: Perplexity not configured');
            return;
        }

        $systemPrompt = "You are a statistics research assistant. Your role is to find VERIFIED statistical data.\n\n"
            . "For EACH statistic found, use EXACTLY this format:\n\n"
            . "STAT: [exact number or percentage]\n"
            . "LABEL: [what this statistic measures]\n"
            . "YEAR: [year of the data]\n"
            . "SOURCE_NAME: [organization name]\n"
            . "SOURCE_URL: [URL]\n"
            . "CONTEXT: [one sentence]\n\n"
            . "Only include statistics with real, verifiable sources. Never invent numbers.";

        $query = "Find the latest verified statistics about {$themeLabel} in {$this->countryName}. "
            . "Include: total numbers, growth trends, demographics, top nationalities, economic impact. "
            . "Focus on data from: UN, OECD, World Bank, Eurostat, national statistics offices. "
            . "Return at least 8-10 distinct statistics.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(90)->post('https://api.perplexity.ai/chat/completions', [
                'model'    => config('services.perplexity.model', 'sonar'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $query],
                ],
                'max_tokens'       => 4000,
                'temperature'      => 0.3,
                'return_citations' => true,
            ]);

            if (!$response->successful()) {
                Log::warning('ResearchStatisticsJob: Perplexity failed', [
                    'status' => $response->status(),
                    'country' => $this->countryCode,
                ]);
                return;
            }

            $data = $response->json();
            $text = $data['choices'][0]['message']['content'] ?? '';
            $citations = $data['citations'] ?? [];

            $stats = $this->parseStats($text);
            $sources = $this->extractSources($stats, $citations);

            try {
                StatisticsDataset::updateOrCreate(
                    [
                        'topic'        => $this->theme . '_' . $this->countryCode,
                        'country_code' => $this->countryCode,
                        'language'     => $this->language,
                    ],
                    [
                        'theme'              => $this->theme,
                        'country_name'       => $this->countryName,
                        'title'              => "Statistics: " . ucfirst($themeLabel) . " in " . $this->countryName,
                        'stats'              => $stats,
                        'sources'            => $sources,
                        'source_count'       => count($sources),
                        'status'             => 'draft',
                        'last_researched_at' => now(),
                    ]
                );
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'stats_topic_country_lang_unique')) {
                    Log::info('ResearchStatisticsJob: dataset already exists, skipping', [
                        'theme' => $this->theme, 'country' => $this->countryCode,
                    ]);
                    return;
                }
                throw $e;
            }

            Log::info('ResearchStatisticsJob: OK', [
                'theme'   => $this->theme,
                'country' => $this->countryCode,
                'stats'   => count($stats),
            ]);

        } catch (\Throwable $e) {
            Log::error('ResearchStatisticsJob failed', [
                'theme'   => $this->theme,
                'country' => $this->countryCode,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function parseStats(string $text): array
    {
        $stats = [];
        $blocks = preg_split('/\n\s*\n/', $text);

        foreach ($blocks as $block) {
            if (strlen($block) > 5000) continue;

            $stat = [];
            if (preg_match('/STAT:\s*(.+)/i', $block, $m))        $stat['value'] = mb_substr(trim($m[1]), 0, 500);
            if (preg_match('/LABEL:\s*(.+)/i', $block, $m))       $stat['label'] = mb_substr(trim($m[1]), 0, 500);
            if (preg_match('/YEAR:\s*(\d{4})/i', $block, $m))     $stat['year'] = $m[1];
            if (preg_match('/SOURCE_NAME:\s*(.+)/i', $block, $m)) $stat['source_name'] = mb_substr(trim($m[1]), 0, 300);
            if (preg_match('/SOURCE_URL:\s*(.+)/i', $block, $m)) {
                $url = trim($m[1]);
                $stat['source_url'] = filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
            }
            if (preg_match('/CONTEXT:\s*(.+)/i', $block, $m))     $stat['context'] = mb_substr(trim($m[1]), 0, 500);

            if (!empty($stat['value']) && !empty($stat['label'])) {
                $stats[] = $stat;
            }
        }

        if (empty($stats)) {
            Log::warning('ResearchStatisticsJob::parseStats: no stats found', [
                'text_length' => strlen($text),
                'preview'     => substr($text, 0, 300),
            ]);
        }

        return $stats;
    }

    private function extractSources(array $stats, array $citations): array
    {
        $sources = [];
        $seen = [];

        foreach ($stats as $stat) {
            $name = $stat['source_name'] ?? null;
            $url = $stat['source_url'] ?? null;
            if ($name && !in_array($name, $seen)) {
                $sources[] = ['name' => $name, 'url' => $url, 'accessed_at' => now()->toDateString()];
                $seen[] = $name;
            }
        }

        foreach ($citations as $url) {
            $domain = parse_url($url, PHP_URL_HOST) ?? $url;
            if (!in_array($domain, $seen)) {
                $sources[] = ['name' => $domain, 'url' => $url, 'accessed_at' => now()->toDateString()];
                $seen[] = $domain;
            }
        }

        return $sources;
    }
}
