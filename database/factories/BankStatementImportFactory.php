<?php

namespace Database\Factories;

use App\Models\BankStatementImport;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankStatementImportFactory extends Factory
{
    protected $model = BankStatementImport::class;

    public function definition(): array
    {
        return ['file_name' => fake()->uuid().'.csv', 'original_file_name' => 'statement.csv',
            'storage_path' => 'private/bank-statements/'.fake()->uuid().'.csv', 'file_hash' => hash('sha256', fake()->uuid()),
            'format' => 'csv', 'parser_version' => 'csv-v1', 'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(), 'opening_balance' => 0, 'closing_balance' => 0,
            'status' => 'imported', 'imported_at' => now()];
    }
}
