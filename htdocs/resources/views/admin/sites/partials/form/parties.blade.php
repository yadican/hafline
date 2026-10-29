<x-form.group legend="Firmalar" icon="fa-solid fa-handshake" description="Bu şantiyede çalışılan cari kartlar" class="is-wide">
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach (\App\Enums\PartyRole::forSites() as $role)
            <x-form.select
                :name="'parties['.$role->value.']'"
                :label="$role->pluralLabel()"
                :options="$parties[$role->value] ?? []"
                :value="old('parties.'.$role->value, $partyValues[$role->value] ?? [])"
                placeholder="Cari kart seçin"
                multiple
            />
        @endforeach
    </div>
    <p class="panel-hint">Seçilen cari karta ilgili rol otomatik eklenir. Bir firma birden fazla şantiyede, bir şantiyede de birden fazla firma olabilir.</p>
</x-form.group>
