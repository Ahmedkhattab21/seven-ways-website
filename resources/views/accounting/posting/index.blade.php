@extends('layouts.app')
@section('title','الترحيل المحاسبي') @section('page-title','مصادر الترحيل المحاسبي')
@section('content')
<div class="sw-alert">المعاينة لا تكتب أي بيانات. الترحيل Idempotent ويعيد نفس القيد عند إعادة المحاولة.</div>
<div class="sw-card"><p>استخدم Post to Accounting من المستند التشغيلي. الأنواع المدعومة:</p>
<ul>@foreach($sourceTypes as $type)<li>{{ $type }}</li>@endforeach</ul></div>
@endsection
