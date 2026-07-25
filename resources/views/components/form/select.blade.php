@props(['name', 'label' => null, 'required' => false, 'help' => null])

<div class="sw-field">
    @if($label)<label class="sw-field__label" for="{{ $name }}">{{ $label }} @if($required)<span class="sw-field__required">*</span>@endif</label>@endif
    <select id="{{ $name }}" name="{{ $name }}" @required($required)
        {{ $attributes->class(['sw-input', 'sw-input--error' => $errors->has($name)]) }}>
        {{ $slot }}
    </select>
    @if($help)<p class="sw-field__help">{{ $help }}</p>@endif
    @error($name)<p class="sw-field__error">{{ $message }}</p>@enderror
</div>
