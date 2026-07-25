@extends('layouts.app')
@section('title', 'الضمانات')
@section('breadcrumb', 'الضمان')
@section('page-title', 'الضمانات')
@section('content')
<div class="sw-card sw-table-wrap"><table class="sw-table"><thead><tr><th>الرقم</th><th>العميل</th><th>السيارة</th><th>الحالة</th><th>النهاية</th><th></th></tr></thead><tbody>
@foreach($warranties as $warranty)<tr><td>{{ $warranty->warranty_number }}</td><td>{{ $warranty->customer->name }}</td><td>{{ $warranty->vehicle->plate_number }}</td><td>{{ $warranty->status }}</td><td>{{ $warranty->end_date }}</td><td><a href="{{ route('warranties.show', $warranty) }}">عرض</a></td></tr>@endforeach
</tbody></table>{{ $warranties->links() }}</div>
@endsection
