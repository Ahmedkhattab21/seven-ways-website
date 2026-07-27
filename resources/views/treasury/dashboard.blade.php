@extends('layouts.app')
@section('title', 'الخزينة والبنوك')
@section('page-title', 'الخزينة والبنوك')
@section('content')
<div class="sw-card"><p>الأرصدة المعروضة دفترية من القيود المرحلة وليست أرصدة كشف البنك.</p></div>
<div class="sw-grid sw-grid-3">
    <div class="sw-card"><h3>الرصيد الدفتري للبنوك</h3><p>{{ $bankTotal }}</p></div>
    <div class="sw-card"><h3>الرصيد الدفتري للخزائن</h3><p>{{ $cashTotal }}</p></div>
    <div class="sw-card"><h3>حسابات معلقة</h3><p>{{ $suspendedAccounts }}</p></div>
    <div class="sw-card"><h3>خزائن فوق الحد</h3><p>{{ $overLimitBoxes }}</p></div>
    <div class="sw-card"><h3>تحويلات قيد الاعتماد</h3><p>{{ $pendingTransfers }}</p></div>
    <div class="sw-card"><h3>آخر مطابقة</h3><p>{{ $lastReconciledDate ?: 'لا توجد' }}</p></div>
</div>
@endsection
