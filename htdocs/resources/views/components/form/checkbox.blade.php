@aware(['model' => null])

@props(['name', 'label', 'text', 'checked' => false])

@php
    $isChecked = (bool) old($name, data_get($model, $name, $checked));
@endphp

<x-form.field :label="$label" :name="$name">
    <label class="panel-choice">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($isChecked)>
        {{ $text }}
    </label>
</x-form.field>
