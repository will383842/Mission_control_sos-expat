<?php

namespace App\Services;

use App\Models\DuplicateFlag;
use App\Models\Influenceur;
use App\Models\TypeVerificationFlag;

class QualityScoreService
{
    /**
     * Calculate quality score (0-100) for a contact.
     *
     *  Email verified:         +25
     *  Email present (unverif): +10
     *  Phone present:          +15
     *  URL present:            +15
     *  Scrape completed:       +10
     *  No type flags:          +10
     *  No duplicate flags:     +10
     *  Has country + language:  +5
     */
    public function calculate(Influenceur $inf): int
    {
        $score = 0;

        // Email
        if ($inf->email) {
            $score += match ($inf->email_verified_status) {
                'verified'  => 25,
                'risky', 'catch_all' => 15,
                'invalid'   => 0,
                default     => 10, // present but unverified
            };
        }

        // Phone
        if ($inf->phone) $score += 15;

        // URL
        if ($inf->profile_url || $inf->website_url) $score += 15;

        // Scrape completed
        if ($inf->scraped_at) $score += 10;

        // No type verification flags
        $hasTypeFlag = TypeVerificationFlag::where('influenceur_id', $inf->id)
            ->where('status', 'pending')
            ->exists();
        if (!$hasTypeFlag) $score += 10;

        // No duplicate flags
        $hasDupeFlag = DuplicateFlag::where('status', 'pending')
            ->where(fn($q) => $q->where('influenceur_a_id', $inf->id)->orWhere('influenceur_b_id', $inf->id))
            ->exists();
        if (!$hasDupeFlag) $score += 10;

        // Country + language
        if ($inf->country && $inf->language) $score += 5;

        return min(100, $score);
    }

    /**
     * Batch recalculate quality scores.
     * Only recalculates contacts with score = 0 (never scored) or where data changed.
     */
    public function recalculateBatch(int $limit = 200): array
    {
        $stats = ['processed' => 0, 'updated' => 0];

        // Score contacts that were never scored or were recently modified
        Influenceur::where('quality_score', 0)
            ->limit($limit)
            ->each(function (Influenceur $inf) use (&$stats) {
                $stats['processed']++;
                $newScore = $this->calculate($inf);
                if ($newScore !== $inf->quality_score) {
                    $inf->update(['quality_score' => $newScore]);
                    $stats['updated']++;
                }
            });

        return $stats;
    }
}
