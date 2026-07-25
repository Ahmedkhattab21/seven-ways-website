@props(['name', 'label' => null, 'required' => false, 'help' => null])

<div class="sw-field">
    @if($label)<label class="sw-field__label" for="{{ $name }}">{{ $label }} @if($required)<span class="sw-field__required">*</span>@endif</label>@endif
    <textarea id="{{ $name }}" name="{{ $name }}" @required($required)
        {{ $attributes->class(['sw-input sw-textarea', 'sw-input--error' => $errors->has($name)]) }}>{{ old($name, $slot) }}</textarea>
    @if($help)<p class="sw-field__help">{{ $help }}</p>@endif
    @error($name)<p class="sw-field__error">{{ $message }}</p>@enderror
</div>
