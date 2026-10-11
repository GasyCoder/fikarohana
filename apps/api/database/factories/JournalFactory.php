<?php

namespace Database\Factories;

use App\Models\Journal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Journal> */
class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        return [
            'title' => 'Synthetic Journal '.fake()->unique()->numerify('#####'),
            'publisher_id' => PublisherFactory::new(),
            'official_website_url' => 'https://journal.example.test',
            'active' => false,
        ];
    }
}
