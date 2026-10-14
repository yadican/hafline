<tbody>
    @forelse ($dumpSites as $dumpSite)
        <tr>
            <td>{{ $dumpSite->name }}</td>
            <td class="is-center">{{ $dumpSite->routes_count ?: '—' }}</td>
            <td><x-ui.active-badge :active="$dumpSite->active" /></td>
            <x-table.actions
                :edit="route('admin.dump-sites.edit', $dumpSite)"
                :delete="route('admin.dump-sites.destroy', $dumpSite)"
                confirm="Döküm yeri silinsin mi?"
                :confirm-text="$dumpSite->name.' listeden kaldırılacak. Geçmiş taşıma fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="4" icon="fa-solid fa-dumpster" title="Henüz döküm yeri yok." text="Taşıma fişlerinde seçmek için döküm yerlerini ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.dump-sites.create')">Yeni döküm yeri</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="4" :reset="route('admin.dump-sites.index')" />
        @endif
    @endforelse
</tbody>
