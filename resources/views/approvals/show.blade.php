@extends('layouts.app')
@section('title', 'تفاصيل الاعتماد')
@section('page-title', 'تفاصيل مهمة الاعتماد')
@section('content')
<div class="sw-card">
    <table class="sw-table"><tbody>
        <tr><th>المستند</th><td>{{ $task->document_number }}</td></tr>
        <tr><th>الموديول</th><td>{{ $task->module }}</td></tr>
        <tr><th>الحالة</th><td>{{ $task->status }}</td></tr>
        <tr><th>المبلغ</th><td>{{ $task->amount_snapshot ?? '—' }}</td></tr>
        <tr><th>مقدم الطلب</th><td>{{ $task->requester?->name }}</td></tr>
        <tr><th>الصلاحية المطلوبة</th><td>{{ $task->required_permission }}</td></tr>
    </tbody></table>
    @if($task->status === 'pending' && auth()->user()->hasPermission('approvals.act'))
        <form method="POST" action="{{ route('approvals.decide', [$task, 'approve']) }}" style="display:inline">@csrf<button class="sw-btn">اعتماد</button></form>
        <form method="POST" action="{{ route('approvals.decide', [$task, 'reject']) }}" class="sw-form">@csrf
            <input name="reason" maxlength="500" required placeholder="سبب الرفض"><button class="sw-btn">رفض</button>
        </form>
    @endif
</div>
<div class="sw-card"><h2>سجل القرارات</h2><table class="sw-table"><thead><tr><th>الإجراء</th><th>المنفذ</th><th>السبب</th><th>الوقت</th></tr></thead><tbody>
@forelse($task->actions as $action)<tr><td>{{ $action->action }}</td><td>#{{ $action->actor_id }}</td><td>{{ $action->reason }}</td><td>{{ $action->occurred_at }}</td></tr>
@empty<tr><td colspan="4">لا توجد قرارات سابقة.</td></tr>@endforelse
</tbody></table></div>
@endsection
