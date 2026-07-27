@extends('layouts.app')
@section('title', 'أسطر كشف الحساب')
@section('page-title', 'أسطر كشف الحساب — '.$import->original_file_name)
@section('content')
<div class="sw-card"><p>الحساب: {{ $import->bankAccount->account_name }} | الحالة: {{ $import->status }} | الافتتاحي: {{ $import->opening_balance }} | الختامي: {{ $import->closing_balance }}</p>
@if(auth()->user()->hasPermission('treasury.bank_statements.view_sensitive'))<a class="sw-btn" href="{{ route('treasury.bank-statements.download',$import) }}">تنزيل خاص</a>@endif</div>
<div class="sw-card"><table class="sw-table"><thead><tr><th>#</th><th>التاريخ</th><th>القيمة</th><th>المرجع</th><th>الوصف</th><th>مدين</th><th>دائن</th><th>الرصيد الجاري</th><th>مطابق</th><th>متبقي</th><th>الحالة</th><th>إجراء</th></tr></thead><tbody>
@foreach($lines as $line)<tr><td>{{ $line->line_number }}</td><td>{{ $line->transaction_date->toDateString() }}</td><td>{{ $line->value_date?->toDateString() }}</td><td>{{ $line->bank_reference }}</td><td>{{ $line->description }} @if($line->maskedCounterpartyIban())<small>{{ $line->maskedCounterpartyIban() }}</small>@endif</td><td>{{ $line->debit_amount }}</td><td>{{ $line->credit_amount }}</td><td>{{ $line->running_balance }}</td><td>{{ $line->matched_amount }}</td><td>{{ $line->unmatched_amount }}</td><td>{{ $line->status }}</td><td>
@if(auth()->user()->hasPermission('treasury.bank_statements.ignore_lines'))<form method="POST" action="{{ route('treasury.bank-statement-lines.action',[$line,'ignore']) }}">@csrf<input name="reason" required placeholder="سبب التجاهل"><button class="sw-btn">تجاهل</button></form>@endif
@if(auth()->user()->hasPermission('treasury.bank_statements.resolve_duplicates'))<form method="POST" action="{{ route('treasury.bank-statement-lines.action',[$line,'duplicate']) }}">@csrf<input name="duplicate_of_id" type="number" placeholder="رقم السطر الأصلي"><input name="reason" required placeholder="سبب التصنيف"><button class="sw-btn">تصنيف</button></form>@endif
</td></tr>@endforeach
</tbody></table>{{ $lines->links() }}</div>
@endsection
