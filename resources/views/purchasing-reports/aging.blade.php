@extends('layouts.app')
@section('title','أعمار الديون الدائنة') @section('page-title','أعمار الديون الدائنة')
@section('content')
<div class="sw-card"><table class="sw-table"><thead><tr><th>العملة</th><th>حالي</th><th>1-30</th><th>31-60</th><th>61-90</th><th>90+</th></tr></thead><tbody>@foreach($aging as $currency=>$buckets)<tr><td>{{ $currency }}</td><td>{{ $buckets['current'] }}</td><td>{{ $buckets['1_30'] }}</td><td>{{ $buckets['31_60'] }}</td><td>{{ $buckets['61_90'] }}</td><td>{{ $buckets['90_plus'] }}</td></tr>@endforeach</tbody></table></div>
@endsection
