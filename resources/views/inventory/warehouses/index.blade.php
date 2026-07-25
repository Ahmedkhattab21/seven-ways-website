@extends('layouts.app')
@section('title', 'المخازن')
@section('page-title', 'المخازن')
@section('breadcrumb', 'المخزون / المخازن')
@section('page-actions')
@if(auth()->user()->hasPermission('warehouses.create'))<a class="sw-button sw-button--primary" href="{{ route('warehouses.create') }}">إضافة مخزن</a>@endif
@endsection
@section('content')
<x-table-shell>
<thead><tr><th>الكود</th><th>الاسم</th><th>الفرع</th><th>النوع</th><th>رئيسي</th><th>الحالة</th></tr></thead>
<tbody>@forelse($warehouses as $warehouse)<tr><td>{{ $warehouse->code }}</td><td>{{ $warehouse->name }}</td><td>{{ $warehouse->branch?->name }}</td><td>{{ $warehouse->warehouse_type }}</td><td>{{ $warehouse->is_main ? 'نعم' : 'لا' }}</td><td><x-status-badge :status="$warehouse->is_active ? 'active' : 'inactive'" /></td></tr>@empty<tr><td colspan="6">لا توجد مخازن.</td></tr>@endforelse</tbody>
<x-slot:footer>{{ $warehouses->links() }}</x-slot:footer>
</x-table-shell>
@endsection
