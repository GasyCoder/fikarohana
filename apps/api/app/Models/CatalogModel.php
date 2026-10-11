<?php

namespace App\Models;

use App\Support\CatalogNormalizer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class CatalogModel extends Model
{
    use HasFactory, HasUuids;

    protected static function boot(): void
    {
        parent::boot();
        static::saving(function (self $model): void {
            $fillable = $model->getFillable();
            if (in_array('normalized_name', $fillable, true)) {
                $model->normalized_name = CatalogNormalizer::name($model->name);
            }
            if (in_array('normalized_title', $fillable, true)) {
                $model->normalized_title = CatalogNormalizer::name($model->title);
            }
            foreach (['website_url', 'official_website_url', 'source_url', 'base_url', 'official_url'] as $attribute) {
                if (array_key_exists($attribute, $model->getAttributes())) {
                    $model->{$attribute} = CatalogNormalizer::url($model->{$attribute});
                }
            }
            if ($model instanceof JournalIdentifier) {
                $model->normalized_value = CatalogNormalizer::issn($model->value);
            }
        });
    }
}
