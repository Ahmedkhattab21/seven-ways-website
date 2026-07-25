@extends('layouts.app')
@section('title','تقويم الحجوزات')
@section('content')
<div class="sw-page-header"><div><h1>تقويم الحجوزات</h1><p>عرض شهري خفيف قابل للتصفية.</p></div><a class="sw-btn" href="{{ route('appointments.index') }}">القائمة</a></div>
<form class="sw-filter-bar" method="GET"><input type="date" name="from" value="{{ request('from',today()->startOfMonth()->format('Y-m-d')) }}"><input type="date" name="to" value="{{ request('to',today()->endOfMonth()->format('Y-m-d')) }}"><select name="branch_id"><option value="">كل الفروع</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select><button class="sw-btn">عرض</button></form>
<div class="sw-card"><div class="sw-grid">@forelse($appointments->groupBy(fn($appointment)=>$appointment->scheduled_start->format('Y-m-d')) as $date=>$rows)<section class="sw-card"><h3>{{ $date }}</h3>@foreach($rows as $appointment)<p><a href="{{ route('appointments.show',$appointment) }}">{{ $appointment->scheduled_start->format('H:i') }} — {{ $appointment->customer->name }}</a><br><small>{{ $appointment->branch->name }} | {{ $appointment->status }}</small></p>@endforeach</section>@empty<p>لا توجد مواعيد في الفترة.</p>@endforelse</div></div>
@endsection
