<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\FilmProductProfile;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $this->assertReferences($data);
            $this->normalizeTracking($data);
            $product = new Product($data);
            $product->forceFill(['company_id' => $this->tenant->companyId(), 'created_by' => $this->tenant->user()?->id]);
            $product->save();
            $this->audit->record('product.created', $product);

            return $product;
        });
    }

    public function update(Product $product, array $data): Product
    {
        $this->assertOwned($product);
        if ($product->movements()->exists() && (
            (isset($data['tracking_type']) && $data['tracking_type'] !== $product->tracking_type)
            || (isset($data['stock_unit_id']) && (int) $data['stock_unit_id'] !== $product->stock_unit_id)
        )) {
            $this->audit->record('product.tracking_change_rejected', $product);
            throw new BusinessRuleException('Tracking type and stock unit cannot change after stock movements.');
        }
        $this->assertReferences($data);
        $this->normalizeTracking($data);
        $product->fill($data)->forceFill(['updated_by' => $this->tenant->user()?->id])->save();
        $this->audit->record('product.updated', $product);

        return $product;
    }

    public function saveFilmProfile(Product $product, array $data): FilmProductProfile
    {
        $this->assertOwned($product);
        if (! in_array($product->product_type, ['ppf', 'thermal_insulation', 'tint', 'glass_protection'], true)) {
            throw new BusinessRuleException('Film profiles are only available for film products.');
        }
        foreach (['visible_light_transmission', 'infrared_rejection', 'uv_rejection', 'heat_rejection'] as $field) {
            if (isset($data[$field]) && (bccomp((string) $data[$field], '0', 2) < 0 || bccomp((string) $data[$field], '100', 2) > 0)) {
                throw new BusinessRuleException("$field must be between 0 and 100.");
            }
        }

        return $product->filmProfile()->updateOrCreate([], $data);
    }

    private function normalizeTracking(array &$data): void
    {
        if (($data['tracking_type'] ?? null) === 'roll' || ($data['tracking_type'] ?? null) === 'serial') {
            $data['costing_method'] = 'specific';
        }
    }

    private function assertReferences(array $data): void
    {
        $companyId = $this->tenant->companyId();
        foreach (['category_id' => ProductCategory::class, 'brand_id' => ProductBrand::class] as $field => $model) {
            if (! empty($data[$field]) && ! $model::query()->whereKey($data[$field])->where('company_id', $companyId)->exists()) {
                throw new BusinessRuleException("$field is outside the current company.");
            }
        }
        foreach (['purchase_unit_id', 'stock_unit_id', 'sale_unit_id'] as $field) {
            if (! Unit::query()->whereKey($data[$field] ?? 0)->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId))->exists()) {
                throw new BusinessRuleException("$field is outside the allowed unit scope.");
            }
        }
        if (! empty($data['default_tax_id']) && ! Tax::query()->whereKey($data['default_tax_id'])->where('company_id', $companyId)->exists()) {
            throw new BusinessRuleException('Tax is outside the current company.');
        }
    }

    private function assertOwned(Product $product): void
    {
        if ($product->company_id !== $this->tenant->companyId()) {
            throw new BusinessRuleException('Product is outside the current company.', status: 403);
        }
    }
}
