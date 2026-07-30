@extends('layouts.app')

@section('title', 'القيود اليومية')
@section('page-title', 'القيود اليومية')

@section('page-actions')
    @if(auth()->user()->hasPermission('accounting.journals.create'))
        <a class="sw-button sw-button--primary" href="{{ route('accounting.journals.create') }}">قيد يدوي جديد</a>
    @endif
@endsection

@section('content')
@php
    $statusLabels = [
        'draft' => 'مسودة',
        'pending_approval' => 'في انتظار الاعتماد',
        'approved' => 'معتمد',
        'posted' => 'مُرحّل',
        'reversed' => 'معكوس',
        'cancelled' => 'ملغي',
    ];
    $typeLabels = [
        'manual' => 'يدوي',
        'automatic' => 'تلقائي',
        'treasury' => 'خزينة',
        'adjustment' => 'تسوية',
        'reversal' => 'عكسي',
    ];
@endphp

<div class="accounting-page">
    <form class="sw-card sw-form accounting-filter-card" method="GET">
        <div class="sw-card__header">
            <div>
                <h2>تصفية القيود</h2>
                <p>حدد حالة القيد ونوعه للوصول إلى النتائج المطلوبة.</p>
            </div>
        </div>
        <div class="sw-card__body">
            <div class="sw-form-grid">
                <label class="sw-field">
                    <span class="sw-field__label">الحالة</span>
                    <select class="sw-input" name="status">
                        <option value="">الكل</option>
                        @foreach($statusLabels as $status => $label)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="sw-field">
                    <span class="sw-field__label">النوع</span>
                    <select class="sw-input" name="entry_type">
                        <option value="">الكل</option>
                        @foreach(['manual', 'automatic', 'reversal'] as $type)
                            <option value="{{ $type }}" @selected(request('entry_type') === $type)>{{ $typeLabels[$type] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="sw-form-actions accounting-form-actions">
                <button class="sw-button sw-button--primary" type="submit">تطبيق التصفية</button>
            </div>
        </div>
    </form>

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>سجل القيود</h2>
                <p>القيود المحاسبية المسجلة وحالتها وإجمالي طرفيها.</p>
            </div>
        </div>
        <div class="sw-table-wrap">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>التاريخ</th>
                        <th>النوع</th>
                        <th>الحالة</th>
                        <th>مدين</th>
                        <th>دائن</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td><a href="{{ route('accounting.journals.show', $entry) }}">{{ $entry->journal_number }}</a></td>
                            <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                            <td>{{ $typeLabels[$entry->entry_type] ?? $entry->entry_type }}</td>
                            <td>{{ $statusLabels[$entry->status] ?? $entry->status }}</td>
                            <td>{{ $entry->total_debit }}</td>
                            <td>{{ $entry->total_credit }}</td>
                        </tr>
                    @empty
                        <tr><td class="accounting-empty-row" colspan="6">لا توجد قيود.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="sw-card__body">{{ $entries->links() }}</div>
    </section>
</div>
@endsection
