@extends('layouts.app')
@section('title', 'الأدوار والصلاحيات')
@section('page-title', 'الأدوار والصلاحيات')
@section('breadcrumb', 'الإعدادات / الأدوار')
@section('page-actions')
@if(auth()->user()->hasPermission('roles.manage'))<a class="sw-button sw-button--primary" href="{{ route('roles.create') }}">إضافة دور</a>@endif
@endsection
@section('content')
<x-table-shell>
    <thead><tr><th>الدور</th><th>النطاق</th><th>المستخدمون</th><th>الصلاحيات</th><th>الإجراء</th></tr></thead>
    <tbody>@foreach($roles as $role)@php($legacy = in_array($role->name, ['sales', 'cashier', 'receptionist', 'warehouse_keeper', 'technician', 'quality_controller'], true))<tr><td>{{ $role->display_name }} @if($legacy)<small class="sw-badge">دور قديم — غير متاح للتعيين الجديد</small>@endif</td><td>{{ $role->scope === 'branch' ? 'الفرع' : ($role->scope === 'company' ? 'الشركة' : 'تقني') }}</td><td>{{ $role->users_count }}</td><td>{{ $role->permissions_count }}</td><td>@if(!$legacy && !$role->is_system && auth()->user()->hasPermission('roles.manage'))<a href="{{ route('roles.edit', $role) }}">إدارة الصلاحيات</a>@else<span>مرجعي</span>@endif</td></tr>@endforeach</tbody>
</x-table-shell>
@endsection
