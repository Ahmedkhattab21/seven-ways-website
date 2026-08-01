<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UatDef031SalesInvoiceApprovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_manager_sees_arabic_action_and_approves_own_branch_with_audit(): void
    {
        $context = $this->context();
        $manager = $this->userWithRole($context, 'branch_manager', $context['alexandria'], true);
        $invoice = $this->invoice($context, $context['alexandria'], 'pending_approval', $manager);

        $this->actingAs($manager)
            ->get(route('sales-invoices.show', $invoice))
            ->assertOk()
            ->assertSee('بانتظار الاعتماد')
            ->assertSee('اعتماد الفاتورة');

        $this->post(route('sales-invoices.action', [$invoice, 'approve']))
            ->assertRedirect()
            ->assertSessionHas('success', 'تم تنفيذ إجراء الفاتورة بنجاح.');

        $invoice->refresh();
        $this->assertSame('approved', $invoice->status);
        $this->assertSame($manager->id, $invoice->approved_by);
        $audit = AuditLog::where('event', 'sales_invoice.approved')
            ->where('auditable_id', $invoice->id)->firstOrFail();
        $this->assertSame('pending_approval', $audit->metadata['previous_status']);
        $this->assertSame('approved', $audit->metadata['new_status']);
        $this->assertSame($context['alexandria']->id, $audit->metadata['branch_id']);
    }

    public function test_branch_manager_cannot_approve_another_branch_or_company(): void
    {
        $context = $this->context();
        $manager = $this->userWithRole($context, 'branch_manager', $context['alexandria'], true);
        $cairoInvoice = $this->invoice($context, $context['cairo'], 'pending_approval');

        $this->actingAs($manager)
            ->post(route('sales-invoices.action', [$cairoInvoice, 'approve']))
            ->assertForbidden();

        $other = $this->context();
        $otherInvoice = $this->invoice($other, $other['alexandria'], 'pending_approval');
        $this->post(route('sales-invoices.action', [$otherInvoice, 'approve']))->assertForbidden();
    }

    public function test_only_pending_invoice_can_be_approved_once(): void
    {
        $context = $this->context();
        $manager = $this->userWithRole($context, 'branch_manager', $context['alexandria'], true);
        $this->actingAs($manager);

        foreach (['draft', 'approved', 'cancelled', 'void'] as $status) {
            $invoice = $this->invoice($context, $context['alexandria'], $status);
            $this->post(route('sales-invoices.action', [$invoice, 'approve']))->assertForbidden();
        }

        $invoice = $this->invoice($context, $context['alexandria'], 'pending_approval');
        $this->post(route('sales-invoices.action', [$invoice, 'approve']))->assertRedirect();
        $this->post(route('sales-invoices.action', [$invoice, 'approve']))->assertForbidden();
    }

    public function test_company_owner_and_general_manager_can_approve_company_branches(): void
    {
        foreach (['company_owner', 'general_manager'] as $role) {
            $context = $this->context();
            $admin = $this->userWithRole($context, $role, $context['alexandria'], true);
            $invoice = $this->invoice($context, $context['cairo'], 'pending_approval');

            $this->actingAs($admin)
                ->post(route('sales-invoices.action', [$invoice, 'approve']))
                ->assertRedirect();
            $this->assertSame('approved', $invoice->fresh()->status);
        }
    }

    public function test_accountant_without_explicit_permission_sees_no_button_and_gets_403(): void
    {
        $context = $this->context();
        $accountant = $this->userWithRole($context, 'accountant', $context['alexandria'], false);
        $invoice = $this->invoice($context, $context['alexandria'], 'pending_approval');

        $this->actingAs($accountant)
            ->get(route('sales-invoices.show', $invoice))
            ->assertOk()
            ->assertDontSee('اعتماد الفاتورة');
        $this->post(route('sales-invoices.action', [$invoice, 'approve']))->assertForbidden();
    }

    public function test_permission_migration_is_idempotent_and_only_grants_management_roles(): void
    {
        $context = $this->context();
        $managers = collect(['branch_manager', 'company_owner', 'general_manager'])
            ->map(fn ($role) => $this->userWithRole($context, $role, $context['alexandria'], false));
        $accountant = $this->userWithRole($context, 'accountant', $context['alexandria'], false);
        $migration = require database_path('migrations/2026_08_01_130000_grant_sales_invoice_approval_to_management_roles.php');

        $migration->up();
        $migration->up();

        $managers->each(fn (User $user) => $this->assertTrue($user->hasPermission('sales_invoices.approve')));
        $this->assertFalse($accountant->hasPermission('sales_invoices.approve'));
    }

    private function context(): array
    {
        $currency = Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'ج.م', 'decimal_places' => 2, 'is_active' => true]
        );
        $company = Company::create(['name' => 'UAT 031 '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true]);
        $alexandria = Branch::create(['company_id' => $company->id, 'code' => 'ALX'.uniqid(), 'name' => 'الإسكندرية', 'is_main' => true, 'is_active' => true]);
        $cairo = Branch::create(['company_id' => $company->id, 'code' => 'CAI'.uniqid(), 'name' => 'القاهرة', 'is_main' => false, 'is_active' => true]);
        $creator = User::factory()->create(['company_id' => $company->id, 'branch_id' => $alexandria->id, 'status' => 'active']);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'created_branch_id' => $alexandria->id, 'assigned_branch_id' => $alexandria->id]);

        return compact('currency', 'company', 'alexandria', 'cairo', 'creator', 'customer');
    }

    private function userWithRole(array $context, string $roleName, Branch $branch, bool $canApprove): User
    {
        $user = User::factory()->create(['company_id' => $context['company']->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::create([
            'company_id' => $context['company']->id,
            'name' => $roleName,
            'display_name' => $roleName,
            'scope' => $roleName === 'branch_manager' ? 'branch' : 'company',
            'is_active' => true,
        ]);
        $permissions = ['sales_invoices.view'];
        if ($canApprove) {
            $permissions[] = 'sales_invoices.approve';
        }
        foreach ($permissions as $name) {
            $role->permissions()->syncWithoutDetaching(
                Permission::firstOrCreate(['name' => $name], ['display_name' => $name])
            );
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true, 'can_approve' => $canApprove]);

        return $user;
    }

    private function invoice(array $context, Branch $branch, string $status, ?User $creator = null): SalesInvoice
    {
        return SalesInvoice::factory()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $branch->id,
            'customer_id' => $context['customer']->id,
            'currency_id' => $context['currency']->id,
            'created_by' => ($creator ?? $context['creator'])->id,
            'status' => $status,
        ]);
    }
}
