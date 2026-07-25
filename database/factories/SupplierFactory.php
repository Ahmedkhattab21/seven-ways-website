<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'supplier_code' => 'SUP-'.$this->faker->unique()->numerify('######'), 'name' => $this->faker->company(), 'supplier_type' => 'distributor', 'payment_terms_days' => 30, 'status' => 'active'];
    }
}
