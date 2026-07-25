<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Models\Quotation;

class QuotationPrintService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function prepare(Quotation $quotation): Quotation
    {
        if ($quotation->company_id !== $this->tenant->companyId()
            || ! $this->tenant->user()?->canAccessBranch($quotation->branch)) {
            throw new BusinessRuleException('Quotation is outside your scope.', status: 403);
        }

        return $quotation->load(['branch.company', 'customer', 'vehicle.brand', 'vehicle.model', 'currency', 'items']);
    }
}
