<form method="POST" action="{{ route('admin.dump-sites.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="Döküm yeri" icon="fa-solid fa-dumpster" description="Döküm ücretleri işletmecinin cari hesabına yazılır">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.select name="party_id" label="İşletmeci (cari)" :options="$parties" placeholder="Cari kart seçin" searchable required />
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde gösterilsin" checked />
        </x-form.group>

        <x-form.group legend="Döküm ücretleri" icon="fa-solid fa-layer-group" description="Sefer başına araç sınıfı döküm ücreti">
            <x-form.class-prices :classes="$classes">
                <p class="panel-hint">Taşıma fişindeki döküm ücreti aracın sınıfına göre buradan alınır. Boş bırakılan sınıf için ücret 0 olur.</p>
            </x-form.class-prices>
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.dump-sites.index')" />
    </x-form.grid>
</form>
