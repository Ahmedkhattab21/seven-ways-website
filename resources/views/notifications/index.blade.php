@extends('layouts.app')
@section('title', 'الإشعارات')
@section('page-title', 'مركز الإشعارات')
@section('content')
<div class="sw-card"><form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="sw-btn">تحديد الكل كمقروء</button></form>
<table class="sw-table"><thead><tr><th>النوع</th><th>العنوان</th><th>الرسالة</th><th>الوقت</th><th></th></tr></thead><tbody>
@forelse($notifications as $row)<tr><td>{{ $row->type }}</td><td>{{ $row->title }}</td><td>{{ $row->message }}</td><td>{{ $row->created_at }}</td><td>@if(!$row->read_at)<form method="POST" action="{{ route('notifications.read', $row) }}">@csrf<button class="sw-btn">مقروء</button></form>@endif</td></tr>
@empty<tr><td colspan="5">لا توجد إشعارات.</td></tr>@endforelse
</tbody></table>{{ $notifications->links() }}</div>
@endsection
