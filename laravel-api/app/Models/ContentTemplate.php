<?php

namespace App\Models;

use App\Models\CountryGeo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ContentTemplate extends Model
{
    protected $fillable = [
        'uuid', 'name', 'description', 'preset_type', 'content_type',
        'title_template', 'variables', 'expansion_mode', 'expansion_values',
        'language', 'tone', 'article_length', 'generation_instructions',
        'generate_faq', 'faq_count', 'research_sources',
        'auto_internal_links', 'auto_affiliate_links', 'auto_translate',
        'image_source', 'total_items', 'generated_items', 'published_items',
        'failed_items', 'is_active', 'created_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'expansion_values' => 'array',
        'generate_faq' => 'boolean',
        'research_sources' => 'boolean',
        'auto_internal_links' => 'boolean',
        'auto_affiliate_links' => 'boolean',
        'auto_translate' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentTemplateItem::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Extract variable names from title_template.
     * "Comment obtenir un visa {pays}" → ["pays"]
     */
    public function getVariableNamesAttribute(): array
    {
        preg_match_all('/\{(\w+)\}/', $this->title_template, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Expand the template into items based on expansion_mode.
     * Returns array of ['expanded_title' => ..., 'variable_values' => [...]]
     */
    public function expand(): array
    {
        $varNames = $this->variable_names;

        if (empty($varNames)) {
            // No variables — single item (manual keyword)
            return [['expanded_title' => $this->title_template, 'variable_values' => []]];
        }

        $expansions = [];

        if ($this->expansion_mode === 'all_countries') {
            $lang = $this->language ?? 'fr';
            $countries = CountryGeo::orderBy('country_name_fr')->get();

            foreach ($countries as $country) {
                $name = $lang === 'en' ? $country->country_name_en : $country->country_name_fr;
                $values = [];
                $title = $this->title_template;

                foreach ($varNames as $var) {
                    if (in_array($var, ['pays', 'country', 'pais', 'land'])) {
                        $values[$var] = $name;
                        $values["{$var}_code"] = $country->country_code;
                        $title = str_replace("{{$var}}", $name, $title);
                    }
                }

                $expansions[] = ['expanded_title' => $title, 'variable_values' => $values];
            }
        } elseif ($this->expansion_mode === 'selected_countries') {
            $codes = $this->expansion_values ?? [];
            $lang = $this->language ?? 'fr';
            $countries = CountryGeo::whereIn('country_code', $codes)->orderBy('country_name_fr')->get();

            foreach ($countries as $country) {
                $name = $lang === 'en' ? $country->country_name_en : $country->country_name_fr;
                $values = [];
                $title = $this->title_template;

                foreach ($varNames as $var) {
                    if (in_array($var, ['pays', 'country', 'pais', 'land'])) {
                        $values[$var] = $name;
                        $values["{$var}_code"] = $country->country_code;
                        $title = str_replace("{{$var}}", $name, $title);
                    }
                }

                $expansions[] = ['expanded_title' => $title, 'variable_values' => $values];
            }
        } elseif ($this->expansion_mode === 'custom_list') {
            $items = $this->expansion_values ?? [];
            foreach ($items as $item) {
                $title = $this->title_template;
                $values = [];

                if (is_string($item)) {
                    // Simple string list — replace first variable
                    $var = $varNames[0] ?? 'value';
                    $values[$var] = $item;
                    $title = str_replace("{{$var}}", $item, $title);
                } elseif (is_array($item)) {
                    foreach ($item as $key => $val) {
                        $values[$key] = $val;
                        $title = str_replace("{{$key}}", $val, $title);
                    }
                }

                $expansions[] = ['expanded_title' => $title, 'variable_values' => $values];
            }
        } else {
            // Manual — return template as-is for manual keyword entry
            return [['expanded_title' => $this->title_template, 'variable_values' => []]];
        }

        return $expansions;
    }
}
