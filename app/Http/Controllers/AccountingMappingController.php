<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\PaymentMethodAccountMappingRequest;
use App\Http\Requests\ProductAccountingMappingRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\PaymentMethodAccountMapping;
use App\Models\Product;
use App\Models\ProductAccountingMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountingMappingController extends Controller
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function index(): View
    {
        abort_unless($this->tenant->user()->hasPermission('accounting.mappings.payment_methods')
            || $this->tenant->user()->hasPermission('accounting.mappings.products'), 403);
        $companyId = $this->tenant->companyId();

        return view('accounting.mappings.index', [
            'paymentMappings' => PaymentMethodAccountMapping::query()->where('company_id', $companyId)->get(),
            'productMappings' => ProductAccountingMapping::query()->where('company_id', $companyId)->get(),
            'accounts' => Account::query()->where('company_id', $companyId)->where('is_posting', true)->where('is_active', true)->get(),
            'paymentMethods' => PaymentMethod::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))->where('is_active', true)->get(),
            'products' => Product::query()->where('company_id', $companyId)->where('is_active', true)->get(),
        ]);
    }

    public function paymentMethod(PaymentMethodAccountMappingRequest $request): RedirectResponse
    {
        abort_unless($this->tenant->user()->hasPermission('accounting.mappings.payment_methods'), 403);
        $data = $request->validated();
        $companyId = $this->tenant->companyId();
        $branch = Branch::query()->where('company_id', $companyId)->findOrFail($data['branch_id']);
        abort_unless($this->tenant->user()->canAccessBranch($branch), 403);
        Account::query()->where('company_id', $companyId)->where('is_posting', true)->findOrFail($data['account_id']);
        PaymentMethod::query()->where('company_id', $companyId)->findOrFail($data['payment_method_id']);
        PaymentMethodAccountMapping::query()->updateOrCreate(
            ['branch_id' => $data['branch_id'], 'payment_method_id' => $data['payment_method_id']],
            $data + ['company_id' => $companyId, 'created_by' => $this->tenant->user()->id, 'updated_by' => $this->tenant->user()->id]
        );

        return back()->with('success', 'Payment method mapping saved.');
    }

    public function product(ProductAccountingMappingRequest $request): RedirectResponse
    {
        abort_unless($this->tenant->user()->hasPermission('accounting.mappings.products'), 403);
        $data = $request->validated();
        $companyId = $this->tenant->companyId();
        Product::query()->where('company_id', $companyId)->findOrFail($data['product_id']);
        foreach (['inventory_account_id', 'revenue_account_id', 'cogs_account_id', 'purchase_return_account_id', 'adjustment_account_id'] as $field) {
            if (! empty($data[$field])) {
                Account::query()->where('company_id', $companyId)->where('is_posting', true)->findOrFail($data[$field]);
            }
        }
        ProductAccountingMapping::query()->updateOrCreate(
            ['company_id' => $companyId, 'product_id' => $data['product_id']],
            $data + ['created_by' => $this->tenant->user()->id, 'updated_by' => $this->tenant->user()->id]
        );

        return back()->with('success', 'Product accounting mapping saved.');
    }
}
