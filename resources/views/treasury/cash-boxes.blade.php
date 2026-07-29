@extends('layouts.app')

@section('title', 'الخزائن النقدية')
@section('page-title', 'الخزائن النقدية وأمناؤها')

@section('content')
<div class="cash-boxes-page">
    @if($errors->has('business'))
        <div class="sw-alert sw-alert--error">{{ $errors->first('business') }}</div>
    @endif

    @if(auth()->user()->hasPermission('treasury.cash_boxes.create'))
        <form class="sw-card sw-form cash-box-create-card" method="POST" action="{{ route('treasury.cash-boxes.store') }}">
            @csrf
            <header class="sw-card__header">
                <div>
                    <h3 class="sw-card__title">خزينة جديدة</h3>
                    <p class="sw-card__subtitle">أدخل بيانات الخزينة وربطها بالفرع والحساب النقدي.</p>
                </div>
            </header>

            <div class="sw-card__body">
                <div class="sw-form-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">الفرع</span>
                        <select class="sw-input" name="branch_id" required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الكود</span>
                        <input class="sw-input" name="code" required>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الاسم</span>
                        <input class="sw-input" name="name" required>
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
                        <span class="sw-field__label">حساب الخزينة</span>
                        <select class="sw-input" name="gl_account_id" required>
                            @foreach($glAccounts->where('is_cash_account', true) as $account)
                                <option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الحد الأقصى للنقدية</span>
                        <input class="sw-input" name="maximum_cash_limit" type="number" min="0" step="0.01">
                    </label>
                </div>

                <div class="cash-box-form-footer">
                    <label class="sw-check">
                        <input type="checkbox" name="is_primary" value="1">
                        <span class="sw-check__box"></span>
                        خزينة رئيسية
                    </label>
                    <button class="sw-button sw-button--primary" type="submit">حفظ كمسودة</button>
                </div>
            </div>
        </form>
    @endif

    <div class="cash-box-list">
        @foreach($cashBoxes as $box)
            <article class="sw-card cash-box-card">
                <header class="sw-card__header">
                    <div>
                        <h3 class="sw-card__title">{{ $box->name }}</h3>
                        <p class="sw-card__subtitle">{{ $box->branch->name }}</p>
                    </div>
                    <div class="cash-box-summary">
                        <span class="sw-badge">{{ $box->status }}</span>
                        <span>الرصيد الدفتري: <strong>{{ $balances[$box->id]['book_balance'] }}</strong></span>
                    </div>
                </header>

                <div class="sw-card__body cash-box-card__body">
                    @if(auth()->user()->hasPermission('treasury.cash_boxes.manage_custodians'))
                        <section class="cash-box-section">
                            <div class="cash-box-section__heading">
                                <h4>تعيين أمين جديد</h4>
                                <p>حدد صلاحيات الأمين وحد العملية النقدية بوضوح.</p>
                            </div>

                            <form class="sw-form" method="POST" action="{{ route('treasury.cash-boxes.custodians', $box) }}">
                                @csrf
                                <div class="sw-form-grid">
                                    <label class="sw-field">
                                        <span class="sw-field__label">أمين الخزينة</span>
                                        <select class="sw-input" name="user_id" required>
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="sw-field">
                                        <span class="sw-field__label">ساري من</span>
                                        <input class="sw-input" type="date" name="valid_from" value="{{ now()->toDateString() }}" required>
                                    </label>

                                    <label class="sw-field">
                                        <span class="sw-field__label">الحد الأقصى للعملية النقدية</span>
                                        <input class="sw-input" name="payment_limit" type="number" min="0" step="0.01">
                                    </label>
                                </div>

                                <div class="cash-box-abilities">
                                    <input type="hidden" name="can_receive" value="0">
                                    <label class="sw-check">
                                        <input type="checkbox" name="can_receive" value="1" checked>
                                        <span class="sw-check__box"></span>
                                        السماح بالقبض
                                    </label>

                                    <input type="hidden" name="can_pay" value="0">
                                    <label class="sw-check">
                                        <input type="checkbox" name="can_pay" value="1">
                                        <span class="sw-check__box"></span>
                                        السماح بالصرف
                                    </label>

                                    <input type="hidden" name="can_transfer" value="0">
                                    <label class="sw-check">
                                        <input type="checkbox" name="can_transfer" value="1">
                                        <span class="sw-check__box"></span>
                                        السماح بالتحويل
                                    </label>

                                    <input type="hidden" name="is_primary" value="0">
                                    <label class="sw-check">
                                        <input type="checkbox" name="is_primary" value="1">
                                        <span class="sw-check__box"></span>
                                        أمين رئيسي
                                    </label>
                                </div>

                                <div class="sw-form-actions">
                                    <button class="sw-button sw-button--primary" type="submit">تعيين أمين</button>
                                </div>
                            </form>
                        </section>
                    @endif

                    <section class="cash-box-section">
                        <div class="cash-box-section__heading">
                            <h4>أمناء الخزينة النشطون</h4>
                            <p>راجع الصلاحيات وحدود الصرف وفترة التكليف.</p>
                        </div>

                        <div class="cash-box-custodians">
                            @forelse($box->custodians->where('is_active', true) as $custodian)
                                <article class="cash-box-custodian">
                                    <div class="cash-box-custodian__header">
                                        <div>
                                            <strong>{{ $custodian->user->name }}</strong>
                                            <span>{{ $custodian->is_primary ? 'أمين رئيسي' : 'أمين مساعد' }}</span>
                                        </div>
                                        <div class="cash-box-custodian__period">
                                            من {{ $custodian->valid_from?->format('Y-m-d') }}
                                            إلى {{ $custodian->valid_to?->format('Y-m-d') ?? 'مفتوح' }}
                                        </div>
                                    </div>

                                    <div class="cash-box-custodian__details">
                                        <span>قبض: <strong>{{ $custodian->can_receive ? 'نعم' : 'لا' }}</strong></span>
                                        <span>صرف: <strong>{{ $custodian->can_pay ? 'نعم' : 'لا' }}</strong></span>
                                        <span>تحويل: <strong>{{ $custodian->can_transfer ? 'نعم' : 'لا' }}</strong></span>
                                        <span>الحد: <strong>{{ $custodian->payment_limit ?? 'غير محدد' }}</strong></span>
                                    </div>

                                    <form class="sw-form cash-box-custodian__form" method="POST" action="{{ route('treasury.cash-box-custodians.update', $custodian) }}">
                                        @csrf
                                        @method('PUT')

                                        <div class="cash-box-abilities">
                                            <input type="hidden" name="can_receive" value="0">
                                            <label class="sw-check">
                                                <input type="checkbox" name="can_receive" value="1" @checked($custodian->can_receive)>
                                                <span class="sw-check__box"></span>
                                                قبض
                                            </label>

                                            <input type="hidden" name="can_pay" value="0">
                                            <label class="sw-check">
                                                <input type="checkbox" name="can_pay" value="1" @checked($custodian->can_pay)>
                                                <span class="sw-check__box"></span>
                                                صرف
                                            </label>

                                            <input type="hidden" name="can_transfer" value="0">
                                            <label class="sw-check">
                                                <input type="checkbox" name="can_transfer" value="1" @checked($custodian->can_transfer)>
                                                <span class="sw-check__box"></span>
                                                تحويل
                                            </label>

                                            <input type="hidden" name="is_primary" value="0">
                                            <label class="sw-check">
                                                <input type="checkbox" name="is_primary" value="1" @checked($custodian->is_primary)>
                                                <span class="sw-check__box"></span>
                                                رئيسي
                                            </label>
                                        </div>

                                        <div class="sw-form-grid">
                                            <label class="sw-field">
                                                <span class="sw-field__label">حد الصرف</span>
                                                <input class="sw-input" name="payment_limit" type="number" min="0" step="0.01" value="{{ $custodian->payment_limit }}">
                                            </label>
                                            <label class="sw-field">
                                                <span class="sw-field__label">ساري حتى</span>
                                                <input class="sw-input" type="date" name="valid_to" value="{{ $custodian->valid_to?->format('Y-m-d') }}">
                                            </label>
                                        </div>

                                        <div class="sw-form-actions">
                                            <button class="sw-button sw-button--secondary" type="submit">تحديث التكليف</button>
                                        </div>
                                    </form>
                                </article>
                            @empty
                                <div class="cash-box-empty">لا يوجد أمناء نشطون لهذه الخزينة.</div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </article>
        @endforeach
    </div>
</div>
@endsection
