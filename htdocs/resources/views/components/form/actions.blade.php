@aware(['model' => null])

@props(['label', 'cancel'])

<div class="panel-form-actions">
    <x-ui.button type="submit" :variant="$model === null ? 'create' : 'edit'" icon="fa-solid fa-floppy-disk">{{ $label }}</x-ui.button>
    <x-ui.button variant="secondary" icon="fa-solid fa-arrow-left" :href="$cancel">Listeye dön</x-ui.button>
</div>
