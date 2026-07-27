<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BankStatementFileService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function validate(UploadedFile $file): void
    {
        if (! $file->isValid() || $file->getSize() === false || $file->getSize() > self::MAX_BYTES) {
            throw new BusinessRuleException('Bank statement file is invalid or exceeds the 10 MB limit.');
        }
        if (strtolower((string) $file->getClientOriginalExtension()) !== 'csv') {
            throw new BusinessRuleException('Only CSV bank statements are enabled in Phase 15B.');
        }
        $allowedMime = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
        if (! in_array((string) $file->getMimeType(), $allowedMime, true)) {
            throw new BusinessRuleException('Bank statement MIME type is not allowed.');
        }
        $handle = fopen($file->getRealPath(), 'rb');
        $prefix = $handle ? (string) fread($handle, 2048) : '';
        if ($handle) {
            fclose($handle);
        }
        if ($prefix === '' || str_contains($prefix, "\0") || ! mb_check_encoding($prefix, 'UTF-8')
            || preg_match('/^\s*<(?:!doctype|html|script|\?xml)/i', $prefix)) {
            throw new BusinessRuleException('Bank statement content is not a supported CSV file.');
        }
    }

    public function hash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }

    public function store(UploadedFile $file, int $companyId, BankAccount $account): array
    {
        $safeName = Str::uuid().'.csv';
        $directory = "private/bank-statements/{$companyId}/{$account->uuid}";
        $path = Storage::disk('local')->putFileAs($directory, $file, $safeName);
        if (! $path) {
            throw new BusinessRuleException('Bank statement could not be stored.');
        }

        return ['file_name' => $safeName, 'storage_path' => $path];
    }

    public function absolutePath(string $storagePath): string
    {
        return Storage::disk('local')->path($storagePath);
    }
}
