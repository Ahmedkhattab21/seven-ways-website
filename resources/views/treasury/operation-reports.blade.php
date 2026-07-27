@extends('layouts.app')
@section('title', 'تقارير عمليات الخزينة')
@section('page-title', 'تقارير عمليات الخزينة')
@section('content')
<div class="sw-card"><h3>Pending Treasury Operations</h3><p>{{ $pendingCount }}</p></div>
<div class="sw-card"><h3>Open Cash Sessions</h3>@foreach($openSessions as $row)<p>{{ $row->session_number }} — {{ $row->status }}</p>@endforeach</div>
<div class="sw-card"><h3>Cash Count / Over-Short Register</h3>@foreach($counts as $row)<p>#{{ $row->id }} {{ $row->count_type }} {{ $row->difference }}</p>@endforeach @foreach($differences as $row)<p>{{ $row->adjustment_type }} {{ $row->amount }} {{ $row->status }}</p>@endforeach</div>
<div class="sw-card"><h3>Treasury Transfer Register</h3>@foreach($transfers as $row)<p>{{ $row->document_number }} — {{ $row->amount }} — {{ $row->status }}</p>@endforeach</div>
<div class="sw-card"><h3>Cheque Register / Aging / Bounced</h3>@foreach($cheques as $row)<p>{{ $row->maskedNumber() }} — {{ $row->due_date->toDateString() }} — {{ $row->status }}</p>@endforeach</div>
<div class="sw-card"><h3>Merchant Settlement Register</h3>@foreach($settlements as $row)<p>{{ $row->document_number }} — {{ $row->net_amount }} — {{ $row->status }}</p>@endforeach</div>
@endsection
