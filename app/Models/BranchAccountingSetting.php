<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchAccountingSetting extends Model
{
    protected $fillable = [
        'branch_id', 'default_cost_center_id', 'cash_account_id', 'bank_account_id',
        'accounts_receivable_account_id', 'accounts_payable_account_id', 'sales_revenue_account_id',
        'service_revenue_account_id', 'product_revenue_account_id', 'sales_discount_account_id',
        'sales_return_account_id', 'inventory_account_id', 'cost_of_goods_sold_account_id',
        'inventory_adjustment_account_id', 'purchase_account_id', 'purchase_return_account_id',
        'vat_input_account_id', 'vat_output_account_id', 'customer_advance_account_id',
        'supplier_advance_account_id', 'rounding_account_id',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
