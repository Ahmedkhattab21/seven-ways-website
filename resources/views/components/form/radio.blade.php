@props(['name', 'value', 'label', 'checked' => false])

<label class="sw-radio">
    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked(old($name) === $value || $checked) {{ $attributes }}>
    <span class="sw-radio__control" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</label>
