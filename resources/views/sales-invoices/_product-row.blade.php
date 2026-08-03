<fieldset class="sales-invoice-item" data-invoice-item>
    <legend data-invoice-item-title>المنتج رقم {{ is_numeric($index) ? ((int) $index + 1) : 1 }}</legend>

    <div class="sales-invoice-item-grid">
        <label class="sw-field sales-invoice-product-field">
            <span class="sw-field__label">المنتج</span>
            <select class="sw-input" name="items[{{ $index }}][product_id]" data-invoice-product required>
                <option value="">اختر المنتج</option>
                @foreach($products as $product)
                    <option
                        value="{{ $product->id }}"
                        data-unit="{{ $product->saleUnit?->symbol ?: $product->saleUnit?->name }}"
                        data-stock="{{ $product->branch_stock_available ?? 0 }}"
                        data-base-price="{{ $product->resolved_base_price }}"
                        data-final-price="{{ $product->resolved_final_price }}"
                        data-promotion="{{ $product->resolved_promotion_name }}"
                        data-default-warehouse="{{ $product->default_sales_warehouse_id }}"
                        @selected((string) ($row['product_id'] ?? '') === (string) $product->id)
                    >{{ $product->sku }} — {{ $product->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="sw-field">
            <span class="sw-field__label">المخزن</span>
            <select class="sw-input" name="items[{{ $index }}][warehouse_id]" data-invoice-warehouse required>
                <option value="">اختر المخزن</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) ($row['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="sw-field sales-invoice-item__description">
            <span class="sw-field__label">الوصف (اختياري)</span>
            <input class="sw-input" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" placeholder="وصف إضافي يظهر داخل الفاتورة">
        </label>

        <label class="sw-field">
            <span class="sw-field__label">الكمية</span>
            <input class="sw-input" type="number" min="0.000001" step="0.000001" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" required>
        </label>
        <label class="sw-field">
            <span class="sw-field__label">نوع الخصم</span>
            <select class="sw-input" name="items[{{ $index }}][discount_type]">
                <option value="">بدون خصم</option>
                <option value="fixed" @selected(($row['discount_type'] ?? '') === 'fixed')>قيمة ثابتة</option>
                <option value="percentage" @selected(($row['discount_type'] ?? '') === 'percentage')>نسبة مئوية</option>
            </select>
        </label>
        <label class="sw-field">
            <span class="sw-field__label">قيمة الخصم</span>
            <input class="sw-input" type="number" min="0" step="0.0001" name="items[{{ $index }}][discount_value]" value="{{ $row['discount_value'] ?? 0 }}">
        </label>
    </div>

    <div class="sales-invoice-product-summary" data-invoice-product-summary hidden>
        <div><span>وحدة البيع</span><strong data-product-unit>—</strong></div>
        <div><span>المتاح بالفرع</span><strong data-product-stock>—</strong></div>
        <div><span>السعر الأساسي</span><strong data-product-base-price>—</strong></div>
        <div><span>السعر المستخدم</span><strong data-product-final-price>—</strong></div>
        <div data-product-promotion-wrap hidden><span>العرض الترويجي</span><strong data-product-promotion>—</strong></div>
    </div>

    <details class="sales-invoice-warranty">
        <summary>بيانات ضمان المنتج (اختياري)</summary>
        <div class="sales-invoice-warranty-grid">
            <label class="sw-field">
                <span class="sw-field__label">يشمل ضمانًا</span>
                <select class="sw-input" name="items[{{ $index }}][warranty][applies]">
                    <option value="">حسب إعداد المنتج</option>
                    <option value="1" @selected((string) data_get($row, 'warranty.applies') === '1')>نعم</option>
                    <option value="0" @selected((string) data_get($row, 'warranty.applies') === '0')>لا</option>
                </select>
            </label>
            <label class="sw-field"><span class="sw-field__label">نوع الفيلم</span><input class="sw-input" name="items[{{ $index }}][warranty][film_type]" value="{{ data_get($row, 'warranty.film_type') }}"></label>
            <label class="sw-field"><span class="sw-field__label">كود الفيلم</span><input class="sw-input" name="items[{{ $index }}][warranty][film_code]" value="{{ data_get($row, 'warranty.film_code') }}"></label>
            <label class="sw-field"><span class="sw-field__label">منطقة التطبيق</span><input class="sw-input" name="items[{{ $index }}][warranty][application_area]" value="{{ data_get($row, 'warranty.application_area') }}"></label>
            <label class="sw-field"><span class="sw-field__label">بداية الضمان</span><input class="sw-input" type="date" name="items[{{ $index }}][warranty][start_date]" value="{{ data_get($row, 'warranty.start_date') }}"></label>
            <label class="sw-field"><span class="sw-field__label">مدة الضمان</span><input class="sw-input" type="number" min="1" name="items[{{ $index }}][warranty][duration_value]" value="{{ data_get($row, 'warranty.duration_value') }}"></label>
            <label class="sw-field">
                <span class="sw-field__label">وحدة المدة</span>
                <select class="sw-input" name="items[{{ $index }}][warranty][duration_unit]">
                    <option value="">من إعداد المنتج</option>
                    @foreach(['days' => 'أيام', 'months' => 'شهور', 'years' => 'سنوات', 'lifetime' => 'مدى الحياة'] as $value => $label)
                        <option value="{{ $value }}" @selected(data_get($row, 'warranty.duration_unit') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="sw-field sales-invoice-field--full"><span class="sw-field__label">شروط الضمان</span><textarea class="sw-input sw-textarea" name="items[{{ $index }}][warranty][terms]">{{ data_get($row, 'warranty.terms') }}</textarea></label>
            <label class="sw-field sales-invoice-field--full"><span class="sw-field__label">ملاحظات الضمان</span><textarea class="sw-input sw-textarea" name="items[{{ $index }}][warranty][notes]">{{ data_get($row, 'warranty.notes') }}</textarea></label>
        </div>
    </details>

    <button class="sw-button sw-button--outline sales-invoice-remove-product" type="button" data-remove-invoice-item>حذف المنتج</button>
</fieldset>
