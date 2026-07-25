<?php

namespace Database\Factories;

use App\Models\SupplierCreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierCreditNoteFactory extends Factory
{
    protected $model = SupplierCreditNote::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'credit_note_number' => 'SCN-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'credit_date' => today(), 'reason' => $this->faker->sentence(), 'subtotal' => 10, 'tax_amount' => 1.5, 'total' => 11.5, 'applied_amount' => 0, 'remaining_amount' => 11.5];
    }
}
