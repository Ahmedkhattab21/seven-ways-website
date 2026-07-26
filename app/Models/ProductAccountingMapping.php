<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductAccountingMapping extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'inventory_account_id', 'revenue_account_id', 'cogs_account_id',
        'purchase_return_account_id', 'adjustment_account_id', 'is_active',
        'company_id', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
