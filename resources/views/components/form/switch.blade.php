@props(['name', 'label', 'checked' => false])

<label class="sw-switch">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) {{ $attributes }}>
    <span class="sw-switch__track" aria-hidden="true"><span></span></span>
    <span>{{ $label }}</span>
</label>
