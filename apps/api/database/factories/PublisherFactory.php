<?php

namespace Database\Factories;

use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Publisher> */
class PublisherFactory extends Factory
{
    protected $model = Publisher::class;

    public function definition(): array
    {
        return [
            'name' => 'Synthetic Publisher '.fake()->unique()->numerify('#####'),
            'website_url' => 'https://publisher.example.test',
        ];
    }
}
