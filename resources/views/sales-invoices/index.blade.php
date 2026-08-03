@extends('layouts.app')

@section('title', 'فواتير المبيعات')
@section('page-title', 'فواتير المبيعات')
@section('breadcrumb', 'المبيعات / فواتير المبيعات')
@section('page-description', 'متابعة حالة الفواتير والمبالغ المحصلة والأرصدة المستحقة.')

@section('page-actions')
    @if(auth()->user()->hasPermission('sales_invoices.direct_sale'))
        <a class="sw-button sw-button--primary" href="{{ route('sales-invoices.create') }}">
            <x-icon name="plus" :size="18" />
            إضافة فاتورة مبيعات
        </a>
    @endif
@endsection

@section('content')
    @php
        $statusLabels = [
            'draft' => 'مسودة',
            'pending_approval' => 'بانتظار الاعتماد',
            'approved' => 'معتمدة',
            'issued' => 'صادرة',
            'partially_paid' => 'مدفوعة جزئيًا',
            'paid' => 'مدفوعة',
            'overdue' => 'متأخرة',
            'credited' => 'عليها إشعار دائن',
            'cancelled' => 'ملغاة',
            'void' => 'مفرغة',
        ];
    @endphp

    <div class="sales-invoices-index-layout">
        <x-table-shell
            title="سجل فواتير المبيعات"
            description="تفاصيل الفواتير المتاحة للفروع المصرح لك بالوصول إليها."
            class="sales-invoices-table-card"
        >
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>العميل</th>
                    <th>الفرع</th>
                    <th>تاريخ الفاتورة</th>
                    <th>الإجمالي</th>
                    <th>المدفوع</th>
                    <th>المتبقي</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                    <tr>
                        <td>
                            <a class="sales-invoice-number" href="{{ route('sales-invoices.show', $invoice) }}">
                                {{ $invoice->invoice_number }}
                            </a>
                        </td>
                        <td class="sales-invoice-customer">{{ $invoice->customer_name_snapshot }}</td>
                        <td>{{ $invoice->branch->name }}</td>
                        <td><span dir="ltr">{{ $invoice->invoice_date->format('Y-m-d') }}</span></td>
                        <td><span class="sales-invoice-amount" dir="ltr">{{ number_format((float) $invoice->total, 2) }}</span></td>
                        <td><span class="sales-invoice-amount" dir="ltr">{{ number_format((float) $invoice->paid_amount, 2) }}</span></td>
                        <td><span class="sales-invoice-amount" dir="ltr">{{ number_format((float) $invoice->balance_due, 2) }}</span></td>
                        <td>
                            <x-status-badge
                                :status="$invoice->status"
                                :label="$statusLabels[$invoice->status] ?? $invoice->status"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-empty-state
                                title="لا توجد فواتير مبيعات"
                                message="ستظهر الفواتير هنا بعد إنشاء أول فاتورة مبيعات."
                                icon="receipt"
                            >
                                @if(auth()->user()->hasPermission('sales_invoices.direct_sale'))
                                    <a class="sw-button sw-button--primary" href="{{ route('sales-invoices.create') }}">
                                        إضافة فاتورة مبيعات
                                    </a>
                                @endif
                            </x-empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if($invoices->hasPages())
                <x-slot:footer>{{ $invoices->links() }}</x-slot:footer>
            @endif
        </x-table-shell>
    </div>
@endsection
