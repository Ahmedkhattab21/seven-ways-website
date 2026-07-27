<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Bank;
use Illuminate\Support\Facades\DB;

class BankService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(array $data, ?Bank $bank = null): Bank
    {
        return DB::transaction(function () use ($data, $bank) {
            if ($bank && ($bank->is_system || $bank->company_id !== $this->tenant->companyId())) {
                throw new BusinessRuleException('System or cross-company banks cannot be changed.');
            }
            $bank ??= new Bank;
            $code = strtoupper(trim($data['code']));
            $bank->fill($data + ['code' => $code]);
            $bank->forceFill([
                'company_id' => $this->tenant->companyId(),
                'scope_key' => $this->tenant->companyId().':'.$code,
                'is_system' => false,
            ])->save();
            $this->audit->record($bank->wasRecentlyCreated ? 'treasury.bank.created' : 'treasury.bank.updated', $bank);

            return $bank;
        });
    }

    public function disable(Bank $bank): Bank
    {
        if ($bank->company_id !== $this->tenant->companyId() || $bank->is_system) {
            throw new BusinessRuleException('Bank cannot be disabled.');
        }
        if ($bank->accounts()->whereIn('status', ['active', 'suspended'])->exists()) {
            throw new BusinessRuleException('A bank with open accounts cannot be disabled.');
        }
        $bank->forceFill(['is_active' => false])->save();
        $this->audit->record('treasury.bank.disabled', $bank);

        return $bank;
    }
}
