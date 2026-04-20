<?php

namespace App\Console\Commands;

use App\Models\ContentBusiness;
use App\Models\ContentContact;
use App\Models\Influenceur;
use App\Models\Lawyer;
use App\Models\PressContact;
use App\Observers\ContentContactObserver;
use App\Services\BacklinkEngineWebhookService;
use Illuminate\Console\Command;

/**
 * Re-synchronize all unsynced contacts to the Backlink Engine.
 * Skips contacts with email provider domains (gmail, hotmail, etc.).
 *
 * Usage: php artisan backlink:resync [--force] [--type=consulat]
 */
class ResyncBacklinkEngine extends Command
{
    protected $signature = 'backlink:resync
                            {--force : Re-sync ALL contacts, even already synced}
                            {--type= : Only sync a specific contact_type}
                            {--only= : Limit to one table: influenceurs|press|lawyers|businesses|web-contacts}
                            {--limit=0 : Cap le nombre de rows traitées par table (0 = illimité)}
                            {--dry-run : Count without sending}';

    protected $description = 'Re-synchronize contacts to the Backlink Engine webhook';

    private const JUNK_EMAIL_DOMAINS = [
        'gmail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com', 'hotmail.fr',
        'outlook.com', 'outlook.fr', 'live.com', 'live.fr', 'aol.com',
        'wanadoo.fr', 'orange.fr', 'free.fr', 'sfr.fr', 'laposte.net',
        'icloud.com', 'me.com', 'mac.com', 'protonmail.com', 'proton.me',
        'ymail.com', 'mail.com', 'gmx.com', 'gmx.fr', 'zoho.com',
        'msn.com', 'comcast.net', 'att.net', 'verizon.net',
    ];

    public function handle(): int
    {
        $force  = $this->option('force');
        $type   = $this->option('type');
        $only   = $this->option('only');
        $limit  = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('=== Backlink Engine Re-Sync ===');
        if ($only) {
            $this->line("  Table filter: {$only}");
        }
        if ($limit > 0) {
            $this->line("  Per-table limit: {$limit}");
        }

        $runTable = fn (string $key) => !$only || $only === $key;

        $sent = $skipped = $errors = 0;
        $pSent = $pSkipped = $pErrors = 0;
        $lSent = $lSkipped = $lErrors = 0;
        $bSent = $bSkipped = $bErrors = 0;
        $cSent = $cSkipped = $cErrors = 0;

        if (!$runTable('influenceurs')) {
            goto skip_influenceurs;
        }

        // ── Influenceurs ──────────────────────────────────────────────
        $query = Influenceur::query()->whereNotNull('email');

        if (! $force) {
            $query->whereNull('backlink_synced_at');
        }
        if ($type) {
            $query->where('contact_type', $type);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }
        $total = $query->count();
        $this->info("Influenceurs à synchro: {$total}");

        $query->chunk(100, function ($chunk) use (&$sent, &$skipped, &$errors, $dryRun) {
        foreach ($chunk as $contact) {
            $contactType = $contact->contact_type instanceof \App\Enums\ContactType
                ? $contact->contact_type->value
                : (string) $contact->contact_type;

            if (! BacklinkEngineWebhookService::isSyncable($contactType)) {
                $skipped++;
                continue;
            }

            // Skip junk email domains
            $emailDomain = strtolower(explode('@', $contact->email)[1] ?? '');
            if (in_array($emailDomain, self::JUNK_EMAIL_DOMAINS)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $sent++;
                continue;
            }

            $synced = BacklinkEngineWebhookService::sendContactCreated([
                'email'        => $contact->email,
                'name'         => $contact->name,
                'firstName'    => $contact->first_name,
                'lastName'     => $contact->last_name,
                'type'         => $contactType,
                'publication'  => $contact->company,
                'country'      => $contact->country,
                'language'     => $contact->language,
                'source_url'   => $contact->website_url ?? $contact->profile_url,
                'source_table' => 'influenceurs',
                'source_id'    => $contact->id,
            ]);

            if ($synced) {
                $contact->updateQuietly(['backlink_synced_at' => now()]);
                $sent++;
            } else {
                $errors++;
            }

            // Small delay to avoid overwhelming the webhook
            usleep(100_000); // 100ms
        }
        }); // end chunk

        $this->line("  Sent: {$sent} | Skipped: {$skipped} | Errors: {$errors}");

        skip_influenceurs:

        if (!$runTable('press')) {
            goto skip_press;
        }

        // ── Press Contacts ────────────────────────────────────────────
        $pressQuery = PressContact::query()->whereNotNull('email');
        if (! $force) {
            $pressQuery->whereNull('backlink_synced_at');
        }
        if ($limit > 0) {
            $pressQuery->limit($limit);
        }

        $pTotal = $pressQuery->count();
        $this->info("Press contacts à synchro: {$pTotal}");

        $pressQuery->chunk(100, function ($chunk) use (&$pSent, &$pSkipped, &$pErrors, $dryRun) {
        foreach ($chunk as $pc) {
            $emailDomain = strtolower(explode('@', $pc->email)[1] ?? '');
            if (in_array($emailDomain, self::JUNK_EMAIL_DOMAINS)) {
                $pSkipped++;
                continue;
            }

            if ($dryRun) {
                $pSent++;
                continue;
            }

            $synced = BacklinkEngineWebhookService::sendContactCreated([
                'email'        => $pc->email,
                'name'         => $pc->full_name,
                'firstName'    => $pc->first_name,
                'lastName'     => $pc->last_name,
                'type'         => 'presse',
                'publication'  => $pc->publication,
                'country'      => $pc->country,
                'language'     => $pc->language,
                'source_url'   => $pc->source_url ?? $pc->profile_url,
                'source_table' => 'press_contacts',
                'source_id'    => $pc->id,
            ]);

            if ($synced) {
                $pc->updateQuietly(['backlink_synced_at' => now()]);
                $pSent++;
            } else {
                $pErrors++;
            }

            usleep(100_000);
        }
        }); // end chunk

        $this->line("  Sent: {$pSent} | Skipped: {$pSkipped} | Errors: {$pErrors}");

        skip_press:

        if (!$runTable('lawyers')) {
            goto skip_lawyers;
        }

        // ── Lawyers ───────────────────────────────────────────────────
        $lawyerQuery = Lawyer::query()->whereNotNull('email');
        if (! $force) {
            $lawyerQuery->whereNull('backlink_synced_at');
        }
        if ($limit > 0) {
            $lawyerQuery->limit($limit);
        }

        $lTotal = $lawyerQuery->count();
        $this->info("Lawyers à synchro: {$lTotal}");

        $lawyerQuery->chunk(100, function ($chunk) use (&$lSent, &$lSkipped, &$lErrors, $dryRun) {
            foreach ($chunk as $lw) {
                $emailDomain = strtolower(explode('@', $lw->email)[1] ?? '');
                if (in_array($emailDomain, self::JUNK_EMAIL_DOMAINS)) {
                    $lSkipped++;
                    continue;
                }
                if ($dryRun) {
                    $lSent++;
                    continue;
                }

                $synced = BacklinkEngineWebhookService::sendContactCreated([
                    'email'        => $lw->email,
                    'name'         => $lw->full_name,
                    'firstName'    => $lw->first_name,
                    'lastName'     => $lw->last_name,
                    'type'         => 'avocat',
                    'publication'  => $lw->firm_name,
                    'country'      => $lw->country,
                    'language'     => $lw->language,
                    'source_url'   => $lw->website ?? $lw->source_url,
                    'source_table' => 'lawyers',
                    'source_id'    => $lw->id,
                ]);

                if ($synced) {
                    $lw->updateQuietly(['backlink_synced_at' => now()]);
                    $lSent++;
                } else {
                    $lErrors++;
                }
                usleep(100_000);
            }
        });

        $this->line("  Sent: {$lSent} | Skipped: {$lSkipped} | Errors: {$lErrors}");

        skip_lawyers:

        if (!$runTable('businesses')) {
            goto skip_businesses;
        }

        // ── Content Businesses ────────────────────────────────────────
        $bizQuery = ContentBusiness::query()->whereNotNull('contact_email');
        if (! $force) {
            $bizQuery->whereNull('backlink_synced_at');
        }
        if ($limit > 0) {
            $bizQuery->limit($limit);
        }

        $bTotal = $bizQuery->count();
        $this->info("Content businesses à synchro: {$bTotal}");

        $bizQuery->chunk(100, function ($chunk) use (&$bSent, &$bSkipped, &$bErrors, $dryRun) {
            foreach ($chunk as $biz) {
                $emailDomain = strtolower(explode('@', $biz->contact_email)[1] ?? '');
                if (in_array($emailDomain, self::JUNK_EMAIL_DOMAINS)) {
                    $bSkipped++;
                    continue;
                }
                if ($dryRun) {
                    $bSent++;
                    continue;
                }

                $synced = BacklinkEngineWebhookService::sendContactCreated([
                    'email'        => $biz->contact_email,
                    'name'         => $biz->contact_name ?: $biz->name,
                    'type'         => 'partenaire',
                    'publication'  => $biz->name,
                    'country'      => $biz->country,
                    'language'     => $biz->language,
                    'source_url'   => $biz->website ?? $biz->url,
                    'source_table' => 'content_businesses',
                    'source_id'    => $biz->id,
                ]);

                if ($synced) {
                    $biz->updateQuietly(['backlink_synced_at' => now()]);
                    $bSent++;
                } else {
                    $bErrors++;
                }
                usleep(100_000);
            }
        });

        $this->line("  Sent: {$bSent} | Skipped: {$bSkipped} | Errors: {$bErrors}");

        skip_businesses:

        if (!$runTable('web-contacts')) {
            goto skip_web_contacts;
        }

        // ── Content Contacts (web/communautés) ────────────────────────
        $webQuery = ContentContact::query()->whereNotNull('email');
        if (! $force) {
            $webQuery->whereNull('backlink_synced_at');
        }
        if ($limit > 0) {
            $webQuery->limit($limit);
        }

        $cTotal = $webQuery->count();
        $this->info("Content contacts (web) à synchro: {$cTotal}");

        $resolver = new ContentContactObserver();
        // Utilise la même méthode resolveType que l'observer via reflection
        // (pour garder un seul mapping source de vérité).
        $typeResolver = function (?string $sector) use ($resolver): string {
            $method = new \ReflectionMethod($resolver, 'resolveType');
            $method->setAccessible(true);
            return $method->invoke($resolver, $sector);
        };

        $webQuery->chunk(100, function ($chunk) use (&$cSent, &$cSkipped, &$cErrors, $dryRun, $typeResolver) {
            foreach ($chunk as $c) {
                $emailDomain = strtolower(explode('@', $c->email)[1] ?? '');
                if (in_array($emailDomain, self::JUNK_EMAIL_DOMAINS)) {
                    $cSkipped++;
                    continue;
                }
                if ($dryRun) {
                    $cSent++;
                    continue;
                }

                $synced = BacklinkEngineWebhookService::sendContactCreated([
                    'email'        => $c->email,
                    'name'         => $c->name,
                    'type'         => $typeResolver($c->sector),
                    'publication'  => $c->company,
                    'country'      => $c->country,
                    'language'     => $c->language,
                    'source_url'   => $c->company_url ?? $c->page_url,
                    'source_table' => 'content_contacts',
                    'source_id'    => $c->id,
                ]);

                if ($synced) {
                    $c->updateQuietly(['backlink_synced_at' => now()]);
                    $cSent++;
                } else {
                    $cErrors++;
                }
                usleep(100_000);
            }
        });

        $this->line("  Sent: {$cSent} | Skipped: {$cSkipped} | Errors: {$cErrors}");

        skip_web_contacts:

        $this->newLine();
        $this->info("TOTAL: " . ($sent + $pSent + $lSent + $bSent + $cSent) . " envoyés, "
            . ($skipped + $pSkipped + $lSkipped + $bSkipped + $cSkipped) . " ignorés, "
            . ($errors + $pErrors + $lErrors + $bErrors + $cErrors) . " erreurs");

        return Command::SUCCESS;
    }
}
