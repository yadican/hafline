@aware(['model' => null])
@props(['name', 'label', 'placeholder', 'options' => [], 'value' => null, 'multiple' => false, 'searchable' => false, 'hideSearch' => false, 'disabled' => false])
@php
    $selected = old($name, $value ?? data_get($model, $name));
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;

    if ($multiple) {
        $selected = collect($selected ?? [])
            ->map(fn ($item) => $item instanceof \BackedEnum ? $item->value : $item)
            ->all();
    }

    $control = $attributes->except('class')->merge(['class' => 'slct']);
    $wrapClass = $attributes->has('class');
@endphp
@if ($wrapClass)
    <div {{ $attributes->only('class')->class(['min-w-0']) }}>
@endif
<select name="{{ $name }}"
        aria-label="{{ $label }}"
        {{ $control }}
        @disabled($disabled)
        @if ($multiple) multiple data-multi-select data-placeholder="{{ $placeholder ?? 'Seçin' }}" @endif
        @if ($searchable && ! $multiple)
            data-searchable-select
            data-placeholder="{{ $placeholder ?? 'Seçin' }}"
            @if ($hideSearch) data-hide-search @endif
        @endif
>
    @if (! $multiple && $placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected($multiple ? in_array((string) $optionValue, array_map('strval', $selected), true) : (string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
    @endforeach
</select>
@if ($wrapClass)
    </div>
@endif
