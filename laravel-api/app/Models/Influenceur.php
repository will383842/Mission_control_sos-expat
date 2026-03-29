<?php

namespace App\Models;

use App\Enums\ContactType;
use App\Enums\PipelineStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Influenceur extends Model
{
    use SoftDeletes;

    // =========================================================================
    // CATÉGORIES — Mapping contact_type → category
    // =========================================================================

    public const CATEGORY_MAP = [
        'consulat'                 => 'institutionnel',
        'association'              => 'institutionnel',
        'ecole'                    => 'institutionnel',
        'institut_culturel'        => 'institutionnel',
        'chambre_commerce'         => 'institutionnel',
        'presse'                   => 'medias_influence',
        'blog'                     => 'medias_influence',
        'podcast_radio'            => 'medias_influence',
        'influenceur'              => 'medias_influence',
        'avocat'                   => 'services_b2b',
        'immobilier'               => 'services_b2b',
        'assurance'                => 'services_b2b',
        'banque_fintech'           => 'services_b2b',
        'traducteur'               => 'services_b2b',
        'agence_voyage'            => 'services_b2b',
        'emploi'                   => 'services_b2b',
        'communaute_expat'         => 'communautes',
        'groupe_whatsapp_telegram' => 'communautes',
        'coworking_coliving'       => 'communautes',
        'logement'                 => 'communautes',
        'lieu_communautaire'       => 'communautes',
        'backlink'                 => 'digital',
        'annuaire'                 => 'digital',
        'plateforme_nomad'         => 'digital',
        'partenaire'               => 'digital',
    ];

    /** Types qui correspondent à des personnes physiques (vs organisations). */
    public const INDIVIDUAL_TYPES = ['influenceur', 'avocat', 'traducteur'];

    /**
     * Types qui ont un site web scrappable (pas un profil réseau social).
     * Utilisé pour décider si profile_url → website_url pour scraping.
     */
    public const NON_SOCIAL_TYPES = [
        'consulat', 'association', 'ecole', 'institut_culturel', 'chambre_commerce',
        'presse', 'blog', 'podcast_radio',
        'avocat', 'immobilier', 'assurance', 'banque_fintech', 'traducteur', 'agence_voyage', 'emploi',
        'communaute_expat', 'coworking_coliving', 'logement', 'lieu_communautaire',
        'backlink', 'annuaire', 'plateforme_nomad', 'partenaire',
        // Legacy (données existantes)
        'school', 'press', 'blogger', 'consulats', 'enterprise', 'insurer',
        'travel_agency', 'real_estate', 'translator', 'lawyer', 'partner', 'job_board', 'erasmus',
    ];

    // =========================================================================
    // CONFIGURATION ELOQUENT
    // =========================================================================

    protected $fillable = [
        // Classification
        'contact_type', 'category', 'contact_kind',
        // Identité
        'name', 'first_name', 'last_name', 'company', 'position',
        // Social
        'handle', 'avatar_url', 'platforms', 'primary_platform',
        'followers', 'followers_secondary',
        // Géographie
        'niche', 'country', 'language', 'timezone',
        // Contact principal
        'email', 'has_email', 'phone', 'has_phone',
        // URLs
        'profile_url', 'profile_url_domain', 'website_url',
        'linkedin_url', 'twitter_url', 'facebook_url',
        'instagram_url', 'tiktok_url', 'youtube_url',
        // Pipeline CRM
        'status', 'deal_value_cents', 'deal_probability', 'expected_close_date',
        'assigned_to', 'reminder_days', 'reminder_active', 'last_contact_at',
        'partnership_date', 'notes', 'tags', 'score', 'source', 'created_by',
        // Qualité CRM
        'is_verified', 'unsubscribed', 'unsubscribed_at', 'bounce_count', 'data_completeness',
        // Scraping
        'scraped_at', 'scraper_status',
        'scraped_emails', 'scraped_phones', 'scraped_social', 'scraped_addresses',
        'email_verified_status', 'email_verified_at', 'quality_score',
    ];

    protected $casts = [
        'contact_type'        => ContactType::class,
        'platforms'           => 'array',
        'followers_secondary' => 'array',
        'tags'                => 'array',
        'reminder_active'     => 'boolean',
        'last_contact_at'     => 'datetime',
        'partnership_date'    => 'date',
        'expected_close_date' => 'date',
        'deal_value_cents'    => 'integer',
        'deal_probability'    => 'integer',
        'score'               => 'integer',
        'scraped_at'          => 'datetime',
        'scraped_emails'      => 'array',
        'scraped_phones'      => 'array',
        'scraped_social'      => 'array',
        'scraped_addresses'   => 'array',
        // Nouveaux champs
        'has_email'           => 'boolean',
        'has_phone'           => 'boolean',
        'is_verified'         => 'boolean',
        'unsubscribed'        => 'boolean',
        'unsubscribed_at'     => 'datetime',
        'bounce_count'        => 'integer',
        'data_completeness'   => 'integer',
    ];

    // =========================================================================
    // AUTO-COMPUTE — category, contact_kind, has_email, has_phone, completeness
    // =========================================================================

    protected static function booted(): void
    {
        static::saving(function (Influenceur $model) {
            // Résoudre la valeur string du contact_type
            $type = $model->contact_type instanceof ContactType
                ? $model->contact_type->value
                : (string) $model->contact_type;

            // Category
            $model->category = self::CATEGORY_MAP[$type] ?? 'autre';

            // Contact kind
            $model->contact_kind = in_array($type, self::INDIVIDUAL_TYPES, true)
                ? 'individual'
                : 'organization';

            // Booléens d'indexation rapide
            $model->has_email = !empty($model->email);
            $model->has_phone = !empty($model->phone);

            // Score de complétude (0–100)
            $score = 0;
            if (!empty($model->name))                                               $score += 15;
            if (!empty($model->email))                                              $score += 25;
            if (!empty($model->phone))                                              $score += 15;
            if (!empty($model->country))                                            $score += 10;
            if (!empty($model->language))                                           $score +=  5;
            if (!empty($model->profile_url) || !empty($model->website_url))        $score += 15;
            if (!empty($model->notes))                                              $score +=  5;
            if (($model->score ?? 0) > 0)                                          $score +=  5;
            if (!empty($model->tags))                                               $score +=  5;
            $model->data_completeness = min(100, $score);
        });
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeByContactKind(Builder $query, string $kind): Builder
    {
        return $query->where('contact_kind', $kind);
    }

    public function scopeWithEmail(Builder $query): Builder
    {
        return $query->where('has_email', true);
    }

    public function scopeWithPhone(Builder $query): Builder
    {
        return $query->where('has_phone', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeNotUnsubscribed(Builder $query): Builder
    {
        return $query->where('unsubscribed', false);
    }

    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeMinCompleteness(Builder $query, int $min): Builder
    {
        return $query->where('data_completeness', '>=', $min);
    }

    /**
     * Contacts "valides" pour le comptage d'objectifs :
     * URL + nom + domaine + (email ou téléphone).
     */
    public function scopeValidForObjective(Builder $query): Builder
    {
        return $query->whereNotNull('profile_url')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereNotNull('profile_url_domain')
            ->where(function ($q) {
                $q->where('has_email', true)->orWhere('has_phone', true);
            });
    }

    // =========================================================================
    // RELATIONS
    // =========================================================================

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class)
            ->orderBy('date')
            ->orderBy('created_at');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function pendingReminder()
    {
        return $this->hasOne(Reminder::class)->where('status', 'pending');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function outreachEmails()
    {
        return $this->hasMany(OutreachEmail::class);
    }

    public function outreachSequence()
    {
        return $this->hasOne(OutreachSequence::class);
    }
}
