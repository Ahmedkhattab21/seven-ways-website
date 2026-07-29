@extends('layouts.app')

@section('title', $entry->journal_number)
@section('page-title', $entry->journal_number)

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
    $actionLabels = [
        'submit' => 'إرسال للاعتماد',
        'approve' => 'اعتماد',
        'post' => 'ترحيل',
        'cancel' => 'إلغاء',
    ];
    $actionStates = [
        'submit' => ['draft'],
        'approve' => ['pending_approval'],
        'post' => ['approved', 'pending_approval'],
        'cancel' => ['draft', 'pending_approval', 'approved'],
    ];
@endphp

<div class="sw-card">
    <p>{{ $entry->description }}</p>
    <p>
        الحالة: {{ $statusLabels[$entry->status] ?? $entry->status }}
        | التاريخ: {{ $entry->entry_date->format('Y-m-d') }}
        | المصدر: {{ $entry->source_number ?: 'يدوي' }}
    </p>

    <table class="sw-table">
        <thead>
            <tr>
                <th>#</th>
                <th>الحساب</th>
                <th>الوصف</th>
                <th>مدين</th>
                <th>دائن</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entry->lines as $line)
                <tr>
                    <td>{{ $line->line_number }}</td>
                    <td>{{ $line->account->account_code }} — {{ $line->account->name_ar }}</td>
                    <td>{{ $line->description }}</td>
                    <td>{{ $line->debit_amount }}</td>
                    <td>{{ $line->credit_amount }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="3">الإجمالي</th>
                <th>{{ $entry->total_debit }}</th>
                <th>{{ $entry->total_credit }}</th>
            </tr>
        </tbody>
    </table>
</div>

@if(! $entry->is_automatic)
    <div class="sw-card">
        @foreach($actionLabels as $action => $label)
            @if(in_array($entry->status, $actionStates[$action], true))
                @can($action, $entry)
                    <form method="POST" action="{{ route('accounting.journals.action', [$entry, $action]) }}" style="display:inline">
                        @csrf
                        <button class="sw-btn">{{ $label }}</button>
                    </form>
                @endcan
            @endif
        @endforeach
    </div>
@endif

@can('reverse', $entry)
    @if($entry->status === 'posted' && ! $entry->reversed_by_entry_id)
        <div class="sw-card">
            <form method="POST" action="{{ route('accounting.journals.reverse', $entry) }}">
                @csrf
                <label>سبب العكس <input name="reason" required></label>
                <button class="sw-btn">عكس القيد</button>
            </form>
        </div>
    @endif
@endcan
@endsection
