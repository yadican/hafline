<div class="panel-liste-bar">
    <x-search.form :action="route('admin.financial-instruments.index')">
        <x-search.text name="q" label="Ara" :value="$filters['q'] ?? null" placeholder="No, banka, keşideci, açıklama" maxlength="100" class="md:col-span-3" />
        <x-search.select searchable hide-search name="payment_method" label="Tür" placeholder="Çek ve senet" :options="$methods" :value="$filters['payment_method'] ?? null" class="md:col-span-2" />
        <x-search.select searchable hide-search name="direction" label="Yön" placeholder="Alınan ve verilen" :options="$directions" :value="$filters['direction'] ?? null" class="md:col-span-2" />
        <x-search.select searchable hide-search name="status" label="Durum" placeholder="Tüm durumlar" :options="$statuses" :value="$filters['status'] ?? null" class="md:col-span-2" />
        <x-search.select searchable name="party" label="Cari" placeholder="Tüm cariler" :options="$parties" :value="$filters['party'] ?? null" class="md:col-span-3" />
        <x-form.input name="due_from" label="" type="date" :value="$filters['due_from'] ?? null" aria-label="Vade başlangıç" class="md:col-span-3" />
        <x-form.input name="due_to" label="" type="date" :value="$filters['due_to'] ?? null" aria-label="Vade bitiş" class="md:col-span-3" />
        @if (($filters['overdue'] ?? null) === '1')
            <input type="hidden" name="overdue" value="1">
        @endif
        <div class="col-span-full mt-1 flex flex-wrap justify-stretch gap-2 border-t border-[var(--ui-border-soft)] pt-3 sm:justify-end">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if ($filters !== [])
                <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.financial-instruments.index')" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
            @can('create-financial-instrument')
                <x-ui.button variant="create" icon="fa-solid fa-money-check" :href="route('admin.financial-instruments.create')" class="w-full justify-center sm:w-auto">Yeni çek / senet</x-ui.button>
            @endcan
        </div>
    </x-search.form>
</div>
