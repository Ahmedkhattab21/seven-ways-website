<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadConversionService
{
    public function __construct(
        private TenantContext $tenant,
        private CustomerService $customers,
        private VehicleService $vehicles,
        private AuditService $audit
    ) {
    }

    public function convert(Lead $lead, array $data): Customer
    {
        return DB::transaction(function () use ($lead, $data) {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);
            abort_unless((int) $lead->company_id === (int) $this->tenant->companyId(), 403);
            if ($lead->converted_customer_id) {
                throw ValidationException::withMessages(['lead' => 'تم تحويل هذا العميل المحتمل سابقًا.']);
            }
            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::query()->where('company_id', $this->tenant->companyId())->findOrFail($data['customer_id']);
            } else {
                $customer = $this->customers->create([
                    'customer_type' => 'individual', 'name' => $lead->name, 'phone' => $lead->phone,
                    'email' => $lead->email, 'source_id' => $lead->source_id, 'preferred_language' => 'ar',
                    'credit_limit' => 0, 'payment_term_days' => 0, 'status' => 'active',
                    'assigned_branch_id' => $lead->branch_id,
                    'confirm_duplicate' => (bool) ($data['confirm_duplicate'] ?? false),
                ]);
            }
            if (! empty($data['create_vehicle']) && $lead->vehicle_brand_id && $lead->vehicle_model_id) {
                $this->vehicles->save(new \App\Models\Vehicle(), [
                    'customer_id' => $customer->id, 'vehicle_brand_id' => $lead->vehicle_brand_id,
                    'vehicle_model_id' => $lead->vehicle_model_id, 'manufacturing_year' => $lead->vehicle_year,
                    'status' => 'active',
                ]);
            }
            $lead->forceFill([
                'status' => 'won', 'converted_customer_id' => $customer->id,
                'converted_at' => now(), 'updated_by' => $this->tenant->user()?->id,
            ])->save();
            $this->audit->record('lead.converted', $lead, ['customer_id' => $customer->id]);

            return $customer;
        });
    }
}
