@props(['label', 'cancel'])

<div class="panel-form-actions">
    <button type="submit" class="panel-btn panel-btn-primary">
        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $label }}
    </button>
    <a href="{{ $cancel }}" class="panel-btn panel-btn-secondary">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Listeye dön
    </a>
</div>
