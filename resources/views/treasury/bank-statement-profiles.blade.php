@extends('layouts.app')
@section('title', 'ملفات تعريف CSV')
@section('page-title', 'ملفات تعريف استيراد كشف الحساب')
@section('content')
<div class="sw-card"><p>Column Mapping بيانات محكومة فقط؛ لا يتم قبول PHP أو SQL أو Regex.</p></div>
<form class="sw-card" method="POST" action="{{ route('treasury.bank-statement-profiles.store') }}">@csrf
<div class="sw-form-grid">
<input name="name" required placeholder="اسم الملف التعريفي"><select name="bank_account_id"><option value="">افتراضي للشركة</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
<input type="hidden" name="format" value="csv"><select name="delimiter"><option value=",">Comma</option><option value=";">Semicolon</option><option value="|">Pipe</option></select>
<input type="hidden" name="encoding" value="UTF-8"><select name="date_format"><option>Y-m-d</option><option>d/m/Y</option><option>m/d/Y</option><option>d-m-Y</option></select>
<select name="decimal_separator"><option value=".">.</option><option value=",">,</option></select><select name="thousands_separator"><option value="">بدون</option><option value=",">,</option><option value=".">.</option></select>
<input type="hidden" name="has_header" value="1"><input name="skip_rows" type="number" value="0" min="0" max="100">
<select name="direction_policy"><option value="credit_increases">Credit increases balance</option><option value="debit_increases">Debit increases balance</option></select>
<input name="balance_tolerance" type="number" step="0.0001" value="0" min="0">
<input name="column_mapping[transaction_date]" value="date" required><input name="column_mapping[description]" value="description" required>
<input name="column_mapping[reference]" value="reference"><input name="column_mapping[debit]" value="debit" required><input name="column_mapping[credit]" value="credit" required>
<input name="column_mapping[running_balance]" value="balance"><input name="column_mapping[external_id]" value="external_id">
<input type="hidden" name="is_default" value="0"><label><input type="checkbox" name="is_default" value="1"> افتراضي</label>
<input type="hidden" name="is_active" value="1">
</div><button class="sw-btn">حفظ الملف التعريفي</button></form>
<div class="sw-card"><table class="sw-table"><thead><tr><th>الاسم</th><th>الحساب</th><th>الفاصل</th><th>التاريخ</th><th>الاتجاه</th><th>افتراضي</th><th>نشط</th></tr></thead><tbody>
@foreach($profiles as $profile)<tr><td>{{ $profile->name }}</td><td>{{ $profile->bankAccount?->account_name ?? 'الشركة' }}</td><td>{{ $profile->delimiter }}</td><td>{{ $profile->date_format }}</td><td>{{ $profile->direction_policy }}</td><td>{{ $profile->is_default ? 'نعم':'لا' }}</td><td>{{ $profile->is_active ? 'نعم':'لا' }}</td></tr>@endforeach
</tbody></table></div>
@endsection
