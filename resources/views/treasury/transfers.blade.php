@extends('layouts.app')

@section('title', 'تحويلات الخزينة')
@section('page-title', 'تحويلات الخزينة')

@section('content')
<div class="treasury-maintenance-page">
    <div class="sw-alert sw-alert--info treasury-info-card">
        التحويل المعتمد يُنفذ ويُرحل مرة واحدة. التحويل المكتمل غير قابل للتعديل، والعكس ينشئ قيدًا معاكسًا في فترة مفتوحة.
    </div>

    @if(auth()->user()->hasPermission('treasury.transfers.create'))
        <form class="sw-card sw-form treasury-form-card" method="POST" action="{{ route('treasury.transfers.store') }}">
            @csrf
            <header class="sw-card__header">
                <div>
                    <h3 class="sw-card__title">تحويل جديد</h3>
                    <p class="sw-card__subtitle">حدد المصدر والوجهة وقيمة التحويل قبل حفظ المسودة.</p>
                </div>
            </header>

            <div class="sw-card__body">
                <div class="sw-form-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">نوع العملية</span>
                        <select class="sw-input" name="transfer_type">
                            <option value="transfer">تحويل</option>
                            <option value="cash_deposit">إيداع نقدي</option>
                            <option value="cash_withdrawal">سحب نقدي</option>
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الفرع</span>
                        <select class="sw-input" name="branch_id">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="sw-form-section treasury-transfer-side">
                        <h3>مصدر التحويل</h3>
                        <div class="sw-form-grid">
                            <label class="sw-field">
                                <span class="sw-field__label">نوع المصدر</span>
                                <select class="sw-input" name="from_type">
                                    <option value="bank">حساب بنكي</option>
                                    <option value="cash_box">خزينة</option>
                                </select>
                            </label>
                            <label class="sw-field">
                                <span class="sw-field__label">الحساب البنكي المصدر</span>
                                <select class="sw-input" name="from_bank_account_id">
                                    <option value="">بدون حساب بنكي</option>
                                    @foreach($bankAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="sw-field">
                                <span class="sw-field__label">الخزينة المصدر</span>
                                <select class="sw-input" name="from_cash_box_id">
                                    <option value="">بدون خزينة</option>
                                    @foreach($cashBoxes as $box)
                                        <option value="{{ $box->id }}">{{ $box->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="sw-form-section treasury-transfer-side">
                        <h3>وجهة التحويل</h3>
                        <div class="sw-form-grid">
                            <label class="sw-field">
                                <span class="sw-field__label">نوع الوجهة</span>
                                <select class="sw-input" name="to_type">
                                    <option value="bank">حساب بنكي</option>
                                    <option value="cash_box">خزينة</option>
                                </select>
                            </label>
                            <label class="sw-field">
                                <span class="sw-field__label">الحساب البنكي المستلم</span>
                                <select class="sw-input" name="to_bank_account_id">
                                    <option value="">بدون حساب بنكي</option>
                                    @foreach($bankAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="sw-field">
                                <span class="sw-field__label">الخزينة المستلمة</span>
                                <select class="sw-input" name="to_cash_box_id">
                                    <option value="">بدون خزينة</option>
                                    @foreach($cashBoxes as $box)
                                        <option value="{{ $box->id }}">{{ $box->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </div>

                    <label class="sw-field">
                        <span class="sw-field__label">العملة</span>
                        <select class="sw-input" name="currency_id">
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">سعر الصرف</span>
                        <input class="sw-input" name="exchange_rate" value="1" type="number" step="1" readonly>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">المبلغ</span>
                        <input class="sw-input" name="amount" required type="number" step="0.01" min="0.01">
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">رسوم التحويل</span>
                        <input class="sw-input" name="fees_amount" value="0" type="number" step="0.01">
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">تاريخ التحويل</span>
                        <input class="sw-input" name="transfer_date" type="date" value="{{ now()->toDateString() }}" required>
                    </label>
                </div>

                <div class="sw-form-actions treasury-form-actions">
                    <button class="sw-button sw-button--primary" type="submit">حفظ كمسودة</button>
                </div>
            </div>
        </form>
    @endif

    <div class="sw-card sw-table-wrap">
        <table class="sw-table">
            <thead>
                <tr>
                    <th>الرقم</th>
                    <th>النوع</th>
                    <th>التاريخ</th>
                    <th>القيمة</th>
                    <th>الحالة</th>
                    <th>القيد</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $transfer)
                    <tr>
                        <td>{{ $transfer->document_number }}</td>
                        <td>{{ $transfer->transfer_type }}</td>
                        <td>{{ $transfer->transfer_date->toDateString() }}</td>
                        <td>{{ $transfer->amount }} + {{ $transfer->fees_amount }}</td>
                        <td>
                            {{ $transfer->status }}
                            @if($transfer->failure_reason)
                                <small>{{ $transfer->failure_reason }}</small>
                            @endif
                        </td>
                        <td>{{ $transfer->journal_entry_id ?: '—' }}</td>
                        <td>
                            <div class="treasury-actions">
                                @php($workflowActions = match($transfer->status) {
                                    'draft' => ['submit','cancel'],
                                    'pending_approval' => ['approve','cancel'],
                                    'approved' => ['cancel'],
                                    default => []
                                })
                                @foreach($workflowActions as $action)
                                    @if(auth()->user()->hasPermission('treasury.transfers.'.$action))
                                        <form method="POST" action="{{ route('treasury.transfers.action', [$transfer, $action]) }}">
                                            @csrf
                                            @if($action === 'cancel')
                                                <input type="hidden" name="reason" value="Approved transfer cancellation">
                                            @endif
                                            <button class="sw-button sw-button--secondary" type="submit">{{ $action }}</button>
                                        </form>
                                    @endif
                                @endforeach

                                @if(in_array($transfer->status, ['approved','failed']) && auth()->user()->hasPermission('treasury.transfers.process'))
                                    <form method="POST" action="{{ route('treasury.transfers.process', $transfer) }}">
                                        @csrf
                                        <button class="sw-button sw-button--primary" type="submit">process</button>
                                    </form>
                                @endif

                                @if($transfer->status === 'completed' && auth()->user()->hasPermission('treasury.transfers.reverse'))
                                    <form method="POST" action="{{ route('treasury.transfers.reverse', $transfer) }}">
                                        @csrf
                                        <input type="hidden" name="reason" value="Approved transfer reversal">
                                        <button class="sw-button sw-button--danger" type="submit">reverse</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="treasury-empty-row">لا توجد تحويلات حتى الآن.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
