<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanySetupProgressService;
use App\Services\CustomerService;
use App\Services\DocumentNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DocumentSequenceConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_single_config_source_drives_validation_form_and_arabic_labels(): void
    {
        [$user] = $this->context();
        $types = config('document_sequences.types');

        $response = $this->actingAs($user)
            ->get(route('reference.create', 'document-sequences'));

        $response->assertOk()
            ->assertSee('customer — العملاء')
            ->assertSee('lead — العملاء المحتملون');
        foreach ($types as $type => $definition) {
            $response->assertSee('value="'.$type.'"', false)
                ->assertSee($definition['label']);
        }

        $this->assertArrayHasKey('customer', $types);
        $this->assertArrayHasKey('lead', $types);
        $this->assertStringNotContainsString(
            "@foreach(['quotation'",
            file_get_contents(resource_path('views/reference/form.blade.php'))
        );
        $this->assertStringContainsString(
            "config('document_sequences.types'",
            file_get_contents(app_path('Http/Requests/ReferenceDataRequest.php'))
        );
    }

    public function test_branch_customer_sequence_can_be_created_and_generates_first_customer_code(): void
    {
        [$user, $branch, $company] = $this->context('CAI-MAIN', 'الفرع الرئيسي - القاهرة');

        $this->actingAs($user)->post(route('reference.store', 'document-sequences'), [
            'branch_id' => $branch->id,
            'document_type' => 'customer',
            'prefix' => '{BRANCH}-CUS-{YYYY}-',
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'is_active' => 1,
        ])->assertRedirect(route('reference.index', 'document-sequences'));

        $this->actingAs($user)->post(route('customers.store'), $this->customerData($branch))
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->id,
            'customer_code' => 'CAI-MAIN-CUS-'.now()->format('Y').'-000001',
            'email' => 'uat.customer006@sevenways.test',
        ]);
        $this->assertSame(1, DocumentSequence::query()
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('document_type', 'customer')
            ->value('current_number'));
    }

    public function test_missing_customer_sequence_returns_clear_message_without_partial_customer(): void
    {
        [$user, $branch, $company] = $this->context('CAI-MAIN', 'الفرع الرئيسي - القاهرة');

        $response = $this->actingAs($user)
            ->from(route('customers.create'))
            ->post(route('customers.store'), $this->customerData($branch));

        $response->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors([
                'document_type' => 'لا يوجد تسلسل نشط لمستند «العملاء» في الفرع «الفرع الرئيسي - القاهرة». أضف التسلسل من الإعدادات ثم أعد المحاولة.',
            ]);
        $this->assertSame(0, Customer::query()->where('company_id', $company->id)->count());
    }

    public function test_failed_customer_transaction_does_not_consume_sequence_number(): void
    {
        [, $branch, $company] = $this->context();
        $sequence = $this->sequence($company, $branch, 'customer');

        try {
            app(CustomerService::class)->create([
                'customer_type' => 'individual',
                'name' => null,
                'phone' => '01000000006',
                'preferred_language' => 'ar',
                'credit_limit' => 0,
                'payment_term_days' => 0,
                'status' => 'active',
                'assigned_branch_id' => $branch->id,
            ]);
            $this->fail('Customer insert should fail after number generation.');
        } catch (QueryException) {
            $this->assertSame(0, $sequence->fresh()->current_number);
            $this->assertSame(0, Customer::query()->where('company_id', $company->id)->count());
        }
    }

    public function testCompanySetupProgressReportsMissingRequiredTypeAndBranch(): void
    {
        config(['modules.leads.enabled' => true]);
        [, $branch, $company] = $this->context('CAI-MAIN', 'الفرع الرئيسي - القاهرة');
        $this->sequence($company, $branch, 'lead');

        $progress = app(CompanySetupProgressService::class)->for($company);
        $step = collect($progress['steps'])->firstWhere('label', 'تسلسل المستندات');

        $this->assertFalse($step['complete']);
        $this->assertSame(1, $step['details']['completed_count']);
        $this->assertSame(2, $step['details']['required_count']);
        $this->assertSame('customer', $step['details']['missing_items'][0]['type']);
        $this->assertSame('CAI-MAIN', $step['details']['missing_items'][0]['branch_code']);
        $this->assertSame('الفرع الرئيسي - القاهرة', $step['details']['missing_items'][0]['branch_name']);

        $this->sequence($company, $branch, 'customer');
        $step = collect(app(CompanySetupProgressService::class)->for($company)['steps'])
            ->firstWhere('label', 'تسلسل المستندات');
        $this->assertTrue($step['complete']);
    }

    public function test_document_sequence_creation_rejects_cross_branch_and_cross_company(): void
    {
        [$user, , $company] = $this->context();
        $otherBranch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'OTHER',
            'name' => 'فرع غير مسموح',
            'is_active' => true,
        ]);
        $foreignCompany = Company::query()->create(['name' => 'Foreign company']);
        $foreignBranch = Branch::query()->create([
            'company_id' => $foreignCompany->id,
            'code' => 'FOREIGN',
            'name' => 'فرع شركة أخرى',
            'is_active' => true,
        ]);
        $payload = [
            'document_type' => 'customer',
            'prefix' => '{BRANCH}-CUS-{YYYY}-',
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'is_active' => 1,
        ];

        $this->actingAs($user)->post(route('reference.store', 'document-sequences'), [
            ...$payload,
            'branch_id' => $otherBranch->id,
        ])->assertSessionHasErrors('branch_id');
        $this->actingAs($user)->post(route('reference.store', 'document-sequences'), [
            ...$payload,
            'branch_id' => $foreignBranch->id,
        ])->assertSessionHasErrors('branch_id');
        $this->assertSame(0, DocumentSequence::query()
            ->whereIn('branch_id', [$otherBranch->id, $foreignBranch->id])->count());
    }

    private function context(string $branchCode = 'MAIN', string $branchName = 'Main branch'): array
    {
        $company = Company::query()->create(['name' => 'Sequence company '.uniqid()]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => $branchCode,
            'name' => $branchName,
            'is_main' => true,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'sequence_manager_'.uniqid(),
            'display_name' => 'Sequence manager',
            'scope' => 'branch',
            'is_active' => true,
        ]);
        foreach ([
            'document_sequences.view',
            'document_sequences.manage',
            'customers.create',
            'customers.view',
        ] as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['display_name' => $name]
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch, [
            'is_default' => true,
            'can_view' => true,
        ]);
        app(TenantContext::class)->initialize($user);

        return [$user, $branch, $company];
    }

    private function sequence(Company $company, Branch $branch, string $type): DocumentSequence
    {
        $period = now()->format('Y');

        return DocumentSequence::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'document_type' => $type,
            'prefix' => '{BRANCH}-CUS-{YYYY}-',
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'period_key' => $period,
            'scope_key' => DocumentNumberService::scopeKey($company->id, $branch->id, $type, $period),
            'is_active' => true,
        ]);
    }

    private function customerData(Branch $branch): array
    {
        return [
            'customer_type' => 'individual',
            'name' => 'أحمد محمد - عميل اختبار',
            'phone' => '01000000006',
            'email' => 'uat.customer006@sevenways.test',
            'preferred_language' => 'ar',
            'credit_limit' => 0,
            'payment_term_days' => 0,
            'status' => 'active',
            'assigned_branch_id' => $branch->id,
        ];
    }
}
