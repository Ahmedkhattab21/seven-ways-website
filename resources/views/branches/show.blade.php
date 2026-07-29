@extends('layouts.app')
@section('title', 'عرض الفرع')
@section('page-title', $branch->name)
@section('breadcrumb', 'الإعدادات / الفروع / عرض')
@section('page-actions')
@can('update', $branch)<a class="sw-button sw-button--primary" href="{{ route('branches.edit', $branch) }}">تعديل</a>@endcan
@endsection
@section('content')
<x-card title="بيانات الفرع">
    <dl class="sw-details-grid">
        <div><dt>الكود</dt><dd>{{ $branch->code }}</dd></div>
        <div><dt>الشركة</dt><dd>{{ $branch->company->name }}</dd></div>
        <div><dt>الهاتف</dt><dd>{{ $branch->phone ?? '—' }}</dd></div>
        <div><dt>البريد</dt><dd>{{ $branch->email ?? '—' }}</dd></div>
        <div><dt>النوع</dt><dd>{{ $branch->is_main ? 'رئيسي' : 'فرع' }}</dd></div>
        <div><dt>الحالة</dt><dd><x-status-badge :status="$branch->is_active ? 'active' : 'inactive'" /></dd></div>
        <div><dt>عدد المستخدمين</dt><dd>{{ $branch->accessible_users_count }}</dd></div>
        <div><dt>مسؤول تشغيل الفرع</dt><dd>{{ $branch->responsibleUser?->name ?? 'غير معيّن' }}</dd></div>
        <div><dt>بريد المسؤول</dt><dd>{{ $branch->responsibleUser?->email ?? '—' }}</dd></div>
        <div><dt>العنوان</dt><dd>{{ $branch->address ?? '—' }}</dd></div>
    </dl>
</x-card>
@endsection
