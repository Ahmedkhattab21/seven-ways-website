@extends('layouts.app')
@section('title', 'إقفال السنة المالية')
@section('content')
<div class="sw-card">
    <h1>إقفال السنة المالية</h1>
    <p>البدء والمراجعة والاعتماد والتنفيذ خطوات منفصلة، ولا يمكن تجاوز الفحوصات المانعة.</p>
</div>
@foreach($years as $year)
<div class="sw-card">
    <h3>{{ $year->name }} — {{ $year->status }}</h3>
    <p>الفترات: {{ $year->periods->count() }}</p>
    @if($year->status === 'soft_closed' && auth()->user()->hasPermission('accounting.year_end.start'))
    <form method="POST" action="{{ route('accounting.closing.year-end.start', $year) }}">
        @csrf
        <input name="reason" required placeholder="سبب بدء إقفال السنة">
        <button class="sw-btn">بدء الفحص</button>
    </form>
    @endif
    @if(in_array($year->status, ['closed', 'locked'], true) && auth()->user()->hasPermission('accounting.year_end.reopen'))
    <form method="POST" action="{{ route('accounting.closing.year-end.reopen', $year) }}">
        @csrf
        <input name="reason" required placeholder="سبب إعادة فتح السنة">
        <button class="sw-btn">طلب إعادة الفتح</button>
    </form>
    @endif
</div>
@endforeach
@foreach($runs->where('closing_type', 'year_end_close') as $run)
<div class="sw-card">
    <h3>{{ $run->run_number }} — {{ $run->status }}</h3>
    <p>{{ $run->reason }}</p>
    @php
        $action = match($run->status) {
            'ready_for_review' => 'review',
            'under_review' => 'approve',
            'approved' => 'execute',
            default => null,
        };
    @endphp
    @if($action && auth()->user()->hasPermission('accounting.year_end.'.$action))
    <form method="POST" action="{{ route('accounting.closing.year-end.action', [$run, $action]) }}">
        @csrf
        @if($action !== 'execute')
        <input name="notes" placeholder="ملاحظات {{ $action === 'review' ? 'المراجعة' : 'الاعتماد' }}">
        @endif
        <button class="sw-btn">{{ ['review' => 'مراجعة', 'approve' => 'اعتماد', 'execute' => 'تنفيذ الإقفال'][$action] }}</button>
    </form>
    @endif
</div>
@endforeach
@foreach($runs->where('closing_type', 'reopen_year')->where('status', 'under_review') as $run)
<div class="sw-card">
    <h3>طلب إعادة فتح {{ $run->run_number }}</h3>
    <p>إعادة الفتح لا تحذف التاريخ، وتعكس قيود الإقفال، وقد تمنعها معاملات السنة التالية.</p>
    @if(auth()->user()->hasPermission('accounting.year_end.reopen'))
    <form method="POST" action="{{ route('accounting.closing.year-end.reopen.approve', $run) }}">
        @csrf
        <input name="notes" placeholder="ملاحظات الموافقة">
        <button class="sw-btn">موافقة وتنفيذ العكس</button>
    </form>
    @endif
</div>
@endforeach
@endsection
