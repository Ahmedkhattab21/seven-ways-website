<?php

namespace App\Http\Controllers;

use App\Models\InvoiceShare;
use App\Models\SalesInvoice;
use App\Services\AuditService;
use App\Services\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class InvoiceShareController extends Controller
{
    public function store(
        SalesInvoice $salesInvoice,
        PhoneNormalizer $phones,
        AuditService $audit
    ): RedirectResponse {
        $this->authorize('share', $salesInvoice);
        $number = $phones->normalize($salesInvoice->customer_phone_snapshot, $salesInvoice->company->country_code);
        if (! $number || ! preg_match('/^\d{8,15}$/', $number)) {
            return back()->withErrors(['phone' => 'رقم هاتف العميل غير صالح للمشاركة عبر واتساب.']);
        }

        $share = new InvoiceShare([
            'channel' => 'whatsapp',
            'destination' => $number,
            'status' => 'generated',
            'generated_at' => now(),
            'expires_at' => now()->addDays(3),
        ]);
        $share->forceFill([
            'company_id' => $salesInvoice->company_id,
            'branch_id' => $salesInvoice->branch_id,
            'sales_invoice_id' => $salesInvoice->id,
            'generated_by' => auth()->id(),
        ])->save();
        $url = URL::temporarySignedRoute('public.sales-invoices.show', $share->expires_at, [
            'invoiceShare' => $share,
        ]);
        $audit->record('sales_invoice.share_generated', $salesInvoice, [
            'invoice_share_id' => $share->id,
            'channel' => 'whatsapp',
        ]);
        $message = rawurlencode("فاتورة Seven Ways رقم {$salesInvoice->invoice_number}\n{$url}");

        return redirect()->away("https://wa.me/{$number}?text={$message}");
    }

    public function show(InvoiceShare $invoiceShare): View
    {
        abort_if($invoiceShare->expires_at->isPast(), 410);
        $invoiceShare->load('invoice.company', 'invoice.branch', 'invoice.customer', 'invoice.currency', 'invoice.items');
        if (! $invoiceShare->opened_at) {
            $invoiceShare->forceFill(['opened_at' => now(), 'status' => 'opened'])->save();
        }

        return view('sales-invoices.print', [
            'invoice' => $invoiceShare->invoice,
            'publicShare' => true,
        ]);
    }
}
