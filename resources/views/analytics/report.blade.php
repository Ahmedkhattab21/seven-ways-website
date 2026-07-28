@extends('layouts.app')

@section('title', $definition['name'])
@section('page-title', $definition['name'])
@section('page-description', 'المصدر: '.$result->meta['data_source'])

@section('page-actions')
    @if(auth()->user()->hasPermission('reports.export'))
        <a class="sw-button" href="{{ route('analytics.reports.export', [$definition['code'], ...request()->query(), 'format' => 'csv']) }}">CSV</a>
        <a class="sw-button" href="{{ route('analytics.reports.export', [$definition['code'], ...request()->query(), 'format' => 'xlsx']) }}">Excel</a>
        <a class="sw-button" target="_blank" href="{{ route('analytics.reports.export', [$definition['code'], ...request()->query(), 'format' => 'print']) }}">طباعة / PDF</a>
    @endif
@endsection

@section('content')
    @include('analytics._filters')
    @php
        $company = auth()->user()->company;
        $currency = $currencies->firstWhere('id', $result->meta['currency_id'] ?? $filters->currencyId ?? $company->currency_id) ?: $company->currency;
        $money = app(\App\Services\MoneyFormatter::class);
        $moneyKeys = ['debit','credit','balance','subtotal','tax','total','valuation','unit_cost','available_book_balance',
            'period_debit','period_credit','revenue','expenses','estimated_operating_result','assets',
            'liabilities_and_equity','balance_difference','net_sales_before_tax','net_sales_after_tax',
            'discounts','credit_notes','outstanding','overdue','unallocated_payments','stock_valuation',
            'cash_book_balance','bank_book_balance','commission_outstanding','expenses_posted','advances_outstanding'];
    @endphp
    <div class="sw-analytics-summary">
        @foreach($result->summary as $key => $value)
            @continue(is_array($value))
            <div>
                <span>{{ str_replace('_', ' ', $key) }}</span>
                <strong>
                    @if(is_bool($value))
                        {{ $value ? 'نعم' : 'لا' }}
                    @elseif($value === null)
                        N/A
                    @elseif(in_array($key, $moneyKeys, true))
                        {{ $money->format($value, $currency, app()->getLocale(), $company) }}
                    @else
                        {{ is_numeric($value) ? number_format((float) $value, 2) : $value }}
                    @endif
                </strong>
            </div>
        @endforeach
    </div>
    @if(isset($result->summary['aging']))
        <x-card title="تحليل الأعمار" subtitle="يعتمد على تاريخ الاستحقاق">
            <div class="sw-aging-bars">
                @foreach($result->summary['aging'] as $bucket => $amount)
                    <p><span>{{ $bucket }}</span><strong>{{ $money->format($amount, $currency, app()->getLocale(), $company) }}</strong></p>
                @endforeach
            </div>
        </x-card>
    @endif
    <x-card title="التفاصيل" :subtitle="'عدد الصفوف المعروضة: '.$rows->count()">
        @if($rows->isEmpty())
            <x-empty-state title="لا توجد بيانات" message="غيّر الفترة أو الفلاتر لعرض نتائج أخرى." icon="chart" />
        @else
            <div class="sw-table-wrap">
                <table class="sw-table">
                    <thead><tr>@foreach($definition['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach($rows as $row)
                            @php($values = (array) $row)
                            <tr>
                                @foreach($definition['columns'] as $key => $label)
                                    <td>
                                        @if(in_array($key, $moneyKeys, true) && isset($values[$key]))
                                            {{ $money->format($values[$key], $currency, app()->getLocale(), $company) }}
                                        @else
                                            {{ $values[$key] ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection

