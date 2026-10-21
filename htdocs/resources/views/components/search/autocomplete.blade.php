{{-- $url, ?q= ile çağrılır ve [{value, label?, meta?}] dizisi döner --}}
@props(['name', 'label', 'url', 'value' => null, 'min' => 2])

@php
    $current = old($name, $value);
    $menu = preg_replace('/[^A-Za-z0-9_-]+/', '-', $name).'-menu';
    $wrapClass = $attributes->has('class');
    $control = $attributes->except('class')->merge(['class' => 'slct panel-plate']);
@endphp

@if ($wrapClass)
    <div {{ $attributes->only('class')->class(['min-w-0']) }}>
@endif
<div class="panel-autocomplete" data-autocomplete>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="search"
        value="{{ $current }}"
        aria-label="{{ $label }}"
        placeholder="{{ $label }}"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded="false"
        aria-controls="{{ $menu }}"
        data-autocomplete-input
        data-url="{{ $url }}"
        data-min="{{ $min }}"
        {{ $control }}
    >
    <div id="{{ $menu }}" class="panel-autocomplete-menu" role="listbox" aria-label="{{ $label }}" hidden data-autocomplete-menu></div>
</div>
@if ($wrapClass)
    </div>
@endif
