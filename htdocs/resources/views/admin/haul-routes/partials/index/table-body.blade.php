<tbody>
    @forelse ($haulRoutes as $haulRoute)
        <tr>
            <td>{{ $haulRoute->site?->name }}</td>
            <td>{{ $haulRoute->dumpSite?->name }}</td>
            <td><x-ui.class-prices :prices="$haulRoute->classPrices" /></td>
            <td class="is-center">{{ $haulRoute->vehicle_prices_count ?: '—' }}</td>
            <td class="panel-tutar"><x-ui.money :value="$haulRoute->price" /></td>
            <td class="panel-tutar"><x-ui.money :value="$haulRoute->bonus" /></td>
            <td><x-ui.active-badge :active="$haulRoute->active" /></td>
            <x-table.actions
                :edit="route('admin.haul-routes.edit', $haulRoute)"
                :delete="route('admin.haul-routes.destroy', $haulRoute)"
                confirm="Güzergah silinsin mi?"
                :confirm-text="$haulRoute->site?->name.' → '.$haulRoute->dumpSite?->name.' listeden kaldırılacak. Geçmiş taşıma fişleri etkilenmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="8" icon="fa-solid fa-route" title="Henüz güzergah yok." text="Taşıma fişi girebilmek için şantiye ile döküm yeri arasında güzergah açın.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.haul-routes.create')">Yeni güzergah</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="8" :reset="route('admin.haul-routes.index')" />
        @endif
    @endforelse
</tbody>
