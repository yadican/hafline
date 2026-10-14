<div id="haul-search" class="panel-liste-bar haul-search-bar" x-show="searchOpen" x-cloak>
    <form method="GET" action="{{ route('admin.hauls.index') }}" role="search" data-haul-search>
        @if (request('sort'))
            <input type="hidden" name="sort" value="{{ request('sort') }}">
            <input type="hidden" name="dir" value="{{ request('dir', 'desc') }}">
        @endif

        <input
            type="search"
            name="f[ticket]"
            value="{{ $filters['ticket'] ?? '' }}"
            class="slct"
            placeholder="Fiş No"
            title="Tam eşleşme veya joker: 123%, %123%, %123"
            aria-label="Fiş No"
            maxlength="20"
            autocomplete="off"
        >

        <input
            type="search"
            name="f[plate]"
            value="{{ $filters['plate'] ?? '' }}"
            class="slct haul-search-plate"
            placeholder="Plaka"
            aria-label="Plaka"
            maxlength="20"
            autocomplete="off"
        >

        @include('admin.hauls.partials.index.search-select', ['key' => 'site', 'label' => 'Şantiye', 'placeholder' => 'Tüm şantiyeler', 'items' => $options['sites']])
        @include('admin.hauls.partials.index.search-select', ['key' => 'dump_site', 'label' => 'Döküm yeri', 'placeholder' => 'Tüm döküm yerleri', 'items' => $options['dumpSites']])
        @include('admin.hauls.partials.index.search-select', ['key' => 'owner', 'label' => 'Plaka sahibi', 'placeholder' => 'Tüm plaka sahipleri', 'items' => $options['owners']])
        @include('admin.hauls.partials.index.search-select', ['key' => 'class', 'label' => 'Sınıf', 'placeholder' => 'Tüm sınıflar', 'items' => $options['classes']])
        @include('admin.hauls.partials.index.search-select', ['key' => 'shift', 'label' => 'Vardiya', 'placeholder' => 'Tüm vardiyalar', 'items' => $options['shifts']])
        @include('admin.hauls.partials.index.search-select', ['key' => 'creator', 'label' => 'İşlem yapan', 'placeholder' => 'Tüm kullanıcılar', 'items' => $options['creators']])

        <x-form.input name="f[hauled_from]" label="" type="datetime-local" :value="$filters['hauled_from'] ?? null" placeholder="Taşıma başlangıç" aria-label="Taşıma tarihi (başlangıç)" data-alt-format="d.m.Y H:i" />
        <x-form.input name="f[hauled_to]" label="" type="datetime-local" :value="$filters['hauled_to'] ?? null" placeholder="Taşıma bitiş" aria-label="Taşıma tarihi (bitiş)" data-alt-format="d.m.Y H:i" />
        <x-form.input name="f[entered_from]" label="" type="datetime-local" :value="$filters['entered_from'] ?? null" placeholder="Giriş başlangıç" aria-label="Giriş zamanı (başlangıç)" data-alt-format="d.m.Y H:i" />
        <x-form.input name="f[entered_to]" label="" type="datetime-local" :value="$filters['entered_to'] ?? null" placeholder="Giriş bitiş" aria-label="Giriş zamanı (bitiş)" data-alt-format="d.m.Y H:i" />

        <div class="haul-search-actions">
            <button type="submit" class="panel-btn">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Ara
            </button>
        </div>
    </form>
</div>
