@extends('layouts.app')

@section('title', 'الفترات المحاسبية')
@section('page-title', 'الفترات المحاسبية')

@section('content')
<div class="accounting-page">
    <div class="sw-alert">إغلاق الفترات هنا تأسيسي فقط ولا ينشئ قيود إقفال.</div>

    @if(auth()->user()->hasPermission('accounting.periods.create'))
        <form class="sw-card sw-form accounting-form-card" method="POST" action="{{ route('accounting.periods.store') }}">
            @csrf
            <div class="sw-card__header">
                <div>
                    <h2>إنشاء فترة محاسبية</h2>
                    <p>حدد السنة ورقم الفترة ونطاق التواريخ قبل الحفظ.</p>
                </div>
            </div>
            <div class="sw-card__body">
                <div class="sw-form-grid">
                    <label class="sw-field">
                        <span class="sw-field__label">السنة المالية</span>
                        <select class="sw-input" name="fiscal_year_id">
                            @foreach($years as $year)
                                <option value="{{ $year->id }}">{{ $year->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">رقم الفترة</span>
                        <input class="sw-input" type="number" min="1" name="period_number" required>
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
                        <span class="sw-field__label">من</span>
                        <input class="sw-input" type="date" name="start_date" required>
                    </label>
                    <label class="sw-field">
                        <span class="sw-field__label">إلى</span>
                        <input class="sw-input" type="date" name="end_date" required>
                    </label>
                </div>
                <label class="sw-check">
                    <input type="hidden" name="is_adjustment_period" value="0">
                    <input class="sw-check__box" type="checkbox" name="is_adjustment_period" value="1">
                    <span>فترة تسويات</span>
                </label>
                <div class="sw-form-actions accounting-form-actions">
                    <button class="sw-button sw-button--primary" type="submit">إنشاء الفترة</button>
                </div>
            </div>
        </form>
    @endif

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>الفترات المسجلة</h2>
                <p>الفترات المحاسبية التابعة للسنوات المالية الحالية والسابقة.</p>
            </div>
        </div>
        <div class="sw-table-wrap">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>السنة</th>
                        <th>الرقم</th>
                        <th>الكود</th>
                        <th>الفترة</th>
                        <th>الحالة</th>
                        <th>تسويات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($periods as $period)
                        <tr>
                            <td>{{ $period->fiscalYear->name }}</td>
                            <td>{{ $period->period_number }}</td>
                            <td>{{ $period->code }}</td>
                            <td>{{ $period->start_date->toDateString() }} — {{ $period->end_date->toDateString() }}</td>
                            <td>{{ $period->status }}</td>
                            <td>{{ $period->is_adjustment_period ? 'نعم' : 'لا' }}</td>
                        </tr>
                    @empty
                        <tr><td class="accounting-empty-row" colspan="6">لا توجد فترات محاسبية.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sw-card__body">{{ $periods->links() }}</div>
    </section>
</div>
@endsection
