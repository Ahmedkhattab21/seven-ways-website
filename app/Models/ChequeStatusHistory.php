<?php

namespace App\Models;

use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeStatusHistory extends Model
{
    protected $guarded = ['id', 'company_id', 'changed_by', 'changed_at'];

    protected $casts = ['metadata' => 'array', 'changed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new BusinessRuleException('Cheque history is append-only.'));
        static::deleting(fn () => throw new BusinessRuleException('Cheque history is append-only.'));
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }
}
