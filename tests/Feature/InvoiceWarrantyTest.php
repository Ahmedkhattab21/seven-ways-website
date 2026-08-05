<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Models\ServicePackageItem;
use App\Services\InvoiceWarrantySnapshotService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class InvoiceWarrantyTest extends TestCase
{
    public function test_month_warranty_end_date_is_calculated_by_backend(): void
    {
        $service = new Service([
            'requires_warranty' => true,
            'default_warranty_duration_value' => 12,
            'default_warranty_duration_unit' => 'months',
            'default_warranty_terms' => 'Invoice snapshot terms',
        ]);

        $snapshot = app(InvoiceWarrantySnapshotService::class)->build($service, '2026-01-31');

        $this->assertTrue($snapshot['applies']);
        $this->assertSame('2027-01-31', $snapshot['end_date']);
        $this->assertSame('Invoice snapshot terms', $snapshot['terms']);
    }

    public function test_lifetime_warranty_has_no_artificial_end_date(): void
    {
        $service = new Service([
            'requires_warranty' => true,
            'default_warranty_duration_unit' => 'lifetime',
        ]);

        $snapshot = app(InvoiceWarrantySnapshotService::class)->build($service, '2026-07-30');

        $this->assertNull($snapshot['duration_value']);
        $this->assertNull($snapshot['end_date']);
    }

    public function test_product_warranty_snapshot_contains_invoice_card_details(): void
    {
        $product = new Product([
            'name' => 'LAYER+ Max Premium',
            'sku' => 'LAYER-MAX-001',
            'requires_warranty' => true,
            'default_warranty_duration_value' => 10,
            'default_warranty_duration_unit' => 'years',
        ]);
        $product->setRelation('brand', new ProductBrand(['name' => 'LAYER+']));

        $snapshot = app(InvoiceWarrantySnapshotService::class)->build($product, '2026-08-04', [
            'manufacturer' => 'Untrusted browser value',
            'roll_name' => 'Max Premium Roll 01',
            'film_code' => 'ROLL-0001',
        ]);

        $this->assertSame('LAYER+ Max Premium', $snapshot['product_name']);
        $this->assertSame('LAYER-MAX-001', $snapshot['product_sku']);
        $this->assertSame('LAYER+', $snapshot['manufacturer']);
        $this->assertSame('Max Premium Roll 01', $snapshot['roll_name']);
        $this->assertSame('ROLL-0001', $snapshot['film_code']);
        $this->assertSame('2036-08-04', $snapshot['end_date']);
    }

    public function test_non_warranted_item_has_no_snapshot(): void
    {
        $this->assertNull(app(InvoiceWarrantySnapshotService::class)->build(
            new Service(['requires_warranty' => false]),
            '2026-07-30'
        ));
    }

    public function test_package_keeps_individual_warranted_components(): void
    {
        $service = new Service([
            'name' => 'PPF',
            'requires_warranty' => true,
            'default_warranty_duration_value' => 2,
            'default_warranty_duration_unit' => 'years',
        ]);
        $service->id = 77;
        $item = new ServicePackageItem(['quantity' => 2]);
        $item->service_id = 77;
        $item->setRelation('service', $service);
        $package = new ServicePackage(['requires_warranty' => false]);
        $package->setRelation('items', collect([$item]));

        $snapshot = app(InvoiceWarrantySnapshotService::class)->build($package, '2026-07-30');

        $this->assertTrue($snapshot['applies']);
        $this->assertCount(1, $snapshot['components']);
        $this->assertSame('PPF', $snapshot['components'][0]['service_name']);
        $this->assertSame('2028-07-30', $snapshot['components'][0]['end_date']);
    }

    public function test_public_invoice_route_is_signed_and_share_route_is_authenticated(): void
    {
        $public = Route::getRoutes()->getByName('public.sales-invoices.show');
        $share = Route::getRoutes()->getByName('sales-invoices.share');

        $this->assertContains('signed', $public->gatherMiddleware());
        $this->assertContains('auth', $share->gatherMiddleware());
        $this->assertContains('permission:sales_invoices.share', $share->gatherMiddleware());
    }

    public function test_print_template_contains_separate_warranty_page_and_local_flags(): void
    {
        $template = file_get_contents(resource_path('views/sales-invoices/print.blade.php'));

        $this->assertStringContainsString('class="warranty-page"', $template);
        $this->assertStringContainsString('page-break-before:always', $template);
        $this->assertStringContainsString('كارت الضمان', $template);
        $this->assertStringContainsString('طباعة الفاتورة وكارت الضمان', $template);
        $this->assertStringContainsString('$warrantyItems = $invoice->items->where(\'item_type\', \'product\')', $template);
        $this->assertStringContainsString('كارت مرتبط بالفاتورة', $template);
        $this->assertStringContainsString('لا توجد مدة ضمان مسجلة لهذا المنتج', $template);
        $this->assertStringContainsString('بنود وشروط الضمان', $template);
        $this->assertStringContainsString('ختم وتوقيع الفرع', $template);
        $this->assertStringContainsString('images/flags/eg.svg', $template);
        $this->assertStringContainsString('images/flags/sa.svg', $template);
        $this->assertStringNotContainsString('warranties.print', $template);
    }
}
