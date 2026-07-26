@extends('layouts.app')
@section('title','المحاسبة') @section('page-title','المحاسبة')
@section('content')
<div class="sw-alert">هذه مرحلة تأسيسية فقط؛ لا توجد قيود محاسبية فعلية في Phase 14A.</div>
<div class="sw-grid sw-grid--cards">
@foreach([
    ['accounting.accounts.index','دليل الحسابات','شجرة الحسابات والحسابات النظامية'],
    ['accounting.fiscal-years.index','السنوات المالية','السنوات وحالاتها'],
    ['accounting.periods.index','الفترات المحاسبية','الفترات الشهرية والمخصصة'],
    ['accounting.cost-centers.index','مراكز التكلفة','هيكل مستقل عن الفروع'],
    ['accounting.settings.edit','إعدادات المحاسبة','الإعدادات وربط حسابات الفروع'],
    ['accounting.posting-profiles.index','قوالب الترحيل','Foundation بدون إنشاء قيود'],
    ['accounting.opening-balances.index','الأرصدة الافتتاحية','مستندات جاهزة للمرحلة التالية'],
] as [$route,$title,$description])
<a class="sw-card" href="{{ route($route) }}"><h3>{{ $title }}</h3><p>{{ $description }}</p></a>
@endforeach
</div>
@endsection
