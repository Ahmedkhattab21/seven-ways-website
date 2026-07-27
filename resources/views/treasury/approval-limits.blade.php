@extends('layouts.app')
@section('title', 'حدود اعتماد الخزينة')
@section('page-title', 'حدود اعتماد عمليات الخزينة')
@section('content')
<form class="sw-card" method="POST" action="{{ route('treasury.approval-limits.store') }}">@csrf
    <input type="hidden" name="currency_id" value="{{ $company->currency_id }}"><input type="hidden" name="approval_level" value="1"><input type="hidden" name="valid_from" value="{{ now()->toDateString() }}"><input type="hidden" name="is_active" value="1">
    <div class="sw-form-grid">
        <select name="role_id"><option value="">Role</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->display_name }}</option>@endforeach</select>
        <select name="user_id"><option value="">User</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
        <select name="operation_type">@foreach(['treasury_transfer','cash_receipt','cash_payment','cash_over_short','received_cheque','issued_cheque','cheque_clearance','cheque_bounce','merchant_settlement'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select>
        <input name="minimum_amount" type="number" min="0" value="0"><input name="maximum_amount" type="number" min="0" placeholder="Maximum">
        @foreach(['can_create','can_submit','can_approve','can_post'] as $ability)<label><input type="hidden" name="{{ $ability }}" value="0"><input type="checkbox" name="{{ $ability }}" value="1"> {{ $ability }}</label>@endforeach
    </div><button class="sw-btn">حفظ الحد</button>
</form>
<div class="sw-card"><table class="sw-table"><thead><tr><th>Operation</th><th>Subject</th><th>Range</th><th>Level</th></tr></thead><tbody>
@foreach($limits as $limit)<tr><td>{{ $limit->operation_type }}</td><td>{{ $limit->user_id ? 'User #'.$limit->user_id : 'Role #'.$limit->role_id }}</td><td>{{ $limit->minimum_amount }} — {{ $limit->maximum_amount ?? 'Blocked unless unlimited permission' }}</td><td>{{ $limit->approval_level }}</td></tr>@endforeach
</tbody></table></div>{{ $limits->links() }}
@endsection
