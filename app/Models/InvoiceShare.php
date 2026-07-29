<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceShare extends BaseModel
{
    protected $guarded = ['id', 'uuid', 'company_id', 'branch_id', 'sales_invoice_id', 'generated_by'];

    protected $casts = [
        'generated_at' => 'datetime',
        'opened_at' => 'datetime',
        'failed_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
