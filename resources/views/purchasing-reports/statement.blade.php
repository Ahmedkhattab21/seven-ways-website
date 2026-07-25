@extends('layouts.app')
@section('title','كشف حساب المورد') @section('page-title','كشف حساب '.$supplier->name)
@section('content')
<div class="sw-card"><table class="sw-table"><thead><tr><th>التاريخ</th><th>النوع</th><th>المرجع</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr></thead><tbody>@foreach($statement['entries'] as $entry)<tr><td>{{ $entry['date'] }}</td><td>{{ $entry['type'] }}</td><td>{{ $entry['reference'] }}</td><td>{{ $entry['debit'] }}</td><td>{{ $entry['credit'] }}</td><td>{{ $entry['balance'] }}</td></tr>@endforeach</tbody></table></div>
@endsection
