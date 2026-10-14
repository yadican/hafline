<tbody>
    @forelse ($sites as $site)
        <tr>
            <td>{{ $site->name }}</td>
            <td class="is-center">{{ $site->routes_count ?: '—' }}</td>
            <td><x-ui.active-badge :active="$site->active" /></td>
            <x-table.actions
                :edit="route('admin.sites.edit', $site)"
                :delete="route('admin.sites.destroy', $site)"
                confirm="Şantiye silinsin mi?"
                :confirm-text="$site->name.' listeden kaldırılacak. Geçmiş taşıma fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="4" icon="fa-solid fa-helmet-safety" title="Henüz şantiye yok." text="Taşıma fişlerinde seçmek için şantiyeleri ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.sites.create')">Yeni şantiye</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="4" :reset="route('admin.sites.index')" />
        @endif
    @endforelse
</tbody>
