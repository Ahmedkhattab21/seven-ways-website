@extends('layouts.app')
@section('title', 'التقدير — '.$service->name)
@section('page-title', 'الحاسبة التقديرية')
@section('breadcrumb', 'الخدمات / '.$service->name.' / التقدير')
@section('content')
<x-alert type="warning">هذه نتيجة تقديرية فقط، لم تُحفظ كمستند ولم تحجز أو تخصم أي مخزون.</x-alert>
<x-card title="السعر والضريبة"><div class="sw-form-grid"><div><small>مصدر السعر</small><strong>{{ $result['price_source'] }}</strong></div><div><small>سعر الوحدة</small><strong>{{ $result['unit_price'] ?? 'عرض مخصص' }}</strong></div><div><small>قبل الضريبة</small><strong>{{ $result['subtotal'] ?? '—' }}</strong></div><div><small>الضريبة</small><strong>{{ $result['tax_amount'] ?? '—' }}</strong></div><div><small>الإجمالي</small><strong>{{ $result['total'] ?? '—' }}</strong></div><div><small>المدة</small><strong>{{ $result['estimated_duration'] }} دقيقة</strong></div></div></x-card>
<x-card title="المواد والاستهلاك المتوقع"><x-table-shell><thead><tr><th>المنتج</th><th>الكمية</th><th>الهالك</th><th>الإجمالي المتوقع</th>@if(array_key_exists('estimated_material_cost',$result))<th>التكلفة</th>@endif</tr></thead><tbody>@foreach($result['materials'] as $item)<tr><td>{{ $item['product'] }}</td><td>{{ $item['expected_quantity'] }} {{ $item['unit'] }}</td><td>{{ $item['expected_waste'] }}</td><td>{{ $item['total_expected_quantity'] }}</td>@if(array_key_exists('estimated_material_cost',$result))<td>{{ $item['estimated_cost'] ?? 'غير متاحة' }}</td>@endif</tr>@endforeach</tbody></x-table-shell></x-card>
@if(array_key_exists('estimated_material_cost',$result))<x-card title="التكلفة والهامش"><div class="sw-form-grid"><div><small>تكلفة المواد</small><strong>{{ $result['estimated_material_cost'] ?? 'غير متاحة' }}</strong></div><div><small>التكلفة الإجمالية</small><strong>{{ $result['estimated_total_cost'] ?? 'غير متاحة' }}</strong></div><div><small>الهامش المتوقع</small><strong>{{ $result['estimated_margin'] ?? 'غير متاح' }}</strong></div></div></x-card>@endif
@foreach($result['warnings'] as $warning)<x-alert type="warning">{{ $warning }}</x-alert>@endforeach
@endsection
