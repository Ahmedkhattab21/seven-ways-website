@extends('layouts.guest')

@section('title', 'خطأ في الخادم')
@section('content')
    <x-error-page
        code="500"
        title="حدث خطأ غير متوقع"
        message="تعذر إكمال الطلب الآن. حاول مرة أخرى بعد قليل."
        :reference="request()->attributes->get('correlation_id')"
    />
@endsection
