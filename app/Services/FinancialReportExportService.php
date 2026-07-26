<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportExportService
{
    public function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            echo "\xEF\xBB\xBF";
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => $this->safe((string) $value), (array) $row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
