<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequisition extends BaseModel
{
    protected $guarded = [
        'id', 'company_id', 'branch_id', 'requisition_number', 'status', 'estimated_total',
        'created_by', 'submitted_by', 'approved_by', 'rejected_by', 'cancelled_by',
    ];

    protected $casts = [
        'request_date' => 'date', 'required_date' => 'date', 'submitted_at' => 'datetime',
        'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $model) => $model->status !== 'draft'
            ? throw new BusinessRuleException('Processed requisitions cannot be deleted.')
            : null);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class);
    }
}
