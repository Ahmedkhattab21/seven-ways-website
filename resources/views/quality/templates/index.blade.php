@extends('layouts.app')
@section('title', 'قوالب الجودة')
@section('breadcrumb', 'الجودة')
@section('page-title', 'قوالب الجودة')
@section('content')
<div class="sw-card"><a class="sw-btn" href="{{ route('quality-templates.create') }}">نسخة قالب جديدة</a></div>
<div class="sw-card sw-table-wrap">
    <table class="sw-table">
        <thead><tr><th>الكود</th><th>الاسم</th><th>النطاق</th><th>الإصدار</th><th>العناصر</th><th>افتراضي</th><th>نشط</th><th></th></tr></thead>
        <tbody>
        @foreach($templates as $template)
            <tr>
                <td>{{ $template->code }}</td><td>{{ $template->name }}</td><td>{{ $template->scope_key }}</td>
                <td>{{ $template->version }}</td><td>{{ $template->items_count }}</td>
                <td>{{ $template->is_default ? 'نعم' : 'لا' }}</td><td>{{ $template->is_active ? 'نعم' : 'لا' }}</td>
                <td><form method="POST" action="{{ route('quality-templates.toggle', $template) }}">@csrf @method('PATCH')<button class="sw-btn">{{ $template->is_active ? 'تعطيل' : 'تفعيل' }}</button></form></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $templates->links() }}
</div>
@endsection
