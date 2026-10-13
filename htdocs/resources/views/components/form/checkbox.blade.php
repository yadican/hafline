@aware(['model' => null])

@props(['name', 'label', 'text'])

@php
    $checked = (bool) old($name, data_get($model, $name, false));
@endphp

<x-form.field :label="$label">
    <label class="panel-choice">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)>
        {{ $text }}
    </label>
</x-form.field>
