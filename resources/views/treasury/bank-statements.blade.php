@extends('layouts.app')
@section('title', 'كشوف الحساب البنكية')
@section('page-title', 'استيراد كشوف الحساب البنكية')
@section('content')
<div class="sw-card"><p>ملفات CSV تُخزن في Private Storage، والأرصدة المعروضة دليل بنكي خارجي ولا تستبدل الأستاذ العام.</p></div>
@if(auth()->user()->hasPermission('treasury.bank_statements.import'))
<form class="sw-card" method="POST" enctype="multipart/form-data" action="{{ route('treasury.bank-statements.store') }}">@csrf
<div class="sw-form-grid">
<select name="bank_account_id" required><option value="">الحساب البنكي</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
<select name="profile_id" required><option value="">ملف تعريف CSV</option>@foreach($profiles as $profile)<option value="{{ $profile->id }}">{{ $profile->name }}</option>@endforeach</select>
<input type="file" name="file" accept=".csv,text/csv" required>
<input name="statement_reference" placeholder="مرجع الكشف">
<input type="date" name="period_start" required><input type="date" name="period_end" required>
<input name="opening_balance" type="number" step="0.0001" placeholder="الرصيد الافتتاحي" required>
<input name="closing_balance" type="number" step="0.0001" placeholder="الرصيد الختامي" required>
<select name="currency_id" required>@foreach($accounts->unique('currency_id') as $account)<option value="{{ $account->currency_id }}">{{ $account->currency?->code ?? $account->currency_id }}</option>@endforeach</select>
</div><button class="sw-btn">رفع والتحقق والاستيراد</button></form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الملف</th><th>الحساب</th><th>الفترة</th><th>الأسطر</th><th>المكرر</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse($imports as $import)<tr><td>{{ $import->original_file_name }}</td><td>{{ $import->bankAccount->account_name }}</td><td>{{ $import->period_start->toDateString() }} — {{ $import->period_end->toDateString() }}</td><td>{{ $import->imported_lines }}</td><td>{{ $import->duplicate_lines }}</td><td>{{ $import->status }}</td><td><a class="sw-btn" href="{{ route('treasury.bank-statements.show',$import) }}">الأسطر</a></td></tr>
@empty<tr><td colspan="7">لا توجد ملفات مستوردة.</td></tr>@endforelse
</tbody></table>{{ $imports->links() }}</div>
@endsection
