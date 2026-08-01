<?php

namespace Tests\Feature\PhaseTwentyOne;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Vehicle;
use App\Services\QuotationApprovalService;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\UsesPhaseTwentyOneUat;
use Tests\TestCase;

class PhaseTwentyOneSalesWorkOrderCycleTest extends TestCase
{
    use DatabaseTransactions;
    use UsesPhaseTwentyOneUat;

    public function test_uat_quotation_uses_server_pricing_approval_and_audit(): void
    {
        $sales = $this->setUpUatContext('uat.sales@sevenways.test');
        $customer = Customer::query()->where('company_id', $this->uatCompany->id)
            ->where('customer_code', 'UAT-CUS-CASH')->firstOrFail();
        $vehicle = Vehicle::query()->where('company_id', $this->uatCompany->id)
            ->where('customer_id', $customer->id)->firstOrFail();
        $product = Product::query()->where('company_id', $this->uatCompany->id)
            ->where('sku', 'CLEANER-PPF-UAT-001')->firstOrFail();

        $quotation = app(QuotationService::class)->save([
            'branch_id' => $this->uatBranches['UAT-CAI']->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'quotation_date' => today()->toDateString(),
            'valid_until' => today()->addDays(7)->toDateString(),
            'currency_id' => $this->uatCompany->currency_id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ], [[
            'product_id' => $product->id,
            'quantity' => 1,
        ]]);
        $this->assertSame('250.0000', $quotation->subtotal);
        $this->assertSame('25.0000', $quotation->discount_amount);
        $this->assertSame('31.5000', $quotation->tax_amount);
        $this->assertSame('256.5000', $quotation->total);

        app(QuotationApprovalService::class)->submit($quotation);
        $manager = $this->uatUser('uat.cairo.manager@sevenways.test');
        $this->actingAs($manager);
        app(TenantContext::class)->initialize($manager);
        app(QuotationApprovalService::class)->approve($quotation->fresh(), 'UAT approved');

        $this->assertSame('approved', $quotation->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->uatCompany->id,
            'event' => 'quotation.submitted',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->uatCompany->id,
            'event' => 'quotation.approved',
        ]);
        $this->assertSame(1, Quotation::query()->where('company_id', $this->uatCompany->id)->count());
        $this->assertNotSame($sales->id, $quotation->fresh()->approved_by);
    }
}
