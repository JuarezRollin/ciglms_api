<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\GlTransmittal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlTransmittal>
 */
class GlTransmittalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bundle_no' => fake()->unique()->bothify('TEST-##-##-####'),
            'category_id' => fn (): int => Category::query()->create(['name' => fake()->unique()->word()])->id,
            'created_by' => User::factory(),
        ];
    }
}
