<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lawyer extends Model
{
    protected $fillable = [
        'source_slug', 'source_url', 'url_hash',
        'full_name', 'first_name', 'last_name', 'firm_name', 'title',
        'email', 'phone', 'website',
        'country', 'country_code', 'city', 'region', 'address', 'latitude', 'longitude',
        'specialty', 'specialties', 'bar_association', 'bar_number',
        'language', 'languages', 'years_experience',
        'directory_category', 'is_immigration_lawyer', 'is_francophone',
        'email_verified', 'detail_scraped', 'enrichment_status',
        'description', 'photo_url', 'social_links',
        'scraped_at',
    ];

    protected $casts = [
        'specialties'           => 'array',
        'languages'             => 'array',
        'social_links'          => 'array',
        'years_experience'      => 'integer',
        'is_immigration_lawyer' => 'boolean',
        'is_francophone'        => 'boolean',
        'email_verified'        => 'boolean',
        'detail_scraped'        => 'boolean',
        'scraped_at'            => 'datetime',
        'latitude'              => 'decimal:7',
        'longitude'             => 'decimal:7',
    ];

    public const FRANCOPHONE_COUNTRIES = [
        'FR', 'BE', 'CH', 'CA', 'LU', 'MC', 'SN', 'CI', 'ML', 'BF',
        'NE', 'TD', 'GN', 'BJ', 'TG', 'CF', 'CG', 'CD', 'GA', 'CM',
        'DJ', 'KM', 'MG', 'MU', 'SC', 'HT', 'VU',
    ];

    public const IMMIGRATION_SPECIALTIES = [
        'immigration', 'visa', 'asylum', 'refugee', 'citizenship',
        'nationality', 'deportation', 'work permit', 'residence permit',
        'green card', 'naturalization', 'migration', 'expat',
        'einwanderung', 'aufenthaltsrecht', 'inmigración', 'extranjería',
        'imigração', 'estrangeiros',
    ];

    public function directorySource()
    {
        return $this->belongsTo(LawyerDirectorySource::class, 'source_slug', 'slug');
    }

    public static function isFrancophone(string $countryCode, ?string $language = null, ?string $city = null): bool
    {
        if (in_array(strtoupper($countryCode), self::FRANCOPHONE_COUNTRIES)) {
            if (strtoupper($countryCode) === 'BE' && $language && !str_starts_with($language, 'fr')) return false;
            if (strtoupper($countryCode) === 'CH' && $language && !str_starts_with($language, 'fr')) return false;
            if (strtoupper($countryCode) === 'CA') {
                $quebecCities = ['montreal', 'quebec', 'laval', 'gatineau', 'sherbrooke', 'trois-rivieres', 'saguenay'];
                if ($city && in_array(strtolower($city), $quebecCities)) return true;
                if ($language && str_starts_with($language, 'fr')) return true;
                return false;
            }
            return true;
        }
        if ($language && str_starts_with(strtolower($language), 'fr')) return true;
        return false;
    }

    public static function isImmigrationSpecialty(?string $specialty): bool
    {
        if (!$specialty) return false;
        $lower = strtolower($specialty);
        foreach (self::IMMIGRATION_SPECIALTIES as $keyword) {
            if (str_contains($lower, $keyword)) return true;
        }
        return false;
    }
}
