<div id="machine-work-search" class="panel-liste-bar haul-search-bar" x-show="searchOpen" x-cloak>
    <x-search.form :action="route('admin.machine-works.index')" data-entry-search>
        <x-search.text
            name="f[ticket]"
            label="Fiş No"
            :value="$filters['ticket'] ?? null"
            title="Tam eşleşme veya joker: 123%, %123%, %123"
            maxlength="20"
        />
        <x-search.text name="f[plate]" label="Plaka" :value="$filters['plate'] ?? null" class="haul-search-plate" maxlength="20" />

        <x-search.select searchable name="f[site]" label="Şantiye" placeholder="Tüm şantiyeler" :options="$options['sites']" :value="$filters['site'] ?? null" />
        <x-search.select searchable name="f[owner]" label="Makine sahibi" placeholder="Tüm makine sahipleri" :options="$options['owners']" :value="$filters['owner'] ?? null" />
        <x-search.select searchable name="f[operator]" label="Operatör" placeholder="Tüm operatörler" :options="$options['operators']" :value="$filters['operator'] ?? null" />
        <x-search.select searchable hide-search name="f[shift]" label="Vardiya" placeholder="Tüm vardiyalar" :options="$options['shifts']" :value="$filters['shift'] ?? null" />
        <x-search.select searchable name="f[creator]" label="İşlem yapan" placeholder="Tüm kullanıcılar" :options="$options['creators']" :value="$filters['creator'] ?? null" />

        <x-form.input name="f[worked_from]" label="" type="date" :value="$filters['worked_from'] ?? null" placeholder="Çalışma başlangıç" aria-label="Çalışma tarihi (başlangıç)" />
        <x-form.input name="f[worked_to]" label="" type="date" :value="$filters['worked_to'] ?? null" placeholder="Çalışma bitiş" aria-label="Çalışma tarihi (bitiş)" />

        <div class="haul-search-actions">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        </div>
    </x-search.form>
</div>
