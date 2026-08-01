@extends('layouts.app')

@section('title', 'تسجيل دفعة')
@section('page-title', 'تسجيل دفعة تشغيلية')
@section('breadcrumb', 'المبيعات / تحصيلات العملاء / تسجيل دفعة')

@section('content')
    <x-card
        title="بيانات الدفعة"
        subtitle="الدفع النقدي يتطلب خزينة فرع وجلسة نشطة، وتُنشأ حركة القبض عند اعتماد الدفعة."
        class="customer-payment-form-card"
    >
        <form class="sw-form" method="POST" action="{{ route('customer-payments.store') }}" data-customer-payment-form>
            @csrf

            <div class="sw-form-grid">
                <x-form.select name="customer_id" label="العميل" required data-payment-customer>
                    <option value="">اختر العميل</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </x-form.select>

                <x-form.select name="payment_method_id" label="طريقة الدفع" required data-payment-method>
                    <option value="">اختر طريقة الدفع</option>
                    @foreach($methods as $method)
                        <option
                            value="{{ $method->id }}"
                            data-is-cash="{{ $method->isCash() ? '1' : '0' }}"
                            @selected((string) old('payment_method_id') === (string) $method->id)
                        >{{ $method->name }}</option>
                    @endforeach
                </x-form.select>

                <x-form.input
                    name="payment_date"
                    type="date"
                    label="تاريخ الدفع"
                    :value="$defaultPaymentDate"
                    required
                    data-payment-date
                />

                <x-form.input
                    name="amount"
                    type="number"
                    step="0.0001"
                    min="0.0001"
                    label="المبلغ"
                    placeholder="0.00"
                    required
                    data-payment-amount
                />

                <x-form.input
                    name="reference_number"
                    label="الرقم المرجعي"
                    placeholder="اختياري"
                    help="مثال: رقم التحويل أو رقم إيصال نقطة البيع."
                />
            </div>

            <section class="sw-form-section" data-cash-payment-fields hidden>
                <div>
                    <h3>استلام النقدية وتخصيصها</h3>
                    <p class="sw-help">اختر خزينة الفرع وجلستها النشطة ثم الفاتورة التي سيُخصص عليها المبلغ بعد الاعتماد.</p>
                </div>

                <div class="sw-form-grid">
                    <x-form.select name="cash_box_id" label="الخزينة" data-cash-box>
                        <option value="">اختر الخزينة</option>
                        @foreach($cashBoxes as $cashBox)
                            <option value="{{ $cashBox->id }}" @selected((string) old('cash_box_id') === (string) $cashBox->id)>
                                {{ $cashBox->code }} — {{ $cashBox->name }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.select name="cash_box_session_id" label="جلسة الخزينة" data-cash-session>
                        <option value="">اختر جلسة الخزينة</option>
                        @foreach($cashSessions as $session)
                            <option
                                value="{{ $session->id }}"
                                data-cash-box-id="{{ $session->cash_box_id }}"
                                data-business-date="{{ $session->business_date->toDateString() }}"
                                @selected((string) old('cash_box_session_id') === (string) $session->id)
                            >{{ $session->session_number }} — {{ $session->business_date->format('Y-m-d') }}</option>
                        @endforeach
                    </x-form.select>

                    <x-form.select name="sales_invoice_id" label="الفاتورة" data-sales-invoice>
                        <option value="">اختر الفاتورة</option>
                        @foreach($invoices as $invoice)
                            <option
                                value="{{ $invoice->id }}"
                                data-customer-id="{{ $invoice->customer_id }}"
                                @selected((string) old('sales_invoice_id') === (string) $invoice->id)
                            >
                                {{ $invoice->invoice_number }} — المتبقي {{ number_format((float) $invoice->balance_due, 2) }} {{ $invoice->currency->code }}
                            </option>
                        @endforeach
                    </x-form.select>

                    <x-form.input
                        name="allocation_amount"
                        type="number"
                        step="0.0001"
                        min="0.0001"
                        label="المبلغ المخصص"
                        placeholder="0.00"
                        data-allocation-amount
                    />
                </div>
            </section>

            <div class="sw-form-actions">
                <x-button type="submit">تسجيل الدفعة</x-button>
                <a class="sw-button sw-button--outline" href="{{ route('customer-payments.index') }}">إلغاء</a>
            </div>
        </form>
    </x-card>

    <script>
        (() => {
            const form = document.querySelector('[data-customer-payment-form]');
            if (!form) return;

            const method = form.querySelector('[data-payment-method]');
            const customer = form.querySelector('[data-payment-customer]');
            const paymentDate = form.querySelector('[data-payment-date]');
            const paymentAmount = form.querySelector('[data-payment-amount]');
            const cashFields = form.querySelector('[data-cash-payment-fields]');
            const cashBox = form.querySelector('[data-cash-box]');
            const cashSession = form.querySelector('[data-cash-session]');
            const invoice = form.querySelector('[data-sales-invoice]');
            const allocation = form.querySelector('[data-allocation-amount]');

            const isCash = () => method.selectedOptions[0]?.dataset.isCash === '1';
            const filterOptions = (select, predicate) => {
                Array.from(select.options).forEach((option, index) => {
                    if (index === 0) return;
                    const visible = predicate(option);
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (!visible && option.selected) select.value = '';
                });
            };
            const syncSessions = () => filterOptions(cashSession, (option) =>
                option.dataset.cashBoxId === cashBox.value
            );
            const syncInvoices = () => filterOptions(invoice, (option) =>
                option.dataset.customerId === customer.value
            );
            const syncCashFields = () => {
                const cash = isCash();
                cashFields.hidden = !cash;
                [cashBox, cashSession, invoice, allocation].forEach((field) => field.required = cash);
                if (!cash) {
                    cashBox.value = '';
                    cashSession.value = '';
                }
                syncSessions();
                syncInvoices();
            };

            method.addEventListener('change', syncCashFields);
            customer.addEventListener('change', syncInvoices);
            cashBox.addEventListener('change', syncSessions);
            cashSession.addEventListener('change', () => {
                const businessDate = cashSession.selectedOptions[0]?.dataset.businessDate;
                if (businessDate) paymentDate.value = businessDate;
            });
            paymentAmount.addEventListener('input', () => {
                if (isCash() && !allocation.dataset.edited) allocation.value = paymentAmount.value;
            });
            allocation.addEventListener('input', () => allocation.dataset.edited = '1');
            syncCashFields();
        })();
    </script>
@endsection
