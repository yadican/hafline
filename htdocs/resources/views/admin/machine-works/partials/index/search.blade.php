<div id="machine-work-search" class="panel-liste-bar haul-search-bar" x-show="searchOpen" x-cloak>
    <x-search.form :action="route('admin.machine-works.index')" data-entry-search>
        <x-search.text
            name="f[ticket]"
            label="Fiş No"
            :value="$filters['ticket'] ?? null"
            title="Tam eşleşme veya joker: 123%, %123%, %123"
            maxlength="20"
            class="md:col-span-4"
        />
        <x-search.text name="f[plate]" label="Plaka" :value="$filters['plate'] ?? null" class="haul-search-plate md:col-span-4" maxlength="20" />

        <x-search.select searchable name="f[site]" label="Şantiye" placeholder="Tüm şantiyeler" :options="$options['sites']" :value="$filters['site'] ?? null" class="md:col-span-4" />
        <x-search.select searchable name="f[owner]" label="Makine sahibi" placeholder="Tüm makine sahipleri" :options="$options['owners']" :value="$filters['owner'] ?? null" class="md:col-span-4" />
        <x-search.select searchable name="f[operator]" label="Operatör" placeholder="Tüm operatörler" :options="$options['operators']" :value="$filters['operator'] ?? null" class="md:col-span-4" />
        <x-search.select searchable hide-search name="f[shift]" label="Vardiya" placeholder="Tüm vardiyalar" :options="$options['shifts']" :value="$filters['shift'] ?? null" class="md:col-span-4" />
        <x-search.select searchable name="f[creator]" label="İşlem yapan" placeholder="Tüm kullanıcılar" :options="$options['creators']" :value="$filters['creator'] ?? null" class="md:col-span-4" />

        <x-form.input name="f[worked_from]" label="" type="date" :value="$filters['worked_from'] ?? null" placeholder="Çalışma başlangıç" aria-label="Çalışma tarihi (başlangıç)" class="md:col-span-4" />
        <x-form.input name="f[worked_to]" label="" type="date" :value="$filters['worked_to'] ?? null" placeholder="Çalışma bitiş" aria-label="Çalışma tarihi (bitiş)" class="md:col-span-4" />

        <div class="col-span-full mt-1 flex flex-wrap justify-stretch gap-2 border-t border-[var(--ui-border-soft)] pt-3 sm:justify-end">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
        </div>
    </x-search.form>
</div>
