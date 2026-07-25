<?php

namespace Database\Factories;

use App\Models\SalesCreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SalesCreditNoteFactory extends Factory
{
    protected $model = SalesCreditNote::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'credit_note_number' => 'CN-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'credit_note_date' => today(), 'reason_code' => 'other', 'reason' => 'Test', 'subtotal' => 50, 'tax_amount' => 7.5, 'total' => 57.5, 'applied_amount' => 0, 'refunded_amount' => 0, 'remaining_amount' => 57.5];
    }
}
