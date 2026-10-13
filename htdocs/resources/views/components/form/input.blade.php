@aware(['model' => null])

@props(['name', 'label', 'type' => 'text', 'value' => null])

@php
    $raw = $value ?? data_get($model, $name);

    if ($raw instanceof DateTimeInterface) {
        $raw = match ($type) {
            'date' => $raw->format('Y-m-d'),
            'time' => $raw->format('H:i'),
            'datetime-local' => $raw->format('Y-m-d\TH:i'),
            'month' => $raw->format('Y-m'),
            default => $raw->format('Y-m-d'),
        };
    }

    if ($type === 'time' && is_string($raw)) {
        $raw = substr($raw, 0, 5);
    }

    if ($raw === null && $type === 'color') {
        $raw = '#c4892e';
    }

    if ($raw === null && $type === 'range') {
        $raw = 0;
    }

    $current = old($name, $raw);
@endphp

<x-form.field :label="$label" :for="$name">
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $current }}"
        {{ $attributes->merge(['class' => 'slct']) }}
    >
</x-form.field>
