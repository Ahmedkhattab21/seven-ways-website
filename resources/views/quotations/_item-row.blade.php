@php
    $discountType = $row['discount_type'] ?? '';
    $selectedProductId = $row['product_id'] ?? null;
    $selectedProduct = $products->firstWhere('id', (int) $selectedProductId);
    $selectedUnit = $selectedProduct?->saleUnit?->symbol
        ?: $selectedProduct?->saleUnit?->name
        ?: data_get($row, 'product.sale_unit.symbol')
        ?: data_get($row, 'product.sale_unit.name');
@endphp
<fieldset class="sw-card quotation-item-card" data-quotation-item>
    <div class="quotation-item-header">
        <h3>المنتج رقم <span data-item-number></span></h3>
        <button type="button" class="sw-btn sw-btn--danger" data-remove-quotation-item>حذف المنتج</button>
    </div>

    <div class="sw-form-grid quotation-item-grid">
        <label>المنتج
            <select name="items[{{ $i }}][product_id]" data-item-reference required>
                <option value="">اختر المنتج</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}"
                            data-sale-unit="{{ $product->saleUnit?->symbol ?: $product->saleUnit?->name }}"
                            data-available-stock="{{ $product->branch_stock_available ?? 0 }}"
                            @selected($selectedProductId == $product->id)>
                        {{ $product->sku }} — {{ $product->name }} — المتاح {{ number_format((float) ($product->branch_stock_available ?? 0), 2) }}
                    </option>
                @endforeach
            </select>
            <small class="sw-field__help" data-product-empty hidden>لا توجد منتجات متاحة بسعر صالح في الفرع المحدد.</small>
        </label>

        <label>الوصف، اختياري
            <input name="items[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" data-item-description>
        </label>

        <label>الكمية
            <input type="number" step="0.000001" min="0.000001" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" required data-item-quantity>
        </label>

        <div class="sw-field">
            <span class="sw-field__label">وحدة البيع</span>
            <strong data-item-unit>{{ $selectedUnit ?: '—' }}</strong>
        </div>
    </div>

    <div class="quotation-price-panel">
        <div><span>السعر الأساسي</span><strong data-item-base-unit-price>—</strong><small data-item-price-source>يُحسب تلقائيًا من النظام</small></div>
        <div><span>سعر الوحدة المستخدم</span><strong data-item-unit-price>—</strong></div>
        <div><span>الخصم</span><strong data-item-discount>—</strong></div>
        <div><span>الضريبة</span><strong data-item-tax>—</strong></div>
        <div><span>صافي المنتج</span><strong data-item-net>—</strong></div>
        <div><span>إجمالي المنتج</span><strong data-item-total>—</strong></div>
    </div>

    <div class="quotation-item-discount">
        <h4>خصم هذا المنتج</h4>
        <div class="sw-form-grid">
            <label>طريقة الخصم
                <select name="items[{{ $i }}][discount_type]" data-item-discount-type>
                    <option value="" @selected($discountType === '')>بدون خصم</option>
                    <option value="fixed" @selected($discountType === 'fixed')>مبلغ ثابت</option>
                    <option value="percentage" @selected($discountType === 'percentage')>نسبة مئوية</option>
                </select>
            </label>
            <label data-item-discount-value @if($discountType === '') hidden @endif>قيمة الخصم
                <input type="number" step="0.0001" min="0" name="items[{{ $i }}][discount_value]" value="{{ $row['discount_value'] ?? 0 }}">
            </label>
        </div>
    </div>

    <p class="sw-alert sw-alert--warning quotation-item-warning" data-item-warning hidden></p>
    <p class="sw-alert sw-alert--danger quotation-item-error" data-item-error hidden></p>
</fieldset>
