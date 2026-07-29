<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashBox;
use App\Models\CashBoxCount;
use App\Models\CashBoxSession;
use App\Models\CashOverShortAdjustment;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CashOverShortService;
use App\Services\FiscalPeriodGenerationService;
use App\Services\TreasuryBalanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class CashOverShortAdjustmentUiTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_ui_only_offers_adjustment_for_approved_non_zero_count_and_translates_difference(): void
    {
        $context = $this->treasuryContext();
        $box = $this->cashBox($context);
        $zero = $this->cashCount($context, $box, '0.0000');
        $short = $this->cashCount($context, $box, '-100.0000');
        $over = $this->cashCount($context, $box, '100.0000');

        $response = $this->get(route('treasury.cash-sessions.index'));

        $response->assertOk()
            ->assertSee('عجز بقيمة 100.0000')
            ->assertSee('زيادة بقيمة 100.0000')
            ->assertSee('إنشاء تسوية العجز')
            ->assertSee('إنشاء تسوية الزيادة');
        $this->assertSame(2, substr_count($response->getContent(), 'cash-over-short-create-form'));
        $this->assertSame('0.0000', $zero->difference);
        $this->assertSame('approved', $short->status);
        $this->assertSame('approved', $over->status);
    }

    public function test_creation_requires_approved_count_reason_and_rejects_duplicate(): void
    {
        $context = $this->treasuryContext();
        $count = $this->cashCount($context, $this->cashBox($context), '-100.0000');

        $this->post(route('treasury.cash-counts.adjustment', $count), [])
            ->assertSessionHasErrors('description');
        $this->assertDatabaseMissing('cash_over_short_adjustments', ['cash_box_count_id' => $count->id]);

        $count->forceFill(['status' => 'reviewed'])->saveQuietly();
        $this->post(route('treasury.cash-counts.adjustment', $count), [
            'description' => 'عجز افتتاحي عند استلام الوردية',
        ])->assertNotFound();
        $this->assertDatabaseMissing('cash_over_short_adjustments', ['cash_box_count_id' => $count->id]);

        $count->forceFill(['status' => 'approved'])->saveQuietly();
        $this->post(route('treasury.cash-counts.adjustment', $count), [
            'description' => 'عجز افتتاحي عند استلام الوردية',
        ])->assertRedirect()->assertSessionHas('success', 'تم إنشاء تسوية فرق الخزينة.');
        $this->post(route('treasury.cash-counts.adjustment', $count), [
            'description' => 'محاولة إنشاء تسوية مكررة',
        ])->assertRedirect()->assertSessionHasErrors('business');
        $this->assertSame(1, CashOverShortAdjustment::query()->where('cash_box_count_id', $count->id)->count());
    }

    public function test_owner_can_complete_balanced_short_and_over_workflow_once(): void
    {
        $context = $this->treasuryContext();
        $reversalYear = FiscalYear::factory()->create([
            'company_id' => $context['company']->id,
            'code' => 'FY-'.now()->year,
            'name' => 'FY '.now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'open',
            'is_current' => false,
            'created_by' => $context['user']->id,
        ]);
        app(FiscalPeriodGenerationService::class)->monthly($reversalYear);
        $box = $this->cashBox($context);
        $balanceBefore = app(TreasuryBalanceService::class)->cashBox($box)['book_balance'];

        $short = app(CashOverShortService::class)->create(
            $this->cashCount($context, $box, '-100.0000'),
            'عجز افتتاحي عند استلام الوردية'
        );
        $service = app(CashOverShortService::class);
        $service->action($short, 'submit');
        $service->action($short->fresh(), 'approve');
        $postedShort = $service->action($short->fresh(), 'post');
        $shortJournal = JournalEntry::query()->with('lines')->findOrFail($postedShort->journal_entry_id);

        $this->assertSame('posted', $postedShort->status);
        $this->assertSame($shortJournal->total_debit, $shortJournal->total_credit);
        $this->assertSame(
            bcsub($balanceBefore, '100.0000', 4),
            app(TreasuryBalanceService::class)->cashBox($box)['book_balance']
        );
        $this->expectBusinessRule(fn () => $service->action($postedShort->fresh(), 'post'));
        $this->get(route('treasury.cash-sessions.index'))
            ->assertSee($shortJournal->journal_number)
            ->assertSee('عكس التسوية');
        $this->post(route('treasury.cash-over-short.action', [$postedShort, 'reverse']))
            ->assertSessionHasErrors('reason');
        $this->post(route('treasury.cash-over-short.action', [$postedShort, 'reverse']), [
            'reason' => 'عكس موثق لتسوية العجز',
        ])->assertRedirect()->assertSessionHas('success', 'تم عكس تسوية فرق الخزينة.');
        $this->assertSame('reversed', $postedShort->fresh()->status);
        $this->assertSame(
            $balanceBefore,
            app(TreasuryBalanceService::class)->cashBox($box)['book_balance']
        );

        $over = $service->create($this->cashCount($context, $box, '100.0000'), 'زيادة مثبتة عند الجرد');
        $service->action($over, 'submit');
        $service->action($over->fresh(), 'approve');
        $service->action($over->fresh(), 'post');

        $this->assertSame(
            bcadd($balanceBefore, '100.0000', 4),
            app(TreasuryBalanceService::class)->cashBox($box)['book_balance']
        );
        $this->assertSame(3, JournalEntry::query()
            ->where('source_type', CashOverShortAdjustment::class)->count());
    }

    public function test_ui_buttons_follow_permissions_and_status_without_role_expansion(): void
    {
        $context = $this->treasuryContext();
        $count = $this->cashCount($context, $this->cashBox($context), '-100.0000');
        $adjustment = app(CashOverShortService::class)->create($count, 'عجز مثبت للمراجعة');
        $this->grant($context['cashier'], ['treasury.cash_sessions.view', 'treasury.cash_over_short.view']);
        $this->switchTreasuryActor($context['cashier']);

        $this->get(route('treasury.cash-sessions.index'))
            ->assertOk()
            ->assertSee('عجز خزينة')
            ->assertSee('مسودة')
            ->assertSee('إرسال التسوية للاعتماد')
            ->assertDontSee('اعتماد التسوية')
            ->assertDontSee('ترحيل التسوية');
        $this->post(route('treasury.cash-over-short.action', [$adjustment, 'approve']))
            ->assertForbidden();

        $accountantRole = Role::query()->create([
            'company_id' => $context['company']->id, 'name' => 'cash_short_accountant_test',
            'display_name' => 'Cash short accountant test', 'scope' => 'branch', 'is_active' => true,
        ]);
        $accountant = $this->treasuryUser($context['company'], $context['branch'], $accountantRole);
        $this->grant($accountant, ['treasury.cash_sessions.view']);
        $this->switchTreasuryActor($accountant);
        $this->get(route('treasury.cash-sessions.index'))
            ->assertOk()
            ->assertSee('عجز بقيمة 100.0000')
            ->assertDontSee('تسوية فرق الخزينة')
            ->assertDontSee('إرسال التسوية للاعتماد')
            ->assertDontSee('اعتماد التسوية')
            ->assertDontSee('ترحيل التسوية');

        $this->switchTreasuryActor($context['user']);
        app(CashOverShortService::class)->action($adjustment->fresh(), 'submit');
        $this->get(route('treasury.cash-sessions.index'))
            ->assertSee('في انتظار الاعتماد')
            ->assertSee('اعتماد التسوية')
            ->assertDontSee('ترحيل التسوية');

        app(CashOverShortService::class)->action($adjustment->fresh(), 'approve');
        $this->get(route('treasury.cash-sessions.index'))
            ->assertSee('معتمدة')
            ->assertSee('ترحيل التسوية');
    }

    public function test_missing_mapping_returns_arabic_business_message_without_posting(): void
    {
        $context = $this->treasuryContext();
        $box = $this->cashBox($context);
        $count = $this->cashCount($context, $box, '-100.0000');
        $adjustment = app(CashOverShortService::class)->create($count, 'عجز بدون حساب مربوط');
        app(CashOverShortService::class)->action($adjustment, 'submit');
        app(CashOverShortService::class)->action($adjustment->fresh(), 'approve');
        $box->forceFill(['over_short_account_id' => null])->saveQuietly();

        $this->post(route('treasury.cash-over-short.action', [$adjustment, 'post']))
            ->assertRedirect()
            ->assertSessionHasErrors('business');

        $this->assertSame('approved', $adjustment->fresh()->status);
        $this->assertNull($adjustment->fresh()->journal_entry_id);
        $this->assertSame(0, JournalEntry::query()
            ->where('source_type', CashOverShortAdjustment::class)->count());
    }

    public function test_cross_company_and_cross_branch_requests_are_forbidden(): void
    {
        $context = $this->treasuryContext();
        $count = $this->cashCount($context, $this->cashBox($context), '-100.0000');

        $branchRole = Role::query()->create([
            'company_id' => $context['company']->id, 'name' => 'cash_short_branch_test',
            'display_name' => 'Cash short branch test', 'scope' => 'branch', 'is_active' => true,
        ]);
        $branchUser = $this->treasuryUser($context['company'], $context['secondBranch'], $branchRole);
        $this->grant($branchUser, ['treasury.cash_over_short.view']);
        $this->switchTreasuryActor($branchUser);
        $this->post(route('treasury.cash-counts.adjustment', $count), [
            'description' => 'محاولة من فرع غير مسموح',
        ])->assertForbidden();

        $other = $this->treasuryContext();
        $this->switchTreasuryActor($other['user']);
        $this->post(route('treasury.cash-counts.adjustment', $count), [
            'description' => 'محاولة من شركة أخرى',
        ])->assertForbidden();
        $this->assertDatabaseMissing('cash_over_short_adjustments', ['cash_box_count_id' => $count->id]);
    }

    private function cashBox(array $context): CashBox
    {
        return CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
    }

    private function cashCount(array $context, CashBox $box, string $difference): CashBoxCount
    {
        $session = CashBoxSession::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'cash_box_id' => $box->id,
            'custodian_user_id' => $context['cashier']->id,
            'business_date' => '2040-01-10',
            'status' => 'counting',
            'active_guard' => null,
            'opening_book_balance' => '800.0000',
            'opening_counted_balance' => '800.0000',
            'opening_difference' => '0.0000',
            'opened_by' => $context['user']->id,
        ]);
        $book = '800.0000';
        $counted = bcadd($book, $difference, 4);

        return CashBoxCount::factory()->create([
            'company_id' => $context['company']->id,
            'cash_box_session_id' => $session->id,
            'count_type' => 'opening',
            'status' => 'approved',
            'book_total' => $book,
            'counted_total' => $counted,
            'difference' => $difference,
            'counted_by' => $context['cashier']->id,
            'reviewed_by' => $context['approver']->id,
            'approved_by' => $context['user']->id,
            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);
    }

    private function grant(User $user, array $permissions): void
    {
        $ids = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $user->roles()->firstOrFail()->permissions()->syncWithoutDetaching($ids);
    }

    private function expectBusinessRule(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a business rule exception.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
    }
}
