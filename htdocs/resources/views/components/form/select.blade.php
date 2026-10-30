{{-- iç içe alan nokta notasyonuyla verilir (payment.bank_id); select adı köşeli paranteze çevrilir --}}
@aware(['model' => null])

@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'multiple' => false, 'searchable' => false, 'hideSearch' => false])

@php
    $id = str_replace('.', '_', $name);
    $field = preg_replace('/\.([^.]+)/', '[$1]', $name);
    $selected = old($name, $value ?? data_get($model, $name));
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;

    if ($multiple) {
        $selected = collect($selected ?? [])
            ->map(fn ($item) => $item instanceof \BackedEnum ? $item->value : $item)
            ->all();
    }
@endphp

<x-form.field :label="$label" :for="$id" :name="$name">
    <select
        id="{{ $id }}"
        name="{{ $multiple ? $field.'[]' : $field }}"
        @if ($multiple) multiple data-multi-select data-placeholder="{{ $placeholder ?? 'Seçin' }}" @endif
        @if ($searchable && ! $multiple)
            data-searchable-select
            data-placeholder="{{ $placeholder ?? 'Seçin' }}"
            @if ($hideSearch) data-hide-search @endif
        @endif
        {{ $attributes->merge(['class' => 'slct']) }}
    >
        @if (! $multiple && $placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($multiple ? in_array((string) $optionValue, array_map('strval', $selected), true) : (string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    {{ $slot }}
</x-form.field>
