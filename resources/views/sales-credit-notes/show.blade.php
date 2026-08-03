@extends('layouts.app')

@section('title', $note->credit_note_number)
@section('page-title', $note->credit_note_number)
@section('breadcrumb', 'المبيعات / الإشعارات الدائنة / التفاصيل')
@section('page-description', 'تفاصيل الإشعار الدائن والبنود والقيم المرتبطة بالفاتورة الأصلية.')

@section('page-actions')
    @can('print', $note)
        <a class="sw-button sw-button--outline" href="{{ route('sales-credit-notes.print', $note) }}" target="_blank">
            <x-icon name="clipboard" :size="18" />
            طباعة
        </a>
    @endcan
@endsection

@section('content')
    @php
        $statusLabels = [
            'draft' => 'مسودة',
            'approved' => 'معتمدة',
            'issued' => 'صادرة',
            'partially_applied' => 'مطبقة جزئيًا',
            'applied' => 'مطبقة بالكامل',
            'refunded' => 'مستردة',
            'cancelled' => 'ملغاة',
        ];
        $reasonLabels = [
            'product_return' => 'مرتجع منتج',
            'service_refund' => 'استرداد قيمة',
            'pricing_error' => 'تصحيح خطأ تسعير',
            'customer_compensation' => 'تعويض عميل',
            'warranty_resolution' => 'تسوية ضمان',
            'cancellation' => 'إلغاء',
            'duplicate_invoice' => 'فاتورة مكررة',
            'other' => 'سبب آخر',
        ];
        $currencyCode = $note->currency?->code;
    @endphp

    <div class="sales-credit-note-show-page">
        <section class="sw-card sales-credit-note-show-hero">
            <header class="sw-card__header">
                <div>
                    <p class="sales-credit-note-show-eyebrow">إشعار دائن للمبيعات</p>
                    <h2 class="sw-card__title" dir="ltr">{{ $note->credit_note_number }}</h2>
                    <p class="sw-card__subtitle">تم إنشاؤه مقابل الفاتورة {{ $note->invoice->invoice_number }}</p>
                </div>
                <x-status-badge :status="$note->status" :label="$statusLabels[$note->status] ?? $note->status" />
            </header>
            <div class="sw-card__body sales-credit-note-show-meta">
                <div><span>الفاتورة الأصلية</span><a href="{{ route('sales-invoices.show', $note->invoice) }}" dir="ltr">{{ $note->invoice->invoice_number }}</a></div>
                <div><span>العميل</span><strong>{{ $note->customer->name }}</strong></div>
                <div><span>الفرع</span><strong>{{ $note->invoice->branch?->name }}</strong></div>
                <div><span>تاريخ الإشعار</span><strong dir="ltr">{{ $note->credit_note_date?->format('Y-m-d') }}</strong></div>
            </div>
        </section>

        <section class="sw-card sales-credit-note-show-reason">
            <header class="sw-card__header">
                <div>
                    <h2 class="sw-card__title">سبب الإشعار</h2>
                    <p class="sw-card__subtitle">{{ $reasonLabels[$note->reason_code] ?? $note->reason_code }}</p>
                </div>
            </header>
            <div class="sw-card__body"><p>{{ $note->reason }}</p></div>
        </section>

        <section class="sw-card sales-credit-note-show-items">
            <header class="sw-card__header">
                <div>
                    <h2 class="sw-card__title">بنود الإشعار الدائن</h2>
                    <p class="sw-card__subtitle">القيم محفوظة من بنود الفاتورة الأصلية وقت إنشاء الإشعار.</p>
                </div>
                <span class="sales-credit-note-show-count">{{ $note->items->count() }} بند</span>
            </header>
            <div class="sw-card__body">
                <div class="sw-table-scroll">
                    <table class="sw-table sales-credit-note-show-table">
                        <thead>
                            <tr>
                                <th>الوصف</th>
                                <th>الكمية</th>
                                <th>نوع المعالجة</th>
                                <th>مخزن الاستلام</th>
                                <th>حركة المخزون</th>
                                <th>سعر الوحدة</th>
                                <th>الصافي</th>
                                <th>الضريبة</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($note->items as $item)
                                @php($productReturn = $note->productReturns->firstWhere('sales_invoice_item_id', $item->sales_invoice_item_id))
                                <tr>
                                    <td class="sales-credit-note-show-description">{{ $item->description }}</td>
                                    <td dir="ltr">{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td>{{ $productReturn ? 'مرتجع مخزني' : 'تخفيض مالي فقط' }}</td>
                                    <td>{{ $productReturn?->warehouse?->name ?? '—' }}</td>
                                    <td>
                                        @if($productReturn?->stockMovement)
                                            <span dir="ltr">{{ $productReturn->stockMovement->movement_number }}</span>
                                        @elseif($productReturn)
                                            <span class="sales-credit-note-financial-only">لم تنفذ بعد — تُنفذ عند الإصدار</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td dir="ltr">{{ number_format((float) $item->unit_price, 2) }}</td>
                                    <td dir="ltr">{{ number_format((float) $item->net_amount, 2) }}</td>
                                    <td dir="ltr">{{ number_format((float) $item->tax_amount, 2) }}</td>
                                    <td class="sales-credit-note-show-total" dir="ltr">{{ number_format((float) $item->total, 2) }} {{ $currencyCode }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="sw-card sales-credit-note-show-financials">
            <header class="sw-card__header">
                <div>
                    <h2 class="sw-card__title">الملخص المالي</h2>
                    <p class="sw-card__subtitle">القيمة الحالية للإشعار وما تم تطبيقه أو رده.</p>
                </div>
            </header>
            <div class="sw-card__body sales-credit-note-show-financial-grid">
                <div><span>الصافي</span><strong dir="ltr">{{ number_format((float) $note->subtotal, 2) }} {{ $currencyCode }}</strong></div>
                <div><span>الضريبة</span><strong dir="ltr">{{ number_format((float) $note->tax_amount, 2) }} {{ $currencyCode }}</strong></div>
                <div class="is-total"><span>الإجمالي</span><strong dir="ltr">{{ number_format((float) $note->total, 2) }} {{ $currencyCode }}</strong></div>
                <div><span>المطبق</span><strong dir="ltr">{{ number_format((float) $note->applied_amount, 2) }} {{ $currencyCode }}</strong></div>
                <div><span>المسترد</span><strong dir="ltr">{{ number_format((float) $note->refunded_amount, 2) }} {{ $currencyCode }}</strong></div>
                <div><span>المتبقي</span><strong dir="ltr">{{ number_format((float) $note->remaining_amount, 2) }} {{ $currencyCode }}</strong></div>
            </div>
        </section>

        @if($note->status === 'draft' || $note->status === 'approved')
            <div class="sales-credit-note-show-actions">
                <div>
                    <strong>الإجراء التالي</strong>
                    <span>{{ $note->status === 'draft' ? 'اعتماد الإشعار قبل إصداره.' : 'إصدار الإشعار وتحديث رصيد الفاتورة.' }}</span>
                </div>
                @if($note->status === 'draft')
                    @can('approve', $note)
                        <form method="POST" action="{{ route('sales-credit-notes.action', [$note, 'approve']) }}">
                            @csrf
                            <button class="sw-button sw-button--primary" type="submit">اعتماد الإشعار</button>
                        </form>
                    @endcan
                @elseif($note->status === 'approved')
                    @can('issue', $note)
                        <form method="POST" action="{{ route('sales-credit-notes.action', [$note, 'issue']) }}">
                            @csrf
                            <button class="sw-button sw-button--primary" type="submit">إصدار الإشعار</button>
                        </form>
                    @endcan
                @endif
            </div>
        @endif
    </div>
@endsection
