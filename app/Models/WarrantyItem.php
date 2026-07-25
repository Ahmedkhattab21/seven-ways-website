<?php

namespace App\Models;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyItem extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'warranty_id'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Warranty items cannot be deleted.'));
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function workOrderService(): BelongsTo
    {
        return $this->belongsTo(WorkOrderService::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(InventoryRoll::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }
}
