@extends('layouts.app')
@section('title',$invoice->invoice_number) @section('page-title',$invoice->invoice_number)
@section('content')
<div class="sw-card"><p>{{ $invoice->customer_name_snapshot }} — {{ $invoice->status }}</p><p>الإجمالي: {{ $invoice->total }} | المدفوع: {{ $invoice->paid_amount }} | الرصيد: {{ $invoice->balance_due }}</p><a class="sw-btn" href="{{ route('sales-invoices.print',$invoice) }}">طباعة</a></div>
<div class="sw-card"><table class="sw-table"><thead><tr><th>الوصف</th><th>الكمية</th><th>السعر</th><th>الخصم</th><th>الضريبة</th><th>الإجمالي</th></tr></thead><tbody>@foreach($invoice->items as $item)<tr><td>{{ $item->description }}</td><td>{{ $item->quantity }}</td><td>{{ $item->unit_price }}</td><td>{{ $item->discount_amount }}</td><td>{{ $item->tax_amount }}</td><td>{{ $item->total }}</td></tr>@endforeach</tbody></table></div>
@foreach(['draft'=>'submit','pending_approval'=>'approve','approved'=>'issue'] as $status=>$action)@if($invoice->status===$status)<form class="sw-card" method="POST" action="{{ route('sales-invoices.action',[$invoice,$action]) }}">@csrf<button class="sw-btn">{{ $action }}</button></form>@endif @endforeach
@if(in_array($invoice->status,['issued','partially_paid','paid','overdue']))<a class="sw-btn" href="{{ route('sales-credit-notes.create',$invoice) }}">إشعار دائن</a>@endif
@endsection
