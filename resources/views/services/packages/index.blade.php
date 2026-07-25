@extends('layouts.app')
@section('title', 'باقات الخدمات')
@section('page-title', 'باقات الخدمات')
@section('breadcrumb', 'الخدمات / الباقات')
@section('page-actions')@if(auth()->user()->hasPermission('service_packages.create'))<a class="sw-button sw-button--primary" href="{{ route('service-packages.create') }}">إضافة باقة</a>@endif@endsection
@section('content')
<x-table-shell><thead><tr><th>الكود</th><th>الباقة</th><th>النوع</th><th>الخدمات</th><th>أسعار الفروع</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse($packages as $package)<tr><td>{{ $package->code }}</td><td>{{ $package->name }}</td><td>{{ $package->package_type }}</td><td>{{ $package->items_count }}</td><td>{{ $package->branchPrices->count() }}</td><td><x-status-badge :status="$package->is_active ? 'active' : 'inactive'" /></td><td>@if(auth()->user()->hasPermission('service_packages.update'))<a href="{{ route('service-packages.edit',$package) }}">تعديل</a>@endif</td></tr>@empty<tr><td colspan="7">لا توجد باقات.</td></tr>@endforelse
</tbody><x-slot:footer>{{ $packages->links() }}</x-slot:footer></x-table-shell>
@endsection
