@extends('layouts.app')
@section('title', 'مطالبات الضمان')
@section('breadcrumb', 'الضمان')
@section('page-title', 'مطالبات الضمان')
@section('content')
<div class="sw-card"><a class="sw-btn" href="{{ route('warranty-claims.create') }}">مطالبة جديدة</a></div>
<div class="sw-card sw-table-wrap"><table class="sw-table"><thead><tr><th>الرقم</th><th>الضمان</th><th>العميل</th><th>الحالة</th><th>القرار</th><th></th></tr></thead><tbody>@foreach($claims as $claim)<tr><td>{{ $claim->claim_number }}</td><td>{{ $claim->warranty->warranty_number }}</td><td>{{ $claim->customer->name }}</td><td>{{ $claim->status }}</td><td>{{ $claim->decision }}</td><td><a href="{{ route('warranty-claims.show', $claim) }}">عرض</a></td></tr>@endforeach</tbody></table>{{ $claims->links() }}</div>
@endsection
