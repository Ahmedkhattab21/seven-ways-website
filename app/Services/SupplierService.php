<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\SupplierCreated;
use App\Models\Supplier;
use App\Models\SupplierAddress;
use App\Models\SupplierContact;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $this->assertSupplierType($data);
            $supplier = new Supplier($data);
            $supplier->forceFill([
                'company_id' => $this->tenant->companyId(),
                'supplier_code' => $this->numbers->next(
                    'supplier',
                    $this->tenant->companyId(),
                    $this->tenant->branchId()
                ),
                'created_by' => $this->tenant->user()->id,
                'status' => 'active',
            ])->save();
            $this->audit->record('supplier.created', $supplier);
            DB::afterCommit(fn () => event(new SupplierCreated($supplier->id)));

            return $supplier;
        });
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        abort_unless($supplier->company_id === $this->tenant->companyId(), 403);
        $this->assertSupplierType($data);
        $supplier->fill($data)->forceFill(['updated_by' => $this->tenant->user()->id])->save();
        $this->audit->record('supplier.updated', $supplier);

        return $supplier;
    }

    public function setStatus(Supplier $supplier, string $status): Supplier
    {
        abort_unless($supplier->company_id === $this->tenant->companyId(), 403);
        if (! in_array($status, ['active', 'inactive', 'suspended', 'blocked'], true)) {
            throw new BusinessRuleException('Invalid supplier status.');
        }
        $supplier->forceFill(['status' => $status, 'updated_by' => $this->tenant->user()->id])->save();
        $this->audit->record('supplier.status_changed', $supplier, ['status' => $status]);

        return $supplier;
    }

    public function contact(Supplier $supplier, array $data): SupplierContact
    {
        return DB::transaction(function () use ($supplier, $data) {
            abort_unless($supplier->company_id === $this->tenant->companyId(), 403);
            if ($data['is_primary'] ?? false) {
                $supplier->contacts()->where('is_primary', true)->lockForUpdate()->update(['is_primary' => false]);
            }

            return $supplier->contacts()->create($data);
        });
    }

    public function address(Supplier $supplier, array $data): SupplierAddress
    {
        return DB::transaction(function () use ($supplier, $data) {
            abort_unless($supplier->company_id === $this->tenant->companyId(), 403);
            if ($data['is_primary'] ?? false) {
                $supplier->addresses()->where('address_type', $data['address_type'])
                    ->where('is_primary', true)->lockForUpdate()->update(['is_primary' => false]);
            }

            return $supplier->addresses()->create($data);
        });
    }

    private function assertSupplierType(array $data): void
    {
        if (isset($data['supplier_type'])
            && ! in_array($data['supplier_type'], config('purchasing.supplier_types'), true)) {
            throw new BusinessRuleException('Invalid supplier type.');
        }
    }
}
