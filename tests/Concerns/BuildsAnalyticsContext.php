<?php

namespace Tests\Concerns;

use App\Models\AccountingPostingLink;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use Database\Seeders\AnalyticsReportingSeeder;
use Illuminate\Support\Facades\DB;

trait BuildsAnalyticsContext
{
    use BuildsTreasuryOperationsContext;

    protected function analyticsContext(): array
    {
        $context = $this->treasuryContext();
        app(AnalyticsReportingSeeder::class)->run();
        $role = $context['user']->roles()->firstOrFail();
        $role->permissions()->syncWithoutDetaching(Permission::query()->pluck('id'));
        $this->switchTreasuryActor($context['user']);
        $context['customer'] = Customer::factory()->create([
            'company_id' => $context['company']->id,
            'created_branch_id' => $context['branch']->id,
            'assigned_branch_id' => $context['branch']->id,
        ]);
        $context['supplier'] = Supplier::factory()->create([
            'company_id' => $context['company']->id,
            'created_by' => $context['user']->id,
        ]);

        return $context;
    }

    protected function analyticsInvoice(
        array $context,
        string $date,
        string $subtotal,
        string $discount,
        string $tax,
        string $total,
        ?int $branchId = null
    ): SalesInvoice {
        $invoice = SalesInvoice::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $branchId ?: $context['branch']->id,
            'customer_id' => $context['customer']->id,
            'currency_id' => $context['currency']->id,
            'status' => 'issued',
            'invoice_date' => $date,
            'due_date' => $date,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => $total,
            'balance_due' => $total,
            'created_by' => $context['user']->id,
        ]);
        AccountingPostingLink::query()->forceCreate([
            'company_id' => $context['company']->id,
            'branch_id' => $branchId ?: $context['branch']->id,
            'source_type' => SalesInvoice::class,
            'source_id' => $invoice->id,
            'source_uuid' => $invoice->uuid,
            'posting_action' => 'post',
            'idempotency_key' => fake()->unique()->sha256(),
            'status' => 'posted',
            'created_by' => $context['user']->id,
        ]);

        return $invoice;
    }

    protected function analyticsJournal(
        array $context,
        string $date,
        string $status,
        array $lines,
        ?int $branchId = null
    ): JournalEntry {
        $entry = new JournalEntry;
        $debit = collect($lines)->sum(fn ($line) => $line[1]);
        $credit = collect($lines)->sum(fn ($line) => $line[2]);
        $entry->forceFill([
            'company_id' => $context['company']->id,
            'branch_id' => $branchId ?: $context['branch']->id,
            'fiscal_year_id' => $context['year']->id,
            'accounting_period_id' => $context['period']->id,
            'journal_number' => 'AN-'.fake()->unique()->numerify('######'),
            'entry_type' => 'manual',
            'status' => $status,
            'entry_date' => $date,
            'posting_date' => $date,
            'currency_id' => $context['currency']->id,
            'exchange_rate' => 1,
            'description' => 'Analytics fixture',
            'total_debit' => $debit,
            'total_credit' => $credit,
            'base_total_debit' => $debit,
            'base_total_credit' => $credit,
            'created_by' => $context['user']->id,
            'posted_by' => $status === 'posted' ? $context['user']->id : null,
            'posted_at' => $status === 'posted' ? now() : null,
        ])->save();
        foreach ($lines as $index => [$account, $lineDebit, $lineCredit]) {
            DB::table('journal_entry_lines')->insert([
                'uuid' => fake()->unique()->uuid(),
                'journal_entry_id' => $entry->id,
                'line_number' => $index + 1,
                'account_id' => $account->id,
                'branch_id' => $branchId ?: $context['branch']->id,
                'currency_id' => $context['currency']->id,
                'exchange_rate' => 1,
                'debit_amount' => $lineDebit,
                'credit_amount' => $lineCredit,
                'base_debit_amount' => $lineDebit,
                'base_credit_amount' => $lineCredit,
                'description' => 'Analytics fixture',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $entry;
    }
}
