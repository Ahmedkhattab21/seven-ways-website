<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\BranchProductPrice;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\Unit;
use App\Models\User;
use App\Services\BranchProductService;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BranchProductPricingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_product_requires_branch_availability_and_effective_price(): void
    {
        $context = $this->context();

        $this->expectException(BusinessRuleException::class);
        app(ProductPricingService::class)->resolvePrice(
            $context['product'],
            $context['branch'],
            '2026-08-01'
        );
    }

    public function test_branch_price_and_product_promotion_are_resolved_without_changing_master_price(): void
    {
        $context = $this->context();
        BranchProduct::query()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'product_id' => $context['product']->id,
            'is_available' => true,
            'is_sellable' => true,
        ]);
        BranchProductPrice::query()->create([
            'company_id' => $context['company']->id,
            'branch_id' => $context['branch']->id,
            'product_id' => $context['product']->id,
            'price' => 100,
            'minimum_price' => 70,
            'effective_from' => '2026-01-01',
            'priority' => 10,
            'is_active' => true,
        ]);
        $promotion = Promotion::query()->forceCreate([
            'company_id' => $context['company']->id,
            'code' => 'PRODUCT-PROMO-'.uniqid(),
            'name' => 'Product promotion',
            'promotion_type' => 'product',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'start_at' => '2026-07-01',
            'end_at' => '2026-08-31',
            'is_active' => true,
        ]);
        $promotion->products()->attach($context['product']);
        $promotion->branches()->attach($context['branch']);

        $resolved = app(ProductPricingService::class)->resolvePrice(
            $context['product'],
            $context['branch'],
            '2026-08-01'
        );

        $this->assertSame('100.0000', $resolved['base_price']);
        $this->assertSame('20.0000', $resolved['discount_amount']);
        $this->assertSame('80.0000', $resolved['final_price']);
        $this->assertSame($promotion->id, $resolved['promotion_id']);
        $this->assertSame('999.0000', $context['product']->fresh()->default_sale_price);
    }

    public function test_branch_product_service_is_idempotent_and_rejects_overlapping_prices(): void
    {
        $context = $this->context();
        $data = [
            'is_available' => true,
            'is_sellable' => true,
            'price' => 125,
            'effective_from' => '2026-08-01',
            'effective_to' => null,
            'priority' => 0,
        ];
        app(BranchProductService::class)->save(
            $context['product'],
            $context['branch'],
            $data,
            $context['user']
        );
        app(BranchProductService::class)->save(
            $context['product'],
            $context['branch'],
            ['is_available' => false, 'is_sellable' => false],
            $context['user']
        );

        $this->assertSame(1, BranchProduct::query()->where('product_id', $context['product']->id)->count());
        $this->assertDatabaseHas('branch_products', [
            'branch_id' => $context['branch']->id,
            'product_id' => $context['product']->id,
            'is_available' => false,
        ]);

        $this->expectException(BusinessRuleException::class);
        app(BranchProductService::class)->save(
            $context['product'],
            $context['branch'],
            $data,
            $context['user']
        );
    }

    private function context(): array
    {
        $company = Company::query()->create(['name' => 'Catalog '.uniqid(), 'is_active' => true]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'BR-'.uniqid(),
            'name' => 'Branch',
            'is_main' => true,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $user->accessibleBranches()->attach($branch->id, [
            'is_default' => true,
            'can_view' => true,
        ]);
        $unit = Unit::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'U'.uniqid(),
            'name' => 'Unit',
            'symbol' => 'u',
            'unit_type' => 'quantity',
            'decimal_places' => 6,
            'is_system' => false,
            'is_active' => true,
        ]);
        $category = ProductCategory::query()->forceCreate([
            'company_id' => $company->id,
            'code' => 'PC'.uniqid(),
            'name' => 'Products',
            'is_active' => true,
        ]);
        $product = Product::query()->forceCreate([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'sku' => 'SKU'.uniqid(),
            'name' => 'Branch product',
            'product_type' => 'consumable',
            'tracking_type' => 'quantity',
            'purchase_unit_id' => $unit->id,
            'stock_unit_id' => $unit->id,
            'sale_unit_id' => $unit->id,
            'costing_method' => 'weighted_average',
            'default_sale_price' => 999,
            'minimum_stock' => 0,
            'is_sellable' => true,
            'is_purchasable' => true,
            'is_consumable' => true,
            'is_active' => true,
        ]);

        return compact('company', 'branch', 'user', 'product');
    }
}
