@extends('layouts.app')
@section('title','تقارير الإقفال') @section('page-title','تقارير الإقفال')
@section('content')
<div class="sw-grid sw-grid-3"><div class="sw-card"><h3>Closing Runs</h3><p>{{ $runs->count() }}</p></div><div class="sw-card"><h3>Exceptions</h3><p>{{ $exceptions->count() }}</p></div><div class="sw-card"><h3>Adjustments</h3><p>{{ $adjustments->count() }}</p></div><div class="sw-card"><h3>Scheduled Reversals</h3><p>{{ $reversals->count() }}</p></div></div>
@endsection
