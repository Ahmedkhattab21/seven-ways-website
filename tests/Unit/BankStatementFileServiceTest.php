<?php

namespace Tests\Unit;

use App\Core\Exceptions\BusinessRuleException;
use App\Services\BankStatementFileService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankStatementFileServiceTest extends TestCase
{
    public function test_invalid_extension_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        app(BankStatementFileService::class)->validate(
            UploadedFile::fake()->createWithContent('statement.exe', 'date,amount')
        );
    }

    public function test_html_content_is_rejected_even_with_csv_extension(): void
    {
        $this->expectException(BusinessRuleException::class);
        app(BankStatementFileService::class)->validate(
            UploadedFile::fake()->createWithContent('statement.csv', '<html><script>alert(1)</script></html>')
        );
    }

    public function test_oversized_file_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        app(BankStatementFileService::class)->validate(
            UploadedFile::fake()->create('statement.csv', 10241, 'text/csv')
        );
    }
}
