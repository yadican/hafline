<div id="haul-search" class="panel-liste-bar haul-search-bar" x-show="searchOpen" x-cloak>
    <x-search.form :action="route('admin.hauls.index')" data-haul-search>
        <x-search.text
            name="f[ticket]"
            label="Fiş No"
            :value="$filters['ticket'] ?? null"
            title="Tam eşleşme veya joker: 123%, %123%, %123"
            maxlength="20"
            class="md:col-span-3"
        />
        <x-search.text name="f[plate]" label="Plaka" :value="$filters['plate'] ?? null" class="haul-search-plate md:col-span-3" maxlength="20" />

        <x-search.select name="f[site]" searchable label="Şantiye" placeholder="Tüm şantiyeler" :options="$options['sites']" :value="$filters['site'] ?? null" class="md:col-span-3" />
        <x-search.select name="f[dump_site]" searchable label="Döküm yeri" placeholder="Tüm döküm yerleri" :options="$options['dumpSites']" :value="$filters['dump_site'] ?? null" class="md:col-span-3" />
        <x-search.select name="f[owner]" searchable label="Plaka sahibi" placeholder="Tüm plaka sahipleri" :options="$options['owners']" :value="$filters['owner'] ?? null" class="md:col-span-3" />
        <x-search.select name="f[class]" searchable hide-search label="Sınıf" placeholder="Tüm sınıflar" :options="$options['classes']" :value="$filters['class'] ?? null" class="md:col-span-3" />
        <x-search.select name="f[shift]" searchable hide-search label="Vardiya" placeholder="Tüm vardiyalar" :options="$options['shifts']" :value="$filters['shift'] ?? null" class="md:col-span-3" />
        <x-search.select name="f[creator]" searchable label="İşlem yapan" placeholder="Tüm kullanıcılar" :options="$options['creators']" :value="$filters['creator'] ?? null" class="md:col-span-3" />

        <x-form.input name="f[hauled_from]" label="" type="datetime-local" :value="$filters['hauled_from'] ?? null" placeholder="Taşıma başlangıç" aria-label="Taşıma tarihi (başlangıç)" data-alt-format="d.m.Y H:i" class="md:col-span-3" />
        <x-form.input name="f[hauled_to]" label="" type="datetime-local" :value="$filters['hauled_to'] ?? null" placeholder="Taşıma bitiş" aria-label="Taşıma tarihi (bitiş)" data-alt-format="d.m.Y H:i" class="md:col-span-3" />
        <x-form.input name="f[entered_from]" label="" type="datetime-local" :value="$filters['entered_from'] ?? null" placeholder="Giriş başlangıç" aria-label="Giriş zamanı (başlangıç)" data-alt-format="d.m.Y H:i" class="md:col-span-3" />
        <x-form.input name="f[entered_to]" label="" type="datetime-local" :value="$filters['entered_to'] ?? null" placeholder="Giriş bitiş" aria-label="Giriş zamanı (bitiş)" data-alt-format="d.m.Y H:i" class="md:col-span-3" />

        <div class="col-span-full mt-1 flex flex-wrap justify-stretch gap-2 border-t border-[var(--ui-border-soft)] pt-3 sm:justify-end">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
        </div>
    </x-search.form>
</div>
