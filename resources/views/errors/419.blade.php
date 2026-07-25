@extends('layouts.guest')

@section('title', 'انتهت الجلسة')
@section('content')
    <x-error-page code="419" title="انتهت صلاحية الجلسة" message="أعد تسجيل الدخول للمتابعة بأمان." />
@endsection
