<?php

namespace Database\Factories;

use App\Models\ProductAccountingMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductAccountingMappingFactory extends Factory
{
    protected $model = ProductAccountingMapping::class;

    public function definition(): array
    {
        return ['is_active' => true];
    }
}
