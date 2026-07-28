@extends('layouts.guest')

@section('title', 'تعذر معالجة الطلب')
@section('content')
    <x-error-page code="422" title="تعذر معالجة الطلب" message="راجع البيانات المرسلة ثم حاول مرة أخرى." />
@endsection
