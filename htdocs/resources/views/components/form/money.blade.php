{{-- name nokta notasyonuyla verilir (class_prices.<id>); input adı köşeli paranteze çevrilir --}}
@aware(['model' => null])

@props(['name', 'label', 'value' => null, 'step' => '0.01', 'suffix' => 'TL'])

@php
    $id = str_replace('.', '_', $name);
    $field = preg_replace('/\.([^.]+)/', '[$1]', $name);
    $current = old($name, $value ?? data_get($model, $name));
@endphp

<x-form.field :label="$label" :for="$id" :name="$name">
    <div class="panel-money">
        <input
            id="{{ $id }}"
            name="{{ $field }}"
            type="number"
            step="{{ $step }}"
            min="0"
            inputmode="decimal"
            value="{{ $current }}"
            {{ $attributes->merge(['class' => 'slct']) }}
        >
        <span class="panel-money-suffix" aria-hidden="true">{{ $suffix }}</span>
    </div>
    {{ $slot }}
</x-form.field>
