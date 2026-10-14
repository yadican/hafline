<div class="panel-liste-bar">
    <button
        type="button"
        class="panel-btn haul-search-toggle"
        :class="{ 'is-open': searchOpen }"
        x-on:click="searchOpen = ! searchOpen"
        :aria-expanded="searchOpen.toString()"
        aria-controls="haul-search"
        aria-expanded="false"
    >
        <i class="fa-solid fa-filter" aria-hidden="true"></i>
        Arama
        @if ($filters !== [])
            <span class="haul-search-count">{{ count($filters) }}</span>
        @endif
        <i class="fa-solid fa-chevron-down haul-search-chevron" aria-hidden="true"></i>
    </button>
    @if ($filters === [])
        <span class="panel-info">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            Liste performansı için son {{ \App\Repositories\HaulRepository::RECENT_DAYS }} günün kayıtları gösteriliyor.
        </span>
    @else
        <a href="{{ route('admin.hauls.index') }}" class="panel-btn">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i> Filtreleri temizle
        </a>
    @endif
    <span class="uyari">{{ number_format($hauls->total(), 0, ',', '.') }} kayıt</span>
    <button type="button" class="panel-btn" data-haul-print>
        <i class="fa-solid fa-print" aria-hidden="true"></i> Yazdır
    </button>
    <a href="{{ route('admin.hauls.create') }}" class="panel-btn">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Yeni fiş
    </a>
</div>
