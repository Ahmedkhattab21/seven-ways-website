<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class SupplierProductService
{
    public function __construct(private TenantContext $tenant, private AuditService $audit)
    {
    }

    public function save(Supplier $supplier, array $data): SupplierProduct
    {
        return DB::transaction(function () use ($supplier, $data) {
            abort_unless($supplier->company_id === $this->tenant->companyId(), 403);
            $product = Product::whereKey($data['product_id'])->where('company_id', $supplier->company_id)->firstOrFail();
            Unit::whereKey($data['purchase_unit_id'])->where(function ($query) use ($supplier) {
                $query->whereNull('company_id')->orWhere('company_id', $supplier->company_id);
            })->firstOrFail();
            if (bccomp((string) $data['conversion_factor'], '0', 6) !== 1) {
                throw new BusinessRuleException('Conversion factor must be positive.');
            }
            if ($data['is_preferred'] ?? false) {
                SupplierProduct::where('product_id', $product->id)->where('is_preferred', true)
                    ->whereHas('supplier', fn ($query) => $query->where('company_id', $supplier->company_id))
                    ->lockForUpdate()->update(['is_preferred' => false]);
            }
            $record = SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplier->id, 'product_id' => $product->id],
                $data
            );
            $this->audit->record('supplier.product_saved', $supplier, ['supplier_product_id' => $record->id]);

            return $record;
        });
    }
}
