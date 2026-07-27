<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBoxCountLine extends Model
{
    protected $guarded = ['id', 'line_total'];

    protected $casts = ['denomination' => 'decimal:4', 'line_total' => 'decimal:4'];

    public function count(): BelongsTo
    {
        return $this->belongsTo(CashBoxCount::class, 'cash_box_count_id');
    }
}
