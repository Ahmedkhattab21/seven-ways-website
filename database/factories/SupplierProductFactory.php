<?php

namespace Database\Factories;

use App\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierProductFactory extends Factory
{
    protected $model = SupplierProduct::class;

    public function definition(): array
    {
        return ['conversion_factor' => 1, 'default_purchase_price' => 100, 'is_preferred' => false, 'is_active' => true];
    }
}
