@php($partyLocked = (bool) \App\Enums\CariEntryKind::tryFrom($filters['kind'] ?? '')?->namesAccount())
<div class="panel-liste-bar">
    <x-search.form
        :action="route('admin.cari-entries.index')"
        data-cari-entry-search
    >
        <x-search.select
            searchable
            hide-search
            name="kind"
            label="İşlem"
            placeholder="Tüm işlemler"
            :options="$kinds"
            :value="$filters['kind'] ?? null"
            class="md:col-span-2"
        />
        <x-search.select
            searchable
            name="party"
            label="Kime"
            placeholder="İşlem Yapan Cari Hesap"
            :options="$parties"
            :value="$partyLocked ? null : ($filters['party'] ?? null)"
            :disabled="$partyLocked"
            class="md:col-span-4"
        />
        <x-form.input
            name="from"
            label=""
            type="date"
            :value="$filters['from'] ?? null"
            aria-label="Başlangıç"
            class="md:col-span-3"
        />
        <x-form.input
            name="to"
            label=""
            type="date"
            :value="$filters['to'] ?? null"
            aria-label="Bitiş"
            class="md:col-span-3"
        />
        <div class="col-span-full mt-1 flex flex-wrap justify-stretch gap-2 border-t border-[var(--ui-border-soft)] pt-3 sm:justify-end">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if ($filters !== [])
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.cari-entries.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            @if (($filters['kind'] ?? null) === 'account_transfer')
                <x-ui.button variant="create" icon="fa-solid fa-right-left" :href="route('admin.cari-entries.create', ['kind' => 'account_transfer'])" class="w-full justify-center sm:w-auto">Yeni virman</x-ui.button>
            @else
                <x-ui.button variant="create" icon="fa-solid fa-money-bill-transfer" :href="route('admin.cari-entries.create')" class="w-full justify-center sm:w-auto">Yeni Cari</x-ui.button>
            @endif
        </div>
    </x-search.form>
</div>
