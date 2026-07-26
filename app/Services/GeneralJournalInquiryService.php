<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\JournalEntry;

class GeneralJournalInquiryService
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function report(array $filters)
    {
        $canViewOtherStatuses = $this->tenant->user()->hasPermission('accounting.journals.view_sensitive');
        $status = $canViewOtherStatuses ? ($filters['status'] ?? 'posted') : 'posted';
        $branches = $this->tenant->accessibleBranches()->pluck('id');

        return JournalEntry::query()->where('company_id', $this->tenant->companyId())
            ->with(['lines' => fn ($query) => $query->with('account')])
            ->where('status', $status)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhereIn('branch_id', $branches))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('posting_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('posting_date', '<=', $date))
            ->when($filters['entry_type'] ?? null, fn ($q, $type) => $q->where('entry_type', $type))
            ->when($filters['source_type'] ?? null, fn ($q, $type) => $q->where('source_type', $type))
            ->when($filters['journal_number'] ?? null, fn ($q, $number) => $q->where('journal_number', 'like', '%'.$number.'%'))
            ->when($filters['branch_id'] ?? null, fn ($q, $branch) => $q->where('branch_id', $branch))
            ->when($filters['account_id'] ?? null, fn ($q, $account) => $q->whereHas('lines', fn ($line) => $line->where('account_id', $account)))
            ->latest('posting_date')->latest('id')->paginate(30)->withQueryString();
    }
}
