@extends('layouts.app')
@section('title', 'مطابقة بنكية')
@section('page-title', 'جلسة المطابقة '.$session->session_number)
@section('content')
<div class="sw-card"><div class="sw-form-grid"><strong>إغلاق الكشف: {{ $totals['statement_closing_balance'] }}</strong><strong>إغلاق الدفاتر: {{ $totals['book_closing_balance'] }}</strong><strong>المطابق: {{ $totals['matched_statement_amount'] }}</strong><strong>غير مطابق: {{ $totals['unreconciled_statement_amount'] }}</strong><strong>الفرق: {{ $totals['difference'] }}</strong><strong>السماحية: {{ $session->tolerance }}</strong><strong>الحالة: {{ $session->status }}</strong></div>
@if(auth()->user()->hasPermission('treasury.reconciliation.export'))<a class="sw-btn" href="{{ route('treasury.reconciliations.export',$session) }}">CSV</a>@endif
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
<div class="sw-card"><h3>جهة كشف البنك</h3><table class="sw-table"><thead><tr><th>ID</th><th>التاريخ</th><th>الوصف</th><th>المبلغ</th><th>المتبقي</th></tr></thead><tbody>@foreach($statementLines as $line)<tr><td>{{ $line->id }}</td><td>{{ $line->transaction_date->toDateString() }}</td><td>{{ $line->description }}</td><td>{{ $line->amount() }} {{ $line->direction() }}</td><td>{{ $line->unmatched_amount }}</td></tr>@endforeach</tbody></table></div>
<div class="sw-card"><h3>جهة الدفاتر — Posted Bank GL</h3><table class="sw-table"><thead><tr><th>ID</th><th>التاريخ</th><th>القيد</th><th>المبلغ</th><th>المتبقي</th></tr></thead><tbody>@foreach($bookLines as $line)<tr><td>{{ $line->id }}</td><td>{{ $line->entry->posting_date->toDateString() }}</td><td>{{ $line->entry->journal_number }}</td><td>{{ $line->debit_amount ?: $line->credit_amount }} {{ $line->reconciliation_direction }}</td><td>{{ $line->reconciliation_unmatched_amount }}</td></tr>@endforeach</tbody></table></div>
</div>
@if(auth()->user()->hasPermission('treasury.reconciliation.match'))
<form class="sw-card" method="POST" action="{{ route('treasury.reconciliations.matches.store',$session) }}">@csrf<h3>Manual / Partial / Multiple Match</h3>
<div class="sw-form-grid"><input name="statement[0][id]" type="number" placeholder="Statement line ID" required><input name="statement[0][amount]" type="number" step="0.0001" placeholder="Statement allocation" required><input name="book[0][id]" type="number" placeholder="Journal line ID" required><input name="book[0][amount]" type="number" step="0.0001" placeholder="Book allocation" required></div><button class="sw-btn">حفظ المطابقة</button></form>
<form class="sw-card" method="POST" action="{{ route('treasury.reconciliations.suggest',$session) }}">@csrf<label><input type="checkbox" name="auto_match" value="1"> Controlled Auto-match</label><button class="sw-btn">توليد الاقتراحات</button></form>
@endif
<div class="sw-card"><h3>المطابقات</h3><table class="sw-table"><thead><tr><th>ID</th><th>النوع</th><th>الطريقة</th><th>Score</th><th>القيمة</th><th>الفرق</th><th>الحالة</th><th></th></tr></thead><tbody>
@foreach($session->matches as $match)<tr><td>{{ $match->id }}</td><td>{{ $match->match_type }}</td><td>{{ $match->match_method }}</td><td>{{ $match->confidence_score }}</td><td>{{ $match->matched_amount }}</td><td>{{ $match->difference_amount }}</td><td>{{ $match->status }}</td><td>@foreach(['accept','reject','unmatch'] as $action)<form method="POST" action="{{ route('treasury.reconciliation-matches.action',[$match,$action]) }}">@csrf@if($action==='unmatch')<input name="reason" value="إلغاء مطابقة موثق">@endif<button class="sw-btn">{{ $action }}</button></form>@endforeach</td></tr>@endforeach
</tbody></table></div>
<div class="sw-card">@foreach(['review','approve','complete','cancel'] as $action)@if(auth()->user()->hasPermission('treasury.reconciliation.'.$action))<form method="POST" action="{{ route('treasury.reconciliations.action',[$session,$action]) }}">@csrf@if($action==='cancel')<input name="reason" value="إلغاء موثق">@else<input name="notes" placeholder="ملاحظات">@endif<button class="sw-btn">{{ $action }}</button></form>@endif @endforeach
@if($session->status==='completed' && auth()->user()->hasPermission('treasury.reconciliation.reopen'))<form method="POST" action="{{ route('treasury.reconciliations.reopen',$session) }}">@csrf<input name="reason" required placeholder="سبب إعادة الفتح"><button class="sw-btn">إعادة فتح</button></form>@endif</div>
@endsection
