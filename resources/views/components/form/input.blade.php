@props([
    'name',
    'label' => null,
    'type' => 'text',
    'required' => false,
    'help' => null,
])

@php($fieldId = $attributes->get('id', $name))
<div class="sw-field">
    @if($label)
        <label class="sw-field__label" for="{{ $fieldId }}">
            {{ $label }}
            @if($required)<span class="sw-field__required" aria-hidden="true">*</span>@endif
        </label>
    @endif
    <div class="sw-field__control">
        <input
            id="{{ $fieldId }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ old($name, $attributes->get('value')) }}"
            @required($required)
            @if($errors->has($name)) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
            {{ $attributes->except(['id', 'value'])->class(['sw-input', 'sw-input--error' => $errors->has($name)]) }}
        >
        {{ $suffix ?? '' }}
    </div>
    @if($help && !$errors->has($name))
        <p class="sw-field__help">{{ $help }}</p>
    @endif
    @error($name)
        <p class="sw-field__error" id="{{ $fieldId }}-error">{{ $message }}</p>
    @enderror
</div>
