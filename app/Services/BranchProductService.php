<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\BranchProductPrice;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class BranchProductService
{
    public function save(Product $product, Branch $branch, array $data, User $actor): BranchProduct
    {
        if ((int) $product->company_id !== (int) $branch->company_id
            || (int) $actor->company_id !== (int) $branch->company_id
            || ! $actor->canAccessBranch($branch)) {
            throw new BusinessRuleException('Branch product scope is invalid.', status: 403);
        }

        if (! empty($data['default_sales_warehouse_id'])) {
            $validWarehouse = Warehouse::query()->whereKey($data['default_sales_warehouse_id'])
                ->where('company_id', $branch->company_id)->where('branch_id', $branch->id)
                ->where('is_active', true)->where('allows_sale_issue', true)->exists();
            if (! $validWarehouse) {
                throw new BusinessRuleException('المستودع الافتراضي يجب أن يكون نشطًا ويتبع نفس الفرع.');
            }
        }

        return DB::transaction(function () use ($product, $branch, $data, $actor) {
            $link = BranchProduct::query()->firstOrNew([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
            ]);
            $link->fill(collect($data)->only([
                'default_sales_warehouse_id', 'is_available', 'is_sellable',
                'minimum_stock', 'maximum_stock', 'reorder_quantity', 'notes',
            ])->all())->forceFill([
                'company_id' => $branch->company_id,
                'created_by' => $link->exists ? $link->created_by : $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            if (isset($data['price'], $data['effective_from'])) {
                $overlap = BranchProductPrice::query()
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->where('priority', (int) ($data['priority'] ?? 0))
                    ->where('is_active', true)
                    ->whereDate('effective_from', '<=', ($data['effective_to'] ?? null) ?: '9999-12-31')
                    ->where(fn ($query) => $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $data['effective_from']))
                    ->exists();
                if ($overlap) {
                    throw new BusinessRuleException('توجد فترة سعر متداخلة لنفس المنتج والفرع والأولوية.');
                }
                BranchProductPrice::query()->create([
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'price' => $data['price'],
                    'minimum_price' => $data['minimum_price'] ?? null,
                    'effective_from' => $data['effective_from'],
                    'effective_to' => $data['effective_to'] ?? null,
                    'priority' => (int) ($data['priority'] ?? 0),
                    'is_active' => true,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            return $link->fresh(['defaultSalesWarehouse']);
        });
    }
}
