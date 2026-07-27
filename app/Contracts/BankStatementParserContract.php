<?php

namespace App\Contracts;

use App\Models\BankStatementImportProfile;
use Generator;

interface BankStatementParserContract
{
    public function version(): string;

    /**
     * @return Generator<int, array{line_number:int, data?:array, error?:string}>
     */
    public function parse(string $path, BankStatementImportProfile $profile): Generator;
}
