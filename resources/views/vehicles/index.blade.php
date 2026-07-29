@extends('layouts.app')
@section('title', 'السيارات')
@section('page-title', 'السيارات')
@section('breadcrumb', 'إدارة السيارات / القائمة')
@section('page-actions')
@if(auth()->user()->hasPermission('vehicles.create'))<a class="sw-button sw-button--primary" href="{{ route('vehicles.create') }}">إضافة سيارة</a>@endif
@endsection
@section('content')
<div class="vehicles-index-layout">
<x-card title="البحث" class="vehicles-filter-card"><form method="GET" class="sw-form vehicles-filter-form"><div class="sw-form-grid vehicles-filter-grid"><x-form.input name="search" label="بحث" :value="request('search')" placeholder="اللوحة أو VIN أو العميل" /></div><div class="sw-form-actions"><x-button type="submit">بحث</x-button><a class="sw-button sw-button--outline" href="{{ route('vehicles.index') }}">مسح</a></div></form></x-card>
<x-table-shell class="vehicles-table-card">
    <thead><tr><th>اللوحة</th><th>VIN</th><th>العميل</th><th>الماركة</th><th>الموديل</th><th>السنة</th><th>الحالة</th></tr></thead>
    <tbody>@forelse($vehicles as $vehicle)<tr><td><a href="{{ route('vehicles.show',$vehicle) }}">{{ $vehicle->plate_number ?? 'بدون لوحة' }}</a></td><td>{{ $vehicle->vin ?? '—' }}</td><td>{{ $vehicle->customer->name }}</td><td>{{ $vehicle->brand->name_ar }}</td><td>{{ $vehicle->model->name_ar }}</td><td>{{ $vehicle->manufacturing_year ?? '—' }}</td><td><x-status-badge :status="$vehicle->status" /></td></tr>@empty<tr><td colspan="7">لا توجد سيارات.</td></tr>@endforelse</tbody>
    <x-slot:footer>{{ $vehicles->links() }}</x-slot:footer>
</x-table-shell>
</div>
@endsection
