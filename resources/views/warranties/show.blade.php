@extends('layouts.app')
@section('title', $warranty->warranty_number)
@section('breadcrumb', 'الضمان')
@section('page-title', $warranty->warranty_number)
@section('content')
<div class="sw-card"><p>{{ $warranty->customer->name }} — {{ $warranty->vehicle->plate_number }}</p><p>{{ $warranty->start_date }} إلى {{ $warranty->end_date }} — {{ $warranty->status }}</p><a class="sw-btn" href="{{ route('warranties.print', $warranty) }}">طباعة</a></div>
<div class="sw-card sw-table-wrap"><table class="sw-table"><thead><tr><th>الخدمة</th><th>المدة</th><th>النهاية</th><th>الحالة</th></tr></thead><tbody>@foreach($warranty->items as $item)<tr><td>{{ $item->service?->name }}</td><td>{{ $item->warranty_months }} شهر</td><td>{{ $item->end_date }}</td><td>{{ $item->status }}</td></tr>@endforeach</tbody></table></div>
@if($warranty->status === 'active' && auth()->user()->hasPermission('warranties.void'))<form class="sw-card" method="POST" action="{{ route('warranties.void', $warranty) }}">@csrf<input name="reason" required placeholder="سبب الإبطال"><button class="sw-btn">إبطال الضمان</button></form>@endif
@endsection
