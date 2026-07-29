@extends('layouts.app')

@section('title', 'الحسابات البنكية')
@section('page-title', 'الحسابات البنكية')

@section('content')
<div class="treasury-maintenance-page">
    @if(auth()->user()->hasPermission('treasury.bank_accounts.create'))
        <form class="sw-card sw-form treasury-form-card" method="POST" action="{{ route('treasury.bank-accounts.store') }}">
            @csrf
            <header class="sw-card__header">
                <div>
                    <h3 class="sw-card__title">حساب بنكي جديد</h3>
                    <p class="sw-card__subtitle">اربط الحساب بالبنك والعملة وحساب الأستاذ العام.</p>
                </div>
            </header>

            <div class="sw-card__body">
                <div class="sw-form-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">البنك</span>
                        <select class="sw-input" name="bank_id" required>
                            <option value="">اختر البنك</option>
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الفرع</span>
                        <select class="sw-input" name="branch_id">
                            <option value="">كل الشركة</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">كود الحساب</span>
                        <input class="sw-input" name="account_code" required>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">اسم الحساب</span>
                        <input class="sw-input" name="account_name" required>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">IBAN</span>
                        <input class="sw-input" name="iban" dir="ltr">
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">العملة</span>
                        <select class="sw-input" name="currency_id" required>
                            @foreach($currencies as $currency)
                                <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">حساب GL</span>
                        <select class="sw-input" name="gl_account_id" required>
                            <option value="">اختر حساب GL</option>
                            @foreach($glAccounts->where('is_bank_account', true) as $account)
                                <option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">نوع الحساب</span>
                        <select class="sw-input" name="account_type" required>
                            @foreach(['current','savings','merchant','collection','payroll','credit_card','other'] as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="treasury-form-footer">
                    <label class="sw-check">
                        <input type="checkbox" name="is_primary" value="1">
                        <span class="sw-check__box"></span>
                        حساب رئيسي
                    </label>
                    <button class="sw-button sw-button--primary" type="submit">حفظ كمسودة</button>
                </div>
            </div>
        </form>
    @endif

    <div class="sw-card sw-table-wrap">
        <table class="sw-table treasury-bank-accounts-table">
            <thead>
                <tr>
                    <th>الكود</th>
                    <th>البنك</th>
                    <th>الفرع</th>
                    <th>العملة</th>
                    <th>الرصيد الدفتري</th>
                    <th>الحالة</th>
                    <th>صلاحية الفرع</th>
                    <th>الإجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bankAccounts as $account)
                    <tr>
                        <td>{{ $account->account_code }}</td>
                        <td>{{ $account->bank->name_ar }}</td>
                        <td>{{ $account->branch?->name ?? 'كل الشركة' }}</td>
                        <td>{{ $account->currency->code }}</td>
                        <td>{{ $balances[$account->id]['book_balance'] }}</td>
                        <td><span class="sw-badge">{{ $account->status }}</span></td>
                        <td>
                            @if(auth()->user()->hasPermission('treasury.bank_accounts.manage_branch_access'))
                                <form class="bank-access-form" method="POST" action="{{ route('treasury.bank-accounts.access', $account) }}">
                                    @csrf
                                    <select class="sw-input" name="branch_id" required>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected($account->branch_id === $branch->id)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="bank-access-form__checks">
                                        @foreach(['can_view' => 'عرض', 'can_receive' => 'استقبال', 'can_pay' => 'دفع', 'can_transfer' => 'تحويل'] as $permission => $label)
                                            <label class="sw-check">
                                                <input type="checkbox" name="{{ $permission }}" value="1" @checked(in_array($permission, ['can_view', 'can_receive']))>
                                                <span class="sw-check__box"></span>
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <button class="sw-button sw-button--secondary" type="submit">حفظ الصلاحية</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="treasury-actions">
                                @foreach(['activate' => 'تفعيل', 'suspend' => 'تعليق', 'close' => 'إغلاق'] as $action => $label)
                                    @if(auth()->user()->hasPermission('treasury.bank_accounts.'.$action))
                                        <form method="POST" action="{{ route('treasury.bank-accounts.action', [$account, $action]) }}">
                                            @csrf
                                            <input type="hidden" name="reason" value="تحديث حالة الحساب البنكي">
                                            <button class="sw-button {{ $action === 'close' ? 'sw-button--danger' : 'sw-button--secondary' }}" type="submit">{{ $label }}</button>
                                        </form>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="treasury-empty-row">لا توجد حسابات بنكية حتى الآن.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
