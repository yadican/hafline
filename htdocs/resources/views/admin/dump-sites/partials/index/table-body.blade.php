<tbody>
    @forelse ($dumpSites as $dumpSite)
        <tr>
            <td>{{ $dumpSite->name }}</td>
            <td>{{ $dumpSite->party?->name ?? '—' }}</td>
            <td class="is-center">
                @if ($dumpSite->routes_count)
                    <a href="{{ route('admin.haul-routes.index', ['dump_site' => $dumpSite->id]) }}" title="Bu döküm yerinin güzergahlarını göster">{{ $dumpSite->routes_count }}</a>
                @else
                    —
                @endif
            </td>
            <td><x-ui.active-badge :active="$dumpSite->active" /></td>
            <x-table.actions
                :show="route('admin.dump-sites.show', $dumpSite)"
                :edit="auth()->user()->can('edit-dump-site') ? route('admin.dump-sites.edit', $dumpSite) : null"
                :delete="auth()->user()->can('delete-dump-site') ? route('admin.dump-sites.destroy', $dumpSite) : null"
                confirm="Döküm yeri silinsin mi?"
                :confirm-text="$dumpSite->name.' listeden kaldırılacak. Geçmiş taşıma fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="5" icon="fa-solid fa-dumpster" title="Henüz döküm yeri yok." text="Taşıma fişlerinde seçmek için döküm yerlerini ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.dump-sites.create')">Yeni döküm yeri</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="5" :reset="route('admin.dump-sites.index')" />
        @endif
    @endforelse
</tbody>
