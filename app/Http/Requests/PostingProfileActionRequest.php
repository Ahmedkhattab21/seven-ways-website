<?php

namespace App\Http\Requests;

class PostingProfileActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        return $this->protectedRules();
    }
}
