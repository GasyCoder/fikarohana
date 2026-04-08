<?php

namespace Database\Factories;

use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        $title = fake()->sentence(6);
        $year = fake()->numberBetween(1998, 2026);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.$year.'-'.fake()->unique()->numberBetween(10, 9999),
            'author' => fake()->name(),
            'year' => $year,
            'abstract' => fake()->paragraph(),
            'source_name' => fake()->randomElement(['HAL', 'Theses.fr', 'Cairn', 'Horizon IRD']),
            'source_url' => fake()->url(),
            'original_id' => fake()->optional()->uuid(),
            'type' => fake()->randomElement(['thesis', 'article']),
            'domain' => fake()->randomElement(['Agronomie', 'Sante publique', 'Biodiversite']),
            'institution' => fake()->randomElement(['Université de Mahajanga', 'Université d’Antananarivo', 'Université de Fianarantsoa']),
            'tags' => fake()->randomElements(['Madagascar', 'Ocean Indien', 'Archipel', 'Terrain'], fake()->numberBetween(1, 3)),
        ];
    }
}
