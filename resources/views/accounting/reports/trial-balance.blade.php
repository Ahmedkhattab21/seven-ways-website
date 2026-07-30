@extends('layouts.app')

@section('title', 'ميزان المراجعة')
@section('page-title', 'ميزان المراجعة')

@section('content')
<div class="accounting-report-page">
    @include('accounting.reports._filters', [
        'allowExport' => auth()->user()->hasPermission('accounting.trial_balance.export'),
        'allowTrialOptions' => true,
    ])

    <div class="sw-alert accounting-report-status">
        {{ $balanced ? 'ميزان المراجعة متوازن.' : 'ميزان المراجعة غير متوازن، ويظهر الفرق دون إنشاء أي قيد تصحيحي.' }}
    </div>

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>أرصدة الحسابات</h2>
                <p>الأرصدة الافتتاحية وحركة الفترة والأرصدة الختامية.</p>
            </div>
        </div>
        <div class="sw-table-wrap">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>الحساب</th>
                        <th>افتتاحي مدين</th>
                        <th>افتتاحي دائن</th>
                        <th>حركة مدين</th>
                        <th>حركة دائن</th>
                        <th>ختامي مدين</th>
                        <th>ختامي دائن</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row->account_code }} — {{ $row->name_ar }}</td>
                            <td>{{ $row->opening_debit }}</td>
                            <td>{{ $row->opening_credit }}</td>
                            <td>{{ $row->period_debit }}</td>
                            <td>{{ $row->period_credit }}</td>
                            <td>{{ $row->closing_debit }}</td>
                            <td>{{ $row->closing_credit }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>الإجمالي</th>
                        @foreach(['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'] as $field)
                            <th>{{ $totals[$field] }}</th>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    @if($summary->isNotEmpty())
        <section class="sw-card accounting-table-card">
            <div class="sw-card__header">
                <div>
                    <h2>ملخص التجميع</h2>
                    <p>إجماليات الحركة والرصيد حسب طريقة العرض المختارة.</p>
                </div>
            </div>
            <div class="sw-table-wrap">
                <table class="sw-table">
                    <thead>
                        <tr>
                            <th>التجميع</th>
                            <th>حركة مدين</th>
                            <th>حركة دائن</th>
                            <th>ختامي مدين</th>
                            <th>ختامي دائن</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($summary as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->period_debit }}</td>
                                <td>{{ $row->period_credit }}</td>
                                <td>{{ $row->closing_debit }}</td>
                                <td>{{ $row->closing_credit }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
