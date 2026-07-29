@extends('layouts.app')
@section('title','فواتير المبيعات') @section('page-title','فواتير المبيعات')
@section('content')
<div class="sw-page-actions"><a class="sw-btn" href="{{ route('sales-invoices.create') }}">إضافة فاتورة مبيعات</a></div>
<div class="sw-card"><table class="sw-table"><thead><tr><th>الرقم</th><th>العميل</th><th>الفرع</th><th>التاريخ</th><th>الإجمالي</th><th>المدفوع</th><th>الرصيد</th><th>الحالة</th></tr></thead><tbody>@foreach($invoices as $invoice)<tr><td><a href="{{ route('sales-invoices.show',$invoice) }}">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->customer_name_snapshot }}</td><td>{{ $invoice->branch->name }}</td><td>{{ $invoice->invoice_date }}</td><td>{{ $invoice->total }}</td><td>{{ $invoice->paid_amount }}</td><td>{{ $invoice->balance_due }}</td><td>{{ $invoice->status }}</td></tr>@endforeach</tbody></table>{{ $invoices->links() }}</div>
@endsection
