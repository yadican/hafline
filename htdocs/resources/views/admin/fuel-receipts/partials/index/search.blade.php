<div id="fuel-receipt-search" class="panel-liste-bar haul-search-bar" x-show="searchOpen" x-cloak>
    <x-search.form :action="route('admin.fuel-receipts.index')" data-entry-search>
        <x-search.text name="f[ticket]" label="Fiş No" :value="$filters['ticket'] ?? null" maxlength="20" />
        <x-search.text name="f[plate]" label="Plaka" :value="$filters['plate'] ?? null" class="haul-search-plate" maxlength="20" />
        <x-search.select name="f[station]" label="Yakıt yeri" placeholder="Tüm yakıt yerleri" :options="$options['stations']" :value="$filters['station'] ?? null" />
        <x-search.select name="f[site]" label="Şantiye" placeholder="Tüm şantiyeler" :options="$options['sites']" :value="$filters['site'] ?? null" />
        <x-search.select name="f[owner]" label="Araç sahibi" placeholder="Tüm araç sahipleri" :options="$options['owners']" :value="$filters['owner'] ?? null" />
        <x-search.select name="f[shift]" label="Vardiya" placeholder="Tüm vardiyalar" :options="$options['shifts']" :value="$filters['shift'] ?? null" />
        <x-search.select name="f[creator]" label="İşlem yapan" placeholder="Tüm kullanıcılar" :options="$options['creators']" :value="$filters['creator'] ?? null" />
        <x-form.input name="f[issued_from]" label="" type="date" :value="$filters['issued_from'] ?? null" placeholder="Tarih başlangıç" aria-label="Fiş tarihi (başlangıç)" />
        <x-form.input name="f[issued_to]" label="" type="date" :value="$filters['issued_to'] ?? null" placeholder="Tarih bitiş" aria-label="Fiş tarihi (bitiş)" />
        <div class="haul-search-actions">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass">Ara</x-ui.button>
        </div>
    </x-search.form>
</div>
