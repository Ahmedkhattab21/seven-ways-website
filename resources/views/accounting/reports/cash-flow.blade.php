@extends('layouts.app')
@section('title','التدفقات النقدية') @section('page-title','التدفقات النقدية — Direct Foundation')
@section('content') @include('accounting.reports._filters')
@if($warning)<div class="sw-alert">{{ $warning }}</div>@endif
<div class="sw-card"><table class="sw-table"><tbody><tr><th>Opening Cash</th><td>{{ $opening_cash }}</td></tr><tr><th>Operating</th><td>{{ $operating }}</td></tr><tr><th>Investing</th><td>{{ $investing }}</td></tr><tr><th>Financing</th><td>{{ $financing }}</td></tr><tr><th>Unclassified</th><td>{{ $unclassified }}</td></tr><tr><th>Net Change</th><td>{{ $net_change }}</td></tr><tr><th>Closing Cash</th><td>{{ $closing_cash }}</td></tr></tbody></table></div>
@endsection
