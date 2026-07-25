<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkOrder extends BaseModel
{
    use HasFactory;

    public const TERMINAL_STATUSES = ['ready_for_delivery', 'delivered', 'cancelled', 'closed'];

    protected $guarded = [
        'id', 'company_id', 'work_order_number', 'status', 'actual_material_cost',
        'actual_waste_cost', 'actual_labor_cost', 'actual_total_cost', 'actual_margin',
        'created_by', 'updated_by', 'cancelled_by', 'delivered_by',
    ];

    protected $casts = [
        'check_in_at' => 'datetime', 'expected_start_at' => 'datetime', 'started_at' => 'datetime',
        'expected_finish_at' => 'datetime', 'finished_at' => 'datetime', 'ready_for_quality_at' => 'datetime',
        'delivered_at' => 'datetime', 'fuel_level' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $order) => throw new BusinessRuleException('Executed work orders cannot be deleted.'));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(WorkOrderService::class)->orderBy('sort_order');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class);
    }

    public function wastes(): HasMany
    {
        return $this->hasMany(WorkOrderWasteRecord::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(WorkOrderStatusLog::class)->orderBy('created_at');
    }

    public function inspection(): HasOne
    {
        return $this->hasOne(VehicleInspection::class)->where('inspection_type', 'check_in');
    }

    public function deliveryInspection(): HasOne
    {
        return $this->hasOne(VehicleInspection::class)->where('inspection_type', 'delivery');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(VehicleInspection::class);
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(QualityCheck::class)->orderBy('round_number');
    }

    public function reworkOrders(): HasMany
    {
        return $this->hasMany(ReworkOrder::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }
}
