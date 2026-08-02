<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerPaymentApprovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_company_management_can_approve_payments_in_another_company_branch(): void
    {
        foreach (['company_owner', 'general_manager'] as $roleName) {
            $context = $this->context();
            $manager = $this->userWithRole($context, $roleName, true);
            $payment = $this->payment($context, $context['cairo']);

            $this->actingAs($manager)
                ->post(route('customer-payments.approve', $payment))
                ->assertRedirect()
                ->assertSessionHas('success', 'تم اعتماد الدفعة بنجاح.');

            $this->assertSame('approved', $payment->fresh()->status);
        }
    }

    public function test_user_without_approval_permission_cannot_see_or_call_approval(): void
    {
        $context = $this->context();
        $viewer = $this->userWithRole($context, 'accountant', false);
        $payment = $this->payment($context, $context['alexandria']);

        $this->actingAs($viewer)
            ->get(route('customer-payments.show', $payment))
            ->assertOk()
            ->assertSee('مسجلة')
            ->assertDontSee('اعتماد الدفعة');
        $this->post(route('customer-payments.approve', $payment))->assertForbidden();
    }

    public function test_only_recorded_payment_exposes_approval_action(): void
    {
        $context = $this->context();
        $manager = $this->userWithRole($context, 'branch_manager', true);
        $payment = $this->payment($context, $context['alexandria'], 'approved');

        $this->actingAs($manager)
            ->get(route('customer-payments.show', $payment))
            ->assertOk()
            ->assertSee('معتمدة')
            ->assertDontSee('اعتماد الدفعة');
        $this->post(route('customer-payments.approve', $payment))->assertForbidden();
    }

    public function test_permission_migration_is_idempotent_and_preserves_other_roles(): void
    {
        $context = $this->context();
        $managers = collect(['branch_manager', 'company_owner', 'general_manager'])
            ->map(fn (string $role) => $this->userWithRole($context, $role, false));
        $accountant = $this->userWithRole($context, 'accountant', false);
        $migration = require database_path('migrations/2026_08_02_010000_grant_customer_payment_approval_to_management_roles.php');

        $migration->up();
        $migration->up();

        $managers->each(fn (User $user) => $this->assertTrue($user->hasPermission('customer_payments.approve')));
        $this->assertFalse($accountant->hasPermission('customer_payments.approve'));
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(uniqid(), -3)),
            'name_ar' => 'جنيه',
            'name_en' => 'Pound',
            'symbol' => 'EGP',
            'decimal_places' => 2,
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'name' => 'UAT 033 '.uniqid(),
            'currency_id' => $currency->id,
            'is_active' => true,
        ]);
        $alexandria = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'ALX'.uniqid(),
            'name' => 'الإسكندرية',
            'is_main' => true,
            'is_active' => true,
        ]);
        $cairo = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'CAI'.uniqid(),
            'name' => 'القاهرة',
            'is_main' => false,
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create([
            'company_id' => $company->id,
            'created_branch_id' => $alexandria->id,
            'assigned_branch_id' => $alexandria->id,
        ]);
        $method = PaymentMethod::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'CARD'.uniqid(),
            'name' => 'بطاقة',
            'type' => 'card',
            'requires_reference' => false,
            'is_cash' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $creator = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $alexandria->id,
            'status' => 'active',
        ]);

        return compact('currency', 'company', 'alexandria', 'cairo', 'customer', 'method', 'creator');
    }

    private function userWithRole(array $context, string $roleName, bool $canApprove): User
    {
        $user = User::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['alexandria']->id,
            'status' => 'active',
        ]);
        $role = Role::query()->create([
            'company_id' => $context['company']->id,
            'name' => $roleName,
            'display_name' => $roleName,
            'scope' => $roleName === 'branch_manager' ? 'branch' : 'company',
            'is_active' => true,
        ]);
        foreach (['customer_payments.view', ...($canApprove ? ['customer_payments.approve'] : [])] as $permission) {
            $role->permissions()->syncWithoutDetaching(
                Permission::query()->firstOrCreate(['name' => $permission], ['display_name' => $permission])
            );
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($context['alexandria'], [
            'is_default' => true,
            'can_view' => true,
            'can_approve' => $canApprove,
        ]);

        return $user;
    }

    private function payment(array $context, Branch $branch, string $status = 'recorded'): CustomerPayment
    {
        return CustomerPayment::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $branch->id,
            'customer_id' => $context['customer']->id,
            'currency_id' => $context['currency']->id,
            'payment_method_id' => $context['method']->id,
            'received_by' => $context['creator']->id,
            'status' => $status,
        ]);
    }
}
