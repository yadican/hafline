@props(['value', 'decimals' => 2, 'suffix' => 'TL'])

@if ($value === null)
    —
@else
    <span {{ $attributes->class(['panel-money-text']) }}>{{ number_format((float) $value, $decimals, ',', '.') }} {{ $suffix }}</span>
@endif
