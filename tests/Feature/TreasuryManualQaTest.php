<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\CashBox;
use App\Models\CashBoxSession;
use App\Models\CashReceipt;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\MerchantSettlement;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\TreasuryApprovalLimit;
use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\CashBoxSessionService;
use App\Services\TreasuryApprovalLimitService;
use Database\Seeders\TreasuryManualQaSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryManualQaTest extends TestCase
{
    use DatabaseTransactions;

    private const QA_EMAILS = [
        'qa.owner@sevenways.test', 'qa.treasury.manager@sevenways.test',
        'qa.treasury.accountant@sevenways.test', 'qa.cairo.cashier@sevenways.test',
        'qa.giza.cashier@sevenways.test', 'qa.treasury.viewer@sevenways.test',
        'qa.disabled.cashier@sevenways.test',
    ];

    private Company $company;

    private Branch $cairo;

    private Branch $giza;

    protected function setUp(): void
    {
        parent::setUp();

        app(TreasuryManualQaSeeder::class)->run();
        $this->company = Company::query()->where('name', 'Seven Ways')->firstOrFail();
        $this->cairo = Branch::query()->where('company_id', $this->company->id)
            ->where('code', 'QA-CAI')->firstOrFail();
        $this->giza = Branch::query()->where('company_id', $this->company->id)
            ->where('code', 'QA-GIZ')->firstOrFail();
    }

    public function test_qa_login_enforces_user_company_and_branch_status(): void
    {
        $owner = $this->user('qa.owner@sevenways.test');
        $this->post(route('login'), $this->credentials($owner->email))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->post(route('login'), $this->credentials('qa.disabled.cashier@sevenways.test'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->company->forceFill(['is_active' => false])->save();
        $this->post(route('login'), $this->credentials('qa.treasury.manager@sevenways.test'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->company->forceFill(['is_active' => true])->save();

        $viewerRole = Role::query()->where('company_id', $this->company->id)
            ->where('name', 'qa_treasury_viewer')->firstOrFail();
        $orphan = User::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => null,
            'name' => 'QA No Branch', 'email' => 'qa.no.branch@sevenways.test',
            'password' => Hash::make('Test@123456'), 'status' => 'active',
        ]);
        $orphan->roles()->attach($viewerRole);
        $this->post(route('login'), $this->credentials($orphan->email))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_treasury_viewer_is_read_only_on_pages_and_actions(): void
    {
        $viewer = $this->user('qa.treasury.viewer@sevenways.test');
        $bank = $this->bankAccount('QA-BANK-CAI');
        $box = $this->cashBox('QA-CAI-MAIN');
        $this->actingAs($viewer)->withSession(['tenant.branch_id' => $this->cairo->id]);

        foreach ([
            'treasury.transfers.index', 'treasury.cash-sessions.index',
            'treasury.cash-receipts.index', 'treasury.cash-payments.index',
            'treasury.cheques.received', 'treasury.merchant-settlements.index',
            'treasury.approval-limits.index', 'treasury.operation-reports',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }

        $payload = [
            'transfer_type' => 'transfer', 'from_type' => 'bank',
            'from_bank_account_id' => $bank->id, 'to_type' => 'cash_box',
            'to_cash_box_id' => $box->id, 'branch_id' => $this->cairo->id,
            'destination_branch_id' => $this->cairo->id,
            'currency_id' => $this->company->currency_id, 'exchange_rate' => 1,
            'amount' => 100, 'fees_amount' => 0, 'transfer_date' => now()->toDateString(),
        ];
        $this->post(route('treasury.transfers.store'), $payload)->assertForbidden();
        $this->post(route('treasury.cash-sessions.store'), [
            'cash_box_id' => $box->id, 'custodian_user_id' => $viewer->id,
            'business_date' => now()->toDateString(),
        ])->assertForbidden();

        $transfer = TreasuryTransfer::factory()->create($payload + [
            'company_id' => $this->company->id, 'document_number' => 'QA-VIEWER-TR',
            'status' => 'pending_approval', 'created_by' => $this->user('qa.treasury.accountant@sevenways.test')->id,
        ]);
        $this->post(route('treasury.transfers.action', [$transfer, 'approve']))->assertForbidden();
        $this->post(route('treasury.transfers.process', $transfer))->assertForbidden();
        $this->post(route('treasury.transfers.reverse', $transfer), [
            'reason' => 'QA forbidden reversal', 'date' => now()->toDateString(),
        ])->assertForbidden();

        $managerRole = Role::query()->where('company_id', $this->company->id)
            ->where('name', 'qa_treasury_manager')->firstOrFail();
        $this->post(route('treasury.approval-limits.store'), [
            'branch_id' => $this->cairo->id, 'role_id' => $managerRole->id,
            'operation_type' => 'cash_payment', 'currency_id' => $this->company->currency_id,
            'minimum_amount' => 0, 'maximum_amount' => 1000, 'approval_level' => 1,
            'can_create' => true, 'can_submit' => true, 'can_approve' => true,
            'can_post' => true, 'is_active' => true, 'valid_from' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertFalse($this->user('qa.treasury.accountant@sevenways.test')
            ->hasPermission('treasury.cash_receipts.approve'));
        $this->assertTrue($this->user('qa.treasury.manager@sevenways.test')
            ->hasPermission('treasury.cash_receipts.approve'));
    }

    public function test_cash_operation_pages_use_spaced_layout_for_operational_roles(): void
    {
        foreach ([
            'qa.owner@sevenways.test',
            'qa.treasury.accountant@sevenways.test',
            'qa.cairo.cashier@sevenways.test',
        ] as $email) {
            foreach (['treasury.cash-receipts.index', 'treasury.cash-payments.index'] as $route) {
                $this->actingAs($this->user($email))
                    ->withSession(['tenant.branch_id' => $this->cairo->id])
                    ->get(route($route))
                    ->assertOk()
                    ->assertSee('cash-operations-page')
                    ->assertSee('cash-operation-table-card');
            }
        }
    }

    public function test_cairo_cashier_is_scoped_requires_session_and_cannot_spoof_protected_fields(): void
    {
        $cashier = $this->user('qa.cairo.cashier@sevenways.test');
        $cairoBox = $this->cashBox('QA-CAI-MAIN');
        $gizaBox = $this->cashBox('QA-GIZ-MAIN');
        $offset = $this->account('QA-OTHER-INCOME');
        $this->actingAs($cashier)->withSession(['tenant.branch_id' => $this->cairo->id]);

        $this->get(route('treasury.cash-sessions.index'))
            ->assertOk()->assertSee($cairoBox->name)->assertDontSee($gizaBox->name);
        $this->post(route('treasury.cash-sessions.store'), [
            'cash_box_id' => $gizaBox->id,
            'custodian_user_id' => $this->user('qa.giza.cashier@sevenways.test')->id,
            'business_date' => now()->toDateString(),
        ])->assertForbidden();

        $cashPayload = [
            'branch_id' => $this->cairo->id, 'cash_box_id' => $cairoBox->id,
            'receipt_type' => 'miscellaneous', 'document_date' => now()->toDateString(),
            'currency_id' => $this->company->currency_id, 'exchange_rate' => 1,
            'amount' => 100, 'offset_account_id' => $offset->id, 'description' => 'QA receipt',
        ];
        $this->post(route('treasury.cash-receipts.store'), $cashPayload)
            ->assertRedirect();

        $this->post(route('treasury.cash-sessions.store'), [
            'cash_box_id' => $cairoBox->id, 'custodian_user_id' => $cashier->id,
            'business_date' => now()->toDateString(),
        ])->assertRedirect();
        $session = CashBoxSession::query()->where('cash_box_id', $cairoBox->id)
            ->where('active_guard', 'active')->firstOrFail();
        $this->post(route('treasury.cash-receipts.store'), $cashPayload + [
            'cash_box_session_id' => $session->id,
        ])->assertRedirect();
        $this->assertDatabaseMissing('cash_receipts', [
            'company_id' => $this->company->id, 'branch_id' => $this->cairo->id,
            'cash_box_id' => $cairoBox->id, 'status' => 'draft', 'amount' => 100,
        ]);

        $before = CashBoxSession::query()->where('cash_box_id', $cairoBox->id)->count();
        try {
            app(CashBoxSessionService::class)->open([
                'cash_box_id' => $cairoBox->id, 'custodian_user_id' => $cashier->id,
                'business_date' => now()->toDateString(),
            ]);
            $this->fail('A second active session was opened.');
        } catch (BusinessRuleException) {
            $this->assertSame(
                $before,
                CashBoxSession::query()->where('cash_box_id', $cairoBox->id)->count()
            );
        }

        $this->post(route('treasury.cash-receipts.store'), $cashPayload + [
            'cash_box_session_id' => $session->id, 'company_id' => $this->company->id,
            'status' => 'posted', 'document_number' => 'SPOOF', 'journal_entry_id' => 1,
        ])->assertSessionHasErrors(['company_id', 'status', 'document_number', 'journal_entry_id']);
    }

    public function test_cross_branch_ids_return_forbidden_for_treasury_documents(): void
    {
        $actor = $this->cairoOnlyManager();
        $gizaBox = $this->cashBox('QA-GIZ-MAIN');
        $gizaBank = $this->bankAccount('QA-BANK-GIZ');
        $gizaCashier = $this->user('qa.giza.cashier@sevenways.test');
        $owner = $this->user('qa.owner@sevenways.test');
        $this->actingAs($actor)->withSession(['tenant.branch_id' => $this->cairo->id]);

        $transfer = TreasuryTransfer::factory()->create([
            'company_id' => $this->company->id, 'branch_id' => $this->giza->id,
            'destination_branch_id' => $this->giza->id, 'currency_id' => $this->company->currency_id,
            'from_type' => 'bank', 'from_bank_account_id' => $gizaBank->id,
            'to_type' => 'cash_box', 'to_cash_box_id' => $gizaBox->id,
            'document_number' => 'QA-XBR-TR', 'status' => 'draft', 'created_by' => $owner->id,
        ]);
        $session = CashBoxSession::factory()->create([
            'company_id' => $this->company->id, 'branch_id' => $this->giza->id,
            'cash_box_id' => $gizaBox->id, 'custodian_user_id' => $gizaCashier->id,
            'session_number' => 'QA-XBR-CS', 'status' => 'opened', 'active_guard' => 'QA-XBR',
            'opened_by' => $owner->id,
        ]);
        $cheque = Cheque::factory()->create([
            'company_id' => $this->company->id, 'branch_id' => $this->giza->id,
            'bank_id' => $gizaBank->bank_id, 'bank_account_id' => $gizaBank->id,
            'currency_id' => $this->company->currency_id,
            'clearing_account_id' => $this->account('116000')->id,
            'offset_account_id' => $this->account('QA-OTHER-INCOME')->id,
            'document_number' => 'QA-XBR-CH', 'created_by' => $owner->id,
        ]);
        $settlement = MerchantSettlement::factory()->create([
            'company_id' => $this->company->id, 'branch_id' => $this->giza->id,
            'bank_account_id' => $gizaBank->id,
            'payment_method_id' => $this->paymentMethod('QA-CARD')->id,
            'currency_id' => $this->company->currency_id,
            'document_number' => 'QA-XBR-MS', 'created_by' => $owner->id,
        ]);

        $this->post(route('treasury.transfers.action', [$transfer, 'submit']))->assertForbidden();
        $this->post(route('treasury.cash-sessions.action', [$session, 'start_counting']))->assertForbidden();
        $this->post(route('treasury.cheques.action', [$cheque, 'submit']))->assertForbidden();
        $this->post(route('treasury.merchant-settlements.action', [$settlement, 'submit']))->assertForbidden();

        $this->actingAs($this->gizaOnlyManager())->withSession(['tenant.branch_id' => $this->giza->id]);
        $this->post(route('treasury.cash-sessions.store'), [
            'cash_box_id' => $this->cashBox('QA-CAI-MAIN')->id,
            'custodian_user_id' => $this->user('qa.cairo.cashier@sevenways.test')->id,
            'business_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_approval_limit_user_and_branch_precedence_is_enforced(): void
    {
        $manager = $this->user('qa.treasury.manager@sevenways.test');
        $this->actingAs($manager)->withSession(['tenant.branch_id' => $this->cairo->id])
            ->get(route('dashboard'))->assertOk();
        $service = app(TreasuryApprovalLimitService::class);

        $service->assert(
            $manager, 'treasury_transfer', 'approve',
            $this->company->currency_id, '15000', $this->cairo->id
        );
        $this->assertBusinessRule(fn () => $service->assert(
            $manager, 'treasury_transfer', 'approve',
            $this->company->currency_id, '20001', $this->cairo->id
        ));

        $probe = $this->managerProbe();
        $service->assert(
            $probe, 'treasury_transfer', 'approve',
            $this->company->currency_id, '4000', $this->cairo->id
        );
        $this->assertBusinessRule(fn () => $service->assert(
            $probe, 'treasury_transfer', 'approve',
            $this->company->currency_id, '7000', $this->cairo->id
        ));
        $service->assert(
            $probe, 'treasury_transfer', 'approve',
            $this->company->currency_id, '7000', $this->giza->id
        );
        $this->assertBusinessRule(fn () => $service->assert(
            $probe, 'treasury_transfer', 'approve',
            $this->company->currency_id, '15000', $this->giza->id
        ));
    }

    public function test_manual_qa_seeder_is_idempotent_and_creates_no_operational_data(): void
    {
        $qaUserIds = User::query()->whereIn('email', self::QA_EMAILS)->pluck('id');
        $qaRoleIds = Role::query()->where('company_id', $this->company->id)
            ->where('name', 'like', 'qa_treasury_%')->pluck('id');
        $before = [
            'users' => User::query()->whereIn('email', self::QA_EMAILS)->count(),
            'branches' => Branch::query()->where('company_id', $this->company->id)
                ->whereIn('code', ['QA-CAI', 'QA-GIZ'])->count(),
            'boxes' => CashBox::query()->where('company_id', $this->company->id)
                ->whereIn('code', ['QA-CAI-MAIN', 'QA-CAI-SALES', 'QA-GIZ-MAIN'])->count(),
            'banks' => BankAccount::query()->where('company_id', $this->company->id)
                ->whereIn('account_code', ['QA-BANK-CAI', 'QA-BANK-GIZ'])->count(),
            'methods' => PaymentMethod::query()->where('company_id', $this->company->id)
                ->whereIn('code', ['QA-CASH', 'QA-CARD', 'QA-ONLINE'])->count(),
            'limits' => TreasuryApprovalLimit::query()->where('company_id', $this->company->id)
                ->where(fn ($query) => $query->whereIn('user_id', $qaUserIds)
                    ->orWhereIn('role_id', $qaRoleIds))->count(),
            'transfers' => TreasuryTransfer::query()->count(),
            'sessions' => CashBoxSession::query()->count(),
            'receipts' => CashReceipt::query()->count(),
            'cheques' => Cheque::query()->count(),
            'settlements' => MerchantSettlement::query()->count(),
            'journals' => JournalEntry::query()->count(),
        ];

        app(TreasuryManualQaSeeder::class)->run();

        $this->assertSame(7, $before['users']);
        $this->assertSame(2, $before['branches']);
        $this->assertSame(3, $before['boxes']);
        $this->assertSame(2, $before['banks']);
        $this->assertSame(3, $before['methods']);
        $this->assertSame($before['users'], User::query()->whereIn('email', self::QA_EMAILS)->count());
        $this->assertSame($before['branches'], Branch::query()->where('company_id', $this->company->id)
            ->whereIn('code', ['QA-CAI', 'QA-GIZ'])->count());
        $this->assertSame($before['boxes'], CashBox::query()->where('company_id', $this->company->id)
            ->whereIn('code', ['QA-CAI-MAIN', 'QA-CAI-SALES', 'QA-GIZ-MAIN'])->count());
        $this->assertSame($before['banks'], BankAccount::query()->where('company_id', $this->company->id)
            ->whereIn('account_code', ['QA-BANK-CAI', 'QA-BANK-GIZ'])->count());
        $this->assertSame($before['methods'], PaymentMethod::query()->where('company_id', $this->company->id)
            ->whereIn('code', ['QA-CASH', 'QA-CARD', 'QA-ONLINE'])->count());
        $this->assertSame($before['limits'], TreasuryApprovalLimit::query()
            ->where('company_id', $this->company->id)
            ->where(fn ($query) => $query->whereIn('user_id', $qaUserIds)
                ->orWhereIn('role_id', $qaRoleIds))->count());
        $this->assertSame($before['transfers'], TreasuryTransfer::query()->count());
        $this->assertSame($before['sessions'], CashBoxSession::query()->count());
        $this->assertSame($before['receipts'], CashReceipt::query()->count());
        $this->assertSame($before['cheques'], Cheque::query()->count());
        $this->assertSame($before['settlements'], MerchantSettlement::query()->count());
        $this->assertSame($before['journals'], JournalEntry::query()->count());
        $this->assertFalse(Schema::hasColumn('cash_boxes', 'balance'));
        $this->assertFalse(Schema::hasColumn('bank_accounts', 'balance'));
        $this->assertSame(1, AccountingPeriod::query()->where('company_id', $this->company->id)
            ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())
            ->where('is_adjustment_period', false)->count());
    }

    private function credentials(string $email): array
    {
        return ['email' => $email, 'password' => 'Test@123456'];
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function cashBox(string $code): CashBox
    {
        return CashBox::query()->where('company_id', $this->company->id)->where('code', $code)->firstOrFail();
    }

    private function bankAccount(string $code): BankAccount
    {
        return BankAccount::query()->where('company_id', $this->company->id)
            ->where('account_code', $code)->firstOrFail();
    }

    private function account(string $code)
    {
        return \App\Models\Account::query()->where('company_id', $this->company->id)
            ->where('account_code', $code)->firstOrFail();
    }

    private function paymentMethod(string $code): PaymentMethod
    {
        return PaymentMethod::query()->where('company_id', $this->company->id)
            ->where('code', $code)->firstOrFail();
    }

    private function cairoOnlyManager(): User
    {
        $role = Role::query()->where('company_id', $this->company->id)
            ->where('name', 'qa_treasury_manager')->firstOrFail();
        $user = User::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->cairo->id,
            'name' => 'QA Cairo Manager Probe', 'email' => 'qa.cairo.manager.probe@sevenways.test',
            'password' => Hash::make('Test@123456'), 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($this->cairo, [
            'is_default' => true, 'can_view' => true, 'can_create' => true,
            'can_update' => true, 'can_approve' => true,
        ]);

        return $user;
    }

    private function managerProbe(): User
    {
        $role = Role::query()->where('company_id', $this->company->id)
            ->where('name', 'qa_treasury_manager')->firstOrFail();
        $user = User::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->cairo->id,
            'name' => 'QA Limit Probe', 'email' => 'qa.limit.probe@sevenways.test',
            'password' => Hash::make('Test@123456'), 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach([
            $this->cairo->id => [
                'is_default' => true, 'can_view' => true, 'can_create' => true,
                'can_update' => true, 'can_approve' => true,
            ],
            $this->giza->id => [
                'is_default' => false, 'can_view' => true, 'can_create' => true,
                'can_update' => true, 'can_approve' => true,
            ],
        ]);

        return $user;
    }

    private function gizaOnlyManager(): User
    {
        $role = Role::query()->where('company_id', $this->company->id)
            ->where('name', 'qa_treasury_manager')->firstOrFail();
        $user = User::query()->forceCreate([
            'company_id' => $this->company->id, 'branch_id' => $this->giza->id,
            'name' => 'QA Giza Manager Probe', 'email' => 'qa.giza.manager.probe@sevenways.test',
            'password' => Hash::make('Test@123456'), 'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($this->giza, [
            'is_default' => true, 'can_view' => true, 'can_create' => true,
            'can_update' => true, 'can_approve' => true,
        ]);

        return $user;
    }

    private function assertBusinessRule(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a treasury business rule exception.');
        } catch (BusinessRuleException) {
            $this->assertTrue(true);
        }
    }
}
