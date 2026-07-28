<?php

namespace Tests\Feature;

use App\Analytics\ReportFilterData;
use App\Core\Tenancy\TenantContext;
use App\Models\Permission;
use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsAnalyticsContext;
use Tests\TestCase;

class PhaseNineteenExportSecurityTest extends TestCase
{
    use BuildsAnalyticsContext;
    use DatabaseTransactions;

    public function test_csv_formula_injection_is_blocked_without_corrupting_negative_numbers(): void
    {
        $export = app(ReportExportService::class);
        $this->assertSame("'=1+1", $export->safe('=1+1'));
        $this->assertSame("'@SUM(A1)", $export->safe('@SUM(A1)'));
        $this->assertSame("'+cmd", $export->safe('+cmd'));
        $this->assertSame('-12.50', $export->safe('-12.50'));
    }

    public function test_xlsx_is_a_real_zip_workbook_and_contains_no_formula_cells(): void
    {
        $context = $this->analyticsContext();
        $filters = ReportFilterData::from([
            'branch_id' => $context['branch']->id,
            'date_from' => '2040-01-01',
            'date_to' => '2040-01-31',
        ], app(TenantContext::class));
        $response = app(ReportExportService::class)->xlsx(
            'test.xlsx',
            ['value' => 'Value'],
            collect([(object) ['value' => '=1+1']]),
            ['report_name' => 'Test', 'company_name' => 'Seven Ways'],
            $filters
        );
        $this->assertStringStartsWith('PK', $response->getContent());
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
    }

    public function test_export_requires_view_and_export_permissions_and_audit_needs_sensitive_permission(): void
    {
        $context = $this->analyticsContext();
        $role = $context['user']->roles()->first();
        $role->permissions()->detach(Permission::where('name', 'reports.export')->value('id'));
        $this->get(route('analytics.reports.export', ['sales', 'format' => 'csv']))->assertForbidden();

        $role->permissions()->syncWithoutDetaching(Permission::where('name', 'reports.export')->pluck('id'));
        $role->permissions()->detach(Permission::where('name', 'reports.export_sensitive')->value('id'));
        $this->get(route('analytics.reports.export', ['audit', 'format' => 'csv']))->assertForbidden();
    }

    public function test_print_to_pdf_view_is_rtl_private_and_branch_scoped(): void
    {
        $context = $this->analyticsContext();
        $this->get(route('analytics.reports.export', [
            'sales', 'format' => 'pdf', 'branch_id' => $context['branch']->id,
            'date_from' => '2040-01-01', 'date_to' => '2040-01-31',
        ]))->assertOk()
            ->assertHeader('X-Report-Output', 'print-to-pdf')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('dir="rtl"', false);
    }
}
