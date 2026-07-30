@props(['name', 'label', 'checked' => false])

@php($inputId = 'account-rule-'.str_replace('_', '-', $name))

<input type="hidden" name="{{ $name }}" value="0">
<label class="sw-check account-rule-option" for="{{ $inputId }}">
    <input
        id="{{ $inputId }}"
        type="checkbox"
        name="{{ $name }}"
        value="1"
        @checked(old($name, $checked))
        {{ $attributes }}
    >
    <span class="sw-check__box" aria-hidden="true"></span>
    <span class="sw-check__label">{{ $label }}</span>
</label>
