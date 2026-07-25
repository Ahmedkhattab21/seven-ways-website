<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Models\CustomerNote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private TenantContext $tenant,
        private PhoneNormalizer $phones,
        private DocumentNumberService $numbers,
        private AuditService $audit
    ) {
    }

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $this->assertBranch($data['assigned_branch_id'] ?? $this->tenant->branchId());
            $normalized = $this->phones->normalize($data['phone'] ?? null);
            $this->warnDuplicate($normalized, (bool) ($data['confirm_duplicate'] ?? false));
            unset($data['confirm_duplicate']);
            $customer = new Customer($data);
            $customer->forceFill([
                'company_id' => $this->tenant->companyId(),
                'created_branch_id' => $this->tenant->branchId(),
                'assigned_branch_id' => $data['assigned_branch_id'] ?? $this->tenant->branchId(),
                'customer_code' => $this->numbers->next('customer', $this->tenant->companyId(), $this->tenant->branchId()),
                'normalized_phone' => $normalized,
                'created_by' => $this->tenant->user()?->id,
            ])->save();
            $this->audit->record('customer.created', $customer);

            return $customer;
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $this->assertTenant($customer);
            $this->assertBranch($data['assigned_branch_id'] ?? $customer->assigned_branch_id);
            $normalized = $this->phones->normalize($data['phone'] ?? null);
            $this->warnDuplicate($normalized, (bool) ($data['confirm_duplicate'] ?? false), $customer->id);
            unset($data['confirm_duplicate']);
            $oldBranch = $customer->assigned_branch_id;
            $customer->fill($data)->forceFill([
                'normalized_phone' => $normalized,
                'assigned_branch_id' => $data['assigned_branch_id'] ?? $customer->assigned_branch_id,
                'updated_by' => $this->tenant->user()?->id,
            ])->save();
            $this->audit->record($oldBranch !== $customer->assigned_branch_id ? 'customer.branch_changed' : 'customer.updated', $customer, [
                'from_branch_id' => $oldBranch, 'to_branch_id' => $customer->assigned_branch_id,
            ]);

            return $customer;
        });
    }

    public function disable(Customer $customer): void
    {
        $this->assertTenant($customer);
        $customer->forceFill(['status' => 'inactive', 'updated_by' => $this->tenant->user()?->id])->save();
        $this->audit->record('customer.disabled', $customer);
    }

    public function addContact(Customer $customer, array $data): CustomerContact
    {
        return DB::transaction(function () use ($customer, $data) {
            $this->assertTenant($customer);
            if ($data['is_primary'] ?? false) {
                $customer->contacts()->update(['is_primary' => false]);
            }
            $data['normalized_phone'] = $this->phones->normalize($data['phone'] ?? null);
            $contact = $customer->contacts()->create($data);
            $this->audit->record('customer.contact_added', $customer, ['contact_id' => $contact->id]);

            return $contact;
        });
    }

    public function deleteContact(CustomerContact $contact): void
    {
        $this->assertTenant($contact->customer);
        $customer = $contact->customer;
        $contact->delete();
        $this->audit->record('customer.contact_deleted', $customer, ['contact_id' => $contact->id]);
    }

    public function addAddress(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data) {
            $this->assertTenant($customer);
            if ($data['is_default'] ?? false) {
                $customer->addresses()->where('address_type', $data['address_type'])->update(['is_default' => false]);
            }

            return $customer->addresses()->create($data);
        });
    }

    public function addNote(Customer $customer, array $data): CustomerNote
    {
        $this->assertTenant($customer);
        $note = new CustomerNote($data);
        $note->forceFill([
            'branch_id' => $data['visibility'] === 'branch' ? $this->tenant->branchId() : null,
            'created_by' => $this->tenant->user()?->id,
        ]);
        $customer->notes()->save($note);

        return $note;
    }

    private function warnDuplicate(?string $phone, bool $confirmed, ?int $ignore = null): void
    {
        if (! $phone || $confirmed) {
            return;
        }
        $duplicate = Customer::query()->where('company_id', $this->tenant->companyId())
            ->where('normalized_phone', $phone)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->first();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'phone' => "يوجد عميل محتمل بنفس الهاتف ({$duplicate->customer_code}). فعّل تأكيد التكرار للمتابعة.",
            ]);
        }
    }

    private function assertTenant(Customer $customer): void
    {
        abort_unless((int) $customer->company_id === (int) $this->tenant->companyId(), 403);
    }

    private function assertBranch(?int $branchId): void
    {
        if (! $branchId || ! $this->tenant->accessibleBranches()->contains('id', $branchId)) {
            throw ValidationException::withMessages(['assigned_branch_id' => 'الفرع خارج النطاق المسموح.']);
        }
    }
}
