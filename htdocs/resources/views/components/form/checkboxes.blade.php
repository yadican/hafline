@aware(['model' => null])

@props(['name', 'label', 'options' => []])

@php
    $selected = collect(old($name, data_get($model, $name, [])))
        ->map(fn ($item) => $item instanceof \BackedEnum ? $item->value : $item)
        ->all();
@endphp

<x-form.field :label="$label">
    @foreach ($options as $optionValue => $optionLabel)
        <label class="panel-choice">
            <input type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" @checked(in_array((string) $optionValue, array_map('strval', $selected), true))>
            {{ $optionLabel }}
        </label>
    @endforeach
</x-form.field>
