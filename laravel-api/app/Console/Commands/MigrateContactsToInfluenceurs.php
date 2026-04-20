<?php

namespace App\Console\Commands;

use App\Models\ContentBusiness;
use App\Models\ContentContact;
use App\Models\Influenceur;
use App\Models\Lawyer;
use App\Models\PressContact;
use App\Support\SectorTypeMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P1 refactor fusion tables contacts — Commande de migration des données.
 *
 * Copie les rows des 4 tables legacy vers `influenceurs` avec :
 * - Préservation de `backlink_synced_at` (GREATEST entre existing et incoming)
 * - COALESCE des champs (ne pas écraser des valeurs déjà remplies dans influenceurs)
 * - Normalisation email (lowercase trim) pour dedup case-insensitive
 * - `withoutEvents` pendant les writes (pas de webhook parasite vers bl-app sur
 *   des contacts déjà syncés)
 * - Logs détaillés des conflits dans storage/logs/migrate-contacts.log
 *
 * Idempotente : rerun = 0 nouvelle insertion. Safe pour prod.
 *
 * Usage :
 *   php artisan contacts:migrate-to-influenceurs --dry-run
 *   php artisan contacts:migrate-to-influenceurs --limit=100 --table=lawyers
 *   php artisan contacts:migrate-to-influenceurs --table=all
 */
class MigrateContactsToInfluenceurs extends Command
{
    protected $signature = 'contacts:migrate-to-influenceurs
                            {--table=all : lawyers|press|businesses|content_contacts|all}
                            {--limit=0 : Cap rows par table (0 = illimite)}
                            {--batch=500 : Taille chunk}
                            {--dry-run : Compte sans ecrire}';

    protected $description = 'Migrate rows from 4 legacy contact tables into influenceurs (preserving backlink_synced_at)';

    /** @var array<string,int> stats */
    private array $stats = [
        'inserted' => 0,
        'enriched' => 0,
        'skipped_no_email' => 0,
        'skipped_invalid_email' => 0,
    ];

    public function handle(): int
    {
        $table = $this->option('table');
        $limit = (int) $this->option('limit');
        $batch = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('=== Migrate Contacts → Influenceurs ===');
        $this->line(sprintf('  table=%s limit=%d batch=%d dry_run=%s', $table, $limit, $batch, $dryRun ? 'yes' : 'no'));
        $this->newLine();

        $runAll = $table === 'all';

        if ($runAll || $table === 'lawyers') {
            $this->migrateBlock('lawyers', fn() => $this->migrateLawyers($limit, $batch, $dryRun));
        }
        if ($runAll || $table === 'press') {
            $this->migrateBlock('press', fn() => $this->migratePress($limit, $batch, $dryRun));
        }
        if ($runAll || $table === 'businesses') {
            $this->migrateBlock('businesses', fn() => $this->migrateBusinesses($limit, $batch, $dryRun));
        }
        if ($runAll || $table === 'content_contacts') {
            $this->migrateBlock('content_contacts', fn() => $this->migrateContentContacts($limit, $batch, $dryRun));
        }

        $this->newLine();
        $this->info('=== TOTAL ===');
        foreach ($this->stats as $k => $v) {
            $this->line(sprintf('  %-24s : %d', $k, $v));
        }
        return Command::SUCCESS;
    }

    private function migrateBlock(string $name, callable $fn): void
    {
        $this->info("-- Table: {$name} --");
        $before = $this->stats;
        $fn();
        $delta = array_map(fn($k) => $this->stats[$k] - $before[$k], array_keys($this->stats));
        $this->line(sprintf('  done inserted=%d enriched=%d skipped_no_email=%d skipped_invalid_email=%d',
            $delta[0], $delta[1], $delta[2], $delta[3]));
        $this->newLine();
    }

    private function migrateLawyers(int $limit, int $batch, bool $dryRun): void
    {
        $q = Lawyer::query()->whereNotNull('email')->where('email', '!=', '');
        if ($limit > 0) $q->limit($limit);

        $q->chunkById($batch, function ($chunk) use ($dryRun) {
            foreach ($chunk as $lw) {
                $this->upsertInfluenceur([
                    'email'            => $lw->email,
                    'contact_type'     => 'avocat',
                    'name'             => $lw->full_name,
                    'first_name'       => $lw->first_name,
                    'last_name'        => $lw->last_name,
                    'company'          => $lw->firm_name,
                    'country'          => $lw->country,
                    'language'         => $lw->language,
                    'phone'            => $lw->phone,
                    'website_url'      => $lw->website,
                    'profile_url'      => $lw->source_url,
                    'firm_name'        => $lw->firm_name,
                    'bar_number'       => $lw->bar_number,
                    'bar_association'  => $lw->bar_association,
                    'specialty'        => $lw->specialty,
                    'source_origin'    => 'lawyers',
                    'source_id_legacy' => $lw->id,
                    'backlink_synced_at' => $lw->backlink_synced_at,
                    'scraped_at'       => $lw->scraped_at,
                    'source'           => 'scraper_lawyers',
                ], $dryRun);
            }
        });
    }

    private function migratePress(int $limit, int $batch, bool $dryRun): void
    {
        $q = PressContact::query()->whereNotNull('email')->where('email', '!=', '');
        if ($limit > 0) $q->limit($limit);

        $q->chunkById($batch, function ($chunk) use ($dryRun) {
            foreach ($chunk as $pc) {
                $this->upsertInfluenceur([
                    'email'              => $pc->email,
                    'contact_type'       => 'presse',
                    'name'               => $pc->full_name,
                    'first_name'         => $pc->first_name,
                    'last_name'          => $pc->last_name,
                    'company'            => $pc->publication,
                    'publication'        => $pc->publication,
                    'role'               => $pc->role,
                    'beat'               => $pc->beat,
                    'media_type'         => $pc->media_type,
                    'country'            => $pc->country,
                    'language'           => $pc->language,
                    'phone'              => $pc->phone,
                    'profile_url'        => $pc->profile_url,
                    'linkedin_url'       => $pc->linkedin,
                    'twitter_url'        => $pc->twitter,
                    'source_origin'      => 'press_contacts',
                    'source_id_legacy'   => $pc->id,
                    'backlink_synced_at' => $pc->backlink_synced_at,
                    'scraped_at'         => $pc->scraped_at,
                    'last_contact_at'    => $pc->last_contacted_at,
                    'notes'              => $pc->notes,
                    'source'             => 'scraper_press',
                ], $dryRun);
            }
        });
    }

    private function migrateBusinesses(int $limit, int $batch, bool $dryRun): void
    {
        $q = ContentBusiness::query()->whereNotNull('contact_email')->where('contact_email', '!=', '');
        if ($limit > 0) $q->limit($limit);

        $q->chunkById($batch, function ($chunk) use ($dryRun) {
            foreach ($chunk as $biz) {
                $name = $biz->contact_name ?: $biz->name;
                $this->upsertInfluenceur([
                    'email'            => $biz->contact_email,
                    'contact_type'     => 'partenaire',
                    'name'             => $name,
                    'company'          => $biz->name,
                    'country'          => $biz->country,
                    'language'         => $biz->language,
                    'phone'            => $biz->contact_phone,
                    'website_url'      => $biz->website ?: $biz->url,
                    'url_hash'         => $biz->url_hash ? 'biz:' . $biz->url_hash : null,
                    'source_origin'    => 'content_businesses',
                    'source_id_legacy' => $biz->id,
                    'backlink_synced_at' => $biz->backlink_synced_at,
                    'scraped_at'       => $biz->scraped_at,
                    'source'           => 'scraper_businesses',
                ], $dryRun);
            }
        });
    }

    private function migrateContentContacts(int $limit, int $batch, bool $dryRun): void
    {
        $q = ContentContact::query()->whereNotNull('email')->where('email', '!=', '');
        if ($limit > 0) $q->limit($limit);

        $q->chunkById($batch, function ($chunk) use ($dryRun) {
            foreach ($chunk as $c) {
                $type = SectorTypeMapper::resolve($c->sector);
                $this->upsertInfluenceur([
                    'email'            => $c->email,
                    'contact_type'     => $type,
                    'name'             => $c->name,
                    'company'          => $c->company,
                    'country'          => $c->country,
                    'language'         => $c->language,
                    'phone'            => $c->phone,
                    'website_url'      => $c->company_url ?: $c->page_url,
                    'linkedin_url'     => $c->linkedin,
                    'source_origin'    => 'content_contacts',
                    'source_id_legacy' => $c->id,
                    'backlink_synced_at' => $c->backlink_synced_at,
                    'scraped_at'       => $c->scraped_at,
                    'notes'            => $c->notes,
                    'source'           => 'scraper_content',
                ], $dryRun);
            }
        });
    }

    /**
     * Upsert un Influenceur en préservant les données existantes.
     *
     * Règles :
     * - Clé de dedup : LOWER(TRIM(email))
     * - Si nouveau : INSERT tous les champs
     * - Si existe : COALESCE (ne pas écraser les valeurs déjà renseignées),
     *   backlink_synced_at = MAX(existing, incoming), source_origin inchangé
     * - withoutEvents : aucun webhook bl-app déclenché (les rows sont déjà
     *   syncés via leur observer legacy si backlink_synced_at != null)
     */
    private function upsertInfluenceur(array $data, bool $dryRun): void
    {
        $email = $data['email'] ?? null;
        if (!$email) {
            $this->stats['skipped_no_email']++;
            return;
        }
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->stats['skipped_invalid_email']++;
            return;
        }
        $data['email'] = $email;

        if ($dryRun) {
            // Juste simuler le branchement
            $existing = Influenceur::where('email', $email)->first();
            if ($existing) {
                $this->stats['enriched']++;
            } else {
                $this->stats['inserted']++;
            }
            return;
        }

        // withoutEvents : empêche InfluenceurObserver de déclencher le webhook
        // pendant cette migration (les rows legacy sont déjà syncés via leur
        // observer respectif ; les pousser à nouveau = double traitement bl-app)
        Influenceur::withoutEvents(function () use ($email, $data) {
            $existing = Influenceur::where('email', $email)->first();

            if (!$existing) {
                // Nouveau : INSERT avec tous les champs
                Influenceur::create($data);
                $this->stats['inserted']++;
                return;
            }

            // Enrichissement : COALESCE champ par champ, préserve l'existant
            $changed = false;
            $protectedFields = ['id', 'email', 'source_origin', 'source_id_legacy', 'created_at', 'updated_at'];

            foreach ($data as $field => $value) {
                if (in_array($field, $protectedFields, true)) continue;
                if ($value === null || $value === '') continue;

                // Règle backlink_synced_at : GREATEST(existing, incoming)
                if ($field === 'backlink_synced_at') {
                    $existingDate = $existing->backlink_synced_at;
                    $incomingDate = $value instanceof Carbon ? $value : Carbon::parse($value);
                    if (!$existingDate || $incomingDate->greaterThan($existingDate)) {
                        $existing->backlink_synced_at = $incomingDate;
                        $changed = true;
                    }
                    continue;
                }

                // Règle générique : ne remplir que les champs vides (pas d'écrasement)
                if (empty($existing->{$field})) {
                    $existing->{$field} = $value;
                    $changed = true;
                }
            }

            // Ajouter source_origin si pas encore défini (preserve le premier)
            if (empty($existing->source_origin) && !empty($data['source_origin'])) {
                $existing->source_origin = $data['source_origin'];
                $existing->source_id_legacy = $data['source_id_legacy'] ?? null;
                $changed = true;
            } else {
                // Déjà défini : loguer le conflict si différent
                if (!empty($existing->source_origin)
                    && $existing->source_origin !== ($data['source_origin'] ?? null)) {
                    Log::channel('single')->info('MigrateContacts: conflict source_origin', [
                        'email' => $email,
                        'existing' => $existing->source_origin,
                        'incoming' => $data['source_origin'] ?? null,
                    ]);
                }
            }

            if ($changed) {
                $existing->save();
                $this->stats['enriched']++;
            }
        });
    }
}
