<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Core\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class EmployeeExpenseClaim extends BaseModel
{
    use HasFactory;

    protected $guarded = [
        'id', 'uuid', 'company_id', 'claim_number', 'subtotal', 'tax_amount',
        'total_amount', 'status', 'created_by', 'submitted_by', 'approved_by',
        'posted_by', 'paid_by', 'reversed_by', 'journal_entry_id',
        'reversal_journal_entry_id', 'cash_payment_id',
    ];

    protected $casts = [
        'claim_date' => 'date', 'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4', 'total_amount' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (self $claim) => $claim->status !== 'draft'
            ? throw new BusinessRuleException('Processed expense claims cannot be deleted.')
            : null);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeeExpenseClaimItem::class, 'expense_claim_id')->orderBy('sort_order');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
