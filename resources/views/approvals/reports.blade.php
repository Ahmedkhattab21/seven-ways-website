@extends('layouts.app')
@section('title', 'تقارير الاعتمادات')
@section('page-title', 'تقارير الاعتمادات والإشعارات')
@section('content')
<div class="sw-card"><h2>ملخص الاعتمادات</h2>
<table class="sw-table"><thead><tr><th>الحالة</th><th>العدد</th></tr></thead><tbody>@foreach($byStatus as $status => $total)<tr><td>{{ $status }}</td><td>{{ $total }}</td></tr>@endforeach</tbody></table>
<p>المهام المتأخرة: {{ $overdue }} — القرارات المفوضة: {{ $delegated }}</p></div>
<div class="sw-card"><h2>حسب الموديول</h2><table class="sw-table"><tbody>@foreach($byModule as $module => $total)<tr><td>{{ $module }}</td><td>{{ $total }}</td></tr>@endforeach</tbody></table></div>
<div class="sw-card"><h2>الإشعارات الخاصة بك</h2><p>الإجمالي: {{ $notificationStats->total ?? 0 }} — غير المقروء: {{ $notificationStats->unread ?? 0 }}</p></div>
@endsection
