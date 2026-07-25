@extends('layouts.app')
@section('title', 'الفروع')
@section('page-title', 'الفروع')
@section('breadcrumb', 'الإعدادات / الفروع')
@section('page-actions')
@if(auth()->user()->hasPermission('branches.create'))<a class="sw-button sw-button--primary" href="{{ route('branches.create') }}">إضافة فرع</a>@endif
@endsection
@section('content')
<x-table-shell>
    <thead><tr><th>الكود</th><th>الاسم</th><th>الهاتف</th><th>النوع</th><th>المستخدمون</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
    <tbody>
    @forelse($branches as $branch)
        <tr>
            <td>{{ $branch->code }}</td><td><a href="{{ route('branches.show', $branch) }}">{{ $branch->name }}</a></td><td>{{ $branch->phone ?? '—' }}</td>
            <td>{{ $branch->is_main ? 'رئيسي' : 'فرع' }}</td><td>{{ $branch->accessible_users_count }}</td>
            <td><x-status-badge :status="$branch->is_active ? 'active' : 'inactive'" /></td>
            <td class="sw-actions">
                @if(auth()->user()->hasPermission('branches.update'))<a href="{{ route('branches.edit', $branch) }}">تعديل</a>@endif
                @if(!$branch->is_main && $branch->is_active && auth()->user()->hasPermission('branches.update'))
                    <form method="POST" action="{{ route('branches.main', $branch) }}">@csrf @method('PATCH')<button type="submit">تعيين رئيسي</button></form>
                @endif
                @if(!$branch->is_main && $branch->is_active && auth()->user()->hasPermission('branches.disable'))
                    <form method="POST" action="{{ route('branches.disable', $branch) }}" onsubmit="return confirm('تأكيد تعطيل الفرع؟')">@csrf @method('PATCH')<button type="submit">تعطيل</button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7">لا توجد فروع.</td></tr>
    @endforelse
    </tbody>
    <x-slot:footer>{{ $branches->links() }}</x-slot:footer>
</x-table-shell>
@endsection
