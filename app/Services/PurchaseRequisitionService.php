<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(array $data, array $items): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $items) {
            $requisition = new PurchaseRequisition($data);
            $requisition->forceFill([
                'company_id' => $this->tenant->companyId(),
                'branch_id' => $this->tenant->branchId(),
                'requisition_number' => $this->numbers->next(
                    'purchase_requisition',
                    $this->tenant->companyId(),
                    $this->tenant->branchId(),
                    $data['request_date']
                ),
                'status' => 'draft',
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $total = '0.0000';
            foreach ($items as $input) {
                $product = Product::whereKey($input['product_id'])->where('company_id', $requisition->company_id)
                    ->where('is_purchasable', true)->where('is_active', true)->firstOrFail();
                if (in_array($product->product_type, ['roll', 'scrap'], true)) {
                    throw new BusinessRuleException('Purchase the source film product, not a roll or scrap record.');
                }
                Unit::whereKey($input['unit_id'])->where(function ($query) use ($requisition) {
                    $query->whereNull('company_id')->orWhere('company_id', $requisition->company_id);
                })->firstOrFail();
                if (! empty($input['preferred_supplier_id'])) {
                    Supplier::whereKey($input['preferred_supplier_id'])->where('company_id', $requisition->company_id)->firstOrFail();
                }
                $quantity = (string) $input['requested_quantity'];
                if (bccomp($quantity, '0', 6) !== 1) {
                    throw new BusinessRuleException('Requested quantity must be positive.');
                }
                $line = isset($input['estimated_unit_cost'])
                    ? bcmul($quantity, (string) $input['estimated_unit_cost'], 4)
                    : null;
                $requisitionItem = $requisition->items()->make();
                $requisitionItem->forceFill(array_merge($input, [
                    'estimated_total' => $line,
                    'status' => 'pending',
                    'ordered_quantity' => 0,
                ]))->save();
                $total = $line === null ? $total : bcadd($total, $line, 4);
            }
            $requisition->forceFill(['estimated_total' => $total])->save();
            $this->audit->record('purchase_requisition.created', $requisition);

            return $requisition->load('items');
        });
    }
}
