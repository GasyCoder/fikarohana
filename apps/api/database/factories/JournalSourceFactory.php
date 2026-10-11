<?php

namespace Database\Factories;

use App\Models\JournalSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JournalSource> */
class JournalSourceFactory extends Factory
{
    protected $model = JournalSource::class;

    public function definition(): array
    {
        return [
            'type' => 'official_journal',
            'name' => 'Synthetic official journal source',
            'base_url' => 'https://journal.example.test',
        ];
    }
}
