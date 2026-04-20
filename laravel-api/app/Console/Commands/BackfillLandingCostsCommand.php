<?php

namespace App\Console\Commands;

use App\Models\ApiCost;
use App\Models\LandingCampaign;
use App\Models\LandingPage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattache les api_costs orphelins (costable_type/costable_id = NULL) aux
 * landing_pages créées dans la même fenêtre temporelle, puis recalcule
 * generation_cost_cents + landing_campaigns.total_cost_cents.
 *
 * Contexte : avant le fix du 2026-04-20, LandingGenerationService ne passait
 * pas costable_type/costable_id aux appels OpenAI/Claude, laissant les
 * ApiCost orphelins. Cette commande les rattache par proximité temporelle.
 */
class BackfillLandingCostsCommand extends Command
{
    protected $signature = 'landings:backfill-costs
        {--start=2026-04-15 : Date début (YYYY-MM-DD, inclus)}
        {--end=2026-04-16 : Date fin (YYYY-MM-DD, exclu)}
        {--dry-run : Affiche les assignments sans écrire}';

    protected $description = 'Rattache les api_costs orphelins aux landing_pages et recalcule les coûts agrégés.';

    public function handle(): int
    {
        $start = (string) $this->option('start');
        $end   = (string) $this->option('end');
        $dry   = (bool) $this->option('dry-run');

        $this->info("Fenêtre : [{$start}, {$end})" . ($dry ? ' — DRY-RUN' : ''));

        $orphans = ApiCost::whereNull('costable_type')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<',  $end)
            ->orderBy('created_at')
            ->get(['id', 'created_at', 'cost_cents']);

        $this->info("Orphans à rattacher : {$orphans->count()}");
        if ($orphans->isEmpty()) {
            return self::SUCCESS;
        }

        $lps = LandingPage::where('generation_source', 'ai_generated')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<',  $end)
            ->orderBy('created_at')
            ->get(['id', 'created_at', 'audience_type']);

        $this->info("LPs candidates : {$lps->count()}");
        if ($lps->isEmpty()) {
            $this->warn('Aucune LP dans la fenêtre — abort.');
            return self::SUCCESS;
        }

        // Attribution par proximité temporelle : chaque orphan est rattaché
        // à la LP dont created_at est >= orphan.created_at la plus proche.
        // Les orphans postérieurs à la dernière LP sont rattachés à elle.
        $assignments = []; // [lpId => int[] costIds]
        $lpIdx = 0;
        $lpsArr = $lps->values();
        $lpsCount = $lpsArr->count();

        foreach ($orphans as $orphan) {
            while ($lpIdx < $lpsCount - 1 && $lpsArr[$lpIdx]->created_at < $orphan->created_at) {
                $lpIdx++;
            }
            // Si on dépasse la dernière LP, on s'y rattache quand même.
            $target = $lpsArr[$lpIdx] ?? $lpsArr->last();
            $assignments[$target->id][] = $orphan->id;
        }

        $totalOrphanCents = (int) $orphans->sum('cost_cents');
        $lpCovered = count($assignments);
        $this->info("LPs qui recevront des costs : {$lpCovered} / {$lpsCount}");
        $this->info("Montant total rattaché : " . number_format($totalOrphanCents / 100, 2) . ' $');

        if ($dry) {
            $this->line('');
            $this->line('Aperçu (10 premières) :');
            $i = 0;
            foreach ($assignments as $lpId => $costIds) {
                if ($i++ >= 10) break;
                $sum = ApiCost::whereIn('id', $costIds)->sum('cost_cents');
                $this->line("  LP {$lpId} → " . count($costIds) . ' costs, ' . number_format($sum / 100, 4) . ' $');
            }
            return self::SUCCESS;
        }

        DB::transaction(function () use ($assignments) {
            foreach ($assignments as $lpId => $costIds) {
                ApiCost::whereIn('id', $costIds)->update([
                    'costable_type' => LandingPage::class,
                    'costable_id'   => $lpId,
                ]);

                $total = (int) ApiCost::where('costable_type', LandingPage::class)
                    ->where('costable_id', $lpId)
                    ->sum('cost_cents');

                LandingPage::where('id', $lpId)->update([
                    'generation_cost_cents' => $total,
                ]);
            }

            $byAudience = LandingPage::where('generation_source', 'ai_generated')
                ->selectRaw('audience_type, SUM(generation_cost_cents) AS total')
                ->groupBy('audience_type')
                ->pluck('total', 'audience_type');

            foreach ($byAudience as $audience => $total) {
                LandingCampaign::where('audience_type', $audience)->update([
                    'total_cost_cents' => (int) $total,
                ]);
            }
        });

        $this->info('✅ Rétro-fix terminé.');
        return self::SUCCESS;
    }
}
