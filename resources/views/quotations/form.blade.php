@extends('layouts.app')
@section('title',$quotation->exists?'تعديل عرض السعر':'إضافة عرض سعر')
@section('content')
@php
    $selectedCustomerId = (int) old('customer_id', $quotation->customer_id);
    $selectedVehicleId = (int) old('vehicle_id', $quotation->vehicle_id);
    $selectedCurrencyId = (int) old(
        'currency_id',
        $quotation->exists ? $quotation->currency_id : $companyCurrencyId
    );
    $canViewCost = auth()->user()->hasPermission('quotations.view_cost');
    $legacyRows = $quotation->exists
        ? $quotation->items->where('item_type', '!=', 'product')
        : collect();
    $rows = old('items', $quotation->exists
        ? $quotation->items->where('item_type', 'product')->values()->toArray()
        : [['quantity' => 1]]);
    if ($rows === []) $rows = [['quantity' => 1]];
@endphp
<div class="configuration-page quotation-form-layout">
    <div class="sw-page-header quotations-index-header">
        <div>
            <h1>{{ $quotation->exists ? 'تعديل مسودة عرض السعر' : 'إضافة عرض سعر' }}</h1>
            <p>الأسعار والخصومات والضريبة تُحسب من الخادم قبل الحفظ.</p>
        </div>
    </div>

    <form class="sw-card sw-form quotation-form" method="POST"
          action="{{ $quotation->exists ? route('quotations.update', $quotation) : route('quotations.store') }}"
          data-quotation-builder data-preview-url="{{ route('quotations.preview') }}"
          data-products-url="{{ route('quotations.products') }}">
        @csrf
        @if($quotation->exists) @method('PUT') @endif

        <section class="quotation-section">
            <h2>بيانات العرض</h2>
            <div class="sw-form-grid">
                <label>الفرع
                    <select name="branch_id" required data-preview-trigger>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $quotation->branch_id ?: $selectedBranchId) == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>العميل
                    <select name="customer_id" required data-quotation-customer data-preview-trigger>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected($selectedCustomerId === $customer->id)>{{ $customer->customer_code }} — {{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>السيارة
                    <select name="vehicle_id" required data-quotation-vehicle data-preview-trigger>
                        <option value="" disabled data-empty-vehicle hidden>لا توجد سيارات مسجلة لهذا العميل</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" data-customer-id="{{ $vehicle->customer_id }}" data-vehicle-size-id="{{ $vehicle->vehicle_size_id }}" @selected($selectedVehicleId === $vehicle->id)>{{ $vehicle->plate_number ?: $vehicle->vin }} — {{ $vehicle->brand?->name_ar ?: $vehicle->brand?->name_en }} / {{ $vehicle->model?->name_ar ?: $vehicle->model?->name_en }} — {{ $vehicle->customer?->customer_code }} — {{ $vehicle->customer?->name }}</option>
                        @endforeach
                    </select>
                    <small class="sw-field__help" data-quotation-vehicle-empty hidden>لا توجد سيارة نشطة مرتبطة بالعميل المحدد.</small>
                </label>
                <label>العملة
                    <select name="currency_id" required data-preview-trigger>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->id }}" data-currency-code="{{ $currency->code }}" @selected($selectedCurrencyId === $currency->id)>{{ $currency->code }}</option>
                        @endforeach
                    </select>
                </label>
                <label>تاريخ العرض
                    <input type="date" name="quotation_date" value="{{ old('quotation_date', $quotation->quotation_date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}" required data-preview-trigger>
                </label>
                <label>صالح حتى
                    <input type="date" name="valid_until" value="{{ old('valid_until', $quotation->valid_until?->format('Y-m-d') ?? today()->addDays(7)->format('Y-m-d')) }}" required>
                </label>
                <input type="hidden" name="lead_id" value="{{ old('lead_id', $leadId) }}">
            </div>
        </section>

        <section class="quotation-section">
            <div class="quotation-section-heading">
                <div><h2>منتجات عرض السعر</h2><p>اختر المنتج؛ السعر والخصم الترويجي والضريبة تُجلب من الخادم تلقائيًا.</p></div>
                <button type="button" class="sw-btn sw-btn--primary" data-add-quotation-item>+ إضافة منتج جديد</button>
            </div>
            @if($legacyRows->isNotEmpty())
                <div class="sw-alert sw-alert--warning">
                    البنود التاريخية غير المنتجية محفوظة للقراءة فقط ولن يتم حذفها أو إعادة تسعيرها.
                </div>
                <div class="sw-table-scroll">
                    <table class="sw-table">
                        <thead><tr><th>النوع التاريخي</th><th>الوصف</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
                        <tbody>
                            @foreach($legacyRows as $legacyItem)
                                <tr>
                                    <td>{{ ['service' => 'خدمة', 'package' => 'باقة', 'custom' => 'عنصر مخصص'][$legacyItem->item_type] ?? $legacyItem->item_type }}</td>
                                    <td>{{ $legacyItem->description }}</td>
                                    <td>{{ number_format((float) $legacyItem->quantity, 2) }}</td>
                                    <td>{{ number_format((float) $legacyItem->unit_price, 2) }}</td>
                                    <td>{{ number_format((float) $legacyItem->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="quotation-items" data-quotation-items>
                @foreach($rows as $i => $row)
                    @include('quotations._item-row', ['i' => $i, 'row' => $row])
                @endforeach
            </div>
        </section>

        <section class="quotation-section quotation-header-discount">
            <h2>خصم إضافي على إجمالي عرض السعر</h2>
            <p>يُطبق بعد خصومات العناصر على إجمالي عرض السعر بالكامل.</p>
            <div class="sw-form-grid">
                <label>طريقة الخصم
                    <select name="discount_type" data-header-discount-type>
                        <option value="">بدون خصم</option>
                        <option value="fixed" @selected(old('discount_type', $quotation->discount_type) === 'fixed')>مبلغ ثابت</option>
                        <option value="percentage" @selected(old('discount_type', $quotation->discount_type) === 'percentage')>نسبة مئوية</option>
                    </select>
                </label>
                <label data-header-discount-value @if(! old('discount_type', $quotation->discount_type)) hidden @endif>قيمة الخصم
                    <input type="number" step="0.0001" min="0" name="discount_value" value="{{ old('discount_value', $quotation->discount_value ?? 0) }}">
                </label>
            </div>
        </section>

        <section class="sw-card quotation-summary" data-quotation-summary>
            <div class="quotation-summary-heading">
                <h2>ملخص عرض السعر</h2>
                <span data-preview-status>اختر العناصر لعرض الحساب</span>
            </div>
            <dl>
                <div><dt>إجمالي العناصر قبل الخصم</dt><dd data-summary="subtotal_before_discounts">—</dd></div>
                <div><dt>خصومات العناصر</dt><dd data-summary="item_discounts_total">—</dd></div>
                <div><dt>الصافي بعد خصومات العناصر</dt><dd data-summary="subtotal_after_item_discounts">—</dd></div>
                <div><dt>الخصم الإضافي على العرض</dt><dd data-summary="header_discount_amount">—</dd></div>
                <div><dt>الضريبة</dt><dd data-summary="tax_amount">—</dd></div>
                <div><dt>عدد العناصر</dt><dd data-summary-plain="item_count">—</dd></div>
                <div><dt>المدة التقديرية</dt><dd><span data-summary-plain="estimated_duration_minutes">—</span> دقيقة</dd></div>
                @if($canViewCost)
                    <div><dt>التكلفة المتوقعة</dt><dd data-summary="estimated_total_cost">—</dd></div>
                    <div><dt>الهامش المتوقع</dt><dd data-summary="estimated_margin">—</dd></div>
                @endif
                <div class="quotation-summary-total"><dt>الإجمالي النهائي</dt><dd data-summary="grand_total">—</dd></div>
            </dl>
            <p class="sw-alert sw-alert--danger" data-preview-error hidden></p>
        </section>

        <div class="sw-form-grid quotation-notes-grid">
            <label>ملاحظات العميل<textarea name="customer_notes">{{ old('customer_notes', $quotation->customer_notes) }}</textarea></label>
            <label>ملاحظات داخلية<textarea name="internal_notes">{{ old('internal_notes', $quotation->internal_notes) }}</textarea></label>
            <label>الشروط والأحكام<textarea name="terms_and_conditions">{{ old('terms_and_conditions', $quotation->terms_and_conditions) }}</textarea></label>
        </div>

        <div class="quotation-submit">
            <button class="sw-btn sw-btn--primary" data-quotation-submit>حفظ عرض السعر كمسودة</button>
            <small>سيعيد النظام حساب الأسعار والخصومات والضريبة قبل الحفظ.</small>
        </div>
    </form>

    <template data-quotation-item-template>
        @include('quotations._item-row', ['i' => '__INDEX__', 'row' => ['quantity' => 1]])
    </template>
</div>
@endsection
