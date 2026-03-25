<?php

namespace App\Services;

use App\Models\Influenceur;
use App\Models\TypeVerificationFlag;
use Illuminate\Support\Facades\Log;

class TypeVerificationService
{
    // Government email patterns → should only be on 'consulat' type
    private const GOV_PATTERNS = [
        '.gouv.fr', '.gov.', '.gov', '.gob.', '.gobierno.',
        'diplomatie.gouv.fr', 'ambafrance', 'consulat',
    ];

    // Known organization names that indicate a specific type
    private const ORG_NAME_RULES = [
        // These names should be in 'association' type
        ['names' => ['ufe', 'union des français', 'accueil', 'alliance française', 'adfe', 'français du monde'],
         'expected_type' => 'association'],
        // These should be in 'consulat'
        ['names' => ['ambassade', 'consulat', 'embassy', 'consulate'],
         'expected_type' => 'consulat'],
        // These should be in 'ecole'
        ['names' => ['lycée', 'école', 'collège', 'university', 'université', 'school', 'aefe'],
         'expected_type' => 'ecole'],
        // These should be in 'chambre_commerce'
        ['names' => ['cci', 'chambre de commerce', 'ccef', 'chamber of commerce', 'business council'],
         'expected_type' => 'chambre_commerce'],
    ];

    /**
     * Detect misclassified contacts.
     * Only checks contacts not yet flagged and with quality_score = 0 (not yet verified).
     */
    public function detectMisclassified(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'flagged' => 0];

        $contacts = Influenceur::where('quality_score', 0)
            ->whereNotIn('id', TypeVerificationFlag::pending()->pluck('influenceur_id'))
            ->limit($limit)
            ->get();

        foreach ($contacts as $inf) {
            $stats['checked']++;

            // Check 1: Government email on non-government type
            if ($inf->email && $inf->contact_type !== 'consulat') {
                $emailLower = strtolower($inf->email);
                foreach (self::GOV_PATTERNS as $pattern) {
                    if (str_contains($emailLower, $pattern)) {
                        $this->flag($inf, 'gov_email_on_non_gov', 'consulat', [
                            'email'   => $inf->email,
                            'pattern' => $pattern,
                        ]);
                        $stats['flagged']++;
                        break;
                    }
                }
            }

            // Check 2: Name suggests wrong type
            $nameLower = mb_strtolower($inf->name);
            foreach (self::ORG_NAME_RULES as $rule) {
                if ($inf->contact_type === $rule['expected_type']) continue;
                foreach ($rule['names'] as $keyword) {
                    if (str_contains($nameLower, $keyword)) {
                        $this->flag($inf, 'name_mismatch', $rule['expected_type'], [
                            'name'    => $inf->name,
                            'keyword' => $keyword,
                        ]);
                        $stats['flagged']++;
                        break 2;
                    }
                }
            }

            // Check 3: URL is a blocked directory (should have been filtered)
            if ($inf->profile_url && BlockedDomainService::isDirectoryUrl($inf->profile_url)) {
                $this->flag($inf, 'directory_url', null, [
                    'url'    => $inf->profile_url,
                    'domain' => BlockedDomainService::getMatchingDomain($inf->profile_url),
                ]);
                $stats['flagged']++;
            }
        }

        return $stats;
    }

    private function flag(Influenceur $inf, string $reason, ?string $suggestedType, array $details): void
    {
        TypeVerificationFlag::firstOrCreate(
            ['influenceur_id' => $inf->id, 'reason' => $reason],
            [
                'current_type'   => $inf->contact_type,
                'suggested_type' => $suggestedType,
                'details'        => $details,
                'status'         => 'pending',
            ]
        );
    }
}
