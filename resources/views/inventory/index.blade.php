@extends('layouts.app')
@php($titles = ['balances'=>'أرصدة المخزون','movements'=>'حركات المخزون','rolls'=>'الرولات','scraps'=>'القصاصات','openings'=>'الرصيد الافتتاحي','adjustments'=>'التسويات','counts'=>'الجرد','reservations'=>'الحجوزات','alerts'=>'تنبيهات المخزون'])
@section('title', $titles[$section])
@section('page-title', $titles[$section])
@section('breadcrumb', 'المخزون / '.$titles[$section])
@section('page-actions')
@if(in_array($section, ['openings','adjustments','counts']))<a class="sw-button sw-button--primary" href="{{ route('inventory.documents.create', $section) }}">إضافة مسودة</a>@endif
@endsection
@section('content')
@if($section === 'alerts')
<div class="sw-stats">
@foreach(['low_products'=>'منتجات منخفضة','low_rolls'=>'رولات منخفضة','expired_or_restricted_rolls'=>'منتهية/تالفة/حجر','available_scraps'=>'قصاصات متاحة','unposted_counts'=>'جرد غير مرحل'] as $key=>$label)
<x-card :title="$label"><strong>{{ $alertSummary[$key] }}</strong></x-card>
@endforeach
</div>
@endif
<x-table-shell>
<thead><tr><th>المرجع</th><th>المنتج / المخزن</th><th>الكمية / المتبقي</th><th>الحالة / النوع</th><th>التاريخ</th><th>الإجراء</th></tr></thead>
<tbody>@forelse($records as $record)<tr>
<td>{{ $record->movement_number ?? $record->document_number ?? $record->roll_number ?? $record->scrap_code ?? $record->uuid }}</td>
<td>{{ $record->product?->name ?? $record->sourceRoll?->roll_number ?? $record->warehouse?->name ?? '—' }}</td>
<td>{{ $record->available_quantity ?? $record->remaining_area ?? $record->area ?? $record->quantity ?? '—' }}</td>
<td>{{ $record->status ?? $record->movement_type ?? '—' }}</td>
<td>{{ optional($record->occurred_at ?? $record->created_at)->format('Y-m-d H:i') }}</td>
<td>
@if($section === 'movements' && auth()->user()->hasPermission('inventory.reverse') && !$record->reversal_of_id)
<form method="POST" action="{{ route('inventory.movements.reverse', $record) }}">@csrf <x-button type="submit">عكس</x-button></form>
@elseif($section === 'openings' && $record->status === 'draft' && auth()->user()->hasPermission('inventory.post'))
<form method="POST" action="{{ route('inventory.openings.post', $record) }}">@csrf <x-button type="submit">ترحيل</x-button></form>
@elseif($section === 'adjustments' && in_array($record->status, ['draft','approved']) && auth()->user()->hasPermission('inventory.post'))
<form method="POST" action="{{ route('inventory.adjustments.post', $record) }}">@csrf <x-button type="submit">ترحيل</x-button></form>
@elseif($section === 'counts' && $record->status === 'draft' && auth()->user()->hasPermission('inventory.count'))
<form method="POST" action="{{ route('inventory.counts.snapshot', $record) }}">@csrf <x-button type="submit">بدء الجرد</x-button></form>
@elseif($section === 'counts' && $record->status === 'counting' && auth()->user()->hasPermission('inventory.post'))
<form method="POST" action="{{ route('inventory.counts.post', $record) }}">@csrf <x-button type="submit">ترحيل</x-button></form>
@elseif($section === 'reservations' && $record->status === 'active' && auth()->user()->hasPermission('inventory_reservations.manage'))
<form method="POST" action="{{ route('inventory.reservations.release', $record) }}">@csrf <x-button type="submit">تحرير</x-button></form>
@elseif($section === 'scraps' && $record->status === 'available' && auth()->user()->hasPermission('rolls.manage_scraps'))
<form method="POST" action="{{ route('inventory.scraps.consume', $record) }}">@csrf <x-button type="submit">استهلاك</x-button></form>
@elseif($section === 'rolls' && in_array($record->status, ['available','opened']) && auth()->user()->hasPermission('rolls.consume'))
<form method="POST" action="{{ route('inventory.rolls.consume', $record) }}" class="sw-form">@csrf
<input name="length" type="number" step="0.000001" min="0.000001" placeholder="الطول" required>
<input name="usable_area" type="number" step="0.000001" min="0" placeholder="المساحة المستخدمة" required>
<input name="waste_area" type="number" step="0.000001" min="0" placeholder="الهالك">
<x-button type="submit">استهلاك</x-button></form>
@else — @endif
</td>
</tr>@empty<tr><td colspan="6">لا توجد بيانات.</td></tr>@endforelse</tbody>
<x-slot:footer>{{ $records->links() }}</x-slot:footer>
</x-table-shell>
@endsection
