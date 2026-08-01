@extends('layouts.app')

@section('title', 'المدفوعات')
@section('page-title', 'مدفوعات العملاء')
@section('breadcrumb', 'المبيعات / تحصيلات العملاء')

@section('page-actions')
    <a class="sw-button sw-button--primary" href="{{ route('customer-payments.create') }}">تسجيل دفعة</a>
@endsection

@section('content')
    <x-table-shell
        title="سجل مدفوعات العملاء"
        description="متابعة المبالغ المحصلة والمخصص منها والمتبقي لكل دفعة."
        class="customer-payments-table-card"
    >
        <thead>
            <tr>
                <th>الرقم</th>
                <th>العميل</th>
                <th>المبلغ</th>
                <th>المخصص</th>
                <th>المتبقي</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td><a href="{{ route('customer-payments.show', $payment) }}">{{ $payment->payment_number }}</a></td>
                    <td>{{ $payment->customer->name }}</td>
                    <td>{{ number_format((float) $payment->amount, 2) }}</td>
                    <td>{{ number_format((float) $payment->allocated_amount, 2) }}</td>
                    <td>{{ number_format((float) $payment->unallocated_amount, 2) }}</td>
                    <td><x-status-badge :status="$payment->status" /></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state
                            title="لا توجد مدفوعات مسجلة"
                            message="ابدأ بتسجيل أول دفعة عميل، وستظهر تفاصيلها هنا."
                            icon="wallet"
                        >
                            <a class="sw-button sw-button--primary" href="{{ route('customer-payments.create') }}">تسجيل دفعة</a>
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if($payments->hasPages())
            <x-slot:footer>{{ $payments->links() }}</x-slot:footer>
        @endif
    </x-table-shell>
@endsection
