@extends('layouts.app')

@section('title', $direction === 'received' ? 'الشيكات الواردة' : 'الشيكات الصادرة')
@section('page-title', $direction === 'received' ? 'الشيكات الواردة' : 'الشيكات الصادرة')

@section('content')
<div class="treasury-maintenance-page">
    @if(auth()->user()->hasPermission('treasury.cheques.create'))
        <form class="sw-card sw-form treasury-form-card" method="POST" action="{{ route('treasury.cheques.store') }}">
            @csrf
            <input type="hidden" name="direction" value="{{ $direction }}">
            <input type="hidden" name="currency_id" value="{{ $company->currency_id }}">

            <header class="sw-card__header">
                <div>
                    <h3 class="sw-card__title">تسجيل شيك {{ $direction === 'received' ? 'وارد' : 'صادر' }}</h3>
                    <p class="sw-card__subtitle">أدخل بيانات الشيك والحسابات المرتبطة به.</p>
                </div>
            </header>

            <div class="sw-card__body">
                <div class="sw-form-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">الفرع</span>
                        <select class="sw-input" name="branch_id">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">رقم الشيك</span>
                        <input class="sw-input" name="cheque_number" required>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">البنك</span>
                        <select class="sw-input" name="bank_id">
                            @foreach($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الحساب البنكي</span>
                        <select class="sw-input" name="bank_account_id">
                            <option value="">بدون حساب بنكي</option>
                            @foreach($bankAccounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">المبلغ</span>
                        <input class="sw-input" name="amount" type="number" min="0.01" step="0.01" required>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">تاريخ الإصدار</span>
                        <input class="sw-input" name="issue_date" type="date" value="{{ now()->toDateString() }}" required>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">تاريخ الاستحقاق</span>
                        <input class="sw-input" name="due_date" type="date" value="{{ now()->addDays(30)->toDateString() }}" required>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">حساب المقاصة</span>
                        <select class="sw-input" name="clearing_account_id">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sw-field">
                        <span class="sw-field__label">الحساب المقابل</span>
                        <select class="sw-input" name="offset_account_id">
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if($direction === 'received')
                        <label class="sw-field">
                            <span class="sw-field__label">اسم الساحب</span>
                            <input class="sw-input" name="drawer_name">
                        </label>
                        <label class="sw-field">
                            <span class="sw-field__label">تاريخ الاستلام</span>
                            <input class="sw-input" name="received_date" type="date" value="{{ now()->toDateString() }}">
                        </label>
                    @else
                        <label class="sw-field">
                            <span class="sw-field__label">اسم المستفيد</span>
                            <input class="sw-input" name="beneficiary_name">
                        </label>
                    @endif
                </div>

                <div class="sw-form-actions treasury-form-actions">
                    <button class="sw-button sw-button--primary" type="submit">تسجيل الشيك</button>
                </div>
            </div>
        </form>
    @endif

    <div class="treasury-record-list">
        @forelse($cheques as $cheque)
            <section class="sw-card treasury-record-card">
                <header class="sw-card__header">
                    <div>
                        <h3 class="sw-card__title">
                            {{ auth()->user()->hasPermission('treasury.cheques.view_sensitive') ? $cheque->cheque_number : $cheque->maskedNumber() }}
                        </h3>
                        <p class="sw-card__subtitle">{{ $cheque->direction === 'received' ? 'شيك وارد' : 'شيك صادر' }}</p>
                    </div>
                    <div class="treasury-record-summary">
                        <strong>{{ $cheque->amount }}</strong>
                        <span class="sw-badge">{{ $cheque->status }}</span>
                    </div>
                </header>

                <div class="sw-card__body treasury-record-card__body">
                    @if($cheque->histories->isNotEmpty())
                        <div class="treasury-history">
                            <strong>سجل الحالات</strong>
                            <div>
                                @foreach($cheque->histories as $history)
                                    <span>{{ $history->to_status }} — {{ $history->changed_at }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="treasury-actions">
                        @php($chequeActions = match($cheque->status) {
                            'draft' => ['submit','cancel'],
                            'received' => ['approve','cancel'],
                            'on_hand' => $cheque->direction === 'received' ? ['deposit','return'] : [],
                            'issued' => $cheque->approved_by ? ['present','return'] : ['approve','cancel'],
                            'deposited', 'under_collection' => ['clear','return'],
                            'presented' => ['clear','return'],
                            'bounced' => ['return','replace'],
                            'returned', 'cancelled' => ['replace'],
                            default => []
                        })
                        @foreach($chequeActions as $action)
                            @continue($action === 'replace')
                            @if(auth()->user()->hasPermission('treasury.cheques.'.$action))
                                <form method="POST" action="{{ route('treasury.cheques.action', [$cheque, $action]) }}">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ now()->toDateString() }}">
                                    <input type="hidden" name="reason" value="Approved cheque action">
                                    <button class="sw-button sw-button--secondary" type="submit">{{ $action }}</button>
                                </form>
                            @endif
                        @endforeach

                        @if($cheque->status === 'cleared' && auth()->user()->hasPermission('treasury.cheques.bounce'))
                            <form method="POST" action="{{ route('treasury.cheques.bounce', $cheque) }}">
                                @csrf
                                <input type="hidden" name="date" value="{{ now()->toDateString() }}">
                                <input type="hidden" name="reason" value="QA cheque bounce">
                                <button class="sw-button sw-button--danger" type="submit">bounce</button>
                            </form>
                        @endif
                    </div>

                    @if(in_array($cheque->status, ['bounced','returned','cancelled']) && auth()->user()->hasPermission('treasury.cheques.replace'))
                        <form method="POST" action="{{ route('treasury.cheques.action', [$cheque, 'replace']) }}" class="sw-form treasury-inline-form">
                            @csrf
                            <h4>استبدال الشيك</h4>
                            <div class="sw-form-grid">
                                <label class="sw-field">
                                    <span class="sw-field__label">رقم الشيك البديل</span>
                                    <input class="sw-input" name="replacement_cheque_number" required>
                                </label>
                                <label class="sw-field">
                                    <span class="sw-field__label">تاريخ الإصدار</span>
                                    <input class="sw-input" name="replacement_issue_date" type="date" value="{{ now()->toDateString() }}" required>
                                </label>
                                <label class="sw-field">
                                    <span class="sw-field__label">تاريخ الاستحقاق</span>
                                    <input class="sw-input" name="replacement_due_date" type="date" value="{{ now()->addDays(30)->toDateString() }}" required>
                                </label>
                            </div>
                            <button class="sw-button sw-button--primary" type="submit">استبدال</button>
                        </form>
                    @endif

                    @if($cheque->direction === 'received' && $cheque->status === 'on_hand' && auth()->user()->hasPermission('treasury.cheques.endorse'))
                        <form method="POST" action="{{ route('treasury.cheques.endorse', $cheque) }}" class="sw-form treasury-inline-form">
                            @csrf
                            <input type="hidden" name="endorsed_to_type" value="other">
                            <h4>تظهير الشيك</h4>
                            <div class="sw-form-grid">
                                <label class="sw-field">
                                    <span class="sw-field__label">مظهر إليه</span>
                                    <input class="sw-input" name="endorsed_to_name" required>
                                </label>
                                <label class="sw-field">
                                    <span class="sw-field__label">تاريخ التظهير</span>
                                    <input class="sw-input" name="endorsement_date" type="date" value="{{ now()->toDateString() }}" required>
                                </label>
                            </div>
                            <button class="sw-button sw-button--primary" type="submit">تظهير</button>
                        </form>
                    @endif

                    @foreach($cheque->endorsements as $endorsement)
                        <div class="treasury-endorsement">
                            <span>التظهير إلى: <strong>{{ $endorsement->endorsed_to_name }}</strong></span>
                            <span class="sw-badge">{{ $endorsement->status }}</span>
                            @if($endorsement->status === 'pending_approval' && auth()->user()->can('approve', $endorsement))
                                <form method="POST" action="{{ route('treasury.cheque-endorsements.approve', $endorsement) }}">
                                    @csrf
                                    <button class="sw-button sw-button--secondary" type="submit">اعتماد التظهير</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="sw-card treasury-empty-card">لا توجد شيكات حتى الآن.</div>
        @endforelse
    </div>

    {{ $cheques->links() }}
</div>
@endsection
