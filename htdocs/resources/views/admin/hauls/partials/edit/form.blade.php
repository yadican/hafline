<form method="POST" action="{{ route('admin.hauls.update', $haul) }}" data-haul-entry data-quote-url="{{ route('admin.hauls.lookups.quote') }}" data-current-route="{{ $haul->route_id }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$haul">
        <x-form.group legend="Fiş" icon="fa-solid fa-file-lines" description="Fiş numarası, araç, tarih ve vardiya">
            <x-form.input name="ticket_no" label="Fiş No" maxlength="20" required autofocus />
            <x-form.autocomplete
                name="plate"
                label="Plaka"
                :url="route('admin.hauls.lookups.vehicles')"
                :value="$haul->vehicle?->plate"
                placeholder="34 ABC 123"
                maxlength="20"
                required
                class="panel-plate"
                data-haul-plate
            >
                <x-form.status data-haul-plate-hint>Yazmaya başlayın, listeden seçin</x-form.status>
            </x-form.autocomplete>
            <x-form.input name="hauled_at" label="Tarih / saat" type="datetime-local" required />
            <x-form.select name="shift" label="Vardiya" :options="$options['shifts']" required />
        </x-form.group>

        <x-form.group legend="Güzergah" icon="fa-solid fa-route" description="Şantiye, döküm yeri, fiyat ve tonaj">
            <x-form.select
                name="site_id"
                label="Şantiye"
                placeholder="Şantiye seçin"
                :options="$options['sites']"
                required
                searchable
                data-haul-site
            />
            <x-form.select
                name="dump_site_id"
                label="Döküm yeri"
                placeholder="Döküm yeri seçin"
                :options="$options['dumpSites']"
                required
                searchable
                data-haul-dump-site
                :data-url="route('admin.hauls.lookups.dump-sites')"
            >
                <x-form.status data-haul-quote>Kayıtlı fiyat: {{ number_format((float) $haul->price + (float) $haul->dump_price, 2, ',', '.') }} TL · plaka veya güzergah değişmedikçe korunur</x-form.status>
            </x-form.select>
            <x-form.input name="tonnage" label="Tonaj" type="number" min="0" max="1000" />
        </x-form.group>

        @if (! empty($photoUrl))
            <x-form.group legend="Saha fotoğrafı" icon="fa-solid fa-camera" description="Plaka taramasından gelen görüntü">
                <div class="md:col-span-full">
                    <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="inline-block">
                        <img
                            src="{{ $photoUrl }}"
                            alt="Saha fotoğrafı"
                            class="max-h-64 max-w-full rounded border border-[var(--ui-border-soft)] object-contain"
                        >
                    </a>
                    <p class="mt-2 text-sm text-slate-500 break-all">
                        <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="underline">{{ $photoUrl }}</a>
                    </p>
                </div>
            </x-form.group>
        @endif

        <x-form.actions label="Güncelle" :cancel="route('admin.hauls.index')" />
    </x-form.grid>
</form>
