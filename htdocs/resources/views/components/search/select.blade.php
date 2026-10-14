@props(['name', 'label', 'placeholder', 'options' => [], 'value' => null])

<select name="{{ $name }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => 'slct']) }}>
    <option value="">{{ $placeholder }}</option>
    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
    @endforeach
</select>
