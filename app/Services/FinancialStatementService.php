<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Collection;

class FinancialStatementService
{
    public function __construct(private TenantContext $tenant, private FinancialReportQueryService $queries)
    {
    }

    public function balances(array $filters, array $classifications, bool $asOf = false): Collection
    {
        $filters = $this->queries->normalize($filters);
        $query = $this->queries->postedLines($filters, false);
        if ($asOf) {
            $query = $this->queries->postedLines([
                ...$filters, 'date_from' => '1900-01-01', 'date_to' => $filters['date_to'],
            ]);
        }

        return $query->join('accounts as report_accounts', 'report_accounts.id', '=', 'jel.account_id')
            ->join('account_types as report_types', 'report_types.id', '=', 'report_accounts.account_type_id')
            ->leftJoin('account_groups as report_groups', 'report_groups.id', '=', 'report_accounts.account_group_id')
            ->whereIn('report_types.classification', $classifications)
            ->groupBy('report_accounts.id', 'report_accounts.account_code', 'report_accounts.name_ar',
                'report_accounts.normal_balance', 'report_types.classification', 'report_groups.code', 'report_groups.name_ar')
            ->select([
                'report_accounts.id', 'report_accounts.account_code', 'report_accounts.name_ar',
                'report_accounts.normal_balance', 'report_types.classification',
                'report_groups.code as group_code', 'report_groups.name_ar as group_name',
            ])
            ->selectRaw('COALESCE(SUM(jel.base_debit_amount),0) debit, COALESCE(SUM(jel.base_credit_amount),0) credit')
            ->orderBy('report_accounts.account_code')->get()->map(function ($row) {
                $row->balance = $row->normal_balance === 'credit'
                    ? bcsub((string) $row->credit, (string) $row->debit, 4)
                    : bcsub((string) $row->debit, (string) $row->credit, 4);

                return $row;
            });
    }
}
