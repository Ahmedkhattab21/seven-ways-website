@extends('layouts.app')
@section('title', 'جلسات المطابقة')
@section('page-title', 'جلسات المطابقة البنكية')
@section('content')
<div class="sw-card"><p>كشف البنك دليل خارجي، والأستاذ العام دليل محاسبي؛ الجلسة تربطهما ولا تستبدل أيًا منهما.</p></div>
@if(auth()->user()->hasPermission('treasury.reconciliation.create'))
<form class="sw-card" method="POST" action="{{ route('treasury.reconciliations.store') }}">@csrf
<div class="sw-form-grid"><select name="bank_account_id" required><option value="">الحساب البنكي</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
<input type="date" name="date_from" required><input type="date" name="date_to" required><input type="number" name="tolerance" step="0.0001" value="0" min="0">
<select name="import_ids[]" multiple required>@foreach($imports as $import)<option value="{{ $import->id }}">{{ $import->original_file_name }} — {{ $import->period_start->toDateString() }}</option>@endforeach</select>
</div><button class="sw-btn">بدء الجلسة</button></form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الرقم</th><th>الحساب</th><th>الفترة</th><th>كشف البنك</th><th>الدفاتر</th><th>الفرق</th><th>الحالة</th><th></th></tr></thead><tbody>
@foreach($sessions as $session)<tr><td>{{ $session->session_number }}</td><td>{{ $session->bankAccount->account_name }}</td><td>{{ $session->date_from->toDateString() }} — {{ $session->date_to->toDateString() }}</td><td>{{ $session->statement_closing_balance }}</td><td>{{ $session->book_closing_balance }}</td><td>{{ $session->difference }}</td><td>{{ $session->status }}</td><td><a class="sw-btn" href="{{ route('treasury.reconciliations.show',$session) }}">فتح</a></td></tr>@endforeach
</tbody></table>{{ $sessions->links() }}</div>
@endsection
