<?php

namespace App\Http\Requests;

class TreasuryTransferProcessingRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'idempotency_key' => ['prohibited'], 'failure_reason' => ['prohibited'],
            'processed_by' => ['prohibited'], 'processed_at' => ['prohibited'],
        ]);
    }
}
