@props(['variant' => 'neutral', 'icon' => null, 'href' => null, 'type' => 'button'])

@php
    $classes = 'panel-btn panel-btn-'.$variant;
@endphp

@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        @if ($icon)
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        @if ($icon)
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif
        {{ $slot }}
    </button>
@endif
