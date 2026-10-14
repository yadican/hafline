<form method="POST" action="{{ route('admin.hauls.store') }}" data-haul-entry>
    @csrf
    <x-form.grid>
        <x-form.group legend="Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, araç ve sefer bilgileri">
            <x-form.input name="ticket_no" label="Fiş No" maxlength="20" required autofocus />
            <x-form.autocomplete
                name="plate"
                label="Plaka"
                :url="route('admin.hauls.lookups.vehicles')"
                placeholder="34 ABC 123"
                maxlength="20"
                required
                class="haul-plate"
                data-haul-plate
            >
                <x-form.status data-haul-plate-hint>Yazmaya başlayın, listeden seçin</x-form.status>
            </x-form.autocomplete>
            <x-form.input name="trips" label="Sefer sayısı" type="number" value="1" min="1" max="50" required />
            <x-form.input name="hauled_at" label="Tarih / saat" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" required />
            <x-form.select name="shift" label="Vardiya" :options="$options['shifts']" value="day" required />
        </x-form.group>

        <x-form.group legend="Güzergah" icon="fa-solid fa-route" description="Şantiye, döküm yeri ve tonaj">
            <x-form.select
                name="site_id"
                label="Şantiye"
                placeholder="Şantiye seçin"
                :options="$options['sites']"
                required
                data-haul-site
            />
            <x-form.select
                name="dump_site_id"
                label="Döküm yeri"
                :placeholder="$options['dumpSites'] ? 'Döküm yeri seçin' : 'Önce şantiye seçin'"
                :options="$options['dumpSites']"
                required
                data-haul-dump-site
                :data-url="route('admin.hauls.lookups.dump-sites')"
            />
            <x-form.input name="tonnage" label="Tonaj" type="number" min="0" max="1000" />
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.hauls.index')" />
    </x-form.grid>
</form>
