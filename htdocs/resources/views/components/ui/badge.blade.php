@props(['tone' => null, 'icon' => null])

<span {{ $attributes->class(['panel-badge', 'panel-badge-'.$tone => $tone !== null]) }}>
    @if ($icon)
        <i class="{{ $icon }}" aria-hidden="true"></i>
    @endif
    {{ $slot }}
</span>
