@extends('layouts.app')
@section('title', 'صندوق الاعتمادات')
@section('page-title', 'صندوق الاعتمادات المركزي')
@section('content')
<div class="sw-card">
    <form method="GET" class="sw-form"><div class="sw-form-grid">
        <select name="status"><option value="">كل الحالات</option>@foreach(['pending','approved','rejected','cancelled','expired'] as $value)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $value }}</option>@endforeach</select>
        <select name="module"><option value="">كل الموديولات</option>@foreach(['purchasing','treasury'] as $value)<option value="{{ $value }}" @selected(request('module')===$value)>{{ $value }}</option>@endforeach</select>
        <select name="priority"><option value="">كل الأولويات</option>@foreach(['normal','high','urgent'] as $value)<option value="{{ $value }}">{{ $value }}</option>@endforeach</select>
        <button class="sw-btn">تصفية</button>
    </div></form>
    <table class="sw-table"><thead><tr><th>المستند</th><th>الموديول</th><th>مقدم الطلب</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الطلب</th></tr></thead><tbody>
    @forelse($tasks as $task)<tr>
        <td><a href="{{ route('approvals.show', $task) }}">{{ $task->document_number ?? '#'.$task->approvable_id }}</a></td>
        <td>{{ $task->module }}</td><td>{{ $task->requester?->name }}</td>
        <td>{{ $task->amount_snapshot ?? '—' }}</td><td>{{ $task->status }}</td><td>{{ $task->requested_at }}</td>
    </tr>@empty<tr><td colspan="6">لا توجد مهام اعتماد.</td></tr>@endforelse
    </tbody></table>{{ $tasks->links() }}
</div>
@endsection
