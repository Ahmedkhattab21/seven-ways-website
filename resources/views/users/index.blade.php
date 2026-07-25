@extends('layouts.app')
@section('title', 'المستخدمون')
@section('page-title', 'المستخدمون')
@section('breadcrumb', 'الإعدادات / المستخدمون')
@section('page-actions')
@if(auth()->user()->hasPermission('users.create'))<a class="sw-button sw-button--primary" href="{{ route('users.create') }}">إضافة مستخدم</a>@endif
@endsection
@section('content')
<x-table-shell>
    <thead><tr><th>الاسم</th><th>البريد</th><th>الفرع الافتراضي</th><th>الأدوار</th><th>الحالة</th><th>الإجراءات</th></tr></thead>
    <tbody>
    @forelse($users as $user)
        <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->branch?->name ?? '—' }}</td>
            <td>{{ $user->roles->pluck('display_name')->join('، ') }}</td>
            <td><x-status-badge :status="$user->status" /></td>
            <td class="sw-actions">
                @if(auth()->user()->hasPermission('users.update'))<a href="{{ route('users.edit', $user) }}">تعديل</a>@endif
                @if($user->status === 'active' && $user->id !== auth()->id() && auth()->user()->hasPermission('users.disable'))
                    <form method="POST" action="{{ route('users.disable', $user) }}">@csrf @method('PATCH')<button type="submit">تعطيل</button></form>
                @endif
            </td>
        </tr>
    @empty<tr><td colspan="6">لا يوجد مستخدمون.</td></tr>@endforelse
    </tbody>
    <x-slot:footer>{{ $users->links() }}</x-slot:footer>
</x-table-shell>
@endsection
