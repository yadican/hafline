<div class="panel-title">
    <div class="panel-title-main">
        @if (! empty($icon))
            <span class="panel-title-icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
        @endif
        <div class="panel-title-text">
            @if (! empty($breadcrumbs))
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
            @if (! empty($description))
                <p class="panel-title-desc">{{ $description }}</p>
            @endif
        </div>
    </div>
    @if (! empty($meta) || ! empty($back))
        <div class="panel-title-aside">
            @if (! empty($meta))
                <span class="panel-meta">{{ $meta }}</span>
            @endif
            @if (! empty($back))
                <a href="{{ $back }}" class="panel-btn panel-btn-secondary">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Listeye dön
                </a>
            @endif
        </div>
    @endif
</div>
