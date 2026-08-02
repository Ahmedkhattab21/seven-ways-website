@extends('layouts.app')
@section('title', $opening->document_number)
@section('page-title', $opening->document_number)
@section('breadcrumb', 'المخزون / الأرصدة الافتتاحية للمخزون / '.$opening->document_number)
@section('content')
<x-card title="بيانات الرصيد الافتتاحي">
    <div class="sw-detail-grid">
        <div><span>المخزن</span><strong>{{ $opening->warehouse?->name ?? '—' }}</strong></div>
        <div><span>التاريخ</span><strong>{{ $opening->opening_date?->format('Y-m-d') }}</strong></div>
        <div><span>الحالة</span><strong>{{ $opening->status }}</strong></div>
    </div>
</x-card>
<x-table-shell>
    <thead><tr><th>المنتج</th><th>الكمية</th><th>تكلفة الوحدة</th></tr></thead>
    <tbody>
    @forelse($opening->items as $item)
        <tr><td>{{ $item->product?->name ?? '—' }}</td><td>{{ $item->quantity }}</td><td>{{ $item->unit_cost }}</td></tr>
    @empty
        <tr><td colspan="3">لا توجد بنود.</td></tr>
    @endforelse
    </tbody>
</x-table-shell>
@endsection
