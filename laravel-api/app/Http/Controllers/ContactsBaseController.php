<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Unified contacts base — merges all scraped contact tables into a single
 * tiered view with deduplication support.
 *
 * TIERS:
 *   1 = Email vérifié (smtp_valid OR email_verified)
 *   2 = Email présent (non vérifié)
 *   3 = Formulaire / site web uniquement (pas d'email)
 *   4 = Aucun moyen de contact
 */
class ContactsBaseController extends Controller
{
    // ─── STATS ────────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $data = Cache::remember('contacts-base-stats', 180, function () {
            // influenceurs
            $inf = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND email_verified_status = 'verified') as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND (email_verified_status IS NULL OR email_verified_status != 'verified')) as tier2,
                    COUNT(*) FILTER (WHERE (email IS NULL OR email = '') AND website_url IS NOT NULL AND website_url != '') as tier3,
                    COUNT(*) FILTER (WHERE (email IS NULL OR email = '') AND (website_url IS NULL OR website_url = '')) as tier4
                FROM influenceurs WHERE deleted_at IS NULL
            ");

            // lawyers
            $law = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND email_verified = true) as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND (email_verified IS NULL OR email_verified = false)) as tier2,
                    COUNT(*) FILTER (WHERE (email IS NULL OR email = '') AND website IS NOT NULL AND website != '') as tier3,
                    COUNT(*) FILTER (WHERE (email IS NULL OR email = '') AND (website IS NULL OR website = '')) as tier4
                FROM lawyers
            ");

            // press_contacts
            $press = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND email_smtp_valid = true) as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '' AND (email_smtp_valid IS NULL OR email_smtp_valid = false)) as tier2,
                    0 as tier3,
                    COUNT(*) FILTER (WHERE email IS NULL OR email = '') as tier4
                FROM press_contacts
            ");

            // content_businesses
            $biz = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    0 as tier1,
                    COUNT(*) FILTER (WHERE contact_email IS NOT NULL AND contact_email != '') as tier2,
                    COUNT(*) FILTER (WHERE (contact_email IS NULL OR contact_email = '') AND website IS NOT NULL AND website != '') as tier3,
                    COUNT(*) FILTER (WHERE (contact_email IS NULL OR contact_email = '') AND (website IS NULL OR website = '')) as tier4
                FROM content_businesses
            ");

            // content_contacts
            $cc = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    0 as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '') as tier2,
                    0 as tier3,
                    COUNT(*) FILTER (WHERE email IS NULL OR email = '') as tier4
                FROM content_contacts
            ");

            // content_contacts
            $cc = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    0 as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '') as tier2,
                    0 as tier3,
                    COUNT(*) FILTER (WHERE email IS NULL OR email = '') as tier4
                FROM content_contacts
            ");

            // country_directory
            $cd = DB::selectOne("
                SELECT
                    COUNT(*) as total,
                    0 as tier1,
                    COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '') as tier2,
                    0 as tier3,
                    COUNT(*) FILTER (WHERE email IS NULL OR email = '') as tier4
                FROM country_directory
            ");

            // duplicates
            $dupInf = DB::selectOne("
                SELECT COUNT(*) as n FROM (
                    SELECT email FROM influenceurs
                    WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL
                    GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ) t
            ");
            $dupLaw = DB::selectOne("
                SELECT COUNT(*) as n FROM (
                    SELECT email FROM lawyers
                    WHERE email IS NOT NULL AND email != ''
                    GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ) t
            ");
            $dupPress = DB::selectOne("
                SELECT COUNT(*) as n FROM (
                    SELECT email FROM press_contacts
                    WHERE email IS NOT NULL AND email != ''
                    GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ) t
            ");

            return [
                'sources' => [
                    'influenceurs'       => (array) $inf,
                    'lawyers'            => (array) $law,
                    'press_contacts'     => (array) $press,
                    'content_businesses' => (array) $biz,
                    'content_contacts'   => (array) $cc,
                    'country_directory'  => (array) $cd,
                ],
                'totals' => [
                    'all'   => ($inf->total + $law->total + $press->total + $biz->total + $cc->total + $cd->total),
                    'tier1' => ($inf->tier1 + $law->tier1 + $press->tier1),
                    'tier2' => ($inf->tier2 + $law->tier2 + $press->tier2 + $biz->tier2 + $cc->tier2 + $cd->tier2),
                    'tier3' => ($inf->tier3 + $law->tier3 + $biz->tier3),
                    'tier4' => ($inf->tier4 + $law->tier4 + $press->tier4 + $biz->tier4 + $cc->tier4 + $cd->tier4),
                ],
                'duplicates' => [
                    'influenceurs'   => (int) $dupInf->n,
                    'lawyers'        => (int) $dupLaw->n,
                    'press_contacts' => (int) $dupPress->n,
                ],
                'inf_by_type' => DB::select("
                    SELECT contact_type, COUNT(*) as n,
                        COUNT(*) FILTER (WHERE email IS NOT NULL AND email != '') as with_email
                    FROM influenceurs WHERE deleted_at IS NULL
                    GROUP BY contact_type ORDER BY n DESC
                "),
            ];
        });

        return response()->json($data);
    }

    // ─── UNIFIED LIST ─────────────────────────────────────────────────────────

    public function contacts(Request $request): JsonResponse
    {
        $source    = $request->input('source', 'all');   // all | influenceurs | lawyers | press | businesses | content
        $tier      = (int) $request->input('tier', 0);   // 0=all, 1-4
        $type      = $request->input('type', '');
        $language  = $request->input('language', '');
        $country   = $request->input('country', '');
        $emailOnly = $request->boolean('email_only', false);
        $search    = $request->input('search', '');
        $page      = max(1, (int) $request->input('page', 1));
        $perPage   = min(100, (int) $request->input('per_page', 50));
        $offset    = ($page - 1) * $perPage;

        $results = [];
        $total   = 0;

        if (in_array($source, ['all', 'influenceurs'])) {
            [$rows, $count] = $this->queryInfluenceurs($tier, $type, $language, $country, $emailOnly, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        if (in_array($source, ['all', 'lawyers'])) {
            [$rows, $count] = $this->queryLawyers($tier, $language, $country, $emailOnly, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        if (in_array($source, ['all', 'press'])) {
            [$rows, $count] = $this->queryPress($tier, $language, $country, $emailOnly, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        if (in_array($source, ['all', 'businesses'])) {
            [$rows, $count] = $this->queryBusinesses($tier, $language, $country, $emailOnly, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        if (in_array($source, ['all', 'content_contacts'])) {
            [$rows, $count] = $this->queryContentContacts($tier, $country, $emailOnly, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        if (in_array($source, ['all', 'country_directory'])) {
            [$rows, $count] = $this->queryCountryDirectory($tier, $language, $country, $emailOnly, $search, $perPage, $offset);
            $results = array_merge($results, $rows);
            $total  += $count;
        }

        // Sort merged results: tier asc, name asc
        usort($results, fn($a, $b) => $a['tier'] <=> $b['tier'] ?: strcmp($a['name'] ?? '', $b['name'] ?? ''));

        return response()->json([
            'data'      => array_slice($results, 0, $perPage),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    // ─── UNIFIED (DEDUPLICATED BY EMAIL) ──────────────────────────────────────

    /**
     * Returns a single deduplicated list of ALL contacts with email.
     * One row per unique email (case-insensitive).
     * Priority: influenceurs (1) > press_contacts (2) > lawyers (3)
     *           > content_businesses (4) > content_contacts (5) > country_directory (6)
     */
    public function unified(Request $request): JsonResponse
    {
        $search   = $request->input('search', '');
        $language = $request->input('language', '');
        $country  = $request->input('country', '');
        $type     = $request->input('type', '');
        $category = $request->input('category', '');
        $source   = $request->input('source', '');   // filter by source_table
        $page     = max(1, (int) $request->input('page', 1));
        $perPage  = min(200, (int) $request->input('per_page', 50));
        $offset   = ($page - 1) * $perPage;

        [$where, $bindings] = $this->unifiedWhereClause($search, $language, $country, $type, $source, $category);
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = $this->unifiedCte();

        $total = DB::selectOne("
            WITH {$sql}
            SELECT COUNT(*) as n FROM unified {$whereStr}
        ", $bindings)->n;

        $rows = DB::select("
            WITH {$sql}
            SELECT * FROM unified {$whereStr}
            ORDER BY name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ", $bindings);

        return response()->json([
            'data'      => $rows,
            'total'     => (int) $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function unifiedExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $search   = $request->input('search', '');
        $language = $request->input('language', '');
        $country  = $request->input('country', '');
        $type     = $request->input('type', '');
        $category = $request->input('category', '');
        $source   = $request->input('source', '');

        [$where, $bindings] = $this->unifiedWhereClause($search, $language, $country, $type, $source, $category);
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = $this->unifiedCte();
        $rows = DB::select("WITH {$sql} SELECT * FROM unified {$whereStr} ORDER BY name ASC", $bindings);

        $filename = 'contacts-unifies-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Nom', 'Email', 'Téléphone', 'Catégorie', 'Type', 'Source', 'Langue', 'Pays', 'Site web', 'Statut'], ';');
            foreach ($rows as $r) {
                fputcsv($h, [
                    $r->name ?? '', $r->email ?? '', $r->phone ?? '',
                    $r->category ?? '', $r->type ?? '', $r->source_table ?? '',
                    $r->language ?? '', $r->country ?? '',
                    $r->website ?? '', $r->status ?? '',
                ], ';');
            }
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function unifiedCte(): string
    {
        return "
        all_contacts AS (
            SELECT
                LOWER(email)           AS email_norm,
                name, email, phone, website_url AS website,
                country, language,
                contact_type AS type,
                COALESCE(category, CASE
                    WHEN contact_type IN ('consulat','association','ecole','institut_culturel','chambre_commerce') THEN 'institutionnel'
                    WHEN contact_type IN ('presse','blog','podcast_radio') THEN 'presse'
                    WHEN contact_type = 'influenceur' THEN 'influenceurs'
                    WHEN contact_type IN ('avocat','immobilier','assurance','banque_fintech','traducteur','agence_voyage','emploi') THEN 'services_b2b'
                    WHEN contact_type IN ('communaute_expat','groupe_whatsapp_telegram','coworking_coliving','logement','lieu_communautaire') THEN 'communautes'
                    WHEN contact_type IN ('backlink','annuaire','plateforme_nomad','partenaire') THEN 'digital'
                    ELSE 'autre'
                END) AS category,
                status,
                1 AS priority, 'influenceurs' AS source_table
            FROM influenceurs
            WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL

            UNION ALL

            SELECT
                LOWER(email),
                full_name, email, phone, NULL,
                country, language,
                'journaliste' AS type, 'presse' AS category,
                contact_status,
                2, 'press_contacts'
            FROM press_contacts
            WHERE email IS NOT NULL AND email != ''

            UNION ALL

            SELECT
                LOWER(email),
                full_name, email, phone, website,
                country, language,
                'avocat' AS type, 'services_b2b' AS category,
                enrichment_status,
                3, 'lawyers'
            FROM lawyers
            WHERE email IS NOT NULL AND email != ''

            UNION ALL

            SELECT
                LOWER(contact_email),
                name, contact_email, NULL, website,
                country, NULL,
                category AS type, 'services_b2b' AS category,
                NULL,
                4, 'content_businesses'
            FROM content_businesses
            WHERE contact_email IS NOT NULL AND contact_email != ''

            UNION ALL

            SELECT
                LOWER(email),
                name, email, phone, NULL,
                country, NULL,
                sector AS type, 'autre' AS category,
                NULL,
                5, 'content_contacts'
            FROM content_contacts
            WHERE email IS NOT NULL AND email != ''

            UNION ALL

            SELECT
                LOWER(email),
                name, email, phone, NULL,
                country, language,
                type AS type, 'institutionnel' AS category,
                NULL,
                6, 'country_directory'
            FROM country_directory
            WHERE email IS NOT NULL AND email != ''
        ),
        ranked AS (
            SELECT *,
                ROW_NUMBER() OVER (PARTITION BY email_norm ORDER BY priority ASC) AS rn
            FROM all_contacts
        ),
        unified AS (
            SELECT email_norm, name, email, phone, website,
                   country, language, type, category, status, source_table
            FROM ranked WHERE rn = 1
        )
        ";
    }

    private function unifiedWhereClause(string $search, string $language, string $country, string $type, string $source, string $category = ''): array
    {
        $where = [];
        $bindings = [];

        if ($search) {
            $where[] = "(LOWER(name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(country) LIKE ?)";
            $s = '%' . strtolower($search) . '%';
            $bindings = array_merge($bindings, [$s, $s, $s]);
        }
        if ($language) { $where[] = "language = ?";          $bindings[] = $language; }
        if ($country)  { $where[] = "LOWER(country) LIKE ?"; $bindings[] = '%' . strtolower($country) . '%'; }
        if ($type)     { $where[] = "type = ?";              $bindings[] = $type; }
        if ($category) { $where[] = "category = ?";          $bindings[] = $category; }
        if ($source)   { $where[] = "source_table = ?";      $bindings[] = $source; }

        return [$where, $bindings];
    }

    // ─── DEDUPLICATION ────────────────────────────────────────────────────────

    public function duplicates(Request $request): JsonResponse
    {
        $source = $request->input('source', 'influenceurs');

        if ($source === 'influenceurs') {
            $groups = DB::select("
                SELECT LOWER(email) as email_norm, COUNT(*) as count,
                    array_agg(id ORDER BY created_at ASC) as ids,
                    array_agg(name ORDER BY created_at ASC) as names,
                    array_agg(contact_type ORDER BY created_at ASC) as types,
                    array_agg(status ORDER BY created_at ASC) as statuses
                FROM influenceurs
                WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ORDER BY count DESC LIMIT 200
            ");
        } elseif ($source === 'lawyers') {
            $groups = DB::select("
                SELECT LOWER(email) as email_norm, COUNT(*) as count,
                    array_agg(id ORDER BY created_at ASC) as ids,
                    array_agg(full_name ORDER BY created_at ASC) as names,
                    array_agg(country ORDER BY created_at ASC) as countries
                FROM lawyers
                WHERE email IS NOT NULL AND email != ''
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ORDER BY count DESC LIMIT 200
            ");
        } elseif ($source === 'press') {
            $groups = DB::select("
                SELECT LOWER(email) as email_norm, COUNT(*) as count,
                    array_agg(id ORDER BY created_at ASC) as ids,
                    array_agg(full_name ORDER BY created_at ASC) as names,
                    array_agg(publication ORDER BY created_at ASC) as publications
                FROM press_contacts
                WHERE email IS NOT NULL AND email != ''
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
                ORDER BY count DESC LIMIT 200
            ");
        } else {
            return response()->json(['error' => 'Source inconnue'], 422);
        }

        return response()->json([
            'source'     => $source,
            'total_groups' => count($groups),
            'groups'     => $groups,
        ]);
    }

    public function deduplicateAuto(Request $request): JsonResponse
    {
        $source   = $request->input('source', 'influenceurs');
        $strategy = $request->input('strategy', 'keep_oldest'); // keep_oldest | keep_most_complete

        $deleted = 0;

        if ($source === 'lawyers') {
            // For lawyers: keep first (oldest), soft-delete or hard-delete duplicates
            $rows = DB::select("
                SELECT array_agg(id ORDER BY created_at ASC) as ids
                FROM lawyers
                WHERE email IS NOT NULL AND email != ''
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
            ");
            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', trim($row->ids, '{}')));
                $toDelete = array_slice($ids, 1); // keep first
                DB::table('lawyers')->whereIn('id', $toDelete)->delete();
                $deleted += count($toDelete);
            }
        } elseif ($source === 'influenceurs') {
            // Soft-delete duplicates (keep oldest or most complete)
            $rows = DB::select("
                SELECT array_agg(id ORDER BY created_at ASC) as ids
                FROM influenceurs
                WHERE email IS NOT NULL AND email != '' AND deleted_at IS NULL
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
            ");
            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', trim($row->ids, '{}')));
                if ($strategy === 'keep_most_complete') {
                    // Keep the one with most filled fields - keep last in this simple impl
                    $toDelete = array_slice($ids, 0, -1);
                } else {
                    $toDelete = array_slice($ids, 1);
                }
                DB::table('influenceurs')->whereIn('id', $toDelete)->update(['deleted_at' => now()]);
                $deleted += count($toDelete);
            }
        } elseif ($source === 'press') {
            $rows = DB::select("
                SELECT array_agg(id ORDER BY created_at ASC) as ids
                FROM press_contacts
                WHERE email IS NOT NULL AND email != ''
                GROUP BY LOWER(email) HAVING COUNT(*) > 1
            ");
            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', trim($row->ids, '{}')));
                $toDelete = array_slice($ids, 1);
                DB::table('press_contacts')->whereIn('id', $toDelete)->delete();
                $deleted += count($toDelete);
            }
        }

        Cache::forget('contacts-base-stats');

        return response()->json([
            'message' => "{$deleted} doublons supprimés dans {$source}",
            'deleted' => $deleted,
        ]);
    }

    // ─── PRIVATE QUERY HELPERS ────────────────────────────────────────────────

    private function queryInfluenceurs(int $tier, string $type, string $language, string $country, bool $emailOnly, string $search, int $limit, int $offset): array
    {
        $where = ["deleted_at IS NULL"];
        $bindings = [];

        if ($emailOnly || $tier === 1) $where[] = "email IS NOT NULL AND email != '' AND email_verified_status = 'verified'";
        elseif ($tier === 2) $where[] = "email IS NOT NULL AND email != '' AND (email_verified_status IS NULL OR email_verified_status != 'verified')";
        elseif ($tier === 3) $where[] = "(email IS NULL OR email = '') AND website_url IS NOT NULL AND website_url != ''";
        elseif ($tier === 4) $where[] = "(email IS NULL OR email = '') AND (website_url IS NULL OR website_url = '')";
        elseif ($emailOnly) $where[] = "email IS NOT NULL AND email != ''";

        if ($type)     { $where[] = "contact_type = ?"; $bindings[] = $type; }
        if ($language) { $where[] = "language = ?"; $bindings[] = $language; }
        if ($country)  { $where[] = "LOWER(country) LIKE ?"; $bindings[] = '%' . strtolower($country) . '%'; }
        if ($search)   { $where[] = "(LOWER(name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(country) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM influenceurs WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, name, email, phone, website_url as website, country, language,
                   contact_type as type, status, email_verified_status, score, 'influenceurs' as source_table
            FROM influenceurs WHERE {$whereStr}
            ORDER BY score DESC NULLS LAST, name ASC
            LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'influenceurs'), (int) $count];
    }

    private function queryLawyers(int $tier, string $language, string $country, bool $emailOnly, string $search, int $limit, int $offset): array
    {
        $where = ["1=1"];
        $bindings = [];

        if ($tier === 1) $where[] = "email IS NOT NULL AND email != '' AND email_verified = true";
        elseif ($tier === 2) $where[] = "email IS NOT NULL AND email != '' AND (email_verified IS NULL OR email_verified = false)";
        elseif ($tier === 3) $where[] = "(email IS NULL OR email = '') AND website IS NOT NULL AND website != ''";
        elseif ($tier === 4) $where[] = "(email IS NULL OR email = '') AND (website IS NULL OR website = '')";
        elseif ($emailOnly) $where[] = "email IS NOT NULL AND email != ''";

        if ($language) { $where[] = "language = ?"; $bindings[] = $language; }
        if ($country)  { $where[] = "LOWER(country) LIKE ?"; $bindings[] = '%' . strtolower($country) . '%'; }
        if ($search)   { $where[] = "(LOWER(full_name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(country) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM lawyers WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, full_name as name, email, phone, website, country, language,
                   'avocat' as type, enrichment_status as status,
                   email_verified::text as email_verified_status, NULL as score, 'lawyers' as source_table
            FROM lawyers WHERE {$whereStr}
            ORDER BY full_name ASC LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'lawyers'), (int) $count];
    }

    private function queryPress(int $tier, string $language, string $country, bool $emailOnly, string $search, int $limit, int $offset): array
    {
        $where = ["1=1"];
        $bindings = [];

        if ($tier === 1) $where[] = "email IS NOT NULL AND email != '' AND email_smtp_valid = true";
        elseif ($tier === 2) $where[] = "email IS NOT NULL AND email != '' AND (email_smtp_valid IS NULL OR email_smtp_valid = false)";
        elseif ($tier === 4) $where[] = "email IS NULL OR email = ''";
        elseif ($emailOnly) $where[] = "email IS NOT NULL AND email != ''";

        if ($language) { $where[] = "language = ?"; $bindings[] = $language; }
        if ($country)  { $where[] = "LOWER(country) LIKE ?"; $bindings[] = '%' . strtolower($country) . '%'; }
        if ($search)   { $where[] = "(LOWER(full_name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(publication) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM press_contacts WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, full_name as name, email, phone, NULL as website, country, language,
                   'journaliste' as type, contact_status as status,
                   email_smtp_valid::text as email_verified_status, NULL as score, 'press_contacts' as source_table
            FROM press_contacts WHERE {$whereStr}
            ORDER BY full_name ASC LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'press_contacts'), (int) $count];
    }

    private function queryBusinesses(int $tier, string $language, string $country, bool $emailOnly, string $search, int $limit, int $offset): array
    {
        $where = ["1=1"];
        $bindings = [];

        if ($tier === 2) $where[] = "contact_email IS NOT NULL AND contact_email != ''";
        elseif ($tier === 3) $where[] = "(contact_email IS NULL OR contact_email = '') AND website IS NOT NULL AND website != ''";
        elseif ($tier === 4) $where[] = "(contact_email IS NULL OR contact_email = '') AND (website IS NULL OR website = '')";
        elseif ($emailOnly) $where[] = "contact_email IS NOT NULL AND contact_email != ''";
        if ($tier === 1) $where[] = "1=0";

        if ($country)  { $where[] = "LOWER(country) LIKE ?"; $bindings[] = '%' . strtolower($country) . '%'; }
        if ($search)   { $where[] = "(LOWER(name) LIKE ? OR LOWER(contact_email) LIKE ? OR LOWER(country) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM content_businesses WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, name, contact_email as email, NULL as phone, website, country, NULL as language,
                   category as type, NULL as status, NULL as email_verified_status,
                   NULL as score, 'content_businesses' as source_table
            FROM content_businesses WHERE {$whereStr}
            ORDER BY name ASC LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'content_businesses'), (int) $count];
    }

    private function queryContentContacts(int $tier, string $country, bool $emailOnly, string $search, int $limit, int $offset): array
    {
        $where = ["1=1"];
        $bindings = [];

        if ($tier === 2 || $emailOnly) $where[] = "email IS NOT NULL AND email != ''";
        elseif ($tier === 4) $where[] = "email IS NULL OR email = ''";
        if ($tier === 1) $where[] = "1=0"; // pas de vérification SMTP pour content_contacts

        if ($country) { $where[] = "LOWER(country) LIKE ?"; $bindings[] = '%' . strtolower($country) . '%'; }
        if ($search)  { $where[] = "(LOWER(name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(company) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM content_contacts WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, name, email, phone, NULL as website, country, NULL as language,
                   sector as type, NULL as status, NULL as email_verified_status,
                   NULL as score, 'content_contacts' as source_table
            FROM content_contacts WHERE {$whereStr}
            ORDER BY name ASC LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'content_contacts'), (int) $count];
    }

    private function queryCountryDirectory(int $tier, string $language, string $country, bool $emailOnly, string $search, int $limit, int $offset): array
    {
        $where = ["1=1"];
        $bindings = [];

        if ($tier === 1) $where[] = "1=0"; // pas de vérification pour country_directory
        elseif ($tier === 2 || $emailOnly) $where[] = "email IS NOT NULL AND email != ''";
        elseif ($tier === 4) $where[] = "email IS NULL OR email = ''";

        if ($language) { $where[] = "language = ?"; $bindings[] = $language; }
        if ($country)  { $where[] = "(LOWER(country) LIKE ? OR LOWER(country_code) LIKE ?)"; $c = '%' . strtolower($country) . '%'; $bindings = array_merge($bindings, [$c, $c]); }
        if ($search)   { $where[] = "(LOWER(name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(city) LIKE ?)"; $s = '%' . strtolower($search) . '%'; $bindings = array_merge($bindings, [$s, $s, $s]); }

        $whereStr = implode(' AND ', $where);
        $count = DB::selectOne("SELECT COUNT(*) as n FROM country_directory WHERE {$whereStr}", $bindings)->n;

        $rows = DB::select("
            SELECT id, name, email, phone, NULL as website, country, language,
                   type as type, NULL as status, NULL as email_verified_status,
                   NULL as score, 'country_directory' as source_table
            FROM country_directory WHERE {$whereStr}
            ORDER BY name ASC LIMIT {$limit} OFFSET {$offset}
        ", $bindings);

        return [$this->tagTier($rows, 'country_directory'), (int) $count];
    }

    private function tagTier(array $rows, string $sourceTable): array
    {
        return array_map(function ($row) use ($sourceTable) {
            $r = (array) $row;
            $hasEmail    = !empty($r['email']);
            $hasWebsite  = !empty($r['website']);
            $isVerified  = $r['email_verified_status'] === 'verified' || $r['email_verified_status'] === 'true';

            if ($hasEmail && $isVerified)       $r['tier'] = 1;
            elseif ($hasEmail && !$isVerified)  $r['tier'] = 2;
            elseif (!$hasEmail && $hasWebsite)  $r['tier'] = 3;
            else                                $r['tier'] = 4;

            return $r;
        }, $rows);
    }
}
