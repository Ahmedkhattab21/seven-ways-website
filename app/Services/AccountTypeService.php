<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\AccountType;

class AccountTypeService
{
    private const DEFAULT_BALANCE = [
        'asset' => 'debit', 'expense' => 'debit', 'liability' => 'credit',
        'equity' => 'credit', 'revenue' => 'credit',
    ];

    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(AccountType $type, array $data): AccountType
    {
        if ($type->exists && ($type->is_system || $type->company_id === null)) {
            throw new BusinessRuleException('System account types cannot be modified.');
        }

        $classification = $data['classification'];
        if (($data['normal_balance'] ?? null) !== self::DEFAULT_BALANCE[$classification]) {
            throw new BusinessRuleException('Normal balance conflicts with the account classification.');
        }

        $type->forceFill($data + ['company_id' => $this->tenant->companyId(), 'is_system' => false])->save();
        $this->audit->record($type->wasRecentlyCreated ? 'account_type.created' : 'account_type.updated', $type);

        return $type;
    }
}
