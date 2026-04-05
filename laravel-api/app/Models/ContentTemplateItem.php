<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentTemplateItem extends Model
{
    protected $fillable = [
        'template_id', 'expanded_title', 'variable_values', 'status',
        'generated_article_id', 'optimized_title', 'error_message',
        'generation_cost_cents', 'generated_at',
    ];

    protected $casts = [
        'variable_values' => 'array',
        'generated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContentTemplate::class, 'template_id');
    }

    public function generatedArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'generated_article_id');
    }
}
