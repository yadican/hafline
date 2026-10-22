<div class="panel-liste-bar">
    <x-search.form :action="route($indexRoute)">
        <x-search.text
            name="q"
            label="Ara"
            :value="$filters['q'] ?? null"
            placeholder="Route, URL…"
            maxlength="200"
            class="md:col-span-4"
        />
        <x-search.select
            searchable
            hide-search
            name="action"
            label="İşlem"
            placeholder="Tüm işlemler"
            :options="\App\Models\AuditLog::ACTION_LABELS"
            :value="$filters['action'] ?? null"
            class="md:col-span-2"
        />
        <x-search.select
            searchable
            hide-search
            name="method"
            label="Method"
            placeholder="Tüm methodlar"
            :options="['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE', 'HEAD' => 'HEAD']"
            :value="$filters['method'] ?? null"
            class="md:col-span-2"
        />
        <x-search.select
            searchable
            name="user_id"
            label="Kullanıcı"
            placeholder="Tüm kullanıcılar"
            :options="$users->mapWithKeys(fn ($user) => [$user->id => $user->name.' ('.$user->username.')'])->all()"
            :value="$filters['user_id'] ?? null"
            class="md:col-span-4"
        />

        <x-search.text
            name="ip"
            label="IP"
            :value="$filters['ip'] ?? null"
            placeholder="IP adresi"
            maxlength="45"
            class="md:col-span-4"
        />
        <x-form.input
            name="from"
            label=""
            type="date"
            :value="$filters['from'] ?? null"
            placeholder="Başlangıç"
            aria-label="Başlangıç"
            class="md:col-span-4"
        />
        <x-form.input
            name="to"
            label=""
            type="date"
            :value="$filters['to'] ?? null"
            placeholder="Bitiş"
            aria-label="Bitiş"
            class="md:col-span-4"
        />

        <div class="col-span-full mt-1 flex flex-wrap justify-stretch gap-2 border-t border-[var(--ui-border-soft)] pt-3 sm:justify-end">
            <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
            @if (! empty($filters))
                <x-ui.button icon="fa-solid fa-xmark" :href="route($indexRoute)" class="w-full justify-center sm:w-auto">Temizle</x-ui.button>
            @endif
        </div>
    </x-search.form>
</div>
