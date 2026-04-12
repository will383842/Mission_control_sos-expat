<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Structured country reference data for article generation.
 * Every data point is sourced and dated for traceability.
 */
class CountryFact extends Model
{
    protected $table = 'country_facts';
    protected $primaryKey = 'country_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'country_code', 'country_name_fr', 'country_name_en',
        // Visa
        'visa_types', 'immigration_office_url',
        // Cost of Living
        'cost_of_living_index', 'avg_rent_1bed_center_usd', 'avg_rent_1bed_outside_usd',
        'coffee_usd', 'meal_restaurant_usd',
        // Salary
        'min_wage_monthly_usd', 'avg_salary_monthly_usd', 'unemployment_rate',
        // Safety & Health
        'safety_index', 'healthcare_type', 'health_expenditure_pct_gdp', 'emergency_number',
        // Economy
        'gdp_per_capita_usd', 'inflation_rate', 'ppp_factor',
        // Tax
        'tax_rate_non_resident', 'corporate_tax_rate', 'has_tax_treaty_france',
        // Digital
        'internet_speed_mbps', 'has_digital_nomad_visa', 'nomad_visa_cost_usd',
        // Demographics
        'official_languages', 'currency_code', 'currency_name', 'timezone',
        'expat_population', 'expat_pct_population', 'total_population',
        // Tourism
        'tourism_arrivals', 'tourism_receipts_usd',
        // Traceability
        'source_urls', 'data_years', 'last_verified_at', 'verification_status',
    ];

    protected $casts = [
        'visa_types'                => 'array',
        'official_languages'        => 'array',
        'source_urls'               => 'array',
        'data_years'                => 'array',
        'cost_of_living_index'      => 'decimal:2',
        'safety_index'              => 'decimal:2',
        'gdp_per_capita_usd'       => 'decimal:2',
        'inflation_rate'            => 'decimal:2',
        'ppp_factor'                => 'decimal:4',
        'tax_rate_non_resident'     => 'decimal:2',
        'corporate_tax_rate'        => 'decimal:2',
        'unemployment_rate'         => 'decimal:2',
        'health_expenditure_pct_gdp' => 'decimal:2',
        'internet_speed_mbps'       => 'decimal:2',
        'coffee_usd'                => 'decimal:2',
        'meal_restaurant_usd'       => 'decimal:2',
        'expat_pct_population'      => 'decimal:2',
        'tourism_receipts_usd'      => 'decimal:2',
        'has_tax_treaty_france'     => 'boolean',
        'has_digital_nomad_visa'    => 'boolean',
        'last_verified_at'          => 'datetime',
        'expat_population'          => 'integer',
        'total_population'          => 'integer',
        'tourism_arrivals'          => 'integer',
    ];

    /**
     * Get related statistics data points.
     */
    public function statisticsDataPoints(): HasMany
    {
        return $this->hasMany(StatisticsDataPoint::class, 'country_code', 'country_code');
    }

    /**
     * Get geo data from countries_geo.
     */
    public function geo()
    {
        return $this->belongsTo(CountryGeo::class, 'country_code', 'country_code');
    }

    /**
     * Check if data is stale (older than N days).
     */
    public function isStale(int $maxDays = 90): bool
    {
        if (!$this->last_verified_at) {
            return true;
        }
        return $this->last_verified_at->diffInDays(now()) > $maxDays;
    }

    /**
     * Get completeness score (0-100) based on filled fields.
     */
    public function getCompletenessAttribute(): int
    {
        $fields = [
            'gdp_per_capita_usd', 'total_population', 'expat_population',
            'cost_of_living_index', 'avg_rent_1bed_center_usd',
            'safety_index', 'healthcare_type', 'emergency_number',
            'min_wage_monthly_usd', 'unemployment_rate',
            'inflation_rate', 'currency_code', 'timezone',
            'tourism_arrivals', 'internet_speed_mbps',
        ];

        $filled = 0;
        foreach ($fields as $field) {
            if ($this->$field !== null) {
                $filled++;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }

    /**
     * Format data for AI prompt injection.
     */
    public function toPromptBlock(): string
    {
        $lines = [];
        $name = $this->country_name_fr;

        $lines[] = "=== DONNEES VERIFIEES : {$name} ({$this->country_code}) ===";
        $lines[] = "Statut verification : {$this->verification_status}";

        if ($this->total_population) {
            $lines[] = "Population : " . number_format($this->total_population, 0, ',', ' ');
        }
        if ($this->gdp_per_capita_usd) {
            $lines[] = "PIB par habitant : " . number_format($this->gdp_per_capita_usd, 0, ',', ' ') . " USD";
        }
        if ($this->expat_population) {
            $pct = $this->expat_pct_population ? " ({$this->expat_pct_population}%)" : '';
            $lines[] = "Population migrante : " . number_format($this->expat_population, 0, ',', ' ') . $pct;
        }
        if ($this->tourism_arrivals) {
            $lines[] = "Arrivees touristiques : " . number_format($this->tourism_arrivals, 0, ',', ' ');
        }
        if ($this->cost_of_living_index) {
            $lines[] = "Indice cout de la vie : {$this->cost_of_living_index}/100 (NYC=100)";
        }
        if ($this->avg_rent_1bed_center_usd) {
            $lines[] = "Loyer 1 chambre centre-ville : {$this->avg_rent_1bed_center_usd} USD/mois";
        }
        if ($this->safety_index) {
            $lines[] = "Indice securite : {$this->safety_index}/100";
        }
        if ($this->healthcare_type) {
            $lines[] = "Systeme de sante : {$this->healthcare_type}";
        }
        if ($this->min_wage_monthly_usd) {
            $lines[] = "Salaire minimum : {$this->min_wage_monthly_usd} USD/mois";
        }
        if ($this->avg_salary_monthly_usd) {
            $lines[] = "Salaire moyen : {$this->avg_salary_monthly_usd} USD/mois";
        }
        if ($this->unemployment_rate) {
            $lines[] = "Taux de chomage : {$this->unemployment_rate}%";
        }
        if ($this->inflation_rate) {
            $lines[] = "Inflation : {$this->inflation_rate}%";
        }
        if ($this->internet_speed_mbps) {
            $lines[] = "Debit internet moyen : {$this->internet_speed_mbps} Mbps";
        }
        if ($this->has_digital_nomad_visa) {
            $cost = $this->nomad_visa_cost_usd ? " ({$this->nomad_visa_cost_usd} USD)" : '';
            $lines[] = "Visa nomade numerique : OUI{$cost}";
        }
        if ($this->currency_code) {
            $lines[] = "Monnaie : {$this->currency_name} ({$this->currency_code})";
        }
        if ($this->emergency_number) {
            $lines[] = "Numero urgences : {$this->emergency_number}";
        }

        // Sources
        $years = $this->data_years ?? [];
        if (!empty($years)) {
            $yearsList = collect($years)->map(fn ($y, $k) => "{$k}: {$y}")->implode(', ');
            $lines[] = "Annees des donnees : {$yearsList}";
        }

        $sources = $this->source_urls ?? [];
        if (!empty($sources)) {
            $lines[] = "Sources : " . implode(', ', array_values($sources));
        }

        return implode("\n", $lines);
    }
}
