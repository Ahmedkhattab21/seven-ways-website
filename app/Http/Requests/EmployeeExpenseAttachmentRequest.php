<?php

namespace App\Http\Requests;

use App\Models\EmployeeExpenseClaim;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeExpenseAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claim = $this->route('expenseClaim');

        return $claim instanceof EmployeeExpenseClaim
            && $claim->company_id === $this->user()->company_id
            && $claim->status === 'draft'
            && $this->user()->canAccessBranch($claim->branch)
            && ($claim->created_by === $this->user()->id
                || $this->user()->hasPermission('employee_expenses.create_for_others'));
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file', 'max:'.config('attachments.max_kb'),
                'mimes:'.implode(',', config('attachments.extensions')),
                'mimetypes:'.implode(',', config('attachments.mimetypes')),
            ],
            'category' => ['required', Rule::in(['expense_receipt', 'supporting_document', 'other'])],
        ];
    }
}
