@aware(['model' => null])

@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'multiple' => false])

@php
    $selected = old($name, $value ?? data_get($model, $name));
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;

    if ($multiple) {
        $selected = collect($selected ?? [])
            ->map(fn ($item) => $item instanceof \BackedEnum ? $item->value : $item)
            ->all();
    }
@endphp

<x-form.field :label="$label" :for="$name">
    <select
        id="{{ $name }}"
        name="{{ $multiple ? $name.'[]' : $name }}"
        @if ($multiple) multiple data-multi-select data-placeholder="{{ $placeholder ?? 'Seçin' }}" @endif
        {{ $attributes->merge(['class' => 'slct']) }}
    >
        @if (! $multiple && $placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($multiple ? in_array((string) $optionValue, array_map('strval', $selected), true) : (string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-form.field>
