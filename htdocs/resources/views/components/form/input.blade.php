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

    $pickerIcon = match ($type) {
        'time' => 'fa-solid fa-clock',
        'datetime-local' => 'fa-solid fa-calendar-day',
        'month', 'week' => 'fa-solid fa-calendar-week',
        'date' => 'fa-solid fa-calendar-days',
        default => null,
    };

    $control = $attributes->except('class')->merge(['class' => 'slct']);
@endphp

<x-form.field :label="$label" :for="$name" {{ $attributes->only('class') }}>
    @if ($pickerIcon)
        <div class="panel-date" data-picker-wrap>
            <i class="{{ $pickerIcon }} panel-date-icon" aria-hidden="true"></i>
            <input
                id="{{ $name }}"
                name="{{ $name }}"
                type="text"
                value="{{ $current }}"
                data-picker="{{ $type }}"
                autocomplete="off"
                {{ $control }}
            >
            <button type="button" class="panel-date-clear" data-picker-clear aria-label="Temizle" @if (blank($current)) hidden @endif>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    @else
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $current }}"
            {{ $control }}
        >
    @endif
    {{ $slot }}
</x-form.field>
