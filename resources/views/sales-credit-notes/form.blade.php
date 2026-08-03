@extends('layouts.app')

@section('title', 'إنشاء إشعار دائن')
@section('page-title', 'إنشاء إشعار دائن')
@section('breadcrumb', 'المبيعات / فواتير المبيعات / إشعار دائن')
@section('page-description', 'اختر البنود والكميات المطلوب رد قيمتها من الفاتورة الأصلية.')

@section('content')
    @php
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
    @endphp

    <div class="sales-credit-note-page">
        <section class="sw-card sales-credit-note-source">
            <header class="sw-card__header">
                <div>
                    <h2 class="sw-card__title">الفاتورة الأصلية</h2>
                    <p class="sw-card__subtitle">راجع بيانات الفاتورة قبل تحديد البنود المرتجعة.</p>
                </div>
                <a class="sw-button sw-button--outline" href="{{ route('sales-invoices.show', $invoice) }}">عرض الفاتورة</a>
            </header>
            <div class="sw-card__body sales-credit-note-source-grid">
                <div><span>رقم الفاتورة</span><strong dir="ltr">{{ $invoice->invoice_number }}</strong></div>
                <div><span>العميل</span><strong>{{ $invoice->customer_name_snapshot }}</strong></div>
                <div><span>الفرع</span><strong>{{ $invoice->branch?->name }}</strong></div>
                <div><span>تاريخ الفاتورة</span><strong dir="ltr">{{ $invoice->invoice_date?->format('Y-m-d') }}</strong></div>
                <div><span>إجمالي الفاتورة</span><strong dir="ltr">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency?->code }}</strong></div>
                <div><span>القيمة المتاحة للإشعار</span><strong dir="ltr">{{ number_format((float) $invoice->total - (float) $invoice->credited_amount, 2) }} {{ $invoice->currency?->code }}</strong></div>
            </div>
        </section>

        <form class="sales-credit-note-form" method="POST" action="{{ route('sales-credit-notes.store') }}" data-credit-note-form>
            @csrf
            <input type="hidden" name="sales_invoice_id" value="{{ $invoice->id }}">

            @if($errors->any())
                <div class="sw-alert sw-alert--danger sales-credit-note-errors">
                    <strong>تعذر حفظ الإشعار الدائن. راجع البيانات المطلوبة.</strong>
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="sw-card">
                <header class="sw-card__header">
                    <div>
                        <h2 class="sw-card__title">بيانات الإشعار</h2>
                        <p class="sw-card__subtitle">حدد التاريخ وسبب إصدار الإشعار الدائن.</p>
                    </div>
                </header>
                <div class="sw-card__body sales-credit-note-details-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">تاريخ الإشعار</span>
                        <input class="sw-input" type="date" name="credit_note_date" value="{{ old('credit_note_date', today()->toDateString()) }}" required>
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">نوع السبب</span>
                        <select class="sw-input" name="reason_code" required>
                            <option value="">اختر سبب الإشعار</option>
                            @foreach($reasonLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('reason_code') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="sw-field sales-credit-note-reason">
                        <span class="sw-field__label">تفاصيل السبب</span>
                        <textarea class="sw-input sw-textarea" name="reason" required placeholder="اكتب سبب إصدار الإشعار الدائن بوضوح">{{ old('reason') }}</textarea>
                    </label>
                </div>
            </section>

            <section class="sw-card sales-credit-note-items-card">
                <header class="sw-card__header">
                    <div>
                        <h2 class="sw-card__title">بنود الإشعار الدائن</h2>
                        <p class="sw-card__subtitle">حدد البنود والكميات، واختر إرجاع المخزون فقط عند استلام المنتج فعليًا.</p>
                    </div>
                    <span class="sales-credit-note-selection-count" data-credit-selection-count>لم يتم اختيار بنود</span>
                </header>
                <div class="sw-card__body">
                    <div class="sw-table-scroll">
                        <table class="sw-table sales-credit-note-items-table">
                            <thead>
                                <tr>
                                    <th>اختيار</th>
                                    <th>الوصف</th>
                                    <th>الكمية الأصلية</th>
                                    <th>سبق إشعارها</th>
                                    <th>سبق إرجاعها</th>
                                    <th>الحد المتبقي</th>
                                    <th>الكمية المرتجعة</th>
                                    <th>المعالجة المخزنية</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->items as $index => $item)
                                    @php
                                        $selected = (string) old("items.{$index}.sales_invoice_item_id") === (string) $item->id;
                                        $creditedQuantity = (string) ($creditedQuantities[$item->id] ?? '0');
                                        $returnedQuantity = (string) $item->returned_quantity;
                                        $remainingQuantity = max(0, (float) $item->quantity - (float) $creditedQuantity);
                                        $returnToStock = old("items.{$index}.return_to_stock") === '1';
                                    @endphp
                                    <tr data-credit-item-row class="{{ $selected ? 'is-selected' : '' }}">
                                        <td>
                                            <label class="sales-credit-note-check">
                                                <input
                                                    type="checkbox"
                                                    name="items[{{ $index }}][sales_invoice_item_id]"
                                                    value="{{ $item->id }}"
                                                    data-credit-item-check
                                                    @checked($selected)
                                                >
                                                <span>اختيار</span>
                                            </label>
                                        </td>
                                        <td class="sales-credit-note-item-description">{{ $item->description }}</td>
                                        <td dir="ltr">{{ number_format((float) $item->quantity, 2) }}</td>
                                        <td dir="ltr">{{ number_format((float) $creditedQuantity, 2) }}</td>
                                        <td dir="ltr">{{ number_format((float) $returnedQuantity, 2) }}</td>
                                        <td dir="ltr">{{ number_format($remainingQuantity, 2) }}</td>
                                        <td>
                                            <input
                                                class="sw-input sales-credit-note-quantity"
                                                type="number"
                                                min="0.000001"
                                                max="{{ $remainingQuantity }}"
                                                step="0.000001"
                                                name="items[{{ $index }}][quantity]"
                                                value="{{ old("items.{$index}.quantity", $remainingQuantity) }}"
                                                data-credit-item-quantity
                                                @disabled(! $selected)
                                            >
                                        </td>
                                        <td>
                                            @if($item->item_type === 'product')
                                                <div class="sales-credit-note-stock-return" data-credit-stock-return>
                                                    <label class="sales-credit-note-check">
                                                        <input
                                                            type="checkbox"
                                                            name="items[{{ $index }}][return_to_stock]"
                                                            value="1"
                                                            data-credit-stock-check
                                                            @checked($returnToStock)
                                                            @disabled(! $selected)
                                                        >
                                                        <span>إرجاع الكمية إلى المخزون</span>
                                                    </label>
                                                    <select
                                                        class="sw-input"
                                                        name="items[{{ $index }}][warehouse_id]"
                                                        data-credit-warehouse
                                                        @disabled(! $selected || ! $returnToStock)
                                                    >
                                                        <option value="">اختر مخزن الاستلام</option>
                                                        @foreach($warehouses as $warehouse)
                                                            <option value="{{ $warehouse->id }}" @selected((string) old("items.{$index}.warehouse_id") === (string) $warehouse->id)>
                                                                {{ $warehouse->code }} — {{ $warehouse->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @else
                                                <span class="sales-credit-note-financial-only">تخفيض مالي فقط</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <div class="sales-credit-note-actions">
                <a class="sw-button sw-button--outline" href="{{ route('sales-invoices.show', $invoice) }}">إلغاء</a>
                <button class="sw-button sw-button--primary" type="submit">حفظ الإشعار كمسودة</button>
            </div>
        </form>
    </div>
@endsection
