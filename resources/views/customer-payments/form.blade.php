@extends('layouts.app')

@section('title', 'تسجيل دفعة')
@section('page-title', 'تسجيل دفعة تشغيلية')
@section('breadcrumb', 'المبيعات / تحصيلات العملاء / تسجيل دفعة')

@section('content')
    <x-card
        title="بيانات الدفعة"
        subtitle="سجّل بيانات التحصيل، ويمكن تخصيص المبلغ على الفواتير بعد حفظ الدفعة."
        class="customer-payment-form-card"
    >
        <form class="sw-form" method="POST" action="{{ route('customer-payments.store') }}">
            @csrf

            <div class="sw-form-grid">
                <x-form.select name="customer_id" label="العميل" required>
                    <option value="">اختر العميل</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </x-form.select>

                <x-form.select name="payment_method_id" label="طريقة الدفع" required>
                    <option value="">اختر طريقة الدفع</option>
                    @foreach($methods as $method)
                        <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                    @endforeach
                </x-form.select>

                <x-form.input
                    name="payment_date"
                    type="date"
                    label="تاريخ الدفع"
                    :value="today()->toDateString()"
                    required
                />

                <x-form.input
                    name="amount"
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    label="المبلغ"
                    placeholder="0.00"
                    required
                />

                <x-form.input
                    name="reference_number"
                    label="الرقم المرجعي"
                    placeholder="اختياري"
                    help="مثال: رقم التحويل أو رقم إيصال نقطة البيع."
                />
            </div>

            <div class="sw-form-actions">
                <x-button type="submit">تسجيل الدفعة</x-button>
                <a class="sw-button sw-button--outline" href="{{ route('customer-payments.index') }}">إلغاء</a>
            </div>
        </form>
    </x-card>
@endsection
