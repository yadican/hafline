{{-- Durum JS tarafında setStatus(element, 'idle' | 'ok' | 'err', mesaj) ile değiştirilir; salt okunur sayfalarda state ile verilir --}}
@props(['state' => 'idle'])

@php
    $icon = ['ok' => 'fa-solid fa-circle-check', 'err' => 'fa-solid fa-circle-xmark'][$state] ?? 'fa-solid fa-circle';
@endphp

<small {{ $attributes->class(['panel-hint', 'panel-status-hint', 'is-'.$state => $state !== 'idle']) }} data-status aria-live="polite">
    <span class="panel-field-status is-{{ $state }}"><i class="{{ $icon }}" aria-hidden="true"></i></span>
    <span data-status-text>{{ $slot }}</span>
</small>
