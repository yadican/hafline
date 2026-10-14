{{-- Durum JS tarafında setStatus(element, 'idle' | 'ok' | 'err', mesaj) ile değiştirilir --}}
<small {{ $attributes->class(['panel-hint', 'panel-status-hint']) }} data-status aria-live="polite">
    <span class="panel-field-status is-idle"><i class="fa-solid fa-circle" aria-hidden="true"></i></span>
    <span data-status-text>{{ $slot }}</span>
</small>
