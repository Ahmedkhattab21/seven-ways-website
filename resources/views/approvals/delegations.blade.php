@extends('layouts.app')
@section('title', 'تفويضات الاعتماد')
@section('page-title', 'تفويضات الاعتماد')
@section('content')
@if(auth()->user()->hasPermission('delegations.create'))<div class="sw-card"><form method="POST" action="{{ route('delegations.store') }}" class="sw-form">@csrf<div class="sw-form-grid">
<select name="delegator_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
<select name="delegate_id" required>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
<select name="branch_id"><option value="">كل الفروع المتاحة</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
<select name="modules[]" multiple required><option value="purchasing">المشتريات</option><option value="treasury">الخزينة</option></select>
<input type="datetime-local" name="starts_at" required><input type="datetime-local" name="ends_at" required>
<input name="reason" maxlength="500" required placeholder="سبب التفويض"><button class="sw-btn">إنشاء</button>
</div></form></div>@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>من</th><th>إلى</th><th>النطاق</th><th>الفترة</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse($delegations as $row)<tr><td>{{ $row->delegator?->name }}</td><td>{{ $row->delegate?->name }}</td><td>{{ implode(', ', $row->modules) }}</td><td>{{ $row->starts_at }} — {{ $row->ends_at }}</td><td>{{ $row->status }}</td><td>@if($row->status==='active' && auth()->user()->hasPermission('delegations.cancel'))<form method="POST" action="{{ route('delegations.cancel', $row) }}">@csrf<button class="sw-btn">إلغاء</button></form>@endif</td></tr>
@empty<tr><td colspan="6">لا توجد تفويضات.</td></tr>@endforelse
</tbody></table>{{ $delegations->links() }}</div>
@endsection
