<?php

namespace Database\Factories;

use App\Models\ChequeEndorsement;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChequeEndorsementFactory extends Factory
{
    protected $model = ChequeEndorsement::class;

    public function definition(): array
    {
        return [
            'endorsed_to_type' => 'other', 'endorsed_to_name' => fake()->name(),
            'endorsement_date' => now()->toDateString(), 'status' => 'draft',
        ];
    }
}
