<?php

namespace App\Policies;

use App\Models\SalesInvoice;
use App\Models\User;

class SalesInvoicePolicy
{
    private function scoped(User $user, SalesInvoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id && $user->canAccessBranch($invoice->branch);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales_invoices.view');
    }

    public function view(User $user, SalesInvoice $invoice): bool
    {
        return $this->scoped($user, $invoice) && $user->hasPermission('sales_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_invoices.create');
    }

    public function directSale(User $user): bool
    {
        return $user->hasPermission('sales_invoices.direct_sale');
    }

    public function submit(User $user, SalesInvoice $invoice): bool
    {
        return $this->scoped($user, $invoice) && $user->hasPermission('sales_invoices.submit');
    }

    public function approve(User $user, SalesInvoice $invoice): bool
    {
        return $invoice->status === 'pending_approval'
            && $this->scoped($user, $invoice)
            && $user->hasPermission('sales_invoices.approve');
    }

    public function issue(User $user, SalesInvoice $invoice): bool
    {
        return $this->scoped($user, $invoice) && $user->hasPermission('sales_invoices.issue');
    }

    public function cancel(User $user, SalesInvoice $invoice): bool
    {
        return $this->scoped($user, $invoice) && $user->hasPermission('sales_invoices.cancel');
    }

    public function void(User $user, SalesInvoice $invoice): bool
    {
        return $this->scoped($user, $invoice) && $user->hasPermission('sales_invoices.void');
    }

    public function print(User $user, SalesInvoice $invoice): bool
    {
        return $this->scoped($user, $invoice) && $user->hasPermission('sales_invoices.print');
    }

    public function share(User $user, SalesInvoice $invoice): bool
    {
        return $this->scoped($user, $invoice) && $user->hasPermission('sales_invoices.share');
    }

    public function viewCost(User $user, SalesInvoice $invoice): bool
    {
        return $this->scoped($user, $invoice) && $user->hasPermission('sales_invoices.view_cost');
    }
}
