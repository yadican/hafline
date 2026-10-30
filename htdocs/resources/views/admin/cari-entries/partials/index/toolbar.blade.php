@php
    $partyLocked = (bool) \App\Enums\CariEntryKind::tryFrom($filters['kind'] ?? '')?->namesAccount();
    $group = $group ?? \App\Enums\CariEntryGroup::tryFrom($filters['group'] ?? '');
    $createQuery = array_filter([
        'group' => $group?->value,
        'kind' => ($filters['kind'] ?? null) === 'account_transfer' ? 'account_transfer' : null,
    ]);
    $canCreateGroup = $group !== null
        ? auth()->user()?->can('create-'.$group->permissionResource())
        : auth()->user()?->canAny(array_map(
            fn (\App\Enums\CariEntryGroup $entryGroup) => 'create-'.$entryGroup->permissionResource(),
            \App\Enums\CariEntryGroup::cases(),
        ));
    $hasSearchFilters = collect($filters)->except('group')->isNotEmpty();
@endphp
<div class="panel-liste-bar">
    <x-search.form
        :action="route('admin.cari-entries.index')"
        data-cari-entry-search
    >
        @if ($group !== null)
            <input type="hidden" name="group" value="{{ $group->value }}">
        @endif
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
            @if ($hasSearchFilters)
                <x-ui.button
                    icon="fa-solid fa-xmark"
                    :href="route('admin.cari-entries.index', array_filter(['group' => $group?->value]))"
                    class="w-full justify-center sm:w-auto"
                >Temizle</x-ui.button>
            @endif
            @if ($canCreateGroup)
                @if (($filters['kind'] ?? null) === 'account_transfer' || $group === \App\Enums\CariEntryGroup::AccountTransfer)
                    <x-ui.button variant="create" icon="fa-solid fa-right-left" :href="route('admin.cari-entries.create', $createQuery ?: ['kind' => 'account_transfer'])" class="w-full justify-center sm:w-auto">Yeni virman</x-ui.button>
                @else
                    <x-ui.button variant="create" icon="fa-solid fa-money-bill-transfer" :href="route('admin.cari-entries.create', $createQuery)" class="w-full justify-center sm:w-auto">Yeni Cari</x-ui.button>
                @endif
            @endif
        </div>
    </x-search.form>
</div>
