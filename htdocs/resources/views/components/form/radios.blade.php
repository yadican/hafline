@aware(['model' => null])

@props(['name', 'label', 'options' => []])

@php
    $selected = old($name, data_get($model, $name));
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;
@endphp

<x-form.field :label="$label">
    @foreach ($options as $optionValue => $optionLabel)
        <label class="panel-choice">
            <input type="radio" name="{{ $name }}" value="{{ $optionValue }}" @checked((string) $selected === (string) $optionValue)>
            {{ $optionLabel }}
        </label>
    @endforeach
</x-form.field>
