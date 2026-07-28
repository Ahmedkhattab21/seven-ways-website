@extends('layouts.guest')

@section('title', 'محاولات كثيرة')
@section('content')
    <x-error-page code="429" title="محاولات كثيرة" message="انتظر قليلًا قبل إعادة المحاولة." />
@endsection
