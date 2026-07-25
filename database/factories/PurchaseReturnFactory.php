<?php

namespace Database\Factories;

use App\Models\PurchaseReturn;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseReturnFactory extends Factory
{
    protected $model = PurchaseReturn::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'purchase_return_number' => 'PRET-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'return_date' => today(), 'reason' => $this->faker->sentence(), 'subtotal' => 10, 'tax_amount' => 1.5, 'total' => 11.5];
    }
}
