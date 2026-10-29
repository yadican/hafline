<tbody>
    @forelse ($haulRoutes as $haulRoute)
        <tr>
            <td>{{ $haulRoute->site?->name }}</td>
            <td>{{ $haulRoute->dumpSite?->name }}</td>
            <td class="is-center">{{ $haulRoute->vehicle_prices_count ?: '—' }}</td>
            <td class="panel-tutar"><x-ui.money :value="$haulRoute->price" /></td>
            <td class="panel-tutar"><x-ui.money :value="$haulRoute->bonus" /></td>
            <td><x-ui.active-badge :active="$haulRoute->active" /></td>
            <x-table.actions
                :show="route('admin.haul-routes.show', $haulRoute)"
                :edit="auth()->user()->can('edit-haul-route') ? route('admin.haul-routes.edit', $haulRoute) : null"
                :delete="auth()->user()->can('delete-haul-route') ? route('admin.haul-routes.destroy', $haulRoute) : null"
                confirm="Güzergah silinsin mi?"
                :confirm-text="$haulRoute->site?->name.' → '.$haulRoute->dumpSite?->name.' listeden kaldırılacak. Geçmiş taşıma fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="7" icon="fa-solid fa-route" title="Henüz güzergah yok." text="Taşıma fişi girebilmek için şantiye ile döküm yeri arasında güzergah açın.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.haul-routes.create')">Yeni güzergah</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="7" :reset="route('admin.haul-routes.index')" />
        @endif
    @endforelse
</tbody>
