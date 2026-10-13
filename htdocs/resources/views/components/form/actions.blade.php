@props(['label', 'cancel'])

<x-form.field label="">
    <div class="panel-form-actions">
        <button type="submit" class="panel-btn">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $label }}
        </button>
        <a href="{{ $cancel }}" class="panel-arama-toggle" style="margin-left:8px;">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Listeye dön
        </a>
    </div>
</x-form.field>
