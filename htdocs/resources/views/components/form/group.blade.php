@props(['legend', 'icon' => null, 'description' => null])

<fieldset {{ $attributes->merge(['class' => 'panel-form-group']) }}>
    <legend class="panel-form-group-head">
        <span class="panel-form-group-icon" aria-hidden="true">
            <i class="{{ $icon ?? 'fa-solid fa-layer-group' }}"></i>
        </span>
        <span class="panel-form-group-text">
            <span class="panel-form-group-title">{{ $legend }}</span>
            @if ($description)
                <span class="panel-form-group-desc">{{ $description }}</span>
            @endif
        </span>
    </legend>
    <div class="panel-form-group-body">
        {{ $slot }}
    </div>
</fieldset>
