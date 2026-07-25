<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Services\AccountsReceivableAgingService;
use App\Services\CustomerStatementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function statement(Request $request, Customer $customer, CustomerStatementService $service, TenantContext $tenant): View
    {
        abort_unless($request->user()->hasPermission('customer_statements.view'), 403);
        $currency = (int) ($request->currency_id ?: $tenant->company()->currency_id);

        return view($request->boolean('print') ? 'customer-statements.print' : 'customer-statements.show', ['customer' => $customer, 'statement' => $service->build($customer, $currency, $request->from, $request->to)]);
    }

    public function aging(Request $request, AccountsReceivableAgingService $service): View
    {
        abort_unless($request->user()->hasPermission('accounts_receivable.aging'), 403);

        return view('sales-reports.aging', ['aging' => $service->report($request->integer('branch_id') ?: null, $request->integer('currency_id') ?: null)]);
    }
}
