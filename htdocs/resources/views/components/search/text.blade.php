@props(['name', 'label', 'value' => null])

<input
    type="search"
    name="{{ $name }}"
    value="{{ $value }}"
    aria-label="{{ $label }}"
    autocomplete="off"
    {{ $attributes->merge(['class' => 'slct', 'placeholder' => $label]) }}
>
