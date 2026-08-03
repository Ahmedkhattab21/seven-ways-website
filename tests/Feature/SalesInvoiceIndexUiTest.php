<?php

namespace Tests\Feature;

use Tests\TestCase;

class SalesInvoiceIndexUiTest extends TestCase
{
    public function test_sales_invoice_index_uses_spaced_table_shell_and_arabic_statuses(): void
    {
        $view = file_get_contents(resource_path('views/sales-invoices/index.blade.php'));

        $this->assertStringContainsString("@section('page-actions')", $view);
        $this->assertStringContainsString('sales-invoices-table-card', $view);
        $this->assertStringContainsString('<x-table-shell', $view);
        $this->assertStringContainsString('<x-status-badge', $view);
        $this->assertStringContainsString("'issued' => 'صادرة'", $view);
        $this->assertStringContainsString("'paid' => 'مدفوعة'", $view);
        $this->assertStringContainsString('number_format((float) $invoice->total, 2)', $view);
        $this->assertStringNotContainsString('{{ $invoice->status }}', $view);
    }
}
