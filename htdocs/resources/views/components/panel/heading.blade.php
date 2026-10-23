@props(['heading', 'icon' => null, 'description' => null, 'breadcrumbs' => [], 'back' => null, 'meta' => null])

@php
    $hasActions = isset($actions) && ! $actions->isEmpty();
@endphp

<div class="panel-title">
    <div class="panel-title-main">
        @if ($icon)
            <span class="panel-title-icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
        @endif
        <div class="panel-title-text">
            @if ($breadcrumbs)
                <nav class="panel-crumbs" aria-label="Sayfa konumu">
                    @foreach ($breadcrumbs as $label => $url)
                        @if ($url)
                            <a href="{{ $url }}">{{ $label }}</a>
                        @else
                            <span>{{ $label }}</span>
                        @endif
                        @unless ($loop->last)
                            <i class="fa-solid fa-chevron-right panel-crumbs-sep" aria-hidden="true"></i>
                        @endunless
                    @endforeach
                </nav>
            @endif
            <h1 class="panel-title-heading">{{ $heading }}</h1>
            @if ($description)
                <p class="panel-title-desc">{{ $description }}</p>
            @endif
        </div>
    </div>
    @if ($meta || $back || $hasActions)
        <div class="panel-title-aside">
            @if ($hasActions)
                {{ $actions }}
            @endif
            @if ($meta)
                <span class="panel-meta">{{ $meta }}</span>
            @endif
            @if ($back)
                <x-ui.button variant="secondary" icon="fa-solid fa-arrow-left" :href="$back">Listeye dön</x-ui.button>
            @endif
        </div>
    @endif
</div>
