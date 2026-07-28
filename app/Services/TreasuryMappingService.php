<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\TreasuryMappingUpdated;
use App\Models\BankAccount;
use App\Models\CashBox;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodAccountMapping;
use Illuminate\Support\Facades\DB;

class TreasuryMappingService
{
    public function __construct(
        private TenantContext $tenant,
        private TreasuryScopeService $scope,
        private AuditService $audit
    ) {
    }

    public function save(array $data): PaymentMethodAccountMapping
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->tenant->companyId();
            $method = PaymentMethod::query()->where('company_id', $companyId)->where('is_active', true)
                ->findOrFail($data['payment_method_id']);
            $branch = $this->scope->branch($data['branch_id'] ?? null);
            $targets = array_filter([
                'account_id' => $data['account_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'cash_box_id' => $data['cash_box_id'] ?? null,
            ]);
            if (count($targets) !== 1) {
                throw new BusinessRuleException('Exactly one GL, bank account, or cash box target is required.');
            }
            if (! empty($data['account_id'])) {
                $this->scope->account((int) $data['account_id']);
            }
            if (! empty($data['bank_account_id'])) {
                $bank = BankAccount::query()->where('company_id', $companyId)->where('status', 'active')
                    ->findOrFail($data['bank_account_id']);
                if ($method->is_cash) {
                    throw new BusinessRuleException('Cash payment method cannot route to a bank account.');
                }
                if ($branch) {
                    $ability = match ($data['operation_type']) {
                        'receipt', 'deposit', 'merchant_settlement' => 'can_receive',
                        'payment', 'refund', 'withdrawal' => 'can_pay',
                        'transfer' => 'can_transfer',
                        default => throw new BusinessRuleException('Unsupported treasury mapping operation.'),
                    };
                    app(BankAccountAccessService::class)->assert($bank, $branch->id, $ability);
                }
            }
            if (! empty($data['cash_box_id'])) {
                $box = CashBox::query()->where('company_id', $companyId)->where('status', 'active')
                    ->findOrFail($data['cash_box_id']);
                if ($branch && $box->branch_id !== $branch->id) {
                    throw new BusinessRuleException('Cash box mapping must use its own branch.');
                }
            }
            foreach (['clearing_account_id', 'fees_account_id'] as $field) {
                if (! empty($data[$field])) {
                    $this->scope->account((int) $data[$field]);
                }
            }
            $scopeKey = implode(':', [
                $companyId, $method->id, $branch?->id ?: 0, $data['operation_type'],
            ]);
            $mapping = PaymentMethodAccountMapping::query()->where('scope_key', $scopeKey)
                ->lockForUpdate()->first() ?? new PaymentMethodAccountMapping;
            $mapping->fill($data);
            $mapping->forceFill([
                'company_id' => $companyId, 'scope_key' => $scopeKey,
                'created_by' => $mapping->exists ? $mapping->created_by : $this->tenant->user()->id,
                'updated_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('treasury.mapping.updated', $mapping);
            DB::afterCommit(fn () => event(new TreasuryMappingUpdated($mapping->id)));

            return $mapping;
        });
    }
}
