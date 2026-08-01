<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentSequencePageSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_empty_and_multi_branch_pages_are_safe_in_current_branch_context(): void
    {
        [$owner, $cairo, $alex, $company] = $this->context();

        $this->actingAs($owner)
            ->withSession(['tenant.branch_id' => $alex->id])
            ->get(route('reference.index', 'document-sequences'))
            ->assertOk()
            ->assertSee('لا توجد بيانات.');

        $this->sequence($company, $cairo, 'customer', '{BRANCH}-CUS-{YYYY}-');
        $this->sequence($company, $alex, 'cash_box_session', '{BRANCH}-CS-{YYYY}-');

        $this->actingAs($owner)
            ->withSession(['tenant.branch_id' => $alex->id])
            ->get(route('reference.index', 'document-sequences'))
            ->assertOk()
            ->assertSee('الفرع الرئيسي - القاهرة')
            ->assertSee('فرع الإسكندرية')
            ->assertSee('cash_box_session — جلسات الخزائن');
        $this->actingAs($owner)
            ->get(route('reference.create', 'document-sequences'))
            ->assertOk()
            ->assertSee('CAI-MAIN — الفرع الرئيسي - القاهرة')
            ->assertSee('ALEX — فرع الإسكندرية');
    }

    public function test_legacy_type_and_missing_branch_render_as_admin_warnings(): void
    {
        [$owner, , , $company] = $this->context();
        $sequence = $this->sequence($company, null, 'legacy_document', 'LEG-{YYYY}-');

        Schema::disableForeignKeyConstraints();
        try {
            $sequence->forceFill(['branch_id' => 999999])->save();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->actingAs($owner)
            ->get(route('reference.index', 'document-sequences'))
            ->assertOk()
            ->assertSee('تنبيه بيانات التسلسل')
            ->assertSee('نوع مستند غير معروف — legacy_document')
            ->assertSee('فرع غير موجود #999999');
    }

    public function test_duplicate_active_sequence_returns_arabic_validation_error_without_mutation(): void
    {
        [$owner, , $alex, $company] = $this->context();
        $this->sequence($company, $alex, 'cash_box_session', '{BRANCH}-CS-{YYYY}-');
        $before = DocumentSequence::query()->where('company_id', $company->id)->count();

        $this->actingAs($owner)
            ->from(route('reference.create', 'document-sequences'))
            ->post(route('reference.store', 'document-sequences'), [
                'branch_id' => $alex->id,
                'document_type' => 'cash_box_session',
                'prefix' => '{BRANCH}-CS-{YYYY}-',
                'current_number' => 0,
                'padding' => 6,
                'reset_period' => 'yearly',
                'is_active' => 1,
            ])
            ->assertRedirect(route('reference.create', 'document-sequences'))
            ->assertSessionHasErrors([
                'document_type' => 'يوجد تسلسل فعال بالفعل لهذا النوع في الفرع المحدد.',
            ]);

        $this->assertSame($before, DocumentSequence::query()->where('company_id', $company->id)->count());
    }

    public function test_alex_sequence_generates_the_expected_first_number(): void
    {
        [$owner, , $alex, $company] = $this->context();
        $sequence = $this->sequence($company, $alex, 'cash_box_session', '{BRANCH}-CS-{YYYY}-');

        app(TenantContext::class)->initialize($owner);

        $this->assertSame(
            'ALEX-CS-'.now()->format('Y').'-000001',
            app(DocumentNumberService::class)->next('cash_box_session', $company->id, $alex->id)
        );
        $this->assertSame(1, $sequence->fresh()->current_number);
    }

    public function test_index_get_does_not_mutate_sequences_and_unauthorized_user_gets_403(): void
    {
        [$owner, , $alex, $company] = $this->context();
        $sequence = $this->sequence($company, $alex, 'cash_box_session', '{BRANCH}-CS-{YYYY}-');
        $snapshot = $sequence->fresh()->toArray();

        $this->actingAs($owner)->get(route('reference.index', 'document-sequences'))->assertOk();
        $this->assertSame($snapshot, $sequence->fresh()->toArray());

        $unauthorized = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $alex->id,
            'status' => 'active',
        ]);

        $this->actingAs($unauthorized)
            ->get(route('reference.index', 'document-sequences'))
            ->assertForbidden();
    }

    private function context(): array
    {
        $company = Company::query()->create(['name' => 'Sequence safety '.uniqid()]);
        $cairo = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'CAI-MAIN',
            'name' => 'الفرع الرئيسي - القاهرة',
            'is_main' => true,
            'is_active' => true,
        ]);
        $alex = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'ALEX',
            'name' => 'فرع الإسكندرية',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'company_owner',
            'display_name' => 'مالك الشركة',
            'scope' => 'company',
            'is_active' => true,
        ]);
        foreach (['document_sequences.view', 'document_sequences.manage'] as $name) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $name],
                ['display_name' => $name]
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $cairo->id,
            'status' => 'active',
        ]);
        $owner->roles()->attach($role);
        $owner->accessibleBranches()->attach($cairo, ['is_default' => true, 'can_view' => true]);
        $owner->accessibleBranches()->attach($alex, ['is_default' => false, 'can_view' => true]);
        app(TenantContext::class)->initialize($owner);

        return [$owner, $cairo, $alex, $company];
    }

    private function sequence(
        Company $company,
        ?Branch $branch,
        string $type,
        string $prefix
    ): DocumentSequence {
        $period = now()->format('Y');

        return DocumentSequence::query()->forceCreate([
            'company_id' => $company->id,
            'branch_id' => $branch?->id,
            'document_type' => $type,
            'prefix' => $prefix,
            'current_number' => 0,
            'padding' => 6,
            'reset_period' => 'yearly',
            'period_key' => $period,
            'scope_key' => DocumentNumberService::scopeKey($company->id, $branch?->id, $type, $period),
            'is_active' => true,
        ]);
    }
}
