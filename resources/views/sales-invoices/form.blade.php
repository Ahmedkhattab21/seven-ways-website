@extends('layouts.app')

@section('title', 'إضافة فاتورة مبيعات')
@section('page-title', 'إضافة فاتورة مبيعات')
@section('breadcrumb', 'المبيعات / فواتير المبيعات / إضافة فاتورة')
@section('page-description', 'أنشئ فاتورة منتجات مباشرة؛ الأسعار والضريبة تُحتسب من النظام عند الحفظ.')

@section('content')
    @php
        $itemRows = old('items', [['quantity' => 1, 'discount_value' => 0]]);
    @endphp

    <form class="sales-invoice-form" method="POST" action="{{ route('sales-invoices.store') }}" data-sales-invoice-form>
        @csrf

        @if($errors->any())
            <div class="sw-alert sw-alert--danger sales-invoice-errors">
                <strong>تعذر حفظ الفاتورة. راجع البيانات المطلوبة.</strong>
                <ul>
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <section class="sw-card sales-invoice-section">
            <header class="sw-card__header">
                <div>
                    <h2 class="sw-card__title">بيانات الفاتورة</h2>
                    <p class="sw-card__subtitle">حدد العميل والتواريخ والخصم العام قبل إضافة المنتجات.</p>
                </div>
            </header>
            <div class="sw-card__body sw-form-grid sales-invoice-header-grid">
                <label class="sw-field sales-invoice-field--wide">
                    <span class="sw-field__label">العميل</span>
                    <select class="sw-input" name="customer_id" data-invoice-customer required>
                        <option value="">اختر العميل</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->code }} — {{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="sw-field sales-invoice-field--wide">
                    <span class="sw-field__label">السيارة (اختياري)</span>
                    <select class="sw-input" name="vehicle_id" data-invoice-vehicle>
                        <option value="">بدون سيارة</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" data-customer-id="{{ $vehicle->customer_id }}" @selected(old('vehicle_id') == $vehicle->id)>
                                {{ $vehicle->plate_number ?: $vehicle->vin }} — {{ $vehicle->customer?->name }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">تاريخ الفاتورة</span>
                    <input class="sw-input" type="date" name="invoice_date" value="{{ old('invoice_date', today()->toDateString()) }}" required>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">تاريخ الاستحقاق</span>
                    <input class="sw-input" type="date" name="due_date" value="{{ old('due_date') }}">
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">نوع الخصم العام</span>
                    <select class="sw-input" name="discount_type">
                        <option value="">بدون خصم</option>
                        <option value="fixed" @selected(old('discount_type') === 'fixed')>قيمة ثابتة</option>
                        <option value="percentage" @selected(old('discount_type') === 'percentage')>نسبة مئوية</option>
                    </select>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">قيمة الخصم العام</span>
                    <input class="sw-input" type="number" min="0" step="0.0001" name="discount_value" value="{{ old('discount_value', 0) }}">
                </label>
            </div>
        </section>

        <section class="sw-card sales-invoice-section sales-invoice-products-card">
            <header class="sw-card__header sales-invoice-products-header">
                <div>
                    <h2 class="sw-card__title">منتجات الفاتورة</h2>
                    <p class="sw-card__subtitle">تظهر منتجات الفرع المتاحة للبيع فقط، ويُجلب السعر والعرض الترويجي من النظام تلقائيًا.</p>
                </div>
                <button class="sw-button sw-button--outline" type="button" data-add-invoice-item>
                    <x-icon name="plus" :size="18" />
                    إضافة منتج
                </button>
            </header>
            <div class="sw-card__body sales-invoice-items" data-invoice-items>
                @foreach($itemRows as $index => $row)
                    @include('sales-invoices._product-row', ['index' => $index, 'row' => $row])
                @endforeach
            </div>
        </section>

        <section class="sw-card sales-invoice-section">
            <header class="sw-card__header">
                <div>
                    <h2 class="sw-card__title">الملاحظات والشروط</h2>
                    <p class="sw-card__subtitle">حقول اختيارية تظهر أو تُحفظ مع الفاتورة حسب نوعها.</p>
                </div>
            </header>
            <div class="sw-card__body sw-form-grid sales-invoice-notes-grid">
                <label class="sw-field">
                    <span class="sw-field__label">ملاحظات العميل</span>
                    <textarea class="sw-input sw-textarea" name="customer_notes">{{ old('customer_notes') }}</textarea>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">شروط الفاتورة</span>
                    <textarea class="sw-input sw-textarea" name="terms_snapshot">{{ old('terms_snapshot') }}</textarea>
                </label>
                <label class="sw-field sales-invoice-field--full">
                    <span class="sw-field__label">ملاحظات داخلية</span>
                    <textarea class="sw-input sw-textarea" name="internal_notes">{{ old('internal_notes') }}</textarea>
                </label>
            </div>
        </section>

        <div class="sales-invoice-actions">
            <p>لن يعتمد النظام أي سعر مرسل من المتصفح؛ التسعير النهائي يتم عند الحفظ.</p>
            <button class="sw-button sw-button--primary" type="submit">حفظ كمسودة</button>
        </div>

        <template data-invoice-item-template>
            @include('sales-invoices._product-row', ['index' => '__INDEX__', 'row' => ['quantity' => 1, 'discount_value' => 0]])
        </template>
    </form>
@endsection
