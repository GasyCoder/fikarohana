<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['publisher_id', 'title', 'normalized_title', 'short_title', 'country_code', 'official_website_url', 'active'])]
class Journal extends CatalogModel
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(JournalIdentifier::class);
    }

    public function subjectLinks(): HasMany
    {
        return $this->hasMany(JournalSubject::class);
    }

    public function languageLinks(): HasMany
    {
        return $this->hasMany(JournalLanguage::class);
    }

    public function articleTypeLinks(): HasMany
    {
        return $this->hasMany(JournalArticleType::class);
    }

    public function indexations(): HasMany
    {
        return $this->hasMany(JournalIndexation::class);
    }

    public function fees(): HasMany
    {
        return $this->hasMany(JournalFee::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(JournalRequirement::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(JournalTemplate::class);
    }

    public function assertions(): HasMany
    {
        return $this->hasMany(JournalAssertion::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(JournalRevision::class);
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(JournalRevision::class)->whereNotNull('published_at')->orderByDesc('version');
    }
}
