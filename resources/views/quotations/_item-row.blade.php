@php
    $itemType = $row['item_type'] ?? 'service';
    $hasManualPrice = array_key_exists('manual_unit_price', $row)
        && $row['manual_unit_price'] !== null
        && $row['manual_unit_price'] !== '';
    $discountType = $row['discount_type'] ?? '';
@endphp
<fieldset class="sw-card quotation-item-card" data-quotation-item>
    <div class="quotation-item-header">
        <h3><span data-item-title>الخدمة</span> رقم <span data-item-number></span></h3>
        <button type="button" class="sw-btn sw-btn--danger" data-remove-quotation-item>حذف العنصر</button>
    </div>

    <div class="sw-form-grid quotation-item-grid">
        <label>نوع العنصر
            <select name="items[{{ $i }}][item_type]" data-item-type required>
                @foreach(['service' => 'خدمة', 'package' => 'باقة', 'product' => 'منتج', 'custom' => 'عنصر مخصص'] as $value => $label)
                    <option value="{{ $value }}" @selected($itemType === $value) @disabled($value === 'custom' && ! $canManualPrice)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label data-item-field="service" @if($itemType !== 'service') hidden @endif>الخدمة
            <select name="items[{{ $i }}][service_id]" data-item-reference>
                <option value="">اختر الخدمة</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected(($row['service_id'] ?? null) == $service->id)>{{ $service->name }}</option>
                @endforeach
            </select>
        </label>

        <label data-item-field="package" @if($itemType !== 'package') hidden @endif>الباقة
            <select name="items[{{ $i }}][service_package_id]" data-item-reference>
                <option value="">اختر الباقة</option>
                @foreach($packages as $package)
                    @php
                        $availability = $package->branchPrices->map(fn ($price) => [
                            'branch_id' => $price->branch_id,
                            'vehicle_size_id' => $price->vehicle_size_id,
                            'effective_from' => $price->effective_from?->toDateString(),
                            'effective_to' => $price->effective_to?->toDateString(),
                        ])->values();
                    @endphp
                    <option value="{{ $package->id }}" data-package-availability='@json($availability)' @selected(($row['service_package_id'] ?? null) == $package->id)>{{ $package->code }} — {{ $package->name }}</option>
                @endforeach
            </select>
            <small class="sw-field__help quotation-package-empty" data-package-empty hidden></small>
        </label>

        <label data-item-field="product" @if($itemType !== 'product') hidden @endif>المنتج
            <select name="items[{{ $i }}][product_id]" data-item-reference>
                <option value="">اختر المنتج</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}"
                            data-product-branches='@json($product->branchProducts->pluck('branch_id')->values())'
                            @selected(($row['product_id'] ?? null) == $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
            <small class="sw-field__help quotation-product-empty" data-product-empty hidden>لا توجد منتجات متاحة للبيع في الفرع المحدد.</small>
        </label>

        <label>الوصف
            <input name="items[{{ $i }}][description]" value="{{ $row['description'] ?? '' }}" data-item-description>
            <small class="sw-field__help">مطلوب للعناصر المخصصة، واختياري لباقي الأنواع.</small>
        </label>

        <label>الكمية
            <input type="number" step="0.000001" min="0.000001" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" required data-item-quantity>
        </label>
    </div>

    <div class="quotation-package-details" data-package-details @if($itemType !== 'package') hidden @endif>
        <div><span>الخدمات المشمولة</span><strong data-package-services>—</strong></div>
        <div><span>مجموع الخدمات منفردة</span><strong data-package-standalone>—</strong></div>
        <div><span>قيمة التوفير</span><strong data-package-saving>—</strong></div>
    </div>

    <div class="quotation-price-panel">
        <div>
            <span>السعر الأساسي</span>
            <strong data-item-base-unit-price>—</strong>
            <small data-item-price-source>يُحسب تلقائيًا من النظام</small>
        </div>
        <div>
            <span>سعر الوحدة المستخدم</span>
            <strong data-item-unit-price>—</strong>
        </div>
        <div>
            <span>قبل الخصم</span>
            <strong data-item-gross>—</strong>
        </div>
        <div>
            <span>خصم العنصر</span>
            <strong data-item-discount>—</strong>
        </div>
        <div>
            <span>الصافي قبل الضريبة</span>
            <strong data-item-net>—</strong>
        </div>
        <div>
            <span>الضريبة</span>
            <strong data-item-tax>—</strong>
        </div>
        <div>
            <span>إجمالي العنصر</span>
            <strong data-item-total>—</strong>
        </div>
        <div>
            <span>المدة المتوقعة</span>
            <strong><span data-item-duration>—</span> دقيقة</strong>
        </div>
    </div>

    @if($canManualPrice)
        <div class="quotation-manual-price" data-manual-price-section>
            <label class="quotation-switch" data-manual-price-toggle-label @if($itemType === 'custom') hidden @endif>
                <input type="checkbox" data-manual-price-toggle @checked($hasManualPrice)>
                تعديل سعر الوحدة
            </label>
            <label data-manual-price-field @if($itemType !== 'custom' && ! $hasManualPrice) hidden @endif>
                سعر الوحدة المعدل
                <input type="number" step="0.0001" min="0" name="items[{{ $i }}][manual_unit_price]" value="{{ $row['manual_unit_price'] ?? '' }}" @if($itemType === 'custom') required @endif>
                <small class="sw-field__help">يُستخدم فقط لتجاوز السعر المسجل، ويتطلب صلاحية خاصة وقد يحتاج اعتمادًا.</small>
            </label>
        </div>
    @endif

    <div class="quotation-item-discount">
        <h4 data-item-discount-title>خصم هذه الخدمة</h4>
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
        <small class="sw-field__help">يُطبق على هذا العنصر فقط.</small>
    </div>

    <p class="sw-alert sw-alert--warning quotation-item-warning" data-item-warning hidden></p>
    <p class="sw-alert sw-alert--danger quotation-item-error" data-item-error hidden></p>
</fieldset>
