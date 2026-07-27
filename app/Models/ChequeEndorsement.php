<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeEndorsement extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id', 'uuid', 'company_id', 'status', 'created_by', 'approved_by'];

    protected $casts = ['endorsement_date' => 'date'];

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }
}
