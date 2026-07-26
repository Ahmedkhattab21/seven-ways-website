@extends('layouts.app')
@section('title','المطابقات المحاسبية') @section('page-title','المطابقات المحاسبية')
@section('content') @include('accounting.reports._filters')
<div class="sw-card"><table class="sw-table"><tbody><tr><th>GL Balance</th><td>{{ $report['gl_balance'] }}</td></tr><tr><th>Operational Balance</th><td>{{ $report['operational_balance'] }}</td></tr><tr><th>Difference</th><td>{{ $report['difference'] }}</td></tr><tr><th>Unposted Documents</th><td>{{ $report['unposted_documents'] }}</td></tr></tbody></table></div>
@endsection
