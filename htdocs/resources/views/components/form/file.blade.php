@aware(['model' => null])

@props(['name', 'label'])

<x-form.field :label="$label" :for="$name">
    <input id="{{ $name }}" name="{{ $name }}" type="file" {{ $attributes->merge(['class' => 'panel-file']) }}>
    @if ($model?->attachment)
        <span class="panel-hint">{{ basename($model->attachment) }}</span>
    @endif
</x-form.field>
