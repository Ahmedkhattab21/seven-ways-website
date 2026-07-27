<?php

namespace Tests\Unit;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\BankStatementImportProfile;
use App\Services\CsvBankStatementParser;
use Tests\TestCase;

class CsvBankStatementParserTest extends TestCase
{
    public function test_amount_and_direction_model_uses_decimal_without_float(): void
    {
        $rows = $this->parse(
            "date,description,amount,direction,balance\n31/01/2040,Receipt,\"1.234,50\",credit,\"1.234,50\"\n",
            ['transaction_date' => 'date', 'description' => 'description', 'amount' => 'amount',
                'direction' => 'direction', 'running_balance' => 'balance'],
            ['date_format' => 'd/m/Y', 'decimal_separator' => ',', 'thousands_separator' => '.']
        );

        $this->assertSame('1234.5000', $rows[0]['data']['credit_amount']);
        $this->assertSame('0.0000', $rows[0]['data']['debit_amount']);
    }

    public function test_missing_required_header_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->parse("date,debit,credit\n2040-01-01,,10\n");
    }

    public function test_invalid_date_is_reported_with_line_number(): void
    {
        $rows = $this->parse("date,description,debit,credit\n31-99-2040,Receipt,,10\n");
        $this->assertSame(2, $rows[0]['line_number']);
        $this->assertSame('Invalid statement date.', $rows[0]['error']);
    }

    public function test_debit_credit_conflict_is_rejected(): void
    {
        $rows = $this->parse("date,description,debit,credit\n2040-01-01,Conflict,10,10\n");
        $this->assertSame('Exactly one of debit or credit must be greater than zero.', $rows[0]['error']);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $rows = $this->parse("date,description,debit,credit\n2040-01-01,Negative,-10,\n");
        $this->assertSame('Invalid decimal format.', $rows[0]['error']);
    }

    public function test_spreadsheet_formula_is_never_parsed_as_numeric_content(): void
    {
        $rows = $this->parse("date,description,debit,credit\n2040-01-01,Formula,,=1+1\n");
        $this->assertSame('Invalid decimal value.', $rows[0]['error']);
    }

    private function parse(string $csv, ?array $mapping = null, array $overrides = []): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bank-csv-');
        file_put_contents($path, $csv);
        $profile = new BankStatementImportProfile($overrides + [
            'delimiter' => ',', 'encoding' => 'UTF-8', 'date_format' => 'Y-m-d',
            'decimal_separator' => '.', 'thousands_separator' => null, 'has_header' => true,
            'column_mapping' => $mapping ?? [
                'transaction_date' => 'date', 'description' => 'description',
                'debit' => 'debit', 'credit' => 'credit',
            ],
            'skip_rows' => 0, 'direction_policy' => 'credit_increases',
        ]);
        try {
            return iterator_to_array(app(CsvBankStatementParser::class)->parse($path, $profile));
        } finally {
            unlink($path);
        }
    }
}
