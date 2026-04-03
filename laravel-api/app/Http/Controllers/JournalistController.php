<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapePressPublicationJob;
use App\Jobs\ScrapePublicationAuthorsJob;
use App\Models\PressContact;
use App\Models\PressPublication;
use App\Services\EmailInferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class JournalistController extends Controller
{
    // ─── PUBLICATIONS ─────────────────────────────────────────────────────

    public function publications(): JsonResponse
    {
        $pubs = PressPublication::withCount('contacts')
            ->orderBy('media_type')
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'slug'            => $p->slug,
                'base_url'        => $p->base_url,
                'team_url'        => $p->team_url,
                'media_type'      => $p->media_type,
                'topics'          => $p->topics,
                'country'         => $p->country,
                'contacts_count'  => $p->contacts_count,
                'status'          => $p->status,
                'last_scraped_at' => $p->last_scraped_at?->toDateTimeString(),
                'last_error'      => $p->last_error,
            ]);

        $stats = [
            'total'          => $pubs->count(),
            'scraped'        => $pubs->where('status', 'scraped')->count(),
            'pending'        => $pubs->where('status', 'pending')->count(),
            'failed'         => $pubs->where('status', 'failed')->count(),
            'total_contacts' => $pubs->sum('contacts_count'),
        ];

        return response()->json(['publications' => $pubs, 'stats' => $stats]);
    }

    public function storePublication(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'base_url'    => 'required|url',
            'team_url'    => 'nullable|url',
            'contact_url' => 'nullable|url',
            'media_type'  => 'required|in:presse_ecrite,web,tv,radio',
            'topics'      => 'required|array',
            'country'     => 'nullable|string|max:100',
        ]);

        $slug = Str::slug($data['name']);
        if (PressPublication::where('slug', $slug)->exists()) {
            return response()->json(['message' => 'Publication déjà existante'], 409);
        }

        $pub = PressPublication::create(array_merge($data, ['slug' => $slug, 'status' => 'pending']));
        return response()->json($pub, 201);
    }

    public function scrapePublications(Request $request): JsonResponse
    {
        $pubId = $request->input('publication_id');

        if ($pubId) {
            $pub = PressPublication::findOrFail($pubId);
            ScrapePressPublicationJob::dispatch($pub->id);
            return response()->json(['message' => "Scraping lancé pour {$pub->name}", 'queued' => 1]);
        }

        $pubs = PressPublication::whereIn('status', ['pending', 'failed'])->get();
        $delay = 0;
        foreach ($pubs as $pub) {
            ScrapePressPublicationJob::dispatch($pub->id)->delay(now()->addSeconds($delay));
            $delay += 5;
        }
        return response()->json(['message' => "{$pubs->count()} publications envoyées en queue", 'queued' => $pubs->count()]);
    }

    /**
     * Launch deep author/byline scraping (+ email inference) for one or all publications.
     * Uses ScrapePublicationAuthorsJob which reads authors_url + articles_url.
     */
    public function scrapeAuthors(Request $request): JsonResponse
    {
        $pubId       = $request->input('publication_id');
        $inferEmails = $request->boolean('infer_emails', true);
        $maxPages    = (int) $request->input('max_article_pages', 5);

        if ($pubId) {
            $pub = PressPublication::findOrFail($pubId);
            ScrapePublicationAuthorsJob::dispatch($pub->id, $inferEmails, $maxPages);
            return response()->json(['message' => "Scraping auteurs lancé pour {$pub->name}", 'queued' => 1]);
        }

        // All publications that have at least authors_url or articles_url configured
        $pubs = PressPublication::where(function ($q) {
            $q->whereNotNull('authors_url')->orWhereNotNull('articles_url');
        })->get();

        $delay = 0;
        foreach ($pubs as $pub) {
            ScrapePublicationAuthorsJob::dispatch($pub->id, $inferEmails, $maxPages)
                ->delay(now()->addSeconds($delay));
            $delay += 10; // slightly more spacing for heavy jobs
        }

        return response()->json([
            'message' => "{$pubs->count()} publications en queue pour scraping auteurs",
            'queued'  => $pubs->count(),
        ]);
    }

    /**
     * Run email inference only (no scraping) for one or all publications.
     */
    public function inferEmails(Request $request, EmailInferenceService $svc): JsonResponse
    {
        $pubId = $request->input('publication_id');

        if ($pubId) {
            $pub    = PressPublication::findOrFail($pubId);
            $result = $svc->inferForPublication($pub);
            Cache::forget('journalist-stats');
            return response()->json(array_merge(['publication' => $pub->name], $result));
        }

        $pubs = PressPublication::whereNotNull('email_pattern')
            ->whereNotNull('email_domain')
            ->get();

        $totals = ['inferred' => 0, 'skipped' => 0, 'publications' => $pubs->count()];
        foreach ($pubs as $pub) {
            $r = $svc->inferForPublication($pub);
            $totals['inferred'] += $r['inferred'];
            $totals['skipped']  += $r['skipped'];
        }

        Cache::forget('journalist-stats');
        return response()->json($totals);
    }

    /**
     * Update scraping config (authors_url, articles_url, email_pattern, email_domain) for a publication.
     */
    public function updatePublicationConfig(Request $request, int $id): JsonResponse
    {
        $pub  = PressPublication::findOrFail($id);
        $data = $request->validate([
            'authors_url'   => 'nullable|url|max:500',
            'articles_url'  => 'nullable|url|max:500',
            'email_pattern' => 'nullable|string|max:100',
            'email_domain'  => 'nullable|string|max:100',
            'team_url'      => 'nullable|url|max:500',
            'contact_url'   => 'nullable|url|max:500',
        ]);
        $pub->update($data);
        return response()->json($pub);
    }

    // ─── CONTACTS ─────────────────────────────────────────────────────────

    public function contacts(Request $request): JsonResponse
    {
        $q = PressContact::query();

        if ($search = $request->input('search')) {
            $q->where(function ($sq) use ($search) {
                $sq->where('full_name', 'ilike', "%{$search}%")
                   ->orWhere('email', 'ilike', "%{$search}%")
                   ->orWhere('publication', 'ilike', "%{$search}%")
                   ->orWhere('role', 'ilike', "%{$search}%")
                   ->orWhere('beat', 'ilike', "%{$search}%");
            });
        }

        if ($mediaType = $request->input('media_type')) {
            $q->where('media_type', $mediaType);
        }
        if ($topic = $request->input('topic')) {
            $q->whereJsonContains('topics', $topic);
        }
        if ($status = $request->input('contact_status')) {
            $q->where('contact_status', $status);
        }
        if ($country = $request->input('country')) {
            $q->where('country', 'ilike', "%{$country}%");
        }
        if ($language = $request->input('language')) {
            $q->where('language', $language);
        }
        if ($request->boolean('with_email')) {
            $q->whereNotNull('email');
        }

        $perPage = min((int) $request->input('per_page', 50), 200);
        $paginated = $q->orderBy('publication')->orderBy('full_name')->paginate($perPage);

        return response()->json($paginated);
    }

    public function stats(): JsonResponse
    {
        $data = Cache::remember('journalist-stats', 300, function () {
            return [
                'total_contacts'      => PressContact::count(),
                'with_email'          => PressContact::whereNotNull('email')->count(),
                'with_phone'          => PressContact::whereNotNull('phone')->count(),
                'total_publications'  => PressPublication::count(),
                'by_media_type'       => PressContact::selectRaw('media_type, COUNT(*) as count')
                                            ->groupBy('media_type')->pluck('count', 'media_type'),
                'by_contact_status'   => PressContact::selectRaw('contact_status, COUNT(*) as count')
                                            ->groupBy('contact_status')->pluck('count', 'contact_status'),
                'top_publications'    => PressContact::selectRaw('publication, COUNT(*) as count')
                                            ->groupBy('publication')->orderByDesc('count')
                                            ->limit(10)->pluck('count', 'publication'),
                'pub_stats'           => [
                    'scraped' => PressPublication::where('status', 'scraped')->count(),
                    'pending' => PressPublication::where('status', 'pending')->count(),
                    'failed'  => PressPublication::where('status', 'failed')->count(),
                ],
            ];
        });

        return response()->json($data);
    }

    public function storeContact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'      => 'required|string|max:150',
            'first_name'     => 'nullable|string|max:80',
            'last_name'      => 'nullable|string|max:80',
            'email'          => 'nullable|email|max:200',
            'phone'          => 'nullable|string|max:30',
            'publication'    => 'required|string|max:150',
            'publication_id' => 'nullable|exists:press_publications,id',
            'role'           => 'nullable|string|max:150',
            'beat'           => 'nullable|string|max:150',
            'media_type'     => 'nullable|in:presse_ecrite,web,tv,radio',
            'topics'         => 'nullable|array',
            'linkedin'       => 'nullable|url|max:300',
            'twitter'        => 'nullable|string|max:200',
            'country'        => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:2000',
        ]);

        if (!empty($data['email'])) {
            $exists = PressContact::where('email', $data['email'])
                ->where('publication', $data['publication'])->exists();
            if ($exists) {
                return response()->json(['message' => 'Contact déjà existant'], 409);
            }
        }

        $contact = PressContact::create(array_merge($data, [
            'contact_status' => 'new',
            'scraped_at'     => now(),
        ]));

        Cache::forget('journalist-stats');
        return response()->json($contact, 201);
    }

    public function updateContact(Request $request, int $id): JsonResponse
    {
        $contact = PressContact::findOrFail($id);
        $data    = $request->validate([
            'first_name'     => 'nullable|string|max:80',
            'last_name'      => 'nullable|string|max:80',
            'full_name'      => 'nullable|string|max:150',
            'email'          => 'nullable|email|max:200',
            'phone'          => 'nullable|string|max:30',
            'role'           => 'nullable|string|max:150',
            'beat'           => 'nullable|string|max:150',
            'topics'         => 'nullable|array',
            'contact_status' => 'nullable|in:new,contacted,replied,won,lost',
            'notes'          => 'nullable|string|max:2000',
            'linkedin'       => 'nullable|url|max:300',
            'twitter'        => 'nullable|string|max:200',
        ]);

        $contact->update($data);
        Cache::forget('journalist-stats');
        return response()->json($contact);
    }

    public function deleteContact(int $id): JsonResponse
    {
        PressContact::findOrFail($id)->delete();
        Cache::forget('journalist-stats');
        return response()->json(null, 204);
    }

    public function exportContacts(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $q = PressContact::query();

        if ($mediaType = $request->input('media_type')) $q->where('media_type', $mediaType);
        if ($topic = $request->input('topic'))           $q->whereJsonContains('topics', $topic);
        if ($language = $request->input('language'))     $q->where('language', $language);
        if ($request->boolean('with_email'))             $q->whereNotNull('email');

        $contacts = $q->orderBy('publication')->orderBy('full_name')->get();
        $filename = 'journalistes-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($contacts) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Nom complet', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Publication', 'Rôle', 'Rubrique', 'Type', 'Langue', 'Pays', 'Twitter', 'LinkedIn', 'Statut', 'Notes'], ';');
            foreach ($contacts as $c) {
                fputcsv($h, [
                    $c->full_name, $c->first_name ?? '', $c->last_name ?? '',
                    $c->email ?? '', $c->phone ?? '', $c->publication,
                    $c->role ?? '', $c->beat ?? '', $c->media_type,
                    $c->language ?? '', $c->country,
                    $c->twitter ?? '', $c->linkedin ?? '',
                    $c->contact_status, $c->notes ?? '',
                ], ';');
            }
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
