<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;

class BankStatementBalanceValidationService
{
    public function validate(array $lines, string $opening, string $closing, string $policy, string $tolerance): array
    {
        $balance = bcadd($opening, '0', 4);
        $credits = $debits = '0.0000';
        foreach ($lines as $line) {
            $debits = bcadd($debits, (string) $line['debit_amount'], 4);
            $credits = bcadd($credits, (string) $line['credit_amount'], 4);
            $balance = $policy === 'debit_increases'
                ? bcadd(bcsub($balance, (string) $line['credit_amount'], 4), (string) $line['debit_amount'], 4)
                : bcadd(bcsub($balance, (string) $line['debit_amount'], 4), (string) $line['credit_amount'], 4);
            if ($line['running_balance'] !== null
                && bccomp($this->absolute(bcsub($balance, (string) $line['running_balance'], 4)), $tolerance, 4) === 1) {
                throw new BusinessRuleException("Running balance is invalid at CSV line {$line['line_number']}.");
            }
        }
        $difference = bcsub($balance, $closing, 4);
        if (bccomp($this->absolute($difference), $tolerance, 4) === 1) {
            throw new BusinessRuleException('Statement opening/closing balance formula is outside tolerance.');
        }

        return compact('credits', 'debits', 'balance', 'difference');
    }

    private function absolute(string $value): string
    {
        return str_starts_with($value, '-') ? substr($value, 1) : $value;
    }
}
