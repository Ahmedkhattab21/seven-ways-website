@extends('layouts.app')
@section('title', 'تصنيفات الخدمات')
@section('page-title', 'تصنيفات الخدمات')
@section('breadcrumb', 'الخدمات / التصنيفات')
@section('page-actions')
@if(auth()->user()->hasPermission('service_categories.manage'))<a class="sw-button sw-button--primary" href="{{ route('service-categories.create') }}">إضافة تصنيف</a>@endif
@endsection
@section('content')
<x-table-shell>
    <thead><tr><th>الكود</th><th>الاسم</th><th>التصنيف الأب</th><th>الأبناء</th><th>الخدمات</th><th>الحالة</th><th></th></tr></thead>
    <tbody>
    @forelse($categories as $category)
        <tr><td>{{ $category->code }}</td><td>{{ $category->name }}</td><td>{{ $category->parent?->name ?? '—' }}</td>
            <td>{{ $category->children_count }}</td><td>{{ $category->services_count }}</td>
            <td><x-status-badge :status="$category->is_active ? 'active' : 'inactive'" /></td>
            <td>@if(auth()->user()->hasPermission('service_categories.manage'))<a href="{{ route('service-categories.edit', $category) }}">تعديل</a>@endif</td></tr>
    @empty<tr><td colspan="7">لا توجد تصنيفات خدمات.</td></tr>@endforelse
    </tbody>
    <x-slot:footer>{{ $categories->links() }}</x-slot:footer>
</x-table-shell>
@endsection
