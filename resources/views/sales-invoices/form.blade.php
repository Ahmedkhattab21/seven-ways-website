@extends('layouts.app')
@section('title', 'إضافة فاتورة مبيعات')
@section('page-title', 'إضافة فاتورة مبيعات')
@section('content')
<form class="sales-invoice-form" method="POST" action="{{ route('sales-invoices.store') }}" data-sales-invoice-form>
    @csrf
    <section class="sw-card">
        <header class="sw-card__header">
            <div>
                <h2 class="sw-card__title">بيانات الفاتورة</h2>
                <p class="sw-card__subtitle">أنشئ فاتورة مباشرة بدون حجز أو أمر عمل.</p>
            </div>
        </header>
        <div class="sw-card__body sw-form-grid sales-invoice-header-grid">
            <label class="sw-field">
                <span class="sw-field__label">العميل</span>
                <select class="sw-input" name="customer_id" data-invoice-customer required>
                    <option value="">اختر العميل</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->code }} — {{ $customer->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="sw-field">
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

    <section class="sw-card">
        <header class="sw-card__header">
            <div>
                <h2 class="sw-card__title">عناصر الفاتورة</h2>
                <p class="sw-card__subtitle">منتج أو خدمة أو باقة أو عنصر مخصص.</p>
            </div>
            <button class="sw-button sw-button--outline" type="button" data-add-invoice-item>إضافة عنصر</button>
        </header>
        <div class="sw-card__body sales-invoice-items" data-invoice-items></div>
    </section>

    <section class="sw-card">
        <div class="sw-card__body sw-form-grid">
            <label class="sw-field">
                <span class="sw-field__label">ملاحظات العميل</span>
                <textarea class="sw-input sw-textarea" name="customer_notes">{{ old('customer_notes') }}</textarea>
            </label>
            <label class="sw-field">
                <span class="sw-field__label">شروط الفاتورة</span>
                <textarea class="sw-input sw-textarea" name="terms_snapshot">{{ old('terms_snapshot') }}</textarea>
            </label>
        </div>
    </section>

    <div class="sales-invoice-actions">
        <button class="sw-button sw-button--primary" type="submit">حفظ كمسودة</button>
    </div>

    <template data-invoice-item-template>
        <fieldset class="sales-invoice-item" data-invoice-item>
            <legend>عنصر الفاتورة</legend>
            <div class="sales-invoice-item-grid">
                <label class="sw-field">
                    <span class="sw-field__label">نوع العنصر</span>
                    <select class="sw-input" name="items[__INDEX__][item_type]" data-invoice-item-type>
                        <option value="product">منتج</option>
                        <option value="service">خدمة</option>
                        <option value="package">باقة خدمات</option>
                        <option value="custom">عنصر مخصص</option>
                    </select>
                </label>
                <label class="sw-field" data-invoice-reference="product">
                    <span class="sw-field__label">المنتج</span>
                    <select class="sw-input" name="items[__INDEX__][product_id]">
                        <option value="">اختر المنتج</option>
                        @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach
                    </select>
                </label>
                <label class="sw-field" data-invoice-reference="service" hidden>
                    <span class="sw-field__label">الخدمة</span>
                    <select class="sw-input" name="items[__INDEX__][service_id]" disabled>
                        <option value="">اختر الخدمة</option>
                        @foreach($services as $service)<option value="{{ $service->id }}">{{ $service->code }} — {{ $service->name }}</option>@endforeach
                    </select>
                </label>
                <label class="sw-field" data-invoice-reference="package" hidden>
                    <span class="sw-field__label">الباقة</span>
                    <select class="sw-input" name="items[__INDEX__][service_package_id]" disabled>
                        <option value="">اختر الباقة</option>
                        @foreach($packages as $package)<option value="{{ $package->id }}">{{ $package->code }} — {{ $package->name }}</option>@endforeach
                    </select>
                </label>
                <label class="sw-field" data-invoice-reference="warehouse">
                    <span class="sw-field__label">المخزن</span>
                    <select class="sw-input" name="items[__INDEX__][warehouse_id]">
                        <option value="">اختر المخزن</option>
                        @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
                    </select>
                </label>
                <label class="sw-field sales-invoice-item__description">
                    <span class="sw-field__label">الوصف</span>
                    <input class="sw-input" name="items[__INDEX__][description]">
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">الكمية</span>
                    <input class="sw-input" type="number" min="0.000001" step="0.000001" name="items[__INDEX__][quantity]" value="1" required>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">السعر اليدوي (اختياري)</span>
                    <input class="sw-input" type="number" min="0" step="0.0001" name="items[__INDEX__][unit_price]">
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">نوع الخصم</span>
                    <select class="sw-input" name="items[__INDEX__][discount_type]">
                        <option value="">بدون خصم</option>
                        <option value="fixed">قيمة ثابتة</option>
                        <option value="percentage">نسبة مئوية</option>
                    </select>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">قيمة الخصم</span>
                    <input class="sw-input" type="number" min="0" step="0.0001" name="items[__INDEX__][discount_value]" value="0">
                </label>
            </div>
            <button class="sw-button sw-button--outline" type="button" data-remove-invoice-item>حذف العنصر</button>
        </fieldset>
    </template>
</form>
@endsection
