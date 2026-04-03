<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Import Pipeline — importe les contacts des tables staging dans influenceurs.
 *
 * Sources :
 *   press      → press_contacts  → contact_type=presse,    category=presse
 *   lawyers    → lawyers         → contact_type=avocat,    category=services_b2b
 *   businesses → content_businesses → contact_type=partenaire, category=digital
 *   contacts   → content_contacts   → contact_type=partenaire, category=digital
 *   directory  → country_directory  → contact_type=consulat,  category=institutionnel
 *
 * Règle de déduplication : on skip si LOWER(email) existe déjà dans influenceurs.
 */
class ImportPipelineController extends Controller
{
    private const CHUNK = 200;

    // ─── STATS ────────────────────────────────────────────────────────────────

    public function stats(): JsonResponse
    {
        $existingEmails = $this->existingEmailsSet();

        return response()->json([
            'press'      => $this->sourceStats('press_contacts', 'email', $existingEmails),
            'lawyers'    => $this->sourceStats('lawyers', 'email', $existingEmails),
            'businesses' => $this->sourceStats('content_businesses', 'contact_email', $existingEmails),
            'contacts'   => $this->sourceStats('content_contacts', 'email', $existingEmails),
            'directory'  => $this->sourceStats('country_directory', 'email', $existingEmails),
        ]);
    }

    // ─── IMPORT PAR SOURCE ────────────────────────────────────────────────────

    public function importSource(Request $request, string $source): JsonResponse
    {
        $imported = 0;
        $skipped  = 0;
        $userId   = $request->user()->id;

        $existingEmails = $this->existingEmailsSet();

        match ($source) {
            'press'      => [$imported, $skipped] = $this->importPress($existingEmails, $userId),
            'lawyers'    => [$imported, $skipped] = $this->importLawyers($existingEmails, $userId),
            'businesses' => [$imported, $skipped] = $this->importBusinesses($existingEmails, $userId),
            'contacts'   => [$imported, $skipped] = $this->importContacts($existingEmails, $userId),
            'directory'  => [$imported, $skipped] = $this->importDirectory($existingEmails, $userId),
            default      => abort(422, "Source inconnue : {$source}"),
        };

        return response()->json([
            'source'   => $source,
            'imported' => $imported,
            'skipped'  => $skipped,
            'message'  => "{$imported} contacts importés, {$skipped} ignorés (doublons email).",
        ]);
    }

    public function importAll(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $existingEmails = $this->existingEmailsSet();
        $totals = ['imported' => 0, 'skipped' => 0, 'details' => []];

        foreach (['press', 'lawyers', 'businesses', 'contacts', 'directory'] as $src) {
            [$imp, $skip] = match ($src) {
                'press'      => $this->importPress($existingEmails, $userId),
                'lawyers'    => $this->importLawyers($existingEmails, $userId),
                'businesses' => $this->importBusinesses($existingEmails, $userId),
                'contacts'   => $this->importContacts($existingEmails, $userId),
                'directory'  => $this->importDirectory($existingEmails, $userId),
            };
            $totals['imported']          += $imp;
            $totals['skipped']           += $skip;
            $totals['details'][$src]      = ['imported' => $imp, 'skipped' => $skip];
        }

        $totals['message'] = "{$totals['imported']} contacts importés au total, {$totals['skipped']} ignorés.";
        return response()->json($totals);
    }

    // ─── IMPORT PRESS CONTACTS ────────────────────────────────────────────────

    private function importPress(array &$existingEmails, int $userId = 1): array
    {
        $imported = 0;
        $skipped  = 0;

        DB::table('press_contacts')
            ->whereNotNull('email')->where('email', '!=', '')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($rows) use (&$imported, &$skipped, &$existingEmails) {
                $toInsert = [];
                foreach ($rows as $r) {
                    $key = strtolower(trim($r->email));
                    if (isset($existingEmails[$key])) { $skipped++; continue; }
                    $existingEmails[$key] = true;

                    $toInsert[] = $this->baseRecord([
                        'name'         => $r->full_name,
                        'first_name'   => $r->first_name,
                        'last_name'    => $r->last_name,
                        'email'        => $r->email,
                        'phone'        => $r->phone,
                        'company'      => $r->publication,
                        'position'     => $r->role,
                        'country'      => $r->country,
                        'language'     => $r->language,
                        'contact_type' => 'presse',
                        'category'     => 'medias_influence',
                        'source'       => 'press_import',
                        'contact_kind' => 'individual',
                        'profile_url'  => $r->source_url,
                        'linkedin_url' => $r->linkedin,
                        'twitter_url'  => $r->twitter,
                        'notes'        => $r->notes,
                        'tags'         => json_encode(array_filter([
                            $r->beat,
                            $r->media_type,
                        ])),
                    ]);
                }
                if ($toInsert) {
                    DB::table('influenceurs')->insert($toInsert);
                    $imported += count($toInsert);
                }
            });

        return [$imported, $skipped];
    }

    // ─── IMPORT LAWYERS ───────────────────────────────────────────────────────

    private function importLawyers(array &$existingEmails, int $userId = 1): array
    {
        $imported = 0;
        $skipped  = 0;

        DB::table('lawyers')
            ->whereNotNull('email')->where('email', '!=', '')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($rows) use (&$imported, &$skipped, &$existingEmails, $userId) {
                $toInsert = [];
                foreach ($rows as $r) {
                    $key = strtolower(trim($r->email));
                    if (isset($existingEmails[$key])) { $skipped++; continue; }
                    $existingEmails[$key] = true;

                    $specialties = $r->specialties ? json_decode($r->specialties, true) : [];
                    $notes = implode("\n", array_filter([
                        $r->specialty    ? "Spécialité : {$r->specialty}" : null,
                        $r->bar_association ? "Barreau : {$r->bar_association}" : null,
                        $r->bar_number   ? "N° barreau : {$r->bar_number}" : null,
                        $r->description,
                    ]));

                    $toInsert[] = $this->baseRecord([
                        'name'         => $r->full_name,
                        'first_name'   => $r->first_name,
                        'last_name'    => $r->last_name,
                        'email'        => $r->email,
                        'phone'        => $r->phone,
                        'company'      => $r->firm_name,
                        'position'     => $r->title,
                        'website_url'  => $r->website,
                        'country'      => $r->country,
                        'language'     => $r->language,
                        'contact_type' => 'avocat',
                        'category'     => 'services_b2b',
                        'source'       => 'lawyers_import',
                        'contact_kind' => 'individual',
                        'notes'        => $notes ?: null,
                        'tags'         => json_encode(array_values(array_filter(
                            array_merge(['avocat'], $specialties)
                        ))),
                    ], $userId);
                }
                if ($toInsert) {
                    DB::table('influenceurs')->insert($toInsert);
                    $imported += count($toInsert);
                }
            });

        return [$imported, $skipped];
    }

    // ─── IMPORT CONTENT BUSINESSES ────────────────────────────────────────────

    private function importBusinesses(array &$existingEmails, int $userId = 1): array
    {
        $imported = 0;
        $skipped  = 0;

        DB::table('content_businesses')
            ->whereNotNull('contact_email')->where('contact_email', '!=', '')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($rows) use (&$imported, &$skipped, &$existingEmails, $userId) {
                $toInsert = [];
                foreach ($rows as $r) {
                    $key = strtolower(trim($r->contact_email));
                    if (isset($existingEmails[$key])) { $skipped++; continue; }
                    $existingEmails[$key] = true;

                    $toInsert[] = $this->baseRecord([
                        'name'         => $r->contact_name ?: $r->name,
                        'email'        => $r->contact_email,
                        'company'      => $r->name,
                        'website_url'  => $r->website,
                        'country'      => $r->country,
                        'language'     => $r->language ?? 'fr',
                        'contact_type' => 'partenaire',
                        'category'     => 'digital',
                        'source'       => 'businesses_import',
                        'contact_kind' => 'organization',
                        'tags'         => json_encode(array_filter([
                            $r->category,
                            $r->subcategory,
                        ])),
                    ], $userId);
                }
                if ($toInsert) {
                    DB::table('influenceurs')->insert($toInsert);
                    $imported += count($toInsert);
                }
            });

        return [$imported, $skipped];
    }

    // ─── IMPORT CONTENT CONTACTS ──────────────────────────────────────────────

    private function importContacts(array &$existingEmails, int $userId = 1): array
    {
        $imported = 0;
        $skipped  = 0;

        DB::table('content_contacts')
            ->whereNotNull('email')->where('email', '!=', '')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($rows) use (&$imported, &$skipped, &$existingEmails, $userId) {
                $toInsert = [];
                foreach ($rows as $r) {
                    $key = strtolower(trim($r->email));
                    if (isset($existingEmails[$key])) { $skipped++; continue; }
                    $existingEmails[$key] = true;

                    $toInsert[] = $this->baseRecord([
                        'name'         => $r->name,
                        'email'        => $r->email,
                        'phone'        => $r->phone,
                        'company'      => $r->company,
                        'position'     => $r->role,
                        'website_url'  => $r->company_url,
                        'country'      => $r->country,
                        'language'     => $r->language ?? 'fr',
                        'contact_type' => 'partenaire',
                        'category'     => 'digital',
                        'source'       => 'content_contacts_import',
                        'contact_kind' => 'individual',
                        'linkedin_url' => $r->linkedin,
                        'notes'        => $r->notes,
                        'tags'         => json_encode(array_filter([$r->sector])),
                    ], $userId);
                }
                if ($toInsert) {
                    DB::table('influenceurs')->insert($toInsert);
                    $imported += count($toInsert);
                }
            });

        return [$imported, $skipped];
    }

    // ─── IMPORT COUNTRY DIRECTORY ─────────────────────────────────────────────

    private function importDirectory(array &$existingEmails, int $userId = 1): array
    {
        $imported = 0;
        $skipped  = 0;

        // Map country_directory.category → contact_type
        $typeMap = [
            'consulat'          => 'consulat',
            'ambassade'         => 'consulat',
            'embassy'           => 'consulat',
            'consulate'         => 'consulat',
            'association'       => 'association',
            'ecole'             => 'ecole',
            'school'            => 'ecole',
            'universite'        => 'ecole',
            'universite'        => 'ecole',
            'chambre_commerce'  => 'chambre_commerce',
            'chambre'           => 'chambre_commerce',
            'institut'          => 'institut_culturel',
            'culturel'          => 'institut_culturel',
        ];

        DB::table('country_directory')
            ->whereNotNull('email')->where('email', '!=', '')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($rows) use (&$imported, &$skipped, &$existingEmails, $typeMap, $userId) {
                $toInsert = [];
                foreach ($rows as $r) {
                    $key = strtolower(trim($r->email));
                    if (isset($existingEmails[$key])) { $skipped++; continue; }
                    $existingEmails[$key] = true;

                    $catKey     = strtolower($r->category ?? '');
                    $contactType = $typeMap[$catKey] ?? 'consulat';

                    $toInsert[] = $this->baseRecord([
                        'name'         => $r->title,
                        'email'        => $r->email,
                        'phone'        => $r->phone,
                        'website_url'  => $r->url,
                        'country'      => $r->country_name,
                        'language'     => $r->language ?? 'fr',
                        'contact_type' => $contactType,
                        'category'     => 'institutionnel',
                        'source'       => 'directory_import',
                        'contact_kind' => 'organization',
                        'notes'        => $r->description,
                        'tags'         => json_encode(array_filter([
                            $r->category,
                            $r->sub_category,
                            $r->country_name,
                        ])),
                    ], $userId);
                }
                if ($toInsert) {
                    DB::table('influenceurs')->insert($toInsert);
                    $imported += count($toInsert);
                }
            });

        return [$imported, $skipped];
    }

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    /**
     * Load all existing emails from influenceurs into a hash map for O(1) lookup.
     */
    private function existingEmailsSet(): array
    {
        $map = [];
        DB::table('influenceurs')
            ->whereNotNull('email')->where('email', '!=', '')->whereNull('deleted_at')
            ->select('email')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$map) {
                foreach ($rows as $r) {
                    $map[strtolower(trim($r->email))] = true;
                }
            });
        return $map;
    }

    private function sourceStats(string $table, string $emailCol, array $existingEmails): array
    {
        $rows = DB::table($table)
            ->whereNotNull($emailCol)->where($emailCol, '!=', '')
            ->select($emailCol)->get();

        $total    = $rows->count();
        $toImport = 0;
        $already  = 0;

        foreach ($rows as $r) {
            $key = strtolower(trim($r->{$emailCol}));
            if (isset($existingEmails[$key])) $already++;
            else $toImport++;
        }

        return [
            'total'     => $total,
            'to_import' => $toImport,
            'already'   => $already,
        ];
    }

    private function baseRecord(array $fields, int $userId = 1): array
    {
        $now = now()->toDateTimeString();
        return array_merge([
            'name'             => null,
            'first_name'       => null,
            'last_name'        => null,
            'email'            => null,
            'phone'            => null,
            'company'          => null,
            'position'         => null,
            'website_url'      => null,
            'country'          => null,
            'language'         => 'fr',
            'contact_type'     => 'partenaire',
            'category'         => 'digital',
            'source'           => 'import',
            'contact_kind'     => 'individual',
            'status'           => 'prospect',
            'primary_platform' => '',
            'platforms'        => json_encode([]),
            'profile_url'      => null,
            'linkedin_url'     => null,
            'twitter_url'      => null,
            'notes'            => null,
            'tags'             => json_encode([]),
            'has_email'        => true,
            'has_phone'        => false,
            'score'            => 0,
            'reminder_days'    => 7,
            'reminder_active'  => true,
            'deal_value_cents' => 0,
            'deal_probability' => 0,
            'bounce_count'     => 0,
            'data_completeness' => 0,
            'is_verified'      => false,
            'unsubscribed'     => false,
            'created_by'       => $userId,
            'created_at'       => $now,
            'updated_at'       => $now,
        ], $fields, [
            'has_phone' => !empty($fields['phone']),
        ]);
    }
}
