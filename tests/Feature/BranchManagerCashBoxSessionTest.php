<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashBox;
use App\Models\CashBoxCustodian;
use App\Models\CashBoxSession;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Role;
use App\Services\CashBoxCustodianService;
use Carbon\Carbon;
use Database\Seeders\ThreeRoleOperatingModelSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class BranchManagerCashBoxSessionTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_three_role_reconciler_grants_only_operational_session_permissions_to_branch_manager(): void
    {
        app(ThreeRoleOperatingModelSeeder::class)->run();

        $role = Role::query()->whereNull('company_id')->where('name', 'branch_manager')->firstOrFail();

        foreach ([
            'treasury.cash_sessions.view',
            'treasury.cash_sessions.open',
            'treasury.cash_sessions.count',
            'treasury.cash_sessions.submit',
        ] as $permission) {
            $this->assertTrue($role->permissions()->where('name', $permission)->exists(), $permission);
        }

        foreach ([
            'treasury.cash_sessions.review',
            'treasury.cash_sessions.approve',
            'treasury.cash_sessions.reopen',
            'treasury.cash_over_short.approve',
            'treasury.cash_over_short.post',
            'treasury.cash_boxes.activate',
            'treasury.cash_boxes.close',
        ] as $permission) {
            $this->assertFalse($role->permissions()->where('name', $permission)->exists(), $permission);
        }
    }

    public function test_branch_manager_sees_only_own_eligible_cash_box_and_self_as_readonly_custodian(): void
    {
        [$context, $manager, $alexandriaBox, $cairoBox] = $this->branchManagerContext();

        $response = $this->actingAs($manager)->get(route('treasury.cash-sessions.index'));

        $response->assertOk()
            ->assertSee(route('treasury.cash-sessions.index'), false)
            ->assertSee($alexandriaBox->name)
            ->assertDontSee($cairoBox->name)
            ->assertSee($manager->name)
            ->assertSee('name="custodian_user_id" value="'.$manager->id.'"', false)
            ->assertDontSee('<select class="sw-input" name="custodian_user_id"', false);

        $this->assertSame([$context['secondBranch']->id], $manager->accessibleBranches()->pluck('branches.id')->all());
    }

    public function test_branch_manager_can_open_own_session_without_posting_financial_entries(): void
    {
        [$context, $manager, $box] = $this->branchManagerContext();
        $journalCount = JournalEntry::query()->count();

        $response = $this->actingAs($manager)->post(route('treasury.cash-sessions.store'), [
            'cash_box_id' => $box->id,
            'custodian_user_id' => $manager->id,
            'business_date' => '2040-01-10',
            'opening_notes' => 'Alexandria opening shift',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('cash_box_sessions', [
            'company_id' => $context['company']->id,
            'branch_id' => $context['secondBranch']->id,
            'cash_box_id' => $box->id,
            'custodian_user_id' => $manager->id,
            'status' => 'opened',
        ]);
        $this->assertSame($journalCount, JournalEntry::query()->count());
    }

    public function test_branch_manager_cannot_open_another_branch_box_or_choose_another_custodian(): void
    {
        [$context, $manager, $alexandriaBox, $cairoBox] = $this->branchManagerContext();

        $this->actingAs($manager)->post(route('treasury.cash-sessions.store'), [
            'cash_box_id' => $cairoBox->id,
            'custodian_user_id' => $manager->id,
            'business_date' => '2040-01-10',
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('treasury.cash-sessions.store'), [
            'cash_box_id' => $alexandriaBox->id,
            'custodian_user_id' => $context['cashier']->id,
            'business_date' => '2040-01-10',
        ])->assertForbidden();

        $this->assertSame(0, CashBoxSession::query()->where('company_id', $context['company']->id)->count());
    }

    public function test_expired_custodian_assignment_is_hidden_and_rejected(): void
    {
        [$context, $manager, $box] = $this->branchManagerContext();
        CashBoxCustodian::query()->where('cash_box_id', $box->id)->where('user_id', $manager->id)
            ->update(['valid_to' => '2026-07-29']);

        $this->actingAs($manager)->get(route('treasury.cash-sessions.index'))
            ->assertOk()
            ->assertDontSee($box->name);

        $this->actingAs($manager)->post(route('treasury.cash-sessions.store'), [
            'cash_box_id' => $box->id,
            'custodian_user_id' => $manager->id,
            'business_date' => '2040-01-10',
        ])->assertRedirect()->assertSessionHasErrors('business');

        $this->assertDatabaseMissing('cash_box_sessions', [
            'company_id' => $context['company']->id,
            'cash_box_id' => $box->id,
        ]);
    }

    public function test_duplicate_active_session_is_rejected_without_creating_another_session(): void
    {
        [$context, $manager, $box] = $this->branchManagerContext();
        $payload = [
            'cash_box_id' => $box->id,
            'custodian_user_id' => $manager->id,
            'business_date' => '2040-01-10',
        ];

        $this->actingAs($manager)->post(route('treasury.cash-sessions.store'), $payload)
            ->assertRedirect()->assertSessionHas('success');
        $this->actingAs($manager)->post(route('treasury.cash-sessions.store'), $payload)
            ->assertRedirect()->assertSessionHasErrors('business');

        $this->assertSame(1, CashBoxSession::query()
            ->where('company_id', $context['company']->id)
            ->where('cash_box_id', $box->id)
            ->count());
    }

    private function branchManagerContext(): array
    {
        $context = $this->treasuryContext();
        Carbon::setTestNow('2026-07-30 09:00:00');
        $role = Role::query()->create([
            'company_id' => $context['company']->id,
            'name' => 'branch_manager',
            'display_name' => 'Branch manager',
            'scope' => 'branch',
            'is_active' => true,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('name', [
            'dashboard.view',
            'treasury.cash_boxes.view',
            'treasury.cash_sessions.view',
            'treasury.cash_sessions.open',
            'treasury.cash_sessions.count',
            'treasury.cash_sessions.submit',
        ])->pluck('id'));
        $manager = $this->treasuryUser($context['company'], $context['secondBranch'], $role);
        $manager->forceFill(['name' => 'Alexandria manager'])->save();

        $cairoBox = CashBox::query()
            ->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)
            ->firstOrFail();
        $cashAccount = $this->secondCashAccount($context, $cairoBox->glAccount);
        $alexandriaBox = new CashBox([
            'branch_id' => $context['secondBranch']->id,
            'code' => 'ALEX-BOX-'.substr(uniqid(), -5),
            'name' => 'Alexandria cash box',
            'currency_id' => $context['currency']->id,
            'gl_account_id' => $cashAccount->id,
            'over_short_account_id' => $this->treasuryAccount($context, '650000')->id,
            'is_primary' => true,
            'allows_receipts' => true,
            'allows_payments' => true,
            'requires_shift_opening' => true,
        ]);
        $alexandriaBox->forceFill([
            'company_id' => $context['company']->id,
            'status' => 'active',
            'created_by' => $context['user']->id,
        ])->save();
        app(CashBoxCustodianService::class)->assign($alexandriaBox, [
            'user_id' => $manager->id,
            'valid_from' => '2026-01-01',
            'can_receive' => true,
            'can_pay' => true,
            'can_transfer' => false,
            'payment_limit' => 10000,
            'is_primary' => true,
        ]);
        $this->switchTreasuryActor($manager);

        return [$context, $manager, $alexandriaBox, $cairoBox];
    }

    private function secondCashAccount(array $context, Account $source): Account
    {
        $account = $source->replicate(['uuid', 'account_code', 'account_path', 'created_at', 'updated_at']);
        $account->forceFill([
            'account_code' => '111-ALEX-'.substr(uniqid(), -5),
            'name_ar' => 'Alexandria cash',
            'name_en' => 'Alexandria cash',
            'is_cash_account' => true,
            'is_bank_account' => false,
            'is_header' => false,
            'is_posting' => true,
            'created_by' => $context['user']->id,
        ])->save();
        $parentPath = $source->parent?->account_path;
        $account->forceFill(['account_path' => trim($parentPath.'/'.$account->id, '/')])->saveQuietly();

        return $account;
    }
}
