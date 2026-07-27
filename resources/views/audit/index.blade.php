@extends('layouts.app')
@section('title', 'سجل التدقيق')
@section('page-title', 'سجل التدقيق الموحد')
@section('content')
<div class="sw-card"><form method="GET" class="sw-form"><div class="sw-form-grid"><input name="module" value="{{ request('module') }}" placeholder="الموديول"><input name="action" value="{{ request('action') }}" placeholder="الإجراء"><input name="document_number" value="{{ request('document_number') }}" placeholder="رقم المستند"><input name="correlation_id" value="{{ request('correlation_id') }}" placeholder="Correlation ID"><button class="sw-btn">بحث</button></div></form>
<table class="sw-table"><thead><tr><th>الحدث</th><th>الموديول</th><th>المستخدم</th><th>المستند</th><th>Correlation</th><th>الوقت</th></tr></thead><tbody>
@forelse($events as $row)<tr><td>{{ $row->event_type }}</td><td>{{ $row->module }}</td><td>#{{ $row->user_id }}</td><td>{{ $row->document_number }}</td><td>{{ $row->correlation_id }}</td><td>{{ $row->occurred_at }}</td></tr>
@empty<tr><td colspan="6">لا توجد أحداث.</td></tr>@endforelse
</tbody></table>{{ $events->links() }}</div>
@endsection
