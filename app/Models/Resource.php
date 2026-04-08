<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'author',
        'year',
        'abstract',
        'source_name',
        'source_url',
        'original_id',
        'type',
        'domain',
        'institution',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'tags' => 'array',
        ];
    }
}
