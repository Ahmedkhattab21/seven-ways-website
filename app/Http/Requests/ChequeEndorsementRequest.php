<?php

namespace App\Http\Requests;

class ChequeEndorsementRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->withProtected([
            'endorsed_to_type' => ['required', 'in:customer,supplier,employee,other'],
            'endorsed_to_id' => ['nullable', 'integer'],
            'endorsed_to_name' => ['required', 'string', 'max:255'],
            'endorsement_date' => ['required', 'date'],
        ]);
    }
}
