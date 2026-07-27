<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;

class BankStatementDuplicateService
{
    public function assertFileIsNew(int $companyId, int $bankAccountId, string $hash): void
    {
        if (BankStatementImport::query()->where('company_id', $companyId)
            ->where('bank_account_id', $bankAccountId)->where('file_hash', $hash)->exists()) {
            throw new BusinessRuleException('The same bank statement file was already uploaded.');
        }
    }

    public function rawHash(int $bankAccountId, array $line): string
    {
        $description = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $line['description'])));
        $direction = bccomp((string) $line['debit_amount'], '0', 4) === 1 ? 'debit' : 'credit';
        $amount = $direction === 'debit' ? $line['debit_amount'] : $line['credit_amount'];

        return hash('sha256', implode('|', [
            $bankAccountId, $line['transaction_date'], $line['value_date'] ?? '',
            $line['external_id'] ?? '', $line['bank_reference'] ?? '', $amount, $direction, $description,
        ]));
    }

    public function duplicateOf(int $bankAccountId, array $line, string $hash): ?BankStatementLine
    {
        $query = BankStatementLine::query()->where('bank_account_id', $bankAccountId);
        if (! empty($line['external_id'])) {
            return $query->where('external_id', $line['external_id'])->oldest('id')->first();
        }

        return $query->where('raw_hash', $hash)->oldest('id')->first();
    }
}
