<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        *{box-sizing:border-box}body{font-family:Tahoma,Arial,sans-serif;margin:0;background:#f5f5f5;color:#171717}
        .document{max-width:980px;margin:24px auto;background:#fff;padding:36px;border:1px solid #ddd}
        .header,.row{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:14px}
        .brand img{width:220px;max-width:100%;max-height:78px;object-fit:contain}.flags{display:flex;gap:8px}.flags img{width:42px;height:28px;border:1px solid #ddd}
        h1{margin:28px 0 8px}.muted{color:#666}.panel{border:1px solid #ddd;border-radius:10px;padding:18px;margin-top:20px}
        table{width:100%;border-collapse:collapse;margin-top:20px}td,th{border:1px solid #ddd;padding:10px;text-align:right}th{background:#f2f2f2}
        .totals{margin:20px 0 0 auto;max-width:420px}.totals p{display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding:7px}
        .warranty{border-color:#c40000}.warranty h2{color:#a90000}.warranty-item{padding:14px 0;border-bottom:1px dashed #bbb}.invalid{color:#b00020;font-weight:bold}
        button{padding:10px 20px;background:#c40000;color:#fff;border:0;border-radius:6px}.footer{margin-top:30px;font-size:12px;color:#666;text-align:center}
        @media(max-width:700px){.document{margin:0;padding:18px}.header,.row{display:block}.flags{margin-top:14px}table{font-size:12px}}
        @media print{body{background:#fff}.document{border:0;margin:0;max-width:none}button{display:none}}
    </style>
</head>
<body>
@php
    $settings = array_merge(['show_egypt_flag' => true, 'show_saudi_flag' => true], $invoice->company->invoice_print_settings ?? []);
    $productItems = $invoice->items->where('item_type', 'product');
    $warrantyItems = $invoice->items->where('warranty_applies', true);
    $warrantyInvalid = in_array($invoice->status, ['cancelled', 'void'], true);
@endphp
<main class="document">
    <header class="header">
        <div class="brand">
            <img src="{{ asset($invoice->company->logo_path ?: config('branding.logo_on_light')) }}" alt="Seven Ways">
            <div>{{ $invoice->company->legal_name ?: $invoice->company->name }}</div>
        </div>
        <div class="flags">
            @if($settings['show_egypt_flag'])<img src="{{ asset('images/flags/eg.svg') }}" alt="علم مصر">@endif
            @if($settings['show_saudi_flag'])<img src="{{ asset('images/flags/sa.svg') }}" alt="علم السعودية">@endif
        </div>
    </header>

    <div class="row">
        <div><h1>فاتورة مبيعات</h1><strong>{{ $invoice->invoice_number }}</strong></div>
        <div><p>التاريخ: {{ $invoice->invoice_date?->format('Y-m-d') }}</p><p>الحالة: {{ $invoice->status }}</p></div>
    </div>

    <section class="panel row">
        <div><strong>بيانات الفرع</strong><p>{{ $invoice->branch->name }}</p><p>{{ $invoice->branch->address }}</p><p>{{ $invoice->branch->phone }}</p></div>
        <div><strong>بيانات العميل</strong><p>{{ $invoice->customer_name_snapshot }}</p><p>{{ $invoice->customer_phone_snapshot }}</p><p>{{ $invoice->customer_address_snapshot }}</p></div>
        @if($invoice->vehicle_snapshot)
            <div><strong>السيارة</strong><p>{{ $invoice->vehicle_snapshot['plate_number'] ?? '—' }}</p><p>VIN: {{ $invoice->vehicle_snapshot['vin'] ?? '—' }}</p></div>
        @endif
    </section>

    <table>
        <thead><tr><th>المنتج</th><th>الكمية</th><th>السعر</th><th>الخصم</th><th>الضريبة</th><th>الإجمالي</th></tr></thead>
        <tbody>
        @foreach($invoice->items as $item)
            @php($warranty = $item->warranty_snapshot ?? [])
            <tr><td><strong>{{ $warranty['product_name'] ?? $item->product?->name ?? $item->description }}</strong><br><small>SKU: {{ $warranty['product_sku'] ?? $item->product?->sku ?? '—' }} | الشركة: {{ $warranty['manufacturer'] ?? $item->product?->brand?->name ?? '—' }}</small></td><td>{{ $item->quantity }}</td><td>{{ $item->unit_price }}</td><td>{{ $item->discount_amount }}</td><td>{{ $item->tax_amount }}</td><td>{{ $item->total }}</td></tr>
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <p><span>قبل الخصم</span><strong>{{ $invoice->subtotal }}</strong></p>
        <p><span>الخصم</span><strong>{{ $invoice->discount_amount }}</strong></p>
        <p><span>الضريبة</span><strong>{{ $invoice->tax_amount }}</strong></p>
        <p><span>الإجمالي</span><strong>{{ $invoice->total }} {{ $invoice->currency?->code }}</strong></p>
        <p><span>المدفوع</span><strong>{{ $invoice->paid_amount }}</strong></p>
        <p><span>المتبقي</span><strong>{{ $invoice->balance_due }}</strong></p>
    </div>

    @if($productItems->isNotEmpty())
        <section class="panel warranty">
            <h2>بيانات المنتجات والضمان</h2>
            @foreach($productItems as $item)
                @php($warranty = $item->warranty_snapshot ?? [])
                <div class="warranty-item">
                    <strong>{{ $warranty['product_name'] ?? $item->product?->name ?? $item->description }}</strong>
                    <p>SKU: {{ $warranty['product_sku'] ?? $item->product?->sku ?? '—' }} | حالة الضمان: <span class="{{ $item->warranty_applies && $warrantyInvalid ? 'invalid' : '' }}">{{ $item->warranty_applies ? ($warrantyInvalid ? 'غير ساري بسبب إلغاء/إبطال الفاتورة' : 'ساري وفق الشروط الموضحة') : 'غير مسجل لهذا المنتج' }}</span></p>
                    <p>الشركة: {{ $warranty['manufacturer'] ?? $item->product?->brand?->name ?? '—' }} | اسم الرول: {{ $warranty['roll_name'] ?? '—' }}</p>
                    <p>نوع الفيلم: {{ $warranty['film_type'] ?? '—' }} | كود/رقم الرول: {{ $warranty['film_code'] ?? '—' }} | منطقة التطبيق: {{ $warranty['application_area'] ?? '—' }}</p>
                    @if($item->warranty_applies)
                        <p>البداية: {{ $warranty['start_date'] ?? '—' }} | النهاية: {{ ($warranty['duration_unit'] ?? null) === 'lifetime' ? 'مدى الحياة' : ($warranty['end_date'] ?? '—') }}</p>
                        <p><strong>بطاقة الضمان الإلكترونية:</strong> هذه الفاتورة رقم {{ $invoice->invoice_number }} هي مستند الضمان، ويمكن عرضها من رابط المشاركة الآمن المرسل للعميل.</p>
                    @endif
                    @if(!empty($warranty['terms']))<p><strong>الشروط:</strong> {{ $warranty['terms'] }}</p>@endif
                    @if(!empty($warranty['notes']))<p><strong>ملاحظات:</strong> {{ $warranty['notes'] }}</p>@endif
                    @foreach($warranty['components'] ?? [] as $component)
                        <p>• {{ $component['service_name'] }} × {{ $component['quantity'] }} — حتى {{ ($component['duration_unit'] ?? null) === 'lifetime' ? 'مدى الحياة' : ($component['end_date'] ?? '—') }}</p>
                    @endforeach
                </div>
            @endforeach
        </section>
    @endif

    @if($invoice->terms_snapshot)<section class="panel"><strong>الشروط العامة</strong><p>{{ $invoice->terms_snapshot }}</p></section>@endif
    @empty($publicShare)<button type="button" onclick="window.print()">طباعة الفاتورة</button>@endempty
    <footer class="footer">هذه الفاتورة هي المستند المالي والضماني الموحد للعميل، ولا تمثل شهادة ضمان منفصلة.</footer>
</main>
</body>
</html>
