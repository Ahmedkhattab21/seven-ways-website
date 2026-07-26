@extends('layouts.app')
@section('title','ميزان المراجعة') @section('page-title','ميزان المراجعة')
@section('content') @include('accounting.reports._filters',['allowExport'=>auth()->user()->hasPermission('accounting.trial_balance.export'),'allowTrialOptions'=>true])
<div class="sw-alert">{{ $balanced ? 'Balanced' : 'Unbalanced — Difference is shown and no correcting entry was created.' }}</div>
<div class="sw-card"><table class="sw-table"><thead><tr><th>الحساب</th><th>افتتاحي مدين</th><th>افتتاحي دائن</th><th>حركة مدين</th><th>حركة دائن</th><th>ختامي مدين</th><th>ختامي دائن</th></tr></thead><tbody>
@foreach($rows as $row)<tr><td>{{ $row->account_code }} — {{ $row->name_ar }}</td><td>{{ $row->opening_debit }}</td><td>{{ $row->opening_credit }}</td><td>{{ $row->period_debit }}</td><td>{{ $row->period_credit }}</td><td>{{ $row->closing_debit }}</td><td>{{ $row->closing_credit }}</td></tr>@endforeach
<tr><th>الإجمالي</th>@foreach(['opening_debit','opening_credit','period_debit','period_credit','closing_debit','closing_credit'] as $field)<th>{{ $totals[$field] }}</th>@endforeach</tr></tbody></table></div>
@if($summary->isNotEmpty())<div class="sw-card"><table class="sw-table"><thead><tr><th>التجميع</th><th>حركة مدين</th><th>حركة دائن</th><th>ختامي مدين</th><th>ختامي دائن</th></tr></thead><tbody>@foreach($summary as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->period_debit }}</td><td>{{ $row->period_credit }}</td><td>{{ $row->closing_debit }}</td><td>{{ $row->closing_credit }}</td></tr>@endforeach</tbody></table></div>@endif
@endsection
