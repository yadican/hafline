@props(['active'])

@if ($active)
    <x-ui.badge tone="aktif" {{ $attributes }}>Aktif</x-ui.badge>
@else
    <x-ui.badge tone="pasif" {{ $attributes }}>Pasif</x-ui.badge>
@endif
