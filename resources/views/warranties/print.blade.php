<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>{{ $warranty->warranty_number }}</title><style>body{font-family:Arial;margin:40px;color:#111}.brand{width:220px;height:auto}table{width:100%;border-collapse:collapse}td,th{border:1px solid #bbb;padding:8px}.state{font-size:24px;font-weight:bold}.void{color:#b5121b}@media print{button{display:none}}</style></head>
<body>
<img class="brand" src="{{ asset(config('branding.logo_on_light')) }}" alt="Seven Ways"><h1>شهادة ضمان</h1><p class="state {{ in_array($warranty->status, ['void', 'expired']) ? 'void' : '' }}">{{ strtoupper($warranty->status) }}</p>
<p>رقم الضمان: {{ $warranty->warranty_number }}</p><p>العميل: {{ $warranty->customer->name }}</p><p>السيارة: {{ $warranty->vehicle->plate_number ?: $warranty->vehicle->vin }}</p>
<table><thead><tr><th>الخدمة</th><th>المنتج</th><th>المدة</th><th>من</th><th>إلى</th></tr></thead><tbody>@foreach($warranty->items as $item)<tr><td>{{ $item->service?->name }}</td><td>{{ $item->product?->name }}</td><td>{{ $item->warranty_months }} شهر</td><td>{{ $item->start_date }}</td><td>{{ $item->end_date }}</td></tr>@endforeach</tbody></table>
<h2>الشروط</h2><p>{{ $warranty->terms_snapshot['coverage'] ?? '' }}</p><h2>الاستثناءات</h2><p>{{ $warranty->terms_snapshot['exclusions'] ?? '' }}</p>
<p>التحقق: {{ route('warranties.verify', $warranty->qr_token) }}</p><button onclick="window.print()">طباعة</button>
</body></html>
