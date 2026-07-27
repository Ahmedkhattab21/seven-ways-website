<?php

namespace App\Services;

use App\Contracts\BankStatementParserContract;
use App\Core\Exceptions\BusinessRuleException;

class BankStatementParserRegistry
{
    public function __construct(private CsvBankStatementParser $csv)
    {
    }

    public function for(string $format): BankStatementParserContract
    {
        if ($format !== 'csv') {
            throw new BusinessRuleException('Only CSV bank statement parsing is implemented in Phase 15B.');
        }

        return $this->csv;
    }
}
