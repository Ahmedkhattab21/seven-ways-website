<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'file_name', 'storage_path', 'file_hash', 'status',
        'total_lines', 'imported_lines', 'duplicate_lines', 'failed_lines', 'uploaded_by',
        'validated_by', 'cancelled_by', 'imported_at', 'validated_at', 'cancelled_at', 'failure_reason',
    ];

    protected $hidden = ['storage_path', 'file_hash', 'metadata'];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date', 'opening_balance' => 'decimal:4',
        'closing_balance' => 'decimal:4', 'imported_at' => 'datetime', 'validated_at' => 'datetime',
        'cancelled_at' => 'datetime', 'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new BusinessRuleException('Bank statement imports are cancelled, not deleted.'));
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
