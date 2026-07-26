<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends BaseModel
{
    use \App\Models\Concerns\HasAccountingPosting;

    protected $guarded = [
        'id', 'company_id', 'branch_id', 'purchase_return_number', 'status', 'subtotal',
        'tax_amount', 'total', 'created_by', 'approved_by', 'posted_by', 'cancelled_by',
    ];

    protected $casts = [
        'return_date' => 'date', 'approved_at' => 'datetime', 'posted_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $model) => $model->status !== 'draft'
            ? throw new BusinessRuleException('Processed purchase returns cannot be deleted.')
            : null);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
