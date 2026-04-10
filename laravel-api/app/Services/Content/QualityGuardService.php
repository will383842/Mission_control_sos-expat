<?php

namespace App\Services\Content;

use App\Models\GeneratedArticle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 4 — Post-generation quality guard.
 *
 * Validates articles AFTER generation against strict quality criteria:
 * - Anti-cannibalization (no duplicate angles)
 * - Maillage interne (internal linking density)
 * - E-E-A-T signals (sources, dates, expertise markers)
 * - Featured snippet optimization (first paragraph format)
 * - AEO readiness (ai_summary, concise answers)
 * - Speakable markup readiness
 *
 * Returns a QualityReport with pass/fail + actionable issues.
 */
class QualityGuardService
{
    // Minimum thresholds — keys must match content_type values used in generation jobs
    private const MIN_WORD_COUNT = [
        'qa' => 300,
        'qa_needs' => 300,
        'qr' => 300,            // legacy alias
        'news' => 600,
        'article' => 1200,
        'guide' => 2000,
        'guide_city' => 2000,
        'pillar' => 2000,
        'comparative' => 1500,
        'fiches_pays' => 2000,
        'fiches_expat' => 1500,
        'fiches_vacances' => 1200,
        'statistics' => 1500,
        'pain_point' => 500,
        'tutorial' => 1000,
        'testimonial' => 800,
        'outreach' => 500,
        'chatters' => 800,
        'influenceurs' => 800,
        'admin_groupes' => 800,
        'avocats' => 800,
        'expats_aidants' => 800,
        'affiliation' => 1000,
        'brand_content' => 800,
    ];

    private const MIN_INTERNAL_LINKS = 2;
    private const MIN_H2_COUNT = 3;
    private const MIN_FAQ_COUNT = 3;
    private const FEATURED_SNIPPET_MIN_WORDS = 35;
    private const FEATURED_SNIPPET_MAX_WORDS = 65;

    /**
     * Run full quality check on a generated article.
     *
     * @return array{passed: bool, score: int, issues: array, warnings: array}
     */
    public function check(GeneratedArticle $article): array
    {
        $issues = [];
        $warnings = [];
        $checks = [];

        $html = $article->content_html ?? '';
        $text = strip_tags($html);
        $lang = $article->language ?? 'fr';
        // CJK/Arabic/Hindi: str_word_count fails — use regex for Unicode word boundaries
        if (in_array($lang, ['zh', 'ar', 'hi'])) {
            $wordCount = max(preg_match_all('/\S+/u', $text), (int) (mb_strlen(trim($text)) / 2));
        } else {
            $wordCount = str_word_count($text);
        }
        $contentType = $article->content_type ?? 'article';

        // ── 1. WORD COUNT ──
        $minWords = self::MIN_WORD_COUNT[$contentType] ?? 800;
        $checks['word_count'] = $wordCount >= $minWords;
        if (!$checks['word_count']) {
            $issues[] = "Contenu trop court: {$wordCount} mots (minimum {$minWords} pour {$contentType})";
        }

        // ── 2. H2 STRUCTURE ──
        $h2Count = preg_match_all('/<h2[^>]*>/i', $html);
        $checks['h2_structure'] = $h2Count >= self::MIN_H2_COUNT;
        if (!$checks['h2_structure']) {
            $issues[] = "Structure H2 insuffisante: {$h2Count} (minimum " . self::MIN_H2_COUNT . ")";
        }

        // ── 3. FEATURED SNIPPET (first paragraph) ──
        $checks['featured_snippet'] = $this->checkFeaturedSnippet($html);
        if (!$checks['featured_snippet']) {
            $warnings[] = "Featured snippet non optimal: le 1er paragraphe doit faire 35-65 mots et repondre directement a l'intention";
        }

        // ── 4. INTERNAL LINKS ──
        $internalLinkCount = preg_match_all('/href=["\']https?:\/\/(sos-expat\.com|blog\.life-expat\.com)/i', $html);
        $checks['internal_links'] = $internalLinkCount >= self::MIN_INTERNAL_LINKS;
        if (!$checks['internal_links']) {
            $warnings[] = "Maillage interne faible: {$internalLinkCount} liens (recommande " . self::MIN_INTERNAL_LINKS . "+)";
        }

        // ── 5. FAQ COUNT ──
        $faqCount = $article->faqs()->count();
        $checks['faq_count'] = $faqCount >= self::MIN_FAQ_COUNT;
        if (!$checks['faq_count']) {
            $warnings[] = "FAQ insuffisantes: {$faqCount} (recommande " . self::MIN_FAQ_COUNT . "+)";
        }

        // ── 6. E-E-A-T SIGNALS ──
        $eeatResult = $this->checkEEAT($html, $text, $article);
        $checks['eeat'] = $eeatResult['passed'];
        if (!$eeatResult['passed']) {
            foreach ($eeatResult['issues'] as $issue) {
                $warnings[] = "E-E-A-T: {$issue}";
            }
        }

        // ── 7. ANTI-CANNIBALIZATION ──
        $cannibResult = $this->checkCannibalization($article);
        $checks['anti_cannib'] = $cannibResult['passed'];
        if (!$cannibResult['passed']) {
            $issues[] = "Cannibalisation detectee: {$cannibResult['reason']}";
        }

        // ── 8. AEO READINESS ──
        $checks['aeo'] = !empty($article->ai_summary) && mb_strlen($article->ai_summary) <= 160;
        if (!$checks['aeo']) {
            $warnings[] = "AEO: ai_summary manquant ou trop long (max 160 caracteres)";
        }

        // ── 9. META TAGS ──
        $metaTitleLen = mb_strlen($article->meta_title ?? '');
        $metaDescLen = mb_strlen($article->meta_description ?? '');
        $checks['meta_title'] = $metaTitleLen >= 30 && $metaTitleLen <= 65;
        $checks['meta_desc'] = $metaDescLen >= 120 && $metaDescLen <= 160;
        if (!$checks['meta_title']) {
            $warnings[] = "Meta title: {$metaTitleLen} chars (optimal 30-65)";
        }
        if (!$checks['meta_desc']) {
            $warnings[] = "Meta description: {$metaDescLen} chars (optimal 120-160)";
        }

        // ── 9b. META DESCRIPTION ACTION VERB ──
        $metaDesc = mb_strtolower($article->meta_description ?? '');
        $hasActionVerb = (bool) preg_match('/\b(découvrez|apprenez|trouvez|consultez|comparez|explorez|discover|learn|find|explore|compare|check|read|get|descubra|aprenda|encuentre|entdecken|erfahren|finden)\b/iu', $metaDesc);
        if (!$hasActionVerb && $metaDescLen > 0) {
            $warnings[] = "Meta description: pas de verbe d'action (Découvrez, Apprenez, Trouvez...)";
        }

        // ── 9c. YEAR IN TITLE ──
        $currentYear = date('Y');
        $titleHasYear = str_contains($article->title ?? '', $currentYear) || str_contains($article->meta_title ?? '', $currentYear);
        if (!$titleHasYear && !in_array($article->content_type, ['qa', 'qa_needs', 'testimonial'])) {
            $warnings[] = "Titre sans année {$currentYear} (signal fraîcheur Google)";
        }

        // ── 10. AI-SOUNDING CONTENT DETECTION ──
        $textLower = mb_strtolower($text);
        $aiPatterns = 0;
        foreach ([
            'il est important de', 'il convient de', 'il est essentiel',
            'il est crucial', 'il est recommandé', 'il est recommande',
            'dans cet article', "n'hésitez pas", "n'hesitez pas",
            'en conclusion,', 'cela signifie que', 'il est à noter',
            'it is important to', 'it is essential', 'it is crucial',
            'in this article', 'in conclusion,',
        ] as $pattern) {
            $aiPatterns += mb_substr_count($textLower, $pattern);
        }
        $checks['natural_writing'] = $aiPatterns < 5;
        if (!$checks['natural_writing']) {
            $issues[] = "Contenu IA détecté: {$aiPatterns} formules robotiques ('il est important de', 'il convient de', etc.)";
        }

        // ── 11b. REPETITIVE YEAR ──
        $yearCount = mb_substr_count($textLower, strtolower(date('Y')));
        if ($yearCount > 10 && $wordCount < 3000) {
            $warnings[] = "Année répétée {$yearCount} fois — semble artificiel";
        }

        // ── 12. BRAND COMPLIANCE ──
        $brandResult = $this->checkBrandCompliance($text);
        $checks['brand'] = $brandResult['passed'];
        if (!$brandResult['passed']) {
            foreach ($brandResult['issues'] as $brandIssue) {
                $issues[] = "Brand compliance: {$brandIssue}";
            }
        }

        // Calculate score
        $passedChecks = count(array_filter($checks));
        $totalChecks = count($checks);
        $score = (int) round(($passedChecks / $totalChecks) * 100);

        // Pass if no critical issues and score >= 60
        $passed = empty($issues) && $score >= 60;

        return [
            'passed' => $passed,
            'score' => $score,
            'checks' => $checks,
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    private function checkFeaturedSnippet(string $html): bool
    {
        // Extract first <p> tag content
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $match)) {
            $firstP = strip_tags($match[1]);
            $words = str_word_count($firstP);
            return $words >= self::FEATURED_SNIPPET_MIN_WORDS && $words <= self::FEATURED_SNIPPET_MAX_WORDS;
        }
        return false;
    }

    private function checkEEAT(string $html, string $text, GeneratedArticle $article): array
    {
        $issues = [];

        // Experience: check for concrete examples, case studies
        $hasExamples = preg_match('/exemple|temoignage|cas concret|experience|vecu/iu', $text);
        if (!$hasExamples) {
            $issues[] = "Manque de temoignages/exemples concrets (Experience)";
        }

        // Expertise: check for data, numbers, year mentions
        $hasData = preg_match('/\d{4}|\d+[\s]?(%|EUR|USD|\$|€)/u', $text);
        if (!$hasData) {
            $issues[] = "Manque de donnees chiffrees/annee (Expertise)";
        }

        // Authoritativeness: check for source citations
        $hasSources = preg_match('/source|selon|d\'apres|ministere|gouvernement|officiel/iu', $text);
        $sourceCount = $article->sources()->count();
        if (!$hasSources && $sourceCount < 1) {
            $issues[] = "Manque de sources officielles citees (Authoritativeness)";
        }

        // Trustworthiness: check date mention
        $hasDate = preg_match('/20[2-3]\d|mise a jour|derniere modification/iu', $text);
        if (!$hasDate) {
            $issues[] = "Manque de date de mise a jour (Trustworthiness)";
        }

        return [
            'passed' => count($issues) <= 1, // Allow 1 minor gap
            'issues' => $issues,
        ];
    }

    private function checkCannibalization(GeneratedArticle $article): array
    {
        if (!$article->country || !$article->language) {
            return ['passed' => true, 'reason' => null];
        }

        // Check for same keyword + same country + same content_type
        $similar = GeneratedArticle::where('country', $article->country)
            ->where('language', $article->language)
            ->where('content_type', $article->content_type)
            ->where('id', '!=', $article->id)
            ->whereNull('parent_article_id')
            ->whereIn('status', ['review', 'published'])
            ->select('id', 'title', 'keywords_primary')
            ->get();

        foreach ($similar as $existing) {
            // Compare primary keywords
            if ($article->keywords_primary && $existing->keywords_primary) {
                $articleKw = mb_strtolower($article->keywords_primary);
                $existingKw = mb_strtolower($existing->keywords_primary);

                if ($articleKw === $existingKw) {
                    return [
                        'passed' => false,
                        'reason' => "Meme mot-cle principal \"{$articleKw}\" que article #{$existing->id} \"{$existing->title}\"",
                    ];
                }
            }
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * @return array{passed: bool, issues: string[]}
     */
    private function checkBrandCompliance(string $text): array
    {
        $issues = [];

        // Must NOT say "SOS Expat" without hyphen (except in URLs)
        if (preg_match('/sos\s+expat(?!\.\w)/iu', $text) && !str_contains(mb_strtolower($text), 'sos-expat')) {
            $issues[] = 'Mention "SOS Expat" sans tiret detectee (utiliser "SOS-Expat")';
        }

        // Must NOT say it's free
        if (preg_match('/sos[- ]?expat.{0,30}(gratuit|free|gratis)/iu', $text)) {
            $issues[] = 'SOS-Expat presente comme gratuit';
        }

        // Must NOT say it provides legal advice directly
        if (preg_match('/sos[- ]?expat.{0,30}(fournit|donne|offre).{0,20}(conseil|avis) juridique/iu', $text)) {
            $issues[] = 'SOS-Expat presente comme fournissant des conseils juridiques';
        }

        // Must NOT use forbidden affiliate terminology
        if (preg_match('/\bMLM\b/i', $text)) {
            $issues[] = 'Terme interdit "MLM" detecte — utiliser "programme d\'affiliation"';
        }

        if (preg_match('/\b(recruter|recruit)\b/iu', $text)) {
            $issues[] = 'Terme interdit "recruter/recruit" detecte — utiliser "parrainer" ou "inviter"';
        }

        if (preg_match('/\b(salarié|salarie)\b/iu', $text)) {
            $issues[] = 'Terme interdit "salarié" detecte — utiliser "affilié" ou "partenaire"';
        }

        if (preg_match('/\brecrutement\b/iu', $text)) {
            $issues[] = 'Terme interdit "recrutement" detecte — utiliser "parrainage" ou "affiliation"';
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
        ];
    }
}
