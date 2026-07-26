@extends('layouts.app')
@section('title','المستندات غير المرحلة') @section('page-title','المستندات غير المرحلة')
@section('content') @include('accounting.reports._filters')
<div class="sw-card"><table class="sw-table"><thead><tr><th>النوع</th><th>المصدر</th><th>التاريخ</th><th>المبلغ</th><th>السبب</th></tr></thead><tbody>@foreach($sources as $source)<tr><td>{{ class_basename($source['source_type']) }}</td><td>{{ $source['source_number'] }}</td><td>{{ $source['date'] }}</td><td>{{ $source['amount'] }}</td><td>{{ $source['reason'] }}</td></tr>@endforeach</tbody></table></div>
@endsection
