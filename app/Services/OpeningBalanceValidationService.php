<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\OpeningBalanceDocument;
use App\Models\Supplier;
use App\Models\Vehicle;

class OpeningBalanceValidationService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function validateLine(OpeningBalanceDocument $document, array $data): Account
    {
        $account = Account::query()->whereKey($data['account_id'])->where('company_id', $document->company_id)
            ->where('is_active', true)->where('is_posting', true)->firstOrFail();
        $debit = (string) ($data['debit_amount'] ?? 0);
        $credit = (string) ($data['credit_amount'] ?? 0);
        if (bccomp($debit, '0', 4) < 0 || bccomp($credit, '0', 4) < 0
            || (bccomp($debit, '0', 4) === 1) === (bccomp($credit, '0', 4) === 1)) {
            throw new BusinessRuleException('Each line requires either a positive debit or a positive credit.');
        }
        Currency::query()->whereKey($data['currency_id'])->where('is_active', true)->firstOrFail();
        if ($account->currency_id && ! $account->allows_multi_currency && $account->currency_id !== (int) $data['currency_id']) {
            throw new BusinessRuleException('Line currency is not allowed for this account.');
        }
        if (bccomp((string) $data['exchange_rate'], '0', 8) !== 1) {
            throw new BusinessRuleException('Exchange rate must be positive.');
        }
        if ($account->requires_branch && empty($data['branch_id'])) {
            throw new BusinessRuleException('This account requires a branch.');
        }
        if ($account->requires_cost_center && empty($data['cost_center_id'])) {
            throw new BusinessRuleException('This account requires a cost center.');
        }
        foreach ([
            'requires_customer' => 'customer_id', 'requires_supplier' => 'supplier_id',
            'requires_employee' => 'employee_id', 'requires_vehicle' => 'vehicle_id',
        ] as $requirement => $dimension) {
            if ($account->$requirement && empty($data[$dimension])) {
                throw new BusinessRuleException("This account requires {$dimension}.");
            }
        }
        foreach ([
            'branch_id' => Branch::class, 'cost_center_id' => CostCenter::class,
            'customer_id' => Customer::class, 'supplier_id' => Supplier::class,
            'employee_id' => Employee::class, 'vehicle_id' => Vehicle::class,
        ] as $column => $model) {
            if (! empty($data[$column])) {
                $record = $model::query()->whereKey($data[$column])->where('company_id', $document->company_id)->firstOrFail();
                if ($column === 'branch_id' && ! $this->tenant->user()->canAccessBranch($record)) {
                    throw new BusinessRuleException('Branch is outside the accessible scope.');
                }
            }
        }

        return $account;
    }

    public function assertBalanced(OpeningBalanceDocument $document): void
    {
        $document->refresh();
        if (bccomp((string) $document->total_debit, '0', 4) !== 1
            || bccomp((string) $document->total_debit, (string) $document->total_credit, 4) !== 0) {
            throw new BusinessRuleException('Opening balance must have equal positive debit and credit totals.');
        }
    }
}
