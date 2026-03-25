<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateOutreachEmailJob;
use App\Models\Influenceur;
use App\Models\OutreachConfig;
use App\Models\OutreachEmail;
use App\Models\OutreachSequence;
use App\Models\WarmupState;
use App\Services\AiEmailGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutreachController extends Controller
{
    // ============================================================
    // Config per type
    // ============================================================

    public function configs()
    {
        $configs = OutreachConfig::orderBy('contact_type')->get();
        return response()->json($configs);
    }

    public function updateConfig(Request $request, string $contactType)
    {
        $data = $request->validate([
            'auto_send'             => 'sometimes|boolean',
            'ai_generation_enabled' => 'sometimes|boolean',
            'max_steps'             => 'sometimes|integer|min:1|max:4',
            'step_delays'           => 'sometimes|array|min:1|max:4',
            'daily_limit'           => 'sometimes|integer|min:1|max:200',
            'is_active'             => 'sometimes|boolean',
            'calendly_url'          => 'sometimes|nullable|url|max:500',
            'calendly_step'         => 'sometimes|nullable|integer|min:1|max:4',
            'custom_prompt'         => 'sometimes|nullable|string|max:5000',
            'from_name'             => 'sometimes|nullable|string|max:100',
        ]);

        $config = OutreachConfig::getFor($contactType);
        $config->update($data);

        return response()->json($config);
    }

    // ============================================================
    // Email generation
    // ============================================================

    public function generate(Request $request)
    {
        $data = $request->validate([
            'contact_type' => 'required|string',
            'country'      => 'nullable|string',
            'step'         => 'sometimes|integer|min:1|max:4',
            'limit'        => 'sometimes|integer|min:1|max:50',
        ]);

        $step = $data['step'] ?? 1;
        $limit = $data['limit'] ?? 20;

        // Find contacts eligible for email generation
        $query = Influenceur::where('contact_type', $data['contact_type'])
            ->whereNotNull('email')
            ->where('email_verified_status', '!=', 'invalid')
            ->whereDoesntHave('outreachEmails', fn($q) => $q->where('step', $step)->whereNotIn('status', ['failed']));

        if (!empty($data['country'])) {
            $query->where('country', $data['country']);
        }

        $ids = $query->limit($limit)->pluck('id')->toArray();

        // Dispatch jobs
        foreach ($ids as $id) {
            GenerateOutreachEmailJob::dispatch($id, $step);
        }

        return response()->json([
            'message'    => count($ids) . " emails en cours de génération (step {$step}).",
            'dispatched' => count($ids),
        ]);
    }

    public function generateOne(Request $request, Influenceur $influenceur)
    {
        $step = $request->query('step', 1);
        $service = app(AiEmailGenerationService::class);
        $email = $service->generate($influenceur, (int) $step);

        if (!$email) {
            return response()->json(['message' => 'Impossible de générer l\'email.'], 422);
        }

        return response()->json($email->load('influenceur'));
    }

    // ============================================================
    // Review queue
    // ============================================================

    public function reviewQueue(Request $request)
    {
        $query = OutreachEmail::with('influenceur:id,name,email,contact_type,country')
            ->where('status', 'pending_review');

        if ($request->query('contact_type')) {
            $query->whereHas('influenceur', fn($q) => $q->where('contact_type', $request->query('contact_type')));
        }

        $emails = $query->orderBy('created_at')->paginate(30);
        return response()->json($emails);
    }

    public function approve(OutreachEmail $outreachEmail)
    {
        if ($outreachEmail->status !== 'pending_review' && $outreachEmail->status !== 'generated') {
            return response()->json(['message' => 'Cet email ne peut pas être approuvé.'], 422);
        }

        $outreachEmail->update(['status' => 'approved']);
        return response()->json(['message' => 'Email approuvé.', 'email' => $outreachEmail]);
    }

    public function reject(OutreachEmail $outreachEmail)
    {
        $outreachEmail->update(['status' => 'failed', 'error_message' => 'Rejeté par admin']);
        return response()->json(['message' => 'Email rejeté.']);
    }

    public function edit(Request $request, OutreachEmail $outreachEmail)
    {
        $data = $request->validate([
            'subject'   => 'sometimes|string|max:500',
            'body_html' => 'sometimes|string',
            'body_text' => 'sometimes|string',
        ]);

        $outreachEmail->update(array_merge($data, ['status' => 'approved']));
        return response()->json(['message' => 'Email modifié et approuvé.', 'email' => $outreachEmail]);
    }

    public function approveBatch(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:outreach_emails,id',
        ]);

        $count = OutreachEmail::whereIn('id', $data['ids'])
            ->where('status', 'pending_review')
            ->update(['status' => 'approved']);

        return response()->json(['message' => "{$count} emails approuvés."]);
    }

    // ============================================================
    // Stats
    // ============================================================

    public function stats()
    {
        $stats = OutreachEmail::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending_review,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'sent' OR status = 'delivered' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'opened' THEN 1 ELSE 0 END) as opened,
            SUM(CASE WHEN status = 'clicked' THEN 1 ELSE 0 END) as clicked,
            SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied,
            SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
            SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed
        ")->first();

        // By step
        $byStep = OutreachEmail::selectRaw("step, COUNT(*) as total, SUM(CASE WHEN status IN ('sent','delivered','opened','clicked','replied') THEN 1 ELSE 0 END) as sent")
            ->groupBy('step')->orderBy('step')->get();

        // By type
        $byType = OutreachEmail::join('influenceurs', 'outreach_emails.influenceur_id', '=', 'influenceurs.id')
            ->selectRaw("influenceurs.contact_type, COUNT(*) as total, SUM(CASE WHEN outreach_emails.status IN ('sent','delivered','opened','clicked','replied') THEN 1 ELSE 0 END) as sent")
            ->groupBy('influenceurs.contact_type')->get();

        // Warmup
        $warmup = WarmupState::all();

        return response()->json([
            'global'  => $stats,
            'by_step' => $byStep,
            'by_type' => $byType,
            'warmup'  => $warmup,
        ]);
    }

    // ============================================================
    // Tracking (public, no auth)
    // ============================================================

    public function trackOpen(string $trackingId)
    {
        OutreachEmail::where('tracking_id', $trackingId)
            ->whereNull('opened_at')
            ->update(['opened_at' => now(), 'status' => 'opened']);

        // Return 1x1 transparent pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        return response($pixel, 200)->header('Content-Type', 'image/gif')->header('Cache-Control', 'no-store');
    }

    public function trackClick(Request $request, string $trackingId)
    {
        $url = $request->query('url', 'https://www.sos-expat.com');

        OutreachEmail::where('tracking_id', $trackingId)
            ->whereNull('clicked_at')
            ->update(['clicked_at' => now(), 'status' => 'clicked']);

        return redirect($url);
    }

    public function unsubscribePage(string $token)
    {
        $email = OutreachEmail::where('unsubscribe_token', $token)->first();
        if (!$email) return response('Lien invalide.', 404);

        return response()->view('unsubscribe', ['email' => $email, 'token' => $token]);
    }

    public function unsubscribeConfirm(string $token)
    {
        $email = OutreachEmail::where('unsubscribe_token', $token)->first();
        if (!$email) return response()->json(['message' => 'Lien invalide.'], 404);

        // Mark email as unsubscribed
        $email->update(['status' => 'unsubscribed']);

        // Stop the sequence
        OutreachSequence::where('influenceur_id', $email->influenceur_id)
            ->where('status', 'active')
            ->update(['status' => 'stopped', 'stop_reason' => 'unsubscribed']);

        // Mark contact as refused
        Influenceur::where('id', $email->influenceur_id)->update(['status' => 'refused']);

        return response()->json(['message' => 'Désinscription confirmée.']);
    }

    // ============================================================
    // Sequences management
    // ============================================================

    public function sequences(Request $request)
    {
        $query = OutreachSequence::with('influenceur:id,name,email,contact_type,country');

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        $sequences = $query->orderByDesc('updated_at')->paginate(30);
        return response()->json($sequences);
    }

    public function pauseSequence(OutreachSequence $sequence)
    {
        $sequence->update(['status' => 'paused']);
        return response()->json(['message' => 'Séquence en pause.']);
    }

    public function resumeSequence(OutreachSequence $sequence)
    {
        $sequence->update(['status' => 'active']);
        return response()->json(['message' => 'Séquence reprise.']);
    }

    public function stopSequence(OutreachSequence $sequence)
    {
        $sequence->update(['status' => 'stopped', 'stop_reason' => 'manual']);
        return response()->json(['message' => 'Séquence arrêtée.']);
    }

    // ============================================================
    // Domain health & alerts
    // ============================================================

    public function domainHealth()
    {
        $health = \App\Models\DomainHealth::all();
        $warmup = WarmupState::all();

        return response()->json([
            'domains' => $health,
            'warmup'  => $warmup,
        ]);
    }

    public function alerts()
    {
        $alerts = [];

        // Check bounce rate per domain
        $domains = \App\Models\DomainHealth::all();
        foreach ($domains as $d) {
            if ($d->bounce_rate > 10) {
                $alerts[] = ['type' => 'critical', 'domain' => $d->domain, 'message' => "Bounce rate {$d->bounce_rate}% — domaine en danger"];
            } elseif ($d->bounce_rate > 5) {
                $alerts[] = ['type' => 'warning', 'domain' => $d->domain, 'message' => "Bounce rate {$d->bounce_rate}% — surveiller"];
            }
            if ($d->is_blacklisted) {
                $alerts[] = ['type' => 'critical', 'domain' => $d->domain, 'message' => "Domaine blacklisté !"];
            }
        }

        // Check failed emails in last 24h
        $failedCount = OutreachEmail::where('status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();
        if ($failedCount > 10) {
            $alerts[] = ['type' => 'warning', 'message' => "{$failedCount} emails échoués dans les 24h"];
        }

        return response()->json($alerts);
    }

    // ============================================================
    // Webhook: PMTA bounce
    // ============================================================

    public function pmtaBounce(Request $request)
    {
        $data = $request->all();
        $messageId = $data['message_id'] ?? $data['tracking_id'] ?? null;
        $bounceType = $data['bounce_type'] ?? 'hard';
        $reason = $data['reason'] ?? $data['diagnostic'] ?? 'Unknown';

        if (!$messageId) {
            return response()->json(['message' => 'Missing message_id'], 400);
        }

        $email = OutreachEmail::where('tracking_id', $messageId)
            ->orWhere('external_id', $messageId)
            ->first();

        if (!$email) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        // Update email status
        $email->update([
            'status'        => 'bounced',
            'bounced_at'    => now(),
            'bounce_type'   => $bounceType === 'soft' ? 'soft' : 'hard',
            'bounce_reason' => mb_substr($reason, 0, 255),
        ]);

        // Record event
        \App\Models\EmailEvent::create([
            'outreach_email_id' => $email->id,
            'event_type'        => 'bounced',
            'metadata'          => ['type' => $bounceType, 'reason' => $reason],
            'occurred_at'       => now(),
        ]);

        // Hard bounce: stop sequence + mark email invalid
        if ($bounceType !== 'soft') {
            OutreachSequence::where('influenceur_id', $email->influenceur_id)
                ->where('status', 'active')
                ->update(['status' => 'stopped', 'stop_reason' => 'hard_bounce']);

            Influenceur::where('id', $email->influenceur_id)
                ->update(['email_verified_status' => 'invalid']);
        }

        return response()->json(['message' => 'Bounce processed.']);
    }
}
