<?php

namespace Tests\Feature;

use Database\Seeders\ProductionReferenceSeeder;
use Database\Seeders\TreasuryManualQaSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PhaseTwentySeederSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_production_reference_seeder_is_idempotent_and_creates_no_operational_data(): void
    {
        $protected = [
            'companies', 'branches', 'users', 'journal_entries', 'sales_invoices',
            'supplier_invoices', 'treasury_transfers', 'cash_receipts', 'cash_payments',
        ];
        $before = collect($protected)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->seed(ProductionReferenceSeeder::class);
        $referenceCounts = [
            'permissions' => DB::table('permissions')->count(),
            'currencies' => DB::table('currencies')->count(),
            'units' => DB::table('units')->count(),
        ];
        $this->seed(ProductionReferenceSeeder::class);

        foreach ($protected as $table) {
            $this->assertSame($before[$table], DB::table($table)->count(), $table);
        }
        foreach ($referenceCounts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertDatabaseHas('currencies', ['code' => 'EGP']);
        $this->assertDatabaseHas('currencies', ['code' => 'SAR']);
    }

    public function test_qa_seeder_refuses_production(): void
    {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->expectException(RuntimeException::class);
            (new TreasuryManualQaSeeder)->run();
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    }

    public function test_database_seeder_uses_only_reference_seeder_in_production(): void
    {
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringContainsString("environment('production')", $source);
        $this->assertStringContainsString('ProductionReferenceSeeder::class', $source);
        $this->assertStringNotContainsString('TreasuryManualQaSeeder::class', $source);
    }
}
