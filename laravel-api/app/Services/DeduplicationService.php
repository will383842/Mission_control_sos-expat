<?php

namespace App\Services;

use App\Models\DuplicateFlag;
use App\Models\Influenceur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeduplicationService
{
    /**
     * Detect cross-type duplicates: same URL or email in different contact types.
     * This is the CRITICAL check — a contact must exist in only ONE type.
     */
    public function findCrossTypeDuplicates(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'flagged' => 0];

        // By email: same email, different type
        $emailDupes = DB::select("
            SELECT a.id as a_id, b.id as b_id, a.email, a.contact_type as a_type, b.contact_type as b_type
            FROM influenceurs a
            JOIN influenceurs b ON a.email = b.email AND a.id < b.id AND a.contact_type != b.contact_type
            WHERE a.email IS NOT NULL AND a.deleted_at IS NULL AND b.deleted_at IS NULL
            LIMIT ?
        ", [$limit]);

        foreach ($emailDupes as $dupe) {
            $this->createFlag($dupe->a_id, $dupe->b_id, 'cross_type', 90);
            $stats['flagged']++;
        }

        // By URL domain: same domain, different type
        $urlDupes = DB::select("
            SELECT a.id as a_id, b.id as b_id, a.profile_url_domain, a.contact_type as a_type, b.contact_type as b_type
            FROM influenceurs a
            JOIN influenceurs b ON a.profile_url_domain = b.profile_url_domain AND a.id < b.id AND a.contact_type != b.contact_type
            WHERE a.profile_url_domain IS NOT NULL AND a.deleted_at IS NULL AND b.deleted_at IS NULL
            LIMIT ?
        ", [$limit]);

        foreach ($urlDupes as $dupe) {
            $this->createFlag($dupe->a_id, $dupe->b_id, 'cross_type', 80);
            $stats['flagged']++;
        }

        $stats['checked'] = count($emailDupes) + count($urlDupes);
        return $stats;
    }

    /**
     * Detect within-type duplicates: same URL, email, or very similar name+country.
     */
    public function findWithinTypeDuplicates(int $limit = 200): array
    {
        $stats = ['checked' => 0, 'flagged' => 0];

        // Same email within same type (shouldn't happen but check)
        $emailDupes = DB::select("
            SELECT a.id as a_id, b.id as b_id
            FROM influenceurs a
            JOIN influenceurs b ON a.email = b.email AND a.id < b.id AND a.contact_type = b.contact_type
            WHERE a.email IS NOT NULL AND a.deleted_at IS NULL AND b.deleted_at IS NULL
            LIMIT ?
        ", [$limit]);

        foreach ($emailDupes as $dupe) {
            $this->createFlag($dupe->a_id, $dupe->b_id, 'same_email', 95);
            $stats['flagged']++;
        }

        // Same URL domain within same type
        $urlDupes = DB::select("
            SELECT a.id as a_id, b.id as b_id
            FROM influenceurs a
            JOIN influenceurs b ON a.profile_url_domain = b.profile_url_domain AND a.id < b.id AND a.contact_type = b.contact_type
            WHERE a.profile_url_domain IS NOT NULL AND a.deleted_at IS NULL AND b.deleted_at IS NULL
            LIMIT ?
        ", [$limit]);

        foreach ($urlDupes as $dupe) {
            $this->createFlag($dupe->a_id, $dupe->b_id, 'same_url', 85);
            $stats['flagged']++;
        }

        // Same name + country (case insensitive)
        $nameDupes = DB::select("
            SELECT a.id as a_id, b.id as b_id
            FROM influenceurs a
            JOIN influenceurs b ON LOWER(a.name) = LOWER(b.name) AND a.country = b.country AND a.id < b.id
            WHERE a.deleted_at IS NULL AND b.deleted_at IS NULL
            LIMIT ?
        ", [$limit]);

        foreach ($nameDupes as $dupe) {
            $this->createFlag($dupe->a_id, $dupe->b_id, 'same_name_country', 75);
            $stats['flagged']++;
        }

        $stats['checked'] = count($emailDupes) + count($urlDupes) + count($nameDupes);
        return $stats;
    }

    /**
     * Auto-merge: keep the one with more data, merge missing fields from the other.
     */
    public function autoMerge(int $keepId, int $mergeId): Influenceur
    {
        $keep = Influenceur::findOrFail($keepId);
        $merge = Influenceur::findOrFail($mergeId);

        // Fill empty fields from merge into keep
        $fieldsToMerge = ['email', 'phone', 'website_url', 'company', 'position', 'notes'];
        foreach ($fieldsToMerge as $field) {
            if (empty($keep->{$field}) && !empty($merge->{$field})) {
                $keep->{$field} = $merge->{$field};
            }
        }

        // Merge scraped data
        if (empty($keep->scraped_emails) && !empty($merge->scraped_emails)) {
            $keep->scraped_emails = $merge->scraped_emails;
        }
        if (empty($keep->scraped_social) && !empty($merge->scraped_social)) {
            $keep->scraped_social = $merge->scraped_social;
        }

        $keep->save();

        // Move contacts (outreach history) from merge to keep
        DB::table('contacts')->where('influenceur_id', $mergeId)->update(['influenceur_id' => $keepId]);

        // Delete the merged contact
        $merge->forceDelete();

        Log::info('Dedup: merged contact', ['keep' => $keepId, 'merged' => $mergeId]);

        return $keep;
    }

    private function createFlag(int $aId, int $bId, string $matchType, int $confidence): void
    {
        DuplicateFlag::firstOrCreate(
            ['influenceur_a_id' => min($aId, $bId), 'influenceur_b_id' => max($aId, $bId)],
            ['match_type' => $matchType, 'confidence' => $confidence, 'status' => 'pending']
        );
    }
}
