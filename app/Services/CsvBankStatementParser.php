<?php

namespace App\Services;

use App\Contracts\BankStatementParserContract;
use App\Core\Exceptions\BusinessRuleException;
use App\Models\BankStatementImportProfile;
use DateTimeImmutable;
use Generator;
use SplFileObject;

class CsvBankStatementParser implements BankStatementParserContract
{
    public const MAX_LINES = 100000;

    private const ALLOWED_FIELDS = [
        'transaction_date', 'value_date', 'description', 'reference', 'debit', 'credit',
        'amount', 'direction', 'running_balance', 'external_id', 'counterparty_name',
        'counterparty_iban', 'transaction_code',
    ];

    public function version(): string
    {
        return 'csv-v1';
    }

    public function parse(string $path, BankStatementImportProfile $profile): Generator
    {
        $mapping = $this->mapping($profile->column_mapping ?? []);
        $this->assertRequiredMapping($mapping);
        $file = new SplFileObject($path, 'rb');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($profile->delimiter, '"', '\\');
        $headers = null;
        $physicalLine = 0;
        $dataLines = 0;

        foreach ($file as $row) {
            $physicalLine++;
            if ($physicalLine <= $profile->skip_rows || $row === [null] || $this->blankRow($row)) {
                continue;
            }
            if ($profile->has_header && $headers === null) {
                $headers = array_map(fn ($value) => $this->header((string) $value), $row);
                $this->assertHeaders($mapping, $headers);

                continue;
            }
            $dataLines++;
            if ($dataLines > self::MAX_LINES) {
                throw new BusinessRuleException('CSV line limit of 100,000 was exceeded.');
            }
            try {
                $values = $this->values($row, $mapping, $headers);
                $data = $this->normalize($values, $profile);
                yield ['line_number' => $physicalLine, 'data' => $data];
            } catch (BusinessRuleException $exception) {
                yield ['line_number' => $physicalLine, 'error' => $exception->getMessage()];
            }
        }
    }

    private function mapping(array $mapping): array
    {
        $clean = [];
        foreach ($mapping as $field => $column) {
            if (! in_array($field, self::ALLOWED_FIELDS, true) || (! is_string($column) && ! is_int($column))) {
                throw new BusinessRuleException('CSV column mapping contains an unsupported value.');
            }
            $clean[$field] = is_string($column) ? $this->header($column) : $column;
        }

        return $clean;
    }

    private function assertRequiredMapping(array $mapping): void
    {
        if (! isset($mapping['transaction_date'], $mapping['description'])) {
            throw new BusinessRuleException('CSV mapping requires transaction date and description.');
        }
        $separate = isset($mapping['debit'], $mapping['credit']);
        $directional = isset($mapping['amount'], $mapping['direction']);
        if ($separate === $directional) {
            throw new BusinessRuleException('CSV mapping requires either Debit/Credit or Amount/Direction columns.');
        }
    }

    private function assertHeaders(array $mapping, array $headers): void
    {
        foreach ($mapping as $column) {
            if (is_string($column) && ! in_array($column, $headers, true)) {
                throw new BusinessRuleException("Mapped CSV header [{$column}] is missing.");
            }
        }
    }

    private function values(array $row, array $mapping, ?array $headers): array
    {
        if (! mb_check_encoding(implode('', array_map('strval', $row)), 'UTF-8')) {
            throw new BusinessRuleException('CSV row is not valid UTF-8.');
        }
        $values = [];
        foreach ($mapping as $field => $column) {
            $index = is_int($column) ? $column : array_search($column, $headers ?: [], true);
            $values[$field] = $index === false ? null : ($row[$index] ?? null);
        }

        return $values;
    }

    private function normalize(array $values, BankStatementImportProfile $profile): array
    {
        $date = $this->date((string) ($values['transaction_date'] ?? ''), $profile->date_format, true);
        $valueDate = $this->date((string) ($values['value_date'] ?? ''), $profile->date_format, false);
        $description = trim((string) ($values['description'] ?? ''));
        if ($description === '') {
            throw new BusinessRuleException('Description is required.');
        }
        if (array_key_exists('amount', $values)) {
            $amount = $this->decimal((string) $values['amount'], $profile);
            $direction = strtolower(trim((string) ($values['direction'] ?? '')));
            if (! in_array($direction, ['debit', 'credit', 'dr', 'cr'], true)) {
                throw new BusinessRuleException('Direction must be debit/credit or dr/cr.');
            }
            $debit = in_array($direction, ['debit', 'dr'], true) ? $amount : '0.0000';
            $credit = in_array($direction, ['credit', 'cr'], true) ? $amount : '0.0000';
        } else {
            $debit = $this->decimal((string) ($values['debit'] ?? ''), $profile, true);
            $credit = $this->decimal((string) ($values['credit'] ?? ''), $profile, true);
        }
        if ((bccomp($debit, '0', 4) === 1) === (bccomp($credit, '0', 4) === 1)) {
            throw new BusinessRuleException('Exactly one of debit or credit must be greater than zero.');
        }

        return [
            'transaction_date' => $date, 'value_date' => $valueDate,
            'description' => mb_substr($description, 0, 2000),
            'bank_reference' => $this->nullable($values['reference'] ?? null, 255),
            'external_id' => $this->nullable($values['external_id'] ?? null, 255),
            'debit_amount' => $debit, 'credit_amount' => $credit,
            'running_balance' => isset($values['running_balance']) && trim((string) $values['running_balance']) !== ''
                ? $this->decimal((string) $values['running_balance'], $profile, false, true) : null,
            'counterparty_name' => $this->nullable($values['counterparty_name'] ?? null, 255),
            'counterparty_iban' => $this->nullable($values['counterparty_iban'] ?? null, 100),
            'transaction_code' => $this->nullable($values['transaction_code'] ?? null, 50),
        ];
    }

    private function decimal(
        string $value,
        BankStatementImportProfile $profile,
        bool $blankIsZero = false,
        bool $allowNegative = false
    ): string {
        $value = trim($value);
        if ($value === '' && $blankIsZero) {
            return '0.0000';
        }
        if ($value === '' || preg_match('/^[=+@]/', $value)) {
            throw new BusinessRuleException('Invalid decimal value.');
        }
        if ($profile->thousands_separator) {
            $value = str_replace($profile->thousands_separator, '', $value);
        }
        if ($profile->decimal_separator !== '.') {
            $value = str_replace($profile->decimal_separator, '.', $value);
        }
        if (! preg_match($allowNegative ? '/^-?\d+(?:\.\d{1,4})?$/' : '/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new BusinessRuleException('Invalid decimal format.');
        }
        if (! $allowNegative && bccomp($value, '0', 4) === -1) {
            throw new BusinessRuleException('Negative statement amounts are not allowed.');
        }

        return bcadd($value, '0', 4);
    }

    private function date(string $value, string $format, bool $required): ?string
    {
        $value = trim($value);
        if ($value === '' && ! $required) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
            || $date->format($format) !== $value) {
            throw new BusinessRuleException('Invalid statement date.');
        }

        return $date->format('Y-m-d');
    }

    private function header(string $value): string
    {
        return mb_strtolower(trim(ltrim($value, "\xEF\xBB\xBF")));
    }

    private function nullable(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function blankRow(array $row): bool
    {
        return count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0;
    }
}
