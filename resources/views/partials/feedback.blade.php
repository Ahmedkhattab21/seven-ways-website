@if(session('status'))
    <x-alert type="success">{{ session('status') }}</x-alert>
@endif

@if(session('error'))
    <x-alert type="danger">{{ session('error') }}</x-alert>
@endif

@if($errors->any() && !request()->routeIs('login'))
    <x-alert type="danger" title="يرجى مراجعة البيانات">
        <ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </x-alert>
@endif
