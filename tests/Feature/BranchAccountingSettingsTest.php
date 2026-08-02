<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\Branch;
use App\Models\BranchAccountingSetting;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchAccountingSettingsService;
use App\Services\PostingAccountResolver;
use Database\Seeders\AccountingFoundationSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BranchAccountingSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_page_exposes_all_branch_account_fields_and_filters_customer_advances(): void
    {
        $context = $this->context();
        $valid = $this->customerAdvance($context);
        $wrong = $this->replicateAccount($valid, [
            'account_code' => '213099',
            'name_ar' => 'حساب غير مؤهل',
            'control_type' => 'accounts_payable',
        ]);

        $response = $this->actingAs($context['user'])->get(route('accounting.settings.edit'));
        $response->assertOk()
            ->assertSee('name="customer_advance_account_id"', false)
            ->assertSee('دفعات مقدمة من العملاء')
            ->assertSee('name="sales_revenue_account_id"', false)
            ->assertSee('name="product_revenue_account_id"', false)
            ->assertSee('name="rounding_account_id"', false);

        preg_match('/<label>دفعات مقدمة من العملاء<select[^>]*>(.*?)<\/select><\/label>/su', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString($valid->name_ar, $matches[1]);
        $this->assertStringNotContainsString($wrong->name_ar, $matches[1]);
    }

    public function test_saving_alexandria_mapping_does_not_change_cairo(): void
    {
        $context = $this->context();
        $valid = $this->customerAdvance($context);
        $cairoBefore = BranchAccountingSetting::query()
            ->where('branch_id', $context['cairo']->id)
            ->value('customer_advance_account_id');

        $this->actingAs($context['user'])->put(
            route('accounting.branch-settings.update', $context['alexandria']),
            ['customer_advance_account_id' => $valid->id]
        )->assertRedirect();

        $this->assertDatabaseHas('branch_accounting_settings', [
            'branch_id' => $context['alexandria']->id,
            'customer_advance_account_id' => $valid->id,
        ]);
        $this->assertSame(
            $cairoBefore,
            BranchAccountingSetting::query()
                ->where('branch_id', $context['cairo']->id)
                ->value('customer_advance_account_id')
        );
    }

    public function test_page_explains_when_no_customer_advance_account_exists(): void
    {
        $context = $this->context();
        Account::query()
            ->where('company_id', $context['company']->id)
            ->where('control_type', 'customer_advances')
            ->update(['is_active' => false]);

        $this->actingAs($context['user'])
            ->get(route('accounting.settings.edit'))
            ->assertOk()
            ->assertSee('لا يوجد حساب حركة نشط من نوع "دفعات مقدمة من العملاء". أنشئ الحساب أولًا من دليل الحسابات.', false);
    }

    public function test_customer_advance_mapping_rejects_another_company_account(): void
    {
        $context = $this->context();
        $other = Company::query()->create(['name' => 'Other '.uniqid(), 'currency_id' => $context['currency']->id]);
        $foreign = $this->replicateAccount($this->customerAdvance($context), [
            'company_id' => $other->id,
            'account_code' => '213001',
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(BranchAccountingSettingsService::class)->update($context['alexandria'], [
            'customer_advance_account_id' => $foreign->id,
        ]);
    }

    public function test_customer_advance_mapping_rejects_inactive_account(): void
    {
        $context = $this->context();
        $account = $this->replicateAccount($this->customerAdvance($context), [
            'account_code' => '213002',
            'is_active' => false,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(BranchAccountingSettingsService::class)->update($context['alexandria'], [
            'customer_advance_account_id' => $account->id,
        ]);
    }

    public function test_customer_advance_mapping_rejects_non_posting_account(): void
    {
        $context = $this->context();
        $account = $this->replicateAccount($this->customerAdvance($context), [
            'account_code' => '213003',
            'is_header' => true,
            'is_posting' => false,
        ]);

        $this->expectException(ModelNotFoundException::class);
        app(BranchAccountingSettingsService::class)->update($context['alexandria'], [
            'customer_advance_account_id' => $account->id,
        ]);
    }

    public function test_customer_advance_mapping_rejects_wrong_control_type(): void
    {
        $context = $this->context();
        $account = $this->replicateAccount($this->customerAdvance($context), [
            'account_code' => '213004',
            'control_type' => 'accounts_payable',
        ]);

        $this->expectException(BusinessRuleException::class);
        app(BranchAccountingSettingsService::class)->update($context['alexandria'], [
            'customer_advance_account_id' => $account->id,
        ]);
    }

    public function test_missing_customer_advance_mapping_has_clear_arabic_message(): void
    {
        $context = $this->context();
        BranchAccountingSetting::query()->where('branch_id', $context['alexandria']->id)
            ->update(['customer_advance_account_id' => null]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(
            'لم يتم تحديد حساب دفعات العملاء المقدمة لفرع الإسكندرية. يرجى ضبطه من إعدادات المحاسبة.'
        );
        app(PostingAccountResolver::class)->branch(
            $context['company']->id,
            $context['alexandria']->id,
            'customer_advance_account_id'
        );
    }

    private function context(): array
    {
        $currency = Currency::query()->create([
            'code' => strtoupper(substr(md5(uniqid()), 0, 3)),
            'name_ar' => 'جنيه',
            'name_en' => 'Pound',
            'symbol' => 'EGP',
            'decimal_places' => 2,
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'name' => 'Branch Accounting '.uniqid(),
            'currency_id' => $currency->id,
        ]);
        $alexandria = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'ALEX',
            'name' => 'فرع الإسكندرية',
            'is_main' => true,
            'is_active' => true,
        ]);
        $cairo = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'CAIRO',
            'name' => 'فرع القاهرة',
            'is_main' => false,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'company_id' => $company->id,
            'name' => 'company_owner',
            'display_name' => 'Owner',
            'scope' => 'company',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $alexandria->id,
            'status' => 'active',
        ]);
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($alexandria, ['is_default' => true, 'can_view' => true]);
        app(AccountingFoundationSeeder::class)->run();
        $this->actingAs($user);
        app(TenantContext::class)->initialize($user);

        return compact('currency', 'company', 'alexandria', 'cairo', 'user');
    }

    private function customerAdvance(array $context): Account
    {
        return Account::query()
            ->where('company_id', $context['company']->id)
            ->where('control_type', 'customer_advances')
            ->where('is_active', true)
            ->where('is_posting', true)
            ->firstOrFail();
    }

    private function replicateAccount(Account $source, array $attributes): Account
    {
        $account = $source->replicate(['uuid', 'account_code', 'created_at', 'updated_at']);
        $account->forceFill($attributes)->save();

        return $account;
    }
}
