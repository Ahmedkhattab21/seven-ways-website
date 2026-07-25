@props(['name', 'label', 'checked' => false])

<label class="sw-check">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) {{ $attributes }}>
    <span class="sw-check__box" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</label>
