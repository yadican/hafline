@aware(['model' => null])

@props(['name', 'label', 'value' => null, 'rows' => 3])

<x-form.field :label="$label" :for="$name">
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->merge(['class' => 'slct']) }}>{{ old($name, $value ?? data_get($model, $name)) }}</textarea>
</x-form.field>
