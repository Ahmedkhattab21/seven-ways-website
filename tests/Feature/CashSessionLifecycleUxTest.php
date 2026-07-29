<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashBox;
use App\Models\JournalEntry;
use App\Services\CashBoxCountService;
use App\Services\CashBoxCustodianService;
use App\Services\CashBoxSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class CashSessionLifecycleUxTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_session_page_translates_active_status_and_hides_manual_start_action(): void
    {
        [$context, $box, $session] = $this->openSession();
        $session->forceFill(['status' => 'counting'])->saveQuietly();

        $response = $this->actingAs($context['cashier'])->get(route('treasury.cash-sessions.index'));

        $response->assertOk()
            ->assertSee('الجلسة نشطة')
            ->assertSee('cash-sessions-page')
            ->assertSee('cash-session-summary')
            ->assertSee('sw-field__label')
            ->assertDontSee('start_counting')
            ->assertDontSee('جارٍ الجرد');
    }

    public function test_opening_and_closing_counts_cannot_be_duplicated(): void
    {
        [, , $session] = $this->openSession();
        $service = app(CashBoxCountService::class);
        $service->create($session, ['count_type' => 'opening', 'count_input_mode' => 'match_book']);

        try {
            $service->create($session, ['count_type' => 'opening', 'count_input_mode' => 'match_book']);
            $this->fail('Duplicate opening count was accepted.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('عد افتتاحي آخر', $exception->getMessage());
        }

        $session->forceFill(['status' => 'counting'])->saveQuietly();
        $service->create($session->fresh(), ['count_type' => 'closing', 'count_input_mode' => 'match_book']);

        $this->expectException(BusinessRuleException::class);
        $service->create($session->fresh(), ['count_type' => 'closing', 'count_input_mode' => 'match_book']);
    }

    public function test_count_form_creates_and_submits_count_in_one_step(): void
    {
        [$context, , $session] = $this->openSession();

        $response = $this->actingAs($context['cashier'])->post(
            route('treasury.cash-sessions.counts.store', $session),
            ['count_type' => 'opening', 'count_input_mode' => 'match_book', 'notes' => 'E2E opening count']
        );

        $response->assertRedirect()->assertSessionHas('success', 'تم تسجيل العد وإرساله للمراجعة.');
        $this->assertDatabaseHas('cash_box_counts', [
            'cash_box_session_id' => $session->id,
            'count_type' => 'opening',
            'status' => 'submitted',
        ]);
    }

    public function test_posted_automatic_journal_uses_arabic_status_and_hides_manual_actions(): void
    {
        $context = $this->treasuryContext();
        $entry = new JournalEntry;
        $entry->forceFill([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'fiscal_year_id' => $context['year']->id,
            'accounting_period_id' => $context['period']->id,
            'journal_number' => 'UAT-E2E-JE-001',
            'entry_type' => 'treasury',
            'status' => 'posted',
            'entry_date' => '2040-01-10',
            'posting_date' => '2040-01-10',
            'description' => 'UAT treasury journal',
            'currency_id' => $context['currency']->id,
            'exchange_rate' => 1,
            'total_debit' => 100,
            'total_credit' => 100,
            'base_total_debit' => 100,
            'base_total_credit' => 100,
            'is_automatic' => true,
            'created_by' => $context['user']->id,
            'posted_by' => $context['user']->id,
            'posted_at' => now(),
        ])->save();
        foreach ([
            [$this->treasuryAccount($context, '111000')->id, 100, 0],
            [$this->treasuryAccount($context, '310000')->id, 0, 100],
        ] as $index => [$accountId, $debit, $credit]) {
            $entry->lines()->create([
                'line_number' => $index + 1,
                'account_id' => $accountId,
                'currency_id' => $context['currency']->id,
                'exchange_rate' => 1,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'base_debit_amount' => $debit,
                'base_credit_amount' => $credit,
            ]);
        }

        $response = $this->actingAs($context['user'])->get(route('accounting.journals.show', $entry));

        $response->assertOk()
            ->assertSee('مُرحّل')
            ->assertDontSee('>submit<', false)
            ->assertDontSee('>approve<', false)
            ->assertDontSee('>post<', false)
            ->assertDontSee('>cancel<', false)
            ->assertDontSee('>Reverse<', false);
    }

    private function openSession(): array
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        app(CashBoxCustodianService::class)->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => '2020-01-01',
            'can_receive' => true, 'can_pay' => true, 'can_transfer' => true, 'is_primary' => true,
        ]);
        $this->switchTreasuryActor($context['cashier']);
        $session = app(CashBoxSessionService::class)->open([
            'cash_box_id' => $box->id, 'custodian_user_id' => $context['cashier']->id,
            'business_date' => '2040-01-10',
        ]);

        return [$context, $box, $session];
    }
}
