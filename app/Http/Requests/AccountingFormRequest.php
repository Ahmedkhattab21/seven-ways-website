<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AccountingFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function protectedRules(): array
    {
        return [
            'company_id' => ['prohibited'], 'status' => ['prohibited'], 'level' => ['prohibited'],
            'path' => ['prohibited'], 'account_level' => ['prohibited'], 'account_path' => ['prohibited'],
            'created_by' => ['prohibited'], 'updated_by' => ['prohibited'], 'approved_by' => ['prohibited'],
            'posted_at' => ['prohibited'], 'total_debit' => ['prohibited'], 'total_credit' => ['prohibited'],
            'document_number' => ['prohibited'], 'submitted_by' => ['prohibited'],
            'completed_by' => ['prohibited'], 'cancelled_by' => ['prohibited'],
            'closed_by' => ['prohibited'], 'assigned_by' => ['prohibited'], 'revoked_by' => ['prohibited'],
            'submitted_at' => ['prohibited'], 'approved_at' => ['prohibited'],
            'completed_at' => ['prohibited'], 'cancelled_at' => ['prohibited'],
            'closed_at' => ['prohibited'], 'revoked_at' => ['prohibited'],
            'journal_entry_id' => ['prohibited'], 'book_balance' => ['prohibited'],
            'bank_balance' => ['prohibited'],
            'reversal_journal_entry_id' => ['prohibited'], 'file_hash' => ['prohibited'],
            'raw_hash' => ['prohibited'], 'matched_amount' => ['prohibited'],
            'unmatched_amount' => ['prohibited'], 'difference' => ['prohibited'],
            'book_opening_balance' => ['prohibited'], 'book_closing_balance' => ['prohibited'],
            'statement_opening_balance' => ['prohibited'], 'statement_closing_balance' => ['prohibited'],
            'matched_statement_amount' => ['prohibited'], 'matched_book_amount' => ['prohibited'],
            'unreconciled_statement_amount' => ['prohibited'], 'unreconciled_book_amount' => ['prohibited'],
            'reviewed_by' => ['prohibited'], 'uploaded_by' => ['prohibited'],
            'validated_by' => ['prohibited'], 'completed_at' => ['prohibited'],
        ];
    }

    protected function withProtected(array $rules): array
    {
        return $rules + $this->protectedRules();
    }
}
