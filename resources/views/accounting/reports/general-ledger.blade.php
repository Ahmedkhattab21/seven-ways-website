@extends('layouts.app')

@section('title', 'الأستاذ العام')
@section('page-title', 'الأستاذ العام')

@section('content')
<div class="accounting-report-page">
    @include('accounting.reports._filters', [
        'allowExport' => auth()->user()->hasPermission('accounting.general_ledger.export'),
    ])

    @isset($summary)
        <div class="accounting-stat-grid">
            <div class="accounting-stat-card"><span>الرصيد الافتتاحي</span><strong>{{ $summary['opening_net'] }}</strong></div>
            <div class="accounting-stat-card"><span>إجمالي المدين</span><strong>{{ $summary['period_debit'] }}</strong></div>
            <div class="accounting-stat-card"><span>إجمالي الدائن</span><strong>{{ $summary['period_credit'] }}</strong></div>
            <div class="accounting-stat-card"><span>الرصيد الختامي</span><strong>{{ $summary['closing_net'] }}</strong></div>
        </div>

        <section class="sw-card accounting-table-card">
            <div class="sw-card__header">
                <div>
                    <h2>حركة الحساب</h2>
                    <p>تفاصيل القيود والرصيد الجاري ضمن الفترة المحددة.</p>
                </div>
            </div>
            <div class="sw-table-wrap">
                <table class="sw-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>القيد</th>
                            <th>المصدر</th>
                            <th>البيان</th>
                            <th>مدين</th>
                            <th>دائن</th>
                            <th>الرصيد الجاري</th>
                            <th>الفرع</th>
                            <th>مركز التكلفة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $line)
                            <tr>
                                <td>{{ $line->posting_date }}</td>
                                <td><a href="{{ route('accounting.journals.show', $line->journal_entry_id) }}">{{ $line->journal_number }}</a></td>
                                <td>{{ $line->source_number }}</td>
                                <td>{{ $line->description }}</td>
                                <td>{{ $line->base_debit_amount }}</td>
                                <td>{{ $line->base_credit_amount }}</td>
                                <td>{{ $line->running_balance }}</td>
                                <td>{{ $line->branch_name }}</td>
                                <td>{{ $line->cost_center_name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="sw-card__body">{{ $lines->links() }}</div>
        </section>
    @endisset
</div>
@endsection
