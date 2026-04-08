<?php

namespace App\Http\Controllers;

use App\Enums\ContactType;
use App\Enums\PipelineStatus;
use App\Enums\Platform;
use App\Jobs\ScrapeContactJob;
use App\Jobs\ScrapeDirectoryJob;
use App\Models\ActivityLog;
use App\Models\Directory;
use App\Models\Influenceur;
use App\Services\BlockedDomainService;
use Illuminate\Http\Request;

class InfluenceurController extends Controller
{
    // =========================================================================
    // INDEX — Liste avec filtres complets + pagination curseur
    // =========================================================================

    public function index(Request $request)
    {
        $query = Influenceur::with(['assignedToUser:id,name', 'pendingReminder']);

        // --- Restriction chercheur : ne voit que ses propres contacts ---
        if ($request->user()->isResearcher()) {
            $query->where('created_by', $request->user()->id);
            if (!empty($request->user()->contact_types)) {
                $query->whereIn('contact_type', $request->user()->contact_types);
            }
        }

        // --- Filtres de classification ---
        if ($request->contact_type) {
            $query->where('contact_type', $request->contact_type);
        }
        if ($request->category) {
            $query->where('category', $request->category);
        }
        if ($request->contact_kind) {
            $query->where('contact_kind', $request->contact_kind);
        }

        // --- Filtres CRM ---
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->platform) {
            $query->whereJsonContains('platforms', $request->platform);
        }
        if ($request->assigned_to) {
            $query->where('assigned_to', $request->assigned_to);
        }
        if ($request->has_reminder) {
            $query->whereHas('pendingReminder');
        }

        // --- Filtres géographiques ---
        if ($request->country) {
            $query->where('country', $request->country);
        }
        if ($request->language) {
            $query->where('language', $request->language);
        }

        // --- Filtres de qualité contact ---
        if ($request->filled('has_email')) {
            $query->where('has_email', filter_var($request->has_email, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('has_phone')) {
            $query->where('has_phone', filter_var($request->has_phone, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('is_verified')) {
            $query->where('is_verified', filter_var($request->is_verified, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('unsubscribed')) {
            $query->where('unsubscribed', filter_var($request->unsubscribed, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->completeness_min) {
            $query->where('data_completeness', '>=', (int) $request->completeness_min);
        }
        if ($request->source) {
            $query->where('source', $request->source);
        }
        if ($request->filled('backlink_synced')) {
            $synced = filter_var($request->backlink_synced, FILTER_VALIDATE_BOOLEAN);
            $synced ? $query->whereNotNull('backlink_synced_at') : $query->whereNull('backlink_synced_at');
        }

        // --- Recherche full-text (nom, email, téléphone, entreprise, handle) ---
        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'ILIKE', "%{$s}%")
                  ->orWhere('email', 'ILIKE', "%{$s}%")
                  ->orWhere('phone', 'ILIKE', "%{$s}%")
                  ->orWhere('company', 'ILIKE', "%{$s}%")
                  ->orWhere('handle', 'ILIKE', "%{$s}%")
                  ->orWhere('website_url', 'ILIKE', "%{$s}%");
            });
        }

        $perPage = min((int) ($request->per_page ?? 30), 100);
        $results = $query->orderBy('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->cursor);

        return response()->json([
            'data'        => $results->items(),
            'next_cursor' => $results->nextCursor()?->encode(),
            'has_more'    => $results->hasMorePages(),
        ]);
    }

    // =========================================================================
    // STORE — Création d'un contact
    // =========================================================================

    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_type'         => 'sometimes|in:' . implode(',', \App\Models\ContactTypeModel::validValues()),
            'name'                 => 'required|string|max:255',
            'first_name'           => 'nullable|string|max:100',
            'last_name'            => 'nullable|string|max:100',
            'company'              => 'nullable|string|max:255',
            'position'             => 'nullable|string|max:255',
            'handle'               => 'nullable|string|max:255',
            'avatar_url'           => 'nullable|url|max:500',
            'platforms'            => 'sometimes|array|min:1',
            'platforms.*'          => 'string|in:' . implode(',', Platform::values()),
            'primary_platform'     => 'sometimes|string|max:50',
            'followers'            => 'nullable|integer|min:0',
            'followers_secondary'  => 'nullable|array',
            'niche'                => 'nullable|string|max:255',
            'country'              => 'nullable|string|max:100',
            'language'             => 'nullable|string|max:10',
            'timezone'             => 'nullable|string|max:50',
            'email'                => 'nullable|email',
            'phone'                => 'nullable|string|max:50',
            'profile_url'          => 'nullable|string|max:500',
            'website_url'          => 'nullable|string|max:500',
            'linkedin_url'         => 'nullable|url|max:500',
            'twitter_url'          => 'nullable|url|max:500',
            'facebook_url'         => 'nullable|url|max:500',
            'instagram_url'        => 'nullable|url|max:500',
            'tiktok_url'           => 'nullable|url|max:500',
            'youtube_url'          => 'nullable|url|max:500',
            'status'               => 'sometimes|in:' . implode(',', PipelineStatus::values()),
            'deal_value_cents'     => 'nullable|integer|min:0',
            'deal_probability'     => 'nullable|integer|min:0|max:100',
            'expected_close_date'  => 'nullable|date',
            'assigned_to'          => 'nullable|exists:users,id',
            'reminder_days'        => 'sometimes|integer|min:1|max:365',
            'notes'                => 'nullable|string',
            'tags'                 => 'nullable|array',
            'score'                => 'nullable|integer|min:0|max:1000',
            'source'               => 'nullable|string|max:100',
            'force_duplicate'      => 'sometimes|boolean',
        ]);

        $forceDuplicate = $data['force_duplicate'] ?? false;
        unset($data['force_duplicate']);

        $data['created_by'] = $request->user()->id;

        // Restriction chercheur : types assignés uniquement
        $user = $request->user();
        if ($user->isResearcher() && !empty($user->contact_types)) {
            $contactType = $data['contact_type'] ?? 'influenceur';
            if (!in_array($contactType, $user->contact_types)) {
                return response()->json(['message' => 'Vous n\'êtes pas autorisé à créer ce type de contact.'], 403);
            }
        }

        // Interception : URLs d'annuaires → table directories
        $urlToCheck = $data['profile_url'] ?? $data['website_url'] ?? null;
        if (BlockedDomainService::isScrapableDirectory($urlToCheck)) {
            $domain = Directory::extractDomain($urlToCheck);
            $contactType = $data['contact_type'] ?? 'ecole';

            $existingDir = Directory::where('domain', $domain)
                ->where('category', $contactType)
                ->first();

            if (!$existingDir) {
                $dir = Directory::create([
                    'name'       => $data['name'],
                    'url'        => $urlToCheck,
                    'domain'     => $domain,
                    'category'   => $contactType,
                    'country'    => $data['country'] ?? null,
                    'language'   => $data['language'] ?? null,
                    'status'     => 'pending',
                    'notes'      => 'Ajouté manuellement',
                    'created_by' => $request->user()->id,
                ]);
                ScrapeDirectoryJob::dispatch($dir->id);

                return response()->json([
                    'redirected_to_directory' => true,
                    'message'     => "URL d'annuaire détectée ! Ajouté dans Annuaires & Répertoires. Le scraping va extraire les contacts individuels.",
                    'directory'   => $dir,
                ], 201);
            }

            return response()->json([
                'redirected_to_directory' => true,
                'message'     => "Cet annuaire existe déjà dans la section Annuaires.",
                'directory'   => $existingDir,
            ], 409);
        }

        // Normalisation du domaine de profil
        if (!empty($data['profile_url'])) {
            $data['profile_url_domain'] = self::normalizeProfileUrl($data['profile_url']);
        }

        // Détection de doublon sur email (priorité absolue)
        $duplicateWarning = null;
        if (!empty($data['email']) && !$forceDuplicate) {
            $existing = Influenceur::whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])
                ->whereNull('deleted_at')
                ->first();
            if ($existing) {
                return response()->json([
                    'warning'       => 'duplicate_email',
                    'message'       => "Un contact avec cet email existe déjà : {$existing->name} (ID {$existing->id}).",
                    'existing_id'   => $existing->id,
                    'existing_name' => $existing->name,
                    'existing_type' => $existing->contact_type,
                    'email'         => $data['email'],
                ], 409);
            }
        }

        // Détection de doublon sur profile_url_domain
        if (!empty($data['profile_url_domain'])) {
            $existing = Influenceur::where('profile_url_domain', $data['profile_url_domain'])
                ->whereNull('deleted_at')->first();
            if ($existing && !$forceDuplicate) {
                return response()->json([
                    'warning'             => 'duplicate_detected',
                    'message'             => "Un contact avec un profil similaire existe déjà : {$existing->name} (ID {$existing->id}).",
                    'existing_id'         => $existing->id,
                    'existing_name'       => $existing->name,
                    'profile_url_domain'  => $data['profile_url_domain'],
                ], 409);
            }
            if ($existing && $forceDuplicate) {
                $duplicateWarning = "Doublon créé malgré l'existant : {$existing->name} (ID {$existing->id}).";
            }
        }

        if (($data['status'] ?? 'prospect') === 'active') {
            $data['partnership_date'] = $data['partnership_date'] ?? now()->toDateString();
        }

        $influenceur = Influenceur::create($data);

        ActivityLog::create([
            'user_id'         => $request->user()->id,
            'influenceur_id'  => $influenceur->id,
            'action'          => 'created',
            'details'         => ['name' => $influenceur->name, 'type' => $influenceur->contact_type],
        ]);

        $response = $influenceur->load('assignedToUser:id,name')->toArray();
        if ($duplicateWarning) {
            $response['duplicate_warning'] = $duplicateWarning;
        }

        $response['is_valid_for_objective'] = !empty($influenceur->profile_url)
            && !empty($influenceur->name)
            && !empty($influenceur->profile_url_domain)
            && ($influenceur->has_email || $influenceur->has_phone);

        return response()->json($response, 201);
    }

    // =========================================================================
    // SHOW — Détail d'un contact
    // =========================================================================

    public function show(Request $request, Influenceur $influenceur)
    {
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        return response()->json($influenceur->load([
            'assignedToUser:id,name',
            'createdBy:id,name',
            'contacts.user:id,name',
            'pendingReminder',
        ]));
    }

    // =========================================================================
    // UPDATE — Mise à jour d'un contact
    // =========================================================================

    public function update(Request $request, Influenceur $influenceur)
    {
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $data = $request->validate([
            'contact_type'        => 'sometimes|in:' . implode(',', \App\Models\ContactTypeModel::validValues()),
            'name'                => 'sometimes|string|max:255',
            'first_name'          => 'nullable|string|max:100',
            'last_name'           => 'nullable|string|max:100',
            'company'             => 'nullable|string|max:255',
            'position'            => 'nullable|string|max:255',
            'handle'              => 'nullable|string|max:255',
            'avatar_url'          => 'nullable|url|max:500',
            'platforms'           => 'sometimes|array|min:1',
            'platforms.*'         => 'string|in:' . implode(',', Platform::values()),
            'primary_platform'    => 'sometimes|string|max:50',
            'followers'           => 'nullable|integer|min:0',
            'followers_secondary' => 'nullable|array',
            'niche'               => 'nullable|string|max:255',
            'country'             => 'nullable|string|max:100',
            'language'            => 'nullable|string|max:10',
            'timezone'            => 'nullable|string|max:50',
            'email'               => 'nullable|email',
            'phone'               => 'nullable|string|max:50',
            'profile_url'         => 'nullable|string|max:500',
            'website_url'         => 'nullable|string|max:500',
            'linkedin_url'        => 'nullable|url|max:500',
            'twitter_url'         => 'nullable|url|max:500',
            'facebook_url'        => 'nullable|url|max:500',
            'instagram_url'       => 'nullable|url|max:500',
            'tiktok_url'          => 'nullable|url|max:500',
            'youtube_url'         => 'nullable|url|max:500',
            'status'              => 'sometimes|in:' . implode(',', PipelineStatus::values()),
            'deal_value_cents'    => 'nullable|integer|min:0',
            'deal_probability'    => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'assigned_to'         => 'nullable|exists:users,id',
            'reminder_days'       => 'sometimes|integer|min:1|max:365',
            'reminder_active'     => 'sometimes|boolean',
            'notes'               => 'nullable|string',
            'tags'                => 'nullable|array',
            'score'               => 'nullable|integer|min:0|max:1000',
            'source'              => 'nullable|string|max:100',
            'is_verified'         => 'sometimes|boolean',
        ]);

        if (isset($data['profile_url'])) {
            $data['profile_url_domain'] = !empty($data['profile_url'])
                ? self::normalizeProfileUrl($data['profile_url'])
                : null;
        }

        // Vérification doublon email sur update (exclut le contact courant)
        if (!empty($data['email'])) {
            $emailConflict = Influenceur::whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))])
                ->whereNull('deleted_at')
                ->where('id', '!=', $influenceur->id)
                ->first();
            if ($emailConflict) {
                return response()->json([
                    'warning'       => 'duplicate_email',
                    'message'       => "Cet email est déjà utilisé par : {$emailConflict->name} (ID {$emailConflict->id}).",
                    'existing_id'   => $emailConflict->id,
                    'existing_name' => $emailConflict->name,
                    'existing_type' => $emailConflict->contact_type,
                ], 409);
            }
        }

        $oldStatus = $influenceur->status;

        if (isset($data['status']) && $data['status'] === 'active'
            && $oldStatus !== 'active'
            && !$influenceur->partnership_date) {
            $data['partnership_date'] = now()->toDateString();
        }

        $influenceur->update($data);

        $action = (isset($data['status']) && $data['status'] !== $oldStatus)
            ? 'status_changed'
            : 'updated';

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $influenceur->id,
            'action'         => $action,
            'details'        => $data,
        ]);

        return response()->json($influenceur->load('assignedToUser:id,name'));
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy(Request $request, Influenceur $influenceur)
    {
        $role = $request->user()->role;
        if ($role === 'member') {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        if ($role === 'researcher' && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => null,
            'action'         => 'deleted',
            'details'        => ['name' => $influenceur->name, 'id' => $influenceur->id],
        ]);

        $influenceur->delete();

        return response()->json(null, 204);
    }

    // =========================================================================
    // RESCRAPE
    // =========================================================================

    public function rescrape(Request $request, Influenceur $influenceur)
    {
        if ($request->user()->isResearcher() && $influenceur->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $influenceur->update([
            'scraped_at'     => null,
            'scraper_status' => null,
        ]);

        ScrapeContactJob::dispatch($influenceur->id);

        ActivityLog::create([
            'user_id'        => $request->user()->id,
            'influenceur_id' => $influenceur->id,
            'action'         => 'rescrape_triggered',
            'details'        => ['name' => $influenceur->name],
        ]);

        return response()->json($influenceur->fresh()->load([
            'assignedToUser:id,name',
            'createdBy:id,name',
            'pendingReminder',
        ]));
    }

    // =========================================================================
    // REMINDERS PENDING
    // =========================================================================

    public function checkEmail(Request $request): \Illuminate\Http\JsonResponse
    {
        $email = trim($request->input('email', ''));
        $excludeId = $request->input('exclude_id'); // ID du contact en cours d'édition

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['exists' => false]);
        }

        $query = Influenceur::whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->whereNull('deleted_at');

        if ($excludeId) {
            $query->where('id', '!=', (int) $excludeId);
        }

        $existing = $query->select('id', 'name', 'contact_type', 'category', 'status')->first();

        if (!$existing) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists'        => true,
            'id'            => $existing->id,
            'name'          => $existing->name,
            'contact_type'  => $existing->contact_type,
            'category'      => $existing->category,
            'status'        => $existing->status,
        ]);
    }

    public function remindersPending(Request $request)
    {
        $query = Influenceur::with(['pendingReminder', 'assignedToUser:id,name'])
            ->whereHas('pendingReminder');

        if ($request->user()->isResearcher()) {
            $query->where('created_by', $request->user()->id);
        }

        $influenceurs = $query->get()
            ->map(function ($inf) {
                $daysElapsed = $inf->last_contact_at
                    ? (int) now()->diffInDays($inf->last_contact_at)
                    : null;

                return array_merge($inf->toArray(), ['days_elapsed' => $daysElapsed]);
            })
            ->sortByDesc('days_elapsed')
            ->values();

        return response()->json($influenceurs);
    }

    // =========================================================================
    // NORMALISATION D'URL DE PROFIL
    // =========================================================================

    /**
     * Normalise une URL de profil pour la déduplication.
     * Extrait le "profil de base" en supprimant chemins de posts/vidéos.
     */
    public static function normalizeProfileUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $url = preg_replace('#^https?://(www\.)?#', '', $url);
        $url = rtrim($url, '/');
        $url = preg_replace('#\?.*$#', '', $url);
        $url = preg_replace('#\#.*$#', '', $url);

        // TikTok : garder seulement @username
        if (str_contains($url, 'tiktok.com/')) {
            if (preg_match('#tiktok\.com/(@[^/]+)#', $url, $m)) {
                return 'tiktok.com/' . $m[1];
            }
        }

        // YouTube : garder channel/username, supprimer chemins vidéos
        if (str_contains($url, 'youtube.com/') || str_contains($url, 'youtu.be/')) {
            if (preg_match('#youtube\.com/(@[^/]+)#', $url, $m)) {
                return 'youtube.com/' . $m[1];
            }
            if (preg_match('#youtube\.com/(channel/[^/]+)#', $url, $m)) {
                return 'youtube.com/' . $m[1];
            }
            if (preg_match('#youtube\.com/(c/[^/]+)#', $url, $m)) {
                return 'youtube.com/' . $m[1];
            }
            return $url;
        }

        // Instagram : username seulement
        if (str_contains($url, 'instagram.com/')) {
            if (preg_match('#instagram\.com/([a-zA-Z0-9_.]+)#', $url, $m)) {
                $username = $m[1];
                if (!in_array($username, ['p', 'reel', 'reels', 'stories', 'explore', 'tv'])) {
                    return 'instagram.com/' . $username;
                }
            }
        }

        // LinkedIn
        if (str_contains($url, 'linkedin.com/')) {
            if (preg_match('#linkedin\.com/(in/[^/]+)#', $url, $m)) {
                return 'linkedin.com/' . $m[1];
            }
            if (preg_match('#linkedin\.com/(company/[^/]+)#', $url, $m)) {
                return 'linkedin.com/' . $m[1];
            }
        }

        // Facebook
        if (str_contains($url, 'facebook.com/')) {
            if (preg_match('#facebook\.com/([a-zA-Z0-9.]+)#', $url, $m)) {
                $name = $m[1];
                if (!in_array($name, ['watch', 'video', 'videos', 'photo', 'photos', 'events', 'groups'])) {
                    return 'facebook.com/' . $name;
                }
            }
        }

        // X/Twitter
        if (str_contains($url, 'twitter.com/') || str_contains($url, 'x.com/')) {
            if (preg_match('#(?:twitter|x)\.com/([a-zA-Z0-9_]+)#', $url, $m)) {
                $username = $m[1];
                if (!in_array($username, ['status', 'i', 'intent', 'search', 'hashtag'])) {
                    return 'x.com/' . $username;
                }
            }
        }

        return $url;
    }

    /** Alias legacy. */
    public static function normalizeUrlDomain(?string $url): ?string
    {
        return self::normalizeProfileUrl($url);
    }
}
