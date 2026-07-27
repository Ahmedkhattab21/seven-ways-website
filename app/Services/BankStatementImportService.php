<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\BankStatementImported;
use App\Events\BankStatementImportFailed;
use App\Events\BankStatementUploaded;
use App\Events\BankStatementValidated;
use App\Models\BankAccount;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportProfile;
use App\Models\BankStatementLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class BankStatementImportService
{
    public function __construct(
        private TenantContext $tenant,
        private BankAccountAccessService $access,
        private BankStatementFileService $files,
        private BankStatementParserRegistry $parsers,
        private BankStatementDuplicateService $duplicates,
        private BankStatementBalanceValidationService $balances,
        private AuditService $audit
    ) {
    }

    public function import(BankAccount $account, BankStatementImportProfile $profile, UploadedFile $file, array $data): BankStatementImport
    {
        $account = BankAccount::query()->where('company_id', $this->tenant->companyId())
            ->where('status', 'active')->findOrFail($account->id);
        if ($profile->company_id !== $account->company_id
            || ($profile->bank_account_id && $profile->bank_account_id !== $account->id)
            || ! $profile->is_active || $profile->format !== 'csv') {
            throw new BusinessRuleException('Import profile is outside the active bank account scope.');
        }
        if ((int) $data['currency_id'] !== $account->currency_id) {
            throw new BusinessRuleException('Statement currency must match the bank account currency.');
        }
        $this->access->assert($account, (int) $this->tenant->branchId(), 'can_view');
        $this->files->validate($file);
        $hash = $this->files->hash($file);
        $this->duplicates->assertFileIsNew($account->company_id, $account->id, $hash);
        $stored = $this->files->store($file, $account->company_id, $account);
        $parser = $this->parsers->for('csv');

        $import = new BankStatementImport($data);
        $import->forceFill($stored + [
            'company_id' => $account->company_id, 'bank_account_id' => $account->id,
            'original_file_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'file_hash' => $hash, 'format' => 'csv', 'parser_version' => $parser->version(),
            'status' => 'validating', 'uploaded_by' => $this->tenant->user()->id,
            'imported_at' => now(), 'metadata' => ['profile_id' => $profile->id],
        ])->save();
        $this->audit->record('bank_statement.uploaded', $import, ['format' => 'csv', 'file_size' => $file->getSize()]);
        DB::afterCommit(fn () => event(new BankStatementUploaded($import->id)));

        try {
            DB::transaction(function () use ($import, $profile, $parser, $account) {
                $import = BankStatementImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
                $parsed = [];
                $errors = [];
                foreach ($parser->parse($this->files->absolutePath($import->storage_path), $profile) as $result) {
                    if (isset($result['error'])) {
                        $errors[] = "Line {$result['line_number']}: {$result['error']}";

                        continue;
                    }
                    $parsed[] = ['line_number' => $result['line_number']] + $result['data'];
                }
                if ($errors !== []) {
                    throw new BusinessRuleException(implode(' | ', array_slice($errors, 0, 20)));
                }
                if ($parsed === []) {
                    throw new BusinessRuleException('CSV contains no importable statement lines.');
                }
                $this->balances->validate(
                    $parsed, (string) $import->opening_balance, (string) $import->closing_balance,
                    $profile->direction_policy, (string) $profile->balance_tolerance
                );
                $duplicateCount = 0;
                foreach ($parsed as $line) {
                    $rawHash = $this->duplicates->rawHash($account->id, $line);
                    $duplicate = $this->duplicates->duplicateOf($account->id, $line, $rawHash);
                    $iban = preg_replace('/\s+/', '', (string) ($line['counterparty_iban'] ?? ''));
                    $amount = bccomp((string) $line['debit_amount'], '0', 4) === 1
                        ? $line['debit_amount'] : $line['credit_amount'];
                    unset($line['counterparty_iban']);
                    $statementLine = new BankStatementLine;
                    $statementLine->forceFill([
                        ...$line, 'company_id' => $account->company_id,
                        'bank_statement_import_id' => $import->id, 'bank_account_id' => $account->id,
                        'currency_id' => $account->currency_id,
                        'counterparty_iban_encrypted' => $iban ?: null,
                        'counterparty_iban_hash' => $iban ? hash('sha256', strtoupper($iban)) : null,
                        'counterparty_iban_last4' => $iban ? substr($iban, -4) : null,
                        'status' => $duplicate ? 'duplicate' : 'unmatched',
                        'matched_amount' => '0.0000', 'unmatched_amount' => $amount,
                        'is_duplicate' => (bool) $duplicate, 'duplicate_of_id' => $duplicate?->id,
                        'raw_hash' => $rawHash,
                        'raw_payload' => array_filter([
                            'reference' => $line['bank_reference'], 'external_id' => $line['external_id'],
                            'transaction_code' => $line['transaction_code'],
                        ], fn ($value) => $value !== null),
                    ])->save();
                    $duplicateCount += $duplicate ? 1 : 0;
                }
                $import->forceFill([
                    'status' => 'imported', 'total_lines' => count($parsed), 'imported_lines' => count($parsed),
                    'duplicate_lines' => $duplicateCount, 'failed_lines' => 0,
                    'validated_by' => $this->tenant->user()->id, 'validated_at' => now(),
                    'failure_reason' => null,
                ])->save();
                $this->audit->record('bank_statement.imported', $import, [
                    'total_lines' => count($parsed), 'duplicate_lines' => $duplicateCount,
                ]);
                DB::afterCommit(function () use ($import) {
                    event(new BankStatementValidated($import->id));
                    event(new BankStatementImported($import->id));
                });
            });
        } catch (\Throwable $exception) {
            $import->forceFill(['status' => 'failed', 'failure_reason' => mb_substr($exception->getMessage(), 0, 2000)])->save();
            $this->audit->record('bank_statement.import_failed', $import, ['reason' => mb_substr($exception->getMessage(), 0, 500)]);
            DB::afterCommit(fn () => event(new BankStatementImportFailed($import->id)));
            throw $exception;
        }

        return $import->fresh('lines');
    }

    public function cancel(BankStatementImport $import, string $reason): BankStatementImport
    {
        return DB::transaction(function () use ($import, $reason) {
            $import = BankStatementImport::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($import->id)->lockForUpdate()->firstOrFail();
            if (BankReconciliationSession::query()->where('status', 'completed')
                ->whereHas('imports', fn ($query) => $query->whereKey($import->id))->exists()) {
                throw new BusinessRuleException('Import used by completed reconciliation cannot be cancelled.');
            }
            if ($import->status === 'cancelled') {
                return $import;
            }
            $import->forceFill([
                'status' => 'cancelled', 'cancelled_by' => $this->tenant->user()->id,
                'cancelled_at' => now(), 'failure_reason' => $reason,
            ])->save();
            $this->audit->record('bank_statement.cancelled', $import, ['reason' => $reason]);

            return $import;
        });
    }
}
