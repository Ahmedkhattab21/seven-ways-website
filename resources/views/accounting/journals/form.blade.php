@extends('layouts.app')
@section('title','قيد يدوي') @section('page-title',$entry->exists ? 'تعديل القيد' : 'قيد يدوي جديد')
@section('content')
<form class="sw-card sw-form" method="POST" action="{{ $entry->exists ? route('accounting.journals.update',$entry) : route('accounting.journals.store') }}">
@csrf @if($entry->exists)@method('PUT')@endif
<div class="sw-form-grid"><label>التاريخ<input type="date" name="entry_date" required value="{{ old('entry_date',$entry->entry_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"></label>
<label>المرجع<input name="reference" value="{{ old('reference',$entry->reference) }}"></label>
<label>الوصف<input name="description" required value="{{ old('description',$entry->description) }}"></label></div>
@php($rows=old('lines',$entry->exists ? $entry->lines->toArray() : [['debit_amount'=>0,'credit_amount'=>0],['debit_amount'=>0,'credit_amount'=>0]]))
<table class="sw-table"><thead><tr><th>الحساب</th><th>العملة</th><th>مدين</th><th>دائن</th><th>الوصف</th></tr></thead><tbody>
@foreach($rows as $i=>$row)<tr>
<td><select name="lines[{{ $i }}][account_id]" required>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(($row['account_id']??null)==$account->id)>{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select></td>
<td><select name="lines[{{ $i }}][currency_id]">@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected(($row['currency_id']??null)==$currency->id)>{{ $currency->code }}</option>@endforeach</select><input type="hidden" name="lines[{{ $i }}][exchange_rate]" value="1"></td>
<td><input type="number" step=".0001" min="0" name="lines[{{ $i }}][debit_amount]" value="{{ $row['debit_amount']??0 }}"></td>
<td><input type="number" step=".0001" min="0" name="lines[{{ $i }}][credit_amount]" value="{{ $row['credit_amount']??0 }}"></td>
<td><input name="lines[{{ $i }}][description]" value="{{ $row['description']??'' }}"></td>
</tr>@endforeach</tbody></table>
<button class="sw-btn">حفظ المسودة</button></form>
@endsection
