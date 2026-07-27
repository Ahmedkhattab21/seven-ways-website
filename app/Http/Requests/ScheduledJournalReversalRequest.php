<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduledJournalReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['company_id' => ['prohibited'], 'status' => ['prohibited'], 'scheduled_date' => ['required', 'date', 'after:today']];
    }
}
