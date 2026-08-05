<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        *{box-sizing:border-box}body{font-family:Tahoma,Arial,sans-serif;margin:0;background:#f5f5f5;color:#171717}
        .document,.warranty-page{max-width:980px;margin:24px auto;background:#fff;padding:36px;border:1px solid #ddd}
        .header,.row{display:flex;align-items:flex-start;justify-content:space-between;gap:24px}.brand{display:flex;align-items:center;gap:14px}
        .brand img{width:220px;max-width:100%;max-height:78px;object-fit:contain}.flags{display:flex;gap:8px}.flags img{width:42px;height:28px;border:1px solid #ddd}
        h1{margin:28px 0 8px}.muted{color:#666}.panel{border:1px solid #ddd;border-radius:10px;padding:18px;margin-top:20px}
        table{width:100%;border-collapse:collapse;margin-top:20px}td,th{border:1px solid #ddd;padding:10px;text-align:right}th{background:#f2f2f2}
        .totals{margin:20px 0 0 auto;max-width:420px}.totals p{display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding:7px}
        .invalid{color:#b00020;font-weight:bold}
        .warranty-page{position:relative;overflow:hidden;page-break-before:always;break-before:page;border-top:8px solid #d71920;min-height:1040px}
        .warranty-page::before{content:"7";position:absolute;left:-35px;top:165px;font-size:430px;font-weight:900;color:#d71920;opacity:.035;transform:rotate(-8deg);pointer-events:none}
        .warranty-header{display:flex;align-items:center;justify-content:space-between;gap:24px;padding-bottom:18px;border-bottom:2px solid #202020}
        .warranty-header img{width:230px;max-height:85px;object-fit:contain}.warranty-title{text-align:left}.warranty-title h1{color:#d71920;margin:0 0 7px;font-size:36px}.warranty-title p{margin:0;color:#555}
        .warranty-status{display:inline-block;margin-top:10px;padding:6px 14px;border-radius:999px;background:#eef9f0;color:#16722d;font-weight:bold}.warranty-status.invalid{background:#fff0f0;color:#b00020}
        .warranty-details{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:22px}.warranty-field{border:1px solid #ddd;border-radius:8px;padding:12px;min-height:72px}.warranty-field span{display:block;color:#777;font-size:12px;margin-bottom:6px}.warranty-field strong{font-size:15px}
        .warranty-product{position:relative;margin-top:20px;border:1px solid #d8d8d8;border-right:5px solid #d71920;border-radius:10px;padding:17px}.warranty-product h2{margin:0 0 13px;color:#202020}.warranty-product-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px 18px}.warranty-product-grid p{margin:0;padding:7px 0;border-bottom:1px dashed #ddd}.warranty-product-grid b{display:block;color:#777;font-size:12px;margin-bottom:4px}
        .warranty-terms{display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-top:20px}.warranty-terms section{border:1px solid #ddd;border-radius:10px;padding:16px}.warranty-terms h3{margin:0 0 10px;color:#d71920}.signature-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:28px}.signature{height:75px;border-bottom:1px solid #222;text-align:center;color:#666;padding-top:45px}.warranty-footer{margin-top:26px;padding-top:12px;border-top:1px solid #ccc;text-align:center;font-size:12px;color:#666}
        button{padding:10px 20px;background:#c40000;color:#fff;border:0;border-radius:6px}.footer{margin-top:30px;font-size:12px;color:#666;text-align:center}
        @media(max-width:700px){.document,.warranty-page{margin:0;padding:18px}.header,.row,.warranty-header{display:block}.flags{margin-top:14px}.warranty-title{text-align:right;margin-top:18px}.warranty-details,.warranty-product-grid,.warranty-terms{grid-template-columns:1fr}table{font-size:12px}}
        @media print{@page{size:A4;margin:0}body{background:#fff}.document,.warranty-page{width:210mm;min-height:297mm;border:0;margin:0 auto;padding:15mm;max-width:none}.warranty-page{page-break-before:always;break-before:page}button{display:none}}
    </style>
</head>
<body>
@php
    $settings = array_merge(['show_egypt_flag' => true, 'show_saudi_flag' => true], $invoice->company->invoice_print_settings ?? []);
    $warrantyItems = $invoice->items->where('item_type', 'product');
    $hasRegisteredWarranty = $warrantyItems->where('warranty_applies', true)->isNotEmpty();
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

    @if($invoice->terms_snapshot)<section class="panel"><strong>الشروط العامة</strong><p>{{ $invoice->terms_snapshot }}</p></section>@endif
    @empty($publicShare)<button type="button" onclick="window.print()">طباعة الفاتورة وكارت الضمان</button>@endempty
    <footer class="footer">هذه الفاتورة هي المستند المالي الرسمي للعميل.</footer>
</main>

@if($warrantyItems->isNotEmpty())
    <section class="warranty-page" aria-label="كارت الضمان">
        <header class="warranty-header">
            <img src="{{ asset($invoice->company->logo_path ?: config('branding.logo_on_light')) }}" alt="Seven Ways">
            <div class="warranty-title">
                <h1>كارت الضمان</h1>
                <p>شهادة ضمان المنتجات وأفلام حماية السيارة</p>
                <span class="warranty-status {{ $warrantyInvalid || ! $hasRegisteredWarranty ? 'invalid' : '' }}">{{ $warrantyInvalid ? 'الضمان غير ساري' : ($hasRegisteredWarranty ? 'الضمان ساري' : 'كارت مرتبط بالفاتورة') }}</span>
            </div>
        </header>

        <div class="warranty-details">
            <div class="warranty-field"><span>رقم الضمان / الفاتورة</span><strong>{{ $invoice->invoice_number }}</strong></div>
            <div class="warranty-field"><span>تاريخ بداية الضمان</span><strong>{{ $invoice->invoice_date?->format('Y-m-d') }}</strong></div>
            <div class="warranty-field"><span>الفرع</span><strong>{{ $invoice->branch->name }}</strong></div>
            <div class="warranty-field"><span>اسم العميل</span><strong>{{ $invoice->customer_name_snapshot }}</strong></div>
            <div class="warranty-field"><span>رقم الهاتف</span><strong>{{ $invoice->customer_phone_snapshot ?: '—' }}</strong></div>
            <div class="warranty-field"><span>بيانات السيارة</span><strong>{{ $invoice->vehicle_snapshot['plate_number'] ?? $invoice->vehicle_snapshot['vin'] ?? '—' }}</strong></div>
        </div>

        @foreach($warrantyItems as $item)
            @php($warranty = $item->warranty_snapshot ?? [])
            <article class="warranty-product">
                <h2>{{ $warranty['product_name'] ?? $item->product?->name ?? $item->description }}</h2>
                <div class="warranty-product-grid">
                    <p><b>حالة الضمان</b>{{ $item->warranty_applies ? ($warrantyInvalid ? 'غير ساري بسبب حالة الفاتورة' : 'ساري وفق الشروط') : 'لا توجد مدة ضمان مسجلة لهذا المنتج' }}</p>
                    <p><b>SKU</b>{{ $warranty['product_sku'] ?? $item->product?->sku ?? '—' }}</p>
                    <p><b>الشركة المصنعة</b>{{ $warranty['manufacturer'] ?? $item->product?->brand?->name ?? '—' }}</p>
                    <p><b>اسم الرول</b>{{ $warranty['roll_name'] ?? '—' }}</p>
                    <p><b>كود / رقم الرول</b>{{ $warranty['film_code'] ?? '—' }}</p>
                    <p><b>نوع الفيلم</b>{{ $warranty['film_type'] ?? '—' }}</p>
                    <p><b>منطقة التطبيق</b>{{ $warranty['application_area'] ?? '—' }}</p>
                    <p><b>بداية الضمان</b>{{ $warranty['start_date'] ?? $invoice->invoice_date?->format('Y-m-d') }}</p>
                    <p><b>نهاية الضمان</b>{{ ($warranty['duration_unit'] ?? null) === 'lifetime' ? 'مدى الحياة' : ($warranty['end_date'] ?? '—') }}</p>
                    <p><b>الكمية</b>{{ $item->quantity }}</p>
                </div>
                @foreach($warranty['components'] ?? [] as $component)
                    <p>• {{ $component['service_name'] }} × {{ $component['quantity'] }} — حتى {{ ($component['duration_unit'] ?? null) === 'lifetime' ? 'مدى الحياة' : ($component['end_date'] ?? '—') }}</p>
                @endforeach
            </article>
        @endforeach

        @php($primaryWarranty = $warrantyItems->first()?->warranty_snapshot ?? [])
        <div class="warranty-terms">
            <section>
                <h3>بنود وشروط الضمان</h3>
                <p>{{ $primaryWarranty['terms'] ?? 'يسري الضمان على عيوب المنتج أو التركيب وفق شروط Seven Ways، مع ضرورة إبراز الفاتورة وكارت الضمان عند طلب الخدمة.' }}</p>
            </section>
            <section>
                <h3>ملاحظات</h3>
                <p>{{ $primaryWarranty['notes'] ?? 'لا يغطي الضمان سوء الاستخدام أو الحوادث أو الإصلاح لدى جهة غير معتمدة.' }}</p>
            </section>
        </div>

        <div class="signature-grid">
            <div class="signature">توقيع العميل</div>
            <div class="signature">ختم وتوقيع الفرع</div>
        </div>
        <footer class="warranty-footer">هذا الكارت جزء مكمل للفاتورة رقم {{ $invoice->invoice_number }} ولا يعتد به منفصلًا عنها.</footer>
    </section>
@endif
</body>
</html>
