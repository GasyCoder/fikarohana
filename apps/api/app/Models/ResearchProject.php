<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchProject extends Model
{
    use HasUuids;

    protected $attributes = ['no_apc_only' => false, 'indexation_services' => '[]'];

    protected $fillable = ['title', 'abstract', 'keywords', 'subject_ids', 'language', 'article_type', 'no_apc_only', 'indexation_services'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'subject_ids' => 'array', 'journal_selected_at' => 'datetime', 'no_apc_only' => 'boolean', 'indexation_services' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function selectedJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'selected_journal_id');
    }
}
