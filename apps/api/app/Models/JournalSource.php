<?php

namespace App\Models;

use App\Enums\JournalSourceType;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'base_url', 'official_journal_id', 'official_purpose', 'official_url', 'official_origin', 'first_observed_at', 'last_checked_at', 'last_changed_at', 'last_outcome', 'last_etag', 'last_modified', 'last_final_url', 'last_source_record_id'])]
class JournalSource extends CatalogModel
{
    public function fill(array $attributes)
    {
        // Registration can supply identity; generic updates cannot replace registered ownership/URL.
        if ($this->exists && $this->getRawOriginal('official_journal_id') !== null) {
            foreach (['type', 'name', 'base_url', 'official_journal_id', 'official_purpose', 'official_url', 'official_origin', 'first_observed_at'] as $field) {
                if (array_key_exists($field, $attributes) && $attributes[$field] !== $this->getAttribute($field)) {
                    throw new DomainException('official_identity_immutable');
                }
            }
        }

        return parent::fill($attributes);
    }

    protected function casts(): array
    {
        return [
            'type' => JournalSourceType::class,
            'approved_at' => 'immutable_datetime',
            'first_observed_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'last_changed_at' => 'immutable_datetime',
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(SourceRecord::class);
    }
}
