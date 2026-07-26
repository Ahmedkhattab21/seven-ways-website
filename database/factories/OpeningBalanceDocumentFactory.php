<?php

namespace Database\Factories;

use App\Models\OpeningBalanceDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpeningBalanceDocumentFactory extends Factory
{
    protected $model = OpeningBalanceDocument::class;

    public function definition(): array
    {
        return [
            'document_number' => strtoupper(fake()->unique()->lexify('OB-????')),
            'status' => 'draft', 'balance_date' => '2030-01-01',
            'total_debit' => 0, 'total_credit' => 0,
        ];
    }
}
