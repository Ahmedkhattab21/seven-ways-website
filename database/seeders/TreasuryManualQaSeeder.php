<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountGroup;
use App\Models\AccountingPeriod;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\BankAccountBranchAccess;
use App\Models\Branch;
use App\Models\CashBox;
use App\Models\CashBoxCustodian;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\FiscalYear;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodAccountMapping;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TreasuryApprovalLimit;
use App\Models\User;
use App\Services\DocumentNumberService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TreasuryManualQaSeeder extends Seeder
{
    private const PASSWORD = 'Test@123456';

    private const QA_EMAILS = [
        'owner' => 'qa.owner@sevenways.test',
        'manager' => 'qa.treasury.manager@sevenways.test',
        'accountant' => 'qa.treasury.accountant@sevenways.test',
        'cairo_cashier' => 'qa.cairo.cashier@sevenways.test',
        'giza_cashier' => 'qa.giza.cashier@sevenways.test',
        'viewer' => 'qa.treasury.viewer@sevenways.test',
        'disabled_cashier' => 'qa.disabled.cashier@sevenways.test',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('TreasuryManualQaSeeder is restricted to local and testing environments.');
        }

        DB::transaction(function (): void {
            $company = Company::query()->where('name', 'Seven Ways')->where('is_active', true)->first();
            $egp = Currency::query()->where('code', 'EGP')->where('is_active', true)->first();
            if (! $egp) {
                throw new RuntimeException('Active EGP currency is required for Treasury QA; SAR fallback is not allowed.');
            }
            if (! $company || $company->currency_id !== $egp->id) {
                throw new RuntimeException('An active Seven Ways company with EGP as its base currency is required.');
            }

            $cairo = $this->branch($company, 'QA-CAI', 'فرع القاهرة QA');
            $giza = $this->branch($company, 'QA-GIZ', 'فرع الجيزة QA');
            $ownerRole = Role::query()->whereNull('company_id')->where('name', 'company_owner')
                ->where('is_active', true)->firstOrFail();
            $managerRole = $this->role($company, 'qa_treasury_manager', 'مدير خزينة QA', 'company');
            $accountantRole = $this->role($company, 'qa_treasury_accountant', 'محاسب خزينة QA', 'company');
            $cashierRole = $this->role($company, 'qa_treasury_cashier', 'أمين صندوق QA', 'branch');
            $viewerRole = $this->role($company, 'qa_treasury_viewer', 'مراقب خزينة QA', 'company');

            $treasuryPermissions = Permission::query()->where('name', 'like', 'treasury.%')->pluck('id');
            if ($treasuryPermissions->isEmpty()) {
                throw new RuntimeException('Phase 15 treasury permissions must be seeded first.');
            }
            $managerRole->permissions()->sync($treasuryPermissions);
            $managerRole->permissions()->syncWithoutDetaching(
                Permission::query()->whereIn('name', ['dashboard.view', 'accounting.journals.view'])->pluck('id')
            );
            $this->syncPermissions($accountantRole, [
                'dashboard.view', 'accounting.journals.view',
                'treasury.banks.view', 'treasury.bank_accounts.view', 'treasury.cash_boxes.view',
                'treasury.balances.view', 'treasury.transfers.view', 'treasury.transfers.create',
                'treasury.transfers.update', 'treasury.transfers.submit', 'treasury.transfers.cancel',
                'treasury.transfers.process', 'treasury.transfers.reverse',
                'treasury.cash_sessions.view', 'treasury.cash_receipts.view',
                'treasury.cash_receipts.create', 'treasury.cash_receipts.submit',
                'treasury.cash_receipts.post', 'treasury.cash_receipts.reverse',
                'treasury.cash_payments.view', 'treasury.cash_payments.create',
                'treasury.cash_payments.submit', 'treasury.cash_payments.post',
                'treasury.cash_payments.reverse', 'treasury.cash_over_short.view',
                'treasury.cash_over_short.post', 'treasury.cheques.view',
                'treasury.cheques.create', 'treasury.cheques.submit', 'treasury.cheques.deposit',
                'treasury.cheques.present', 'treasury.cheques.clear', 'treasury.cheques.bounce',
                'treasury.cheques.return', 'treasury.cheques.cancel', 'treasury.cheques.replace',
                'treasury.cheques.endorse', 'treasury.merchant_settlements.view',
                'treasury.merchant_settlements.create', 'treasury.merchant_settlements.submit',
                'treasury.merchant_settlements.post', 'treasury.merchant_settlements.reverse',
                'treasury.approval_limits.view', 'treasury.reports.view',
            ]);
            $this->syncPermissions($cashierRole, [
                'dashboard.view', 'treasury.cash_boxes.view', 'treasury.balances.view',
                'treasury.transfers.view', 'treasury.transfers.create', 'treasury.transfers.submit',
                'treasury.cash_sessions.view', 'treasury.cash_sessions.open',
                'treasury.cash_sessions.count', 'treasury.cash_sessions.submit',
                'treasury.cash_sessions.close', 'treasury.cash_receipts.view',
                'treasury.cash_receipts.create', 'treasury.cash_receipts.submit',
                'treasury.cash_payments.view', 'treasury.cash_payments.create',
                'treasury.cash_payments.submit', 'treasury.cheques.view',
                'treasury.cheques.create', 'treasury.cheques.submit',
            ]);
            $this->syncPermissions($viewerRole, [
                'dashboard.view', 'treasury.banks.view', 'treasury.bank_accounts.view',
                'treasury.cash_boxes.view', 'treasury.balances.view', 'treasury.transfers.view',
                'treasury.cash_sessions.view', 'treasury.cash_receipts.view',
                'treasury.cash_payments.view', 'treasury.cash_over_short.view',
                'treasury.cheques.view', 'treasury.merchant_settlements.view',
                'treasury.approval_limits.view', 'treasury.reports.view',
            ]);

            $users = [
                'owner' => $this->user($company, $cairo, $ownerRole, 'QA Company Owner', self::QA_EMAILS['owner']),
                'manager' => $this->user($company, $cairo, $managerRole, 'QA Treasury Manager', self::QA_EMAILS['manager']),
                'accountant' => $this->user($company, $cairo, $accountantRole, 'QA Treasury Accountant', self::QA_EMAILS['accountant']),
                'cairo_cashier' => $this->user($company, $cairo, $cashierRole, 'QA Cairo Cashier', self::QA_EMAILS['cairo_cashier']),
                'giza_cashier' => $this->user($company, $giza, $cashierRole, 'QA Giza Cashier', self::QA_EMAILS['giza_cashier']),
                'viewer' => $this->user($company, $cairo, $viewerRole, 'QA Treasury Viewer', self::QA_EMAILS['viewer']),
                'disabled_cashier' => $this->user(
                    $company, $cairo, $cashierRole, 'QA Disabled Cashier',
                    self::QA_EMAILS['disabled_cashier'], 'inactive'
                ),
            ];
            $allBranches = $company->branches()->where('is_active', true)->pluck('id')->all();
            $this->branchAccess($users['owner'], $allBranches, $cairo->id);
            $this->branchAccess($users['manager'], [$cairo->id, $giza->id], $cairo->id);
            $this->branchAccess($users['accountant'], [$cairo->id, $giza->id], $cairo->id);
            $this->branchAccess($users['cairo_cashier'], [$cairo->id], $cairo->id);
            $this->branchAccess($users['giza_cashier'], [$giza->id], $giza->id);
            $this->branchAccess($users['viewer'], [$cairo->id], $cairo->id);
            $this->branchAccess($users['disabled_cashier'], [$cairo->id], $cairo->id);

            // A clean local database may not have had an actor when the accounting
            // foundation first ran. Re-run its idempotent reference setup now that
            // the QA owner exists, without creating journals or balances.
            app(AccountingFoundationSeeder::class)->run();

            $accounts = $this->accounts($company, $users['owner']);
            $boxes = [
                'cairo_main' => $this->cashBox(
                    $company, $cairo, $users['owner'], 'QA-CAI-MAIN',
                    'صندوق رئيسي — القاهرة QA', $accounts['cash_cairo_main'], $accounts['over_short'], true
                ),
                'cairo_sales' => $this->cashBox(
                    $company, $cairo, $users['owner'], 'QA-CAI-SALES',
                    'صندوق مبيعات — القاهرة QA', $accounts['cash_cairo_sales'], $accounts['over_short'], false
                ),
                'giza_main' => $this->cashBox(
                    $company, $giza, $users['owner'], 'QA-GIZ-MAIN',
                    'صندوق رئيسي — الجيزة QA', $accounts['cash_giza_main'], $accounts['over_short'], true
                ),
            ];
            $this->custodian($company, $boxes['cairo_main'], $users['cairo_cashier'], $users['owner']);
            $this->custodian($company, $boxes['giza_main'], $users['giza_cashier'], $users['owner']);

            $bank = $this->bank($company);
            $bankAccounts = [
                'cairo' => $this->bankAccount(
                    $company, $cairo, $users['owner'], $bank, 'QA-BANK-CAI',
                    'QA Cairo Bank Account', 'QA-****-1001', $accounts['bank_cairo'], $accounts['bank_fees']
                ),
                'giza' => $this->bankAccount(
                    $company, $giza, $users['owner'], $bank, 'QA-BANK-GIZ',
                    'QA Giza Bank Account', 'QA-****-2001', $accounts['bank_giza'], $accounts['bank_fees']
                ),
            ];
            $this->bankAccess($company, $bankAccounts['cairo'], $cairo);
            $this->bankAccess($company, $bankAccounts['giza'], $giza);

            $paymentMethods = [
                'cash' => $this->paymentMethod($company, 'QA-CASH', 'Cash QA', 'cash', true, false),
                'card' => $this->paymentMethod($company, 'QA-CARD', 'Card QA', 'card', false, true),
                'online' => $this->paymentMethod($company, 'QA-ONLINE', 'Online QA', 'online', false, true),
            ];
            foreach ([
                [$cairo, $boxes['cairo_main'], $bankAccounts['cairo']],
                [$giza, $boxes['giza_main'], $bankAccounts['giza']],
            ] as [$branch, $box, $bankAccount]) {
                $this->mapping(
                    $company, $branch, $users['owner'], $paymentMethods['cash'],
                    $box->gl_account_id, null, $box->id, null, null
                );
                foreach ([$paymentMethods['card'], $paymentMethods['online']] as $method) {
                    $this->mapping(
                        $company, $branch, $users['owner'], $method,
                        $accounts['merchant_clearing']->id, $bankAccount->id, null,
                        $accounts['merchant_clearing']->id, $accounts['merchant_fees']->id
                    );
                }
            }

            $this->approvalLimit($company, null, $managerRole, null, $users['owner'], 'treasury_transfer', 10000, 1);
            $this->approvalLimit($company, $cairo, $managerRole, null, $users['owner'], 'treasury_transfer', 5000, 2);
            $this->approvalLimit($company, $cairo, null, $users['manager'], $users['owner'], 'treasury_transfer', 20000, 3);
            foreach ([
                'cash_receipt', 'cash_payment', 'cash_over_short', 'received_cheque',
                'issued_cheque', 'cheque_clearance', 'cheque_bounce', 'merchant_settlement',
            ] as $operation) {
                $this->approvalLimit(
                    $company, null, null, $users['manager'], $users['owner'], $operation, 20000, 3
                );
                $this->approvalLimit(
                    $company, null, null, $users['accountant'], $users['owner'], $operation, 50000, 2,
                    ['create' => true, 'submit' => true, 'approve' => false, 'post' => true]
                );
            }
            foreach ([$cairo, $giza] as $branch) {
                foreach (['cash_receipt', 'cash_payment'] as $operation) {
                    $this->approvalLimit(
                        $company, $branch, $cashierRole, null, $users['owner'], $operation, 5000, 1,
                        ['create' => true, 'submit' => true, 'approve' => false, 'post' => false]
                    );
                }
            }
            $this->period($company, $users['owner']);

            foreach ([$cairo, $giza] as $branch) {
                foreach ([
                    'treasury_transfer' => 'TR', 'cash_box_session' => 'CS',
                    'cash_receipt' => 'CR', 'cash_payment' => 'CP',
                    'cheque_received' => 'RCH', 'cheque_issued' => 'ICH',
                    'merchant_settlement' => 'MS',
                ] as $type => $prefix) {
                    $this->sequence($company, $branch, $type, 'QA-'.$branch->code.'-'.$prefix.'-{YYYY}-');
                }
            }
        });
    }

    private function branch(Company $company, string $code, string $name): Branch
    {
        $branch = Branch::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
        $branch->fill([
            'company_id' => $company->id, 'code' => $code, 'name' => $name,
            'commercial_name' => $name, 'is_main' => false, 'is_active' => true,
        ]);
        $branch->forceFill(['deleted_at' => null])->save();
        $branch->settings()->firstOrCreate([], [
            'invoice_prefix' => $code.'-INV', 'quotation_prefix' => $code.'-QUO',
            'work_order_prefix' => $code.'-WO', 'warranty_prefix' => $code.'-WAR',
        ]);

        return $branch;
    }

    private function role(Company $company, string $name, string $displayName, string $scope): Role
    {
        return Role::query()->updateOrCreate(
            ['company_id' => $company->id, 'name' => $name],
            ['display_name' => $displayName, 'scope' => $scope, 'is_system' => false, 'is_active' => true]
        );
    }

    private function syncPermissions(Role $role, array $names): void
    {
        $ids = Permission::query()->whereIn('name', $names)->pluck('id');
        $role->permissions()->sync($ids);
    }

    private function user(
        Company $company,
        Branch $branch,
        Role $role,
        string $name,
        string $email,
        string $status = 'active'
    ): User {
        $user = User::query()->where('email', $email)->firstOrNew();
        if ($user->exists && (int) $user->company_id !== (int) $company->id) {
            throw new RuntimeException("QA email {$email} is already assigned to another company.");
        }
        $password = $user->exists && Hash::check(self::PASSWORD, (string) $user->password)
            ? $user->password : Hash::make(self::PASSWORD);
        $user->forceFill([
            'company_id' => $company->id, 'branch_id' => $branch->id,
            'name' => $name, 'email' => $email, 'password' => $password,
            'status' => $status, 'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function branchAccess(User $user, array $branchIds, int $defaultBranchId): void
    {
        $access = [];
        foreach ($branchIds as $branchId) {
            $access[$branchId] = [
                'is_default' => $branchId === $defaultBranchId,
                'can_view' => true, 'can_create' => true, 'can_update' => true, 'can_approve' => true,
            ];
        }
        $user->accessibleBranches()->sync($access);
    }

    private function accounts(Company $company, User $actor): array
    {
        return [
            'cash_cairo_main' => $this->account($company, $actor, 'QA-CASH-CAI-M', 'نقدية القاهرة الرئيسية QA', '111000', '111', 'debit', true),
            'cash_cairo_sales' => $this->account($company, $actor, 'QA-CASH-CAI-S', 'نقدية مبيعات القاهرة QA', '111000', '111', 'debit', true),
            'cash_giza_main' => $this->account($company, $actor, 'QA-CASH-GIZ-M', 'نقدية الجيزة الرئيسية QA', '111000', '111', 'debit', true),
            'bank_cairo' => $this->account($company, $actor, 'QA-BANK-CAI', 'حساب بنك القاهرة QA', '112000', '111', 'debit', false, true),
            'bank_giza' => $this->account($company, $actor, 'QA-BANK-GIZ', 'حساب بنك الجيزة QA', '112000', '111', 'debit', false, true),
            'transfer_clearing' => $this->account($company, $actor, 'QA-TR-CLEAR', 'حساب وسيط تحويلات QA', '110000', '111', 'debit'),
            'bank_fees' => $this->account($company, $actor, 'QA-BANK-FEES', 'مصروف رسوم بنكية QA', '600000', '600', 'debit'),
            'over_short' => $this->account($company, $actor, 'QA-CASH-OS', 'فروق الصندوق بالزيادة والعجز QA', '600000', '600', 'debit'),
            'cheques_receivable' => $this->account($company, $actor, '116000', 'شيكات تحت التحصيل', '110000', '113', 'debit', false, false, false, true),
            'cheques_payable' => $this->account($company, $actor, '214000', 'شيكات واجبة الدفع', '210000', '211', 'credit', false, false, false, true),
            'merchant_clearing' => $this->account($company, $actor, '117000', 'تحصيلات نقاط البيع', '110000', '113', 'debit', false, false, false, true),
            'merchant_fees' => $this->account($company, $actor, '651000', 'رسوم نقاط البيع والبنوك', '600000', '600', 'debit', false, false, false, true),
            'vat_input' => Account::query()->where('company_id', $company->id)->where('account_code', '115000')->firstOrFail(),
            'receipt_offset' => $this->account($company, $actor, 'QA-OTHER-INCOME', 'إيراد نقدي عام QA', '400000', '400', 'credit', false, false, true),
            'payment_offset' => $this->account($company, $actor, 'QA-GENERAL-EXP', 'مصروف نقدي عام QA', '600000', '600', 'debit', false, false, true),
        ];
    }

    private function account(
        Company $company,
        User $actor,
        string $code,
        string $name,
        string $parentCode,
        string $groupCode,
        string $normalBalance,
        bool $cash = false,
        bool $bank = false,
        bool $manual = false,
        bool $system = false
    ): Account {
        $parent = Account::query()->where('company_id', $company->id)
            ->where('account_code', $parentCode)->firstOrFail();
        $group = AccountGroup::query()->where('company_id', $company->id)
            ->where('code', $groupCode)->firstOrFail();
        $account = Account::withTrashed()->firstOrNew([
            'company_id' => $company->id, 'account_code' => $code,
        ]);
        $account->forceFill([
            'company_id' => $company->id, 'account_code' => $code,
            'account_type_id' => $group->account_type_id, 'account_group_id' => $group->id,
            'parent_account_id' => $parent->id, 'name_ar' => $name,
            'account_level' => $parent->account_level + 1, 'normal_balance' => $normalBalance,
            'currency_id' => $company->currency_id, 'is_header' => false, 'is_posting' => true,
            'requires_branch' => $cash || $bank, 'is_control_account' => false,
            'is_bank_account' => $bank, 'is_cash_account' => $cash,
            'is_inventory_account' => false, 'is_tax_account' => false,
            'is_system' => $system, 'is_active' => true, 'allow_manual_entry' => $manual,
            'created_by' => $account->created_by ?: $actor->id, 'deleted_at' => null,
        ])->save();
        $account->forceFill(['account_path' => $parent->account_path.'/'.$account->id])->saveQuietly();

        return $account;
    }

    private function cashBox(
        Company $company,
        Branch $branch,
        User $actor,
        string $code,
        string $name,
        Account $gl,
        Account $overShort,
        bool $requiresShift
    ): CashBox {
        $box = CashBox::withTrashed()->firstOrNew(['company_id' => $company->id, 'code' => $code]);
        $box->forceFill([
            'company_id' => $company->id, 'branch_id' => $branch->id, 'code' => $code,
            'name' => $name, 'currency_id' => $company->currency_id, 'gl_account_id' => $gl->id,
            'over_short_account_id' => $overShort->id, 'status' => 'active',
            'is_primary' => str_ends_with($code, 'MAIN'), 'allows_receipts' => true,
            'allows_payments' => true, 'requires_shift_opening' => $requiresShift,
            'created_by' => $box->created_by ?: $actor->id, 'deleted_at' => null,
        ])->save();

        return $box;
    }

    private function custodian(Company $company, CashBox $box, User $user, User $actor): void
    {
        $custodian = CashBoxCustodian::query()->firstOrNew([
            'company_id' => $company->id, 'cash_box_id' => $box->id,
            'user_id' => $user->id, 'valid_from' => '2026-01-01',
        ]);
        $custodian->forceFill([
            'company_id' => $company->id, 'cash_box_id' => $box->id, 'user_id' => $user->id,
            'valid_from' => '2026-01-01', 'valid_to' => null, 'can_receive' => true,
            'can_pay' => true, 'can_transfer' => true, 'payment_limit' => 5000,
            'is_primary' => true, 'is_active' => true,
            'assigned_by' => $custodian->assigned_by ?: $actor->id,
            'revoked_by' => null, 'revoked_at' => null,
        ])->save();
    }

    private function bank(Company $company): Bank
    {
        $bank = Bank::withTrashed()->firstOrNew(['scope_key' => $company->id.':QA-BANK']);
        $bank->forceFill([
            'company_id' => $company->id, 'scope_key' => $company->id.':QA-BANK',
            'code' => 'QA-BANK', 'name_ar' => 'بنك اختبار مصر QA', 'name_en' => 'QA Egypt Bank',
            'swift_code' => 'QATESTXX', 'is_system' => false, 'is_active' => true, 'deleted_at' => null,
        ])->save();

        return $bank;
    }

    private function bankAccount(
        Company $company,
        Branch $branch,
        User $actor,
        Bank $bank,
        string $code,
        string $name,
        string $maskedNumber,
        Account $gl,
        Account $fees
    ): BankAccount {
        $account = BankAccount::withTrashed()->firstOrNew([
            'company_id' => $company->id, 'account_code' => $code,
        ]);
        $account->forceFill([
            'company_id' => $company->id, 'bank_id' => $bank->id, 'branch_id' => $branch->id,
            'account_code' => $code, 'account_name' => $name, 'iban' => null, 'iban_hash' => null,
            'account_number_masked' => $maskedNumber, 'currency_id' => $company->currency_id,
            'gl_account_id' => $gl->id, 'bank_fees_account_id' => $fees->id,
            'status' => 'active', 'account_type' => 'current', 'opening_date' => '2026-01-01',
            'is_primary' => true, 'allows_receipts' => true, 'allows_payments' => true,
            'allows_transfers' => true, 'requires_reconciliation' => true,
            'created_by' => $account->created_by ?: $actor->id, 'deleted_at' => null,
        ])->save();

        return $account;
    }

    private function bankAccess(Company $company, BankAccount $account, Branch $branch): void
    {
        $access = BankAccountBranchAccess::query()->firstOrNew([
            'bank_account_id' => $account->id, 'branch_id' => $branch->id,
        ]);
        $access->forceFill([
            'company_id' => $company->id, 'bank_account_id' => $account->id,
            'branch_id' => $branch->id, 'can_view' => true, 'can_receive' => true,
            'can_pay' => true, 'can_transfer' => true, 'daily_payment_limit' => 50000,
            'daily_transfer_limit' => 50000, 'is_active' => true,
        ])->save();
    }

    private function paymentMethod(
        Company $company,
        string $code,
        string $name,
        string $type,
        bool $cash,
        bool $reference
    ): PaymentMethod {
        $method = PaymentMethod::withTrashed()->firstOrNew([
            'company_id' => $company->id, 'code' => $code,
        ]);
        $method->forceFill([
            'company_id' => $company->id, 'code' => $code, 'name' => $name, 'type' => $type,
            'requires_reference' => $reference, 'is_cash' => $cash, 'is_active' => true,
            'sort_order' => $cash ? 10 : 20, 'deleted_at' => null,
        ])->save();

        return $method;
    }

    private function mapping(
        Company $company,
        Branch $branch,
        User $actor,
        PaymentMethod $method,
        int $accountId,
        ?int $bankAccountId,
        ?int $cashBoxId,
        ?int $clearingAccountId,
        ?int $feesAccountId
    ): void {
        $scopeKey = implode(':', [$company->id, $method->id, $branch->id, 'receipt']);
        $mapping = PaymentMethodAccountMapping::query()->firstOrNew(['scope_key' => $scopeKey]);
        $mapping->forceFill([
            'company_id' => $company->id, 'scope_key' => $scopeKey, 'branch_id' => $branch->id,
            'payment_method_id' => $method->id, 'operation_type' => 'receipt',
            'account_id' => $accountId, 'bank_account_id' => $bankAccountId,
            'cash_box_id' => $cashBoxId, 'clearing_account_id' => $clearingAccountId,
            'fees_account_id' => $feesAccountId, 'settlement_days' => $cashBoxId ? 0 : 2,
            'is_active' => true, 'created_by' => $mapping->created_by ?: $actor->id,
        ])->save();
    }

    private function approvalLimit(
        Company $company,
        ?Branch $branch,
        ?Role $role,
        ?User $user,
        User $actor,
        string $operation,
        int $maximum,
        int $level,
        array $abilities = ['create' => true, 'submit' => true, 'approve' => true, 'post' => true]
    ): void {
        $limit = TreasuryApprovalLimit::query()
            ->where('company_id', $company->id)->where('operation_type', $operation)
            ->where('currency_id', $company->currency_id)->where('branch_id', $branch?->id)
            ->where('role_id', $role?->id)->where('user_id', $user?->id)->first()
            ?? new TreasuryApprovalLimit;
        $limit->forceFill([
            'company_id' => $company->id, 'branch_id' => $branch?->id,
            'role_id' => $role?->id, 'user_id' => $user?->id,
            'operation_type' => $operation, 'currency_id' => $company->currency_id,
            'minimum_amount' => 0, 'maximum_amount' => $maximum, 'approval_level' => $level,
            'can_create' => $abilities['create'], 'can_submit' => $abilities['submit'],
            'can_approve' => $abilities['approve'], 'can_post' => $abilities['post'],
            'is_active' => true, 'valid_from' => '2026-01-01',
            'valid_to' => null, 'created_by' => $limit->created_by ?: $actor->id,
            'updated_by' => $limit->exists ? $actor->id : null,
        ])->save();
    }

    private function period(Company $company, User $actor): AccountingPeriod
    {
        $today = now()->toDateString();
        $periods = AccountingPeriod::query()->where('company_id', $company->id)
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
            ->where('is_adjustment_period', false)->get();
        if ($periods->count() > 1) {
            throw new RuntimeException('The QA date resolves to more than one accounting period.');
        }
        if ($periods->count() === 1) {
            $period = $periods->first();
            if ($period->status !== 'open' || in_array('treasury', $period->locked_modules ?? [], true)) {
                throw new RuntimeException('The current accounting period is not open for treasury; it was not modified.');
            }

            return $period;
        }

        $year = FiscalYear::query()->firstOrNew([
            'company_id' => $company->id, 'code' => 'QA-FY-'.now()->format('Y'),
        ]);
        $year->forceFill([
            'company_id' => $company->id, 'code' => 'QA-FY-'.now()->format('Y'),
            'name' => 'QA Fiscal Year '.now()->format('Y'),
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(), 'status' => 'open',
            'is_current' => false, 'created_by' => $year->created_by ?: $actor->id,
            'closed_by' => null, 'closed_at' => null, 'locked_at' => null,
        ])->save();
        $code = 'QA-'.now()->format('Ymd');
        $period = AccountingPeriod::query()->firstOrNew([
            'company_id' => $company->id, 'fiscal_year_id' => $year->id, 'code' => $code,
        ]);
        $periodNumber = $period->exists ? $period->period_number
            : ((int) AccountingPeriod::query()->where('fiscal_year_id', $year->id)->max('period_number')) + 1;
        $period->forceFill([
            'company_id' => $company->id, 'fiscal_year_id' => $year->id,
            'period_number' => $periodNumber, 'code' => $code,
            'name' => 'QA Treasury '.now()->format('Y-m-d'),
            'start_date' => $today, 'end_date' => $today, 'status' => 'open',
            'is_adjustment_period' => false, 'locked_modules' => null,
            'closed_by' => null, 'closed_at' => null,
        ])->save();

        return $period;
    }

    private function sequence(
        Company $company,
        Branch $branch,
        string $type,
        string $prefix
    ): void {
        $scopeKey = DocumentNumberService::scopeKey($company->id, $branch->id, $type, null);
        $sequence = DocumentSequence::query()->firstOrNew(['scope_key' => $scopeKey]);
        if (! $sequence->exists) {
            $sequence->forceFill([
                'company_id' => $company->id, 'branch_id' => $branch->id,
                'document_type' => $type, 'prefix' => $prefix, 'current_number' => 0,
                'padding' => 6, 'reset_period' => 'yearly', 'period_key' => null,
                'scope_key' => $scopeKey, 'is_active' => true,
            ])->save();
        }
    }
}
