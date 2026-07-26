@extends('layouts.app')
@section('title','الأرصدة الافتتاحية') @section('page-title','الأرصدة الافتتاحية')
@section('content')
<div class="sw-alert">Ready for Posting يعني أن المستند اجتاز الاعتماد ويمكن ترحيله إلى قيد افتتاحي.</div>
@if(auth()->user()->hasPermission('accounting.opening_balances.create'))<form class="sw-card sw-form" method="POST" action="{{ route('accounting.opening-balances.store') }}">@csrf<div class="sw-form-grid">
<label>السنة<select name="fiscal_year_id">@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select></label>
<label>الفرع<select name="branch_id"><option value="">كل الشركة</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
<label>تاريخ الرصيد<input type="date" name="balance_date" required></label><label>الوصف<input name="description"></label>
</div><button class="sw-btn">إنشاء Draft</button></form>@endif
@foreach($documents as $document)<div class="sw-card"><h3>{{ $document->document_number }} — {{ $document->status }}</h3>
<p>إجمالي المدين: {{ $document->total_debit }} | إجمالي الدائن: {{ $document->total_credit }} | الفرق: {{ bcsub($document->total_debit,$document->total_credit,4) }}</p>
@if($document->status==='draft')<form class="sw-form" method="POST" action="{{ route('accounting.opening-balances.lines.store',$document) }}">@csrf<div class="sw-form-grid">
<label>الحساب<select name="account_id">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select></label>
<label>العملة<select name="currency_id">@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select></label>
<label>سعر الصرف<input type="number" step=".00000001" min=".00000001" name="exchange_rate" value="1"></label>
<label>مدين<input type="number" step=".0001" min="0" name="debit_amount" value="0"></label><label>دائن<input type="number" step=".0001" min="0" name="credit_amount" value="0"></label>
</div><button class="sw-btn">إضافة Line</button></form>
<form method="POST" action="{{ route('accounting.opening-balances.action',[$document,'submit']) }}">@csrf<button class="sw-btn">Validate & Submit</button></form>@endif
@if($document->status==='pending_approval')<form method="POST" action="{{ route('accounting.opening-balances.action',[$document,'approve']) }}">@csrf<button class="sw-btn">Approve</button></form>@endif
@if($document->status==='approved')<form method="POST" action="{{ route('accounting.opening-balances.action',[$document,'mark_ready']) }}">@csrf<button class="sw-btn">Mark Ready for Posting</button></form>@endif
@if($document->status==='ready_for_posting' && auth()->user()->hasPermission('accounting.opening_balances.post'))<form method="POST" action="{{ route('accounting.opening-balances.post',$document) }}">@csrf<button class="sw-btn">Post to Accounting</button></form>@endif
@if($document->journal_entry_id)<a class="sw-btn" href="{{ route('accounting.journals.show',$document->journal_entry_id) }}">View Journal</a>@endif
@if($document->status==='posted' && auth()->user()->hasPermission('accounting.opening_balances.reverse'))<form method="POST" action="{{ route('accounting.opening-balances.reverse',$document) }}">@csrf<label>سبب العكس<input name="reason" required></label><button class="sw-btn">Reverse</button></form>@endif
<table class="sw-table"><thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th></tr></thead><tbody>@foreach($document->lines as $line)<tr><td>{{ $line->account->account_code }} — {{ $line->account->name_ar }}</td><td>{{ $line->debit_amount }}</td><td>{{ $line->credit_amount }}</td></tr>@endforeach</tbody></table>
</div>@endforeach
@endsection
