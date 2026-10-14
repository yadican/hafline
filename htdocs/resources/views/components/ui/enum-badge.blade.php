{{-- $value: badge(), icon() ve label() metotları olan bir enum --}}
@props(['value', 'text' => false])

@if ($value === null)
    —
@else
    <span {{ $attributes->class(['panel-badge', $value->badge()]) }} title="{{ $value->label() }}">
        <i class="{{ $value->icon() }}" aria-hidden="true"></i>
        <span @class(['sr-only' => ! $text])>{{ $value->label() }}</span>
    </span>
@endif
