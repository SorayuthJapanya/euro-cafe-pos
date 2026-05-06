<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id'  => Category::factory(),
            'name'         => fake()->words(2, true),
            'description'  => fake()->sentence(),
            'price'        => fake()->randomFloat(2, 30, 300),
            'is_available' => true,
            'image_url'    => null,
        ];
    }
}
