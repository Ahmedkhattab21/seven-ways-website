<?php

namespace Database\Factories;

use App\Models\PostingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostingProfileFactory extends Factory
{
    protected $model = PostingProfile::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('PP-????')), 'name' => fake()->words(3, true),
            'source_type' => 'sales_invoice', 'version' => 1, 'status' => 'draft',
            'is_default' => false,
        ];
    }
}
