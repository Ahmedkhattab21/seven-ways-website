<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends BaseModel
{
    use \App\Models\Concerns\HasAccountingPosting;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'decimal:6', 'stock_quantity' => 'decimal:6', 'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4', 'balance_before' => 'decimal:6', 'balance_after' => 'decimal:6',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new BusinessRuleException('Stock movements are append-only.'));
        static::deleting(fn () => throw new BusinessRuleException('Stock movements are append-only.'));
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
