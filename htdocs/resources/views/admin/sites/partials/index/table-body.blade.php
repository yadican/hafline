<tbody>
    @forelse ($sites as $site)
        <tr>
            <td>{{ $site->name }}</td>
            <td class="is-center">
                @if ($site->routes_count)
                    <a href="{{ route('admin.haul-routes.index', ['site' => $site->id]) }}" title="Bu şantiyenin güzergahlarını göster">{{ $site->routes_count }}</a>
                @else
                    —
                @endif
            </td>
            <td class="is-center">{{ $site->parties_count ?: '—' }}</td>
            <td><x-ui.active-badge :active="$site->active" /></td>
            <x-table.actions
                :show="route('admin.sites.show', $site)"
                :edit="auth()->user()->can('edit-site') ? route('admin.sites.edit', $site) : null"
                :delete="auth()->user()->can('delete-site') ? route('admin.sites.destroy', $site) : null"
                confirm="Şantiye silinsin mi?"
                :confirm-text="$site->name.' listeden kaldırılacak. Geçmiş taşıma fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="5" icon="fa-solid fa-helmet-safety" title="Henüz şantiye yok." text="Taşıma fişlerinde seçmek için şantiyeleri ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.sites.create')">Yeni şantiye</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="5" :reset="route('admin.sites.index')" />
        @endif
    @endforelse
</tbody>
