<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WarrantyVoidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('warranties.void');
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:5000']];
    }
}
