@extends('layouts.app')

@section('title', $payment->payment_number)
@section('page-title', $payment->payment_number)
@section('breadcrumb', 'المبيعات / تحصيلات العملاء / تفاصيل الدفعة')

@section('page-actions')
    <a class="sw-button sw-button--outline" href="{{ route('customer-payments.receipt', $payment) }}">طباعة الإيصال</a>
@endsection

@section('content')
    <div class="customer-payment-page">
        <x-card title="بيانات الدفعة" subtitle="تفاصيل التحصيل ومكان استلام النقدية وحالة التخصيص.">
            <dl class="sw-details-grid">
                <div><dt>رقم الدفعة</dt><dd>{{ $payment->payment_number }}</dd></div>
                <div><dt>العميل</dt><dd>{{ $payment->customer->name }}</dd></div>
                <div><dt>طريقة الدفع</dt><dd>{{ $payment->paymentMethod->name }}</dd></div>
                <div><dt>الخزينة</dt><dd>{{ $payment->cashBox ? $payment->cashBox->code.' — '.$payment->cashBox->name : '—' }}</dd></div>
                <div><dt>جلسة الخزينة</dt><dd>{{ $payment->cashBoxSession?->session_number ?? '—' }}</dd></div>
                <div><dt>الفاتورة</dt><dd>{{ $payment->intendedInvoice?->invoice_number ?? $payment->allocations->first()?->invoice?->invoice_number ?? '—' }}</dd></div>
                <div><dt>المبلغ</dt><dd>{{ number_format((float) $payment->amount, 2) }}</dd></div>
                <div><dt>المخصص</dt><dd>{{ number_format((float) $payment->allocated_amount, 2) }}</dd></div>
                <div><dt>غير المخصص</dt><dd>{{ number_format((float) $payment->unallocated_amount, 2) }}</dd></div>
                <div><dt>الحالة</dt><dd><x-status-badge :status="$payment->status" /></dd></div>
                <div><dt>حركة الخزينة</dt><dd>{{ $payment->cashReceipt?->document_number ?? '—' }}</dd></div>
            </dl>
        </x-card>

        @if($payment->status === 'recorded')
            @if($payment->paymentMethod->isCash() && (! $payment->cash_box_id || ! $payment->cash_box_session_id))
                <div class="sw-alert sw-alert--warning">
                    <div>
                        <strong>بيانات الخزينة غير مكتملة</strong>
                        لا يمكن اعتماد هذه الدفعة النقدية القديمة قبل تحديد خزينة وجلسة نشطة من خلال إجراء تصحيحي معتمد.
                    </div>
                </div>
            @else
                @can('approve', $payment)
                    <form method="POST" action="{{ route('customer-payments.approve', $payment) }}">
                        @csrf
                        <x-button type="submit">اعتماد الدفعة</x-button>
                    </form>
                @endcan
            @endif
        @endif

        <x-table-shell title="الفواتير المخصصة" description="الفواتير التي تم تخصيص الدفعة عليها.">
            <thead><tr><th>الفاتورة</th><th>المبلغ</th><th>الحالة</th></tr></thead>
            <tbody>
                @forelse($payment->allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->invoice->invoice_number }}</td>
                        <td>{{ number_format((float) $allocation->amount, 2) }}</td>
                        <td>{{ $allocation->reversed_at ? 'معكوسة' : 'نشطة' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3">لا توجد تخصيصات حتى الآن.</td></tr>
                @endforelse
            </tbody>
        </x-table-shell>

        @if(in_array($payment->status, ['approved', 'partially_allocated'], true) && bccomp((string) $payment->unallocated_amount, '0', 4) === 1)
            <x-card title="تخصيص الدفعة" subtitle="اختر فاتورة العميل وحدد المبلغ المطلوب تخصيصه.">
                <form class="sw-form" method="POST" action="{{ route('customer-payments.allocate', $payment) }}">
                    @csrf
                    <div class="sw-form-grid">
                        <x-form.select name="sales_invoice_id" label="الفاتورة" required>
                            <option value="">اختر الفاتورة</option>
                            @foreach($invoices as $invoice)
                                <option value="{{ $invoice->id }}">
                                    {{ $invoice->invoice_number }} — المتبقي {{ number_format((float) $invoice->balance_due, 2) }}
                                </option>
                            @endforeach
                        </x-form.select>
                        <x-form.input
                            name="amount"
                            type="number"
                            step="0.0001"
                            min="0.0001"
                            :max="$payment->unallocated_amount"
                            label="المبلغ"
                            :value="$payment->unallocated_amount"
                            required
                        />
                    </div>
                    <div class="sw-form-actions"><x-button type="submit">تخصيص الدفعة</x-button></div>
                </form>
            </x-card>
        @endif
    </div>
@endsection
