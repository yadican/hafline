{{-- $url, ?q= ile çağrılır ve [{value, label?, meta?}] dizisi döner --}}
@aware(['model' => null])

@props(['name', 'label', 'url', 'value' => null, 'min' => 2])

@php
    $current = old($name, $value ?? data_get($model, $name));
    $menu = preg_replace('/[^A-Za-z0-9_-]+/', '-', $name).'-menu';
@endphp

<x-form.field :label="$label" :for="$name">
    <div class="panel-autocomplete" data-autocomplete>
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="text"
            value="{{ $current }}"
            autocomplete="off"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="{{ $menu }}"
            data-autocomplete-input
            data-url="{{ $url }}"
            data-min="{{ $min }}"
            {{ $attributes->merge(['class' => 'slct']) }}
        >
        <div id="{{ $menu }}" class="panel-autocomplete-menu" role="listbox" aria-label="{{ $label }}" hidden data-autocomplete-menu></div>
    </div>
    {{ $slot }}
</x-form.field>
