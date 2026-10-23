<div class="panel-liste-bar haul-toolbar">
    <div class="haul-toolbar-row">
        <div class="haul-toolbar-start">
            <x-ui.button
                variant="search"
                icon="fa-solid fa-filter"
                class="haul-search-toggle"
                x-bind:class="{ 'is-open': searchOpen }"
                x-on:click="searchOpen = ! searchOpen"
                x-bind:aria-expanded="searchOpen.toString()"
                aria-controls="haul-search"
                aria-expanded="false"
            >
                Arama
                @if ($filters !== [])
                    <span class="haul-search-count">{{ count($filters) }}</span>
                @endif
                <i class="fa-solid fa-chevron-down haul-search-chevron" aria-hidden="true"></i>
            </x-ui.button>
            @if ($filters !== [])
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.hauls.index')">Filtreleri temizle</x-ui.button>
            @endif
        </div>
        <div class="haul-toolbar-end">
            <span class="haul-toolbar-count">{{ number_format($hauls->total(), 0, ',', '.') }} kayıt</span>
            <x-ui.button icon="fa-solid fa-print" data-haul-print>Yazdır</x-ui.button>
            <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.hauls.create')">Yeni fiş</x-ui.button>
        </div>
    </div>
    @if ($filters === [])
        <p class="haul-toolbar-note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            Son {{ \App\Repositories\HaulRepository::RECENT_DAYS }} günün kayıtları listeleniyor — daha eski fişler için Arama’yı açın.
        </p>
    @endif
</div>
