@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="haul-list"
        heading="Seferler"
        icon="fa-solid fa-truck-moving"
        description="Nakliye seferleri. Her sefer müşteriyi borçlandırır; nakliyeciye ve referansa alacak yazar."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Seferler' => null]"
    >
        <div x-data="{ searchOpen: false }">
            <div class="panel-liste-bar haul-toolbar">
                <div class="haul-toolbar-row">
                    <div class="haul-toolbar-start">
                        <x-ui.button
                            variant="search"
                            icon="fa-solid fa-filter"
                            class="haul-search-toggle"
                            x-bind:class="{ 'is-open': searchOpen }"
                            x-on:click="searchOpen = ! searchOpen"
                            x-bind:aria-expanded="searchOpen.toString()"
                            aria-controls="shipment-search"
                            aria-expanded="false"
                        >
                            Arama
                            @if ($filters !== [])
                                <span class="haul-search-count">{{ count($filters) }}</span>
                            @endif
                            <i class="fa-solid fa-chevron-down haul-search-chevron" aria-hidden="true"></i>
                        </x-ui.button>
                        @if ($filters !== [])
                            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.shipments.index')">Filtreleri temizle</x-ui.button>
                        @endif
                    </div>
                    <div class="haul-toolbar-end">
                        <span class="haul-toolbar-count">{{ number_format($shipments->total(), 0, ',', '.') }} kayıt</span>
                        @can('create-shipment')
                            <x-ui.button variant="create" icon="fa-solid fa-truck-moving" :href="route('admin.shipments.create')">Yeni sefer</x-ui.button>
                        @endcan
                    </div>
                </div>
                @if ($filters === [])
                    <p class="haul-toolbar-note">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        Son {{ \App\Repositories\ShipmentRepository::RECENT_DAYS }} günün seferleri listeleniyor — daha eskiler için Arama’yı açın.
                    </p>
                @endif
            </div>

            <div id="shipment-search" class="panel-liste-bar haul-search-bar" x-show="searchOpen" x-cloak>
                <x-search.form :action="route('admin.shipments.index')">
                    <x-search.text name="f[plate]" label="Plaka" :value="$filters['plate'] ?? null" class="haul-search-plate md:col-span-4" maxlength="20" />
                    <x-search.text name="f[waybill]" label="İrsaliye no" :value="$filters['waybill'] ?? null" maxlength="30" class="md:col-span-4" />
                    <x-search.text name="f[loading]" label="Ocak (yükleme yeri)" :value="$filters['loading'] ?? null" maxlength="120" class="md:col-span-4" />
                    <x-search.select searchable name="f[carrier]" label="Nakliyeci" placeholder="Tüm nakliyeciler" :options="$options['carriers']" :value="$filters['carrier'] ?? null" class="md:col-span-4" />
                    <x-search.select searchable name="f[customer]" label="Müşteri" placeholder="Tüm müşteriler" :options="$options['customers']" :value="$filters['customer'] ?? null" class="md:col-span-4" />
                    <x-form.input name="f[from]" label="" type="date" :value="$filters['from'] ?? null" aria-label="Tarih (başlangıç)" class="md:col-span-2" />
                    <x-form.input name="f[to]" label="" type="date" :value="$filters['to'] ?? null" aria-label="Tarih (bitiş)" class="md:col-span-2" />
                    <div class="col-span-full mt-1 flex flex-wrap justify-stretch gap-2 border-t border-[var(--ui-border-soft)] pt-3 sm:justify-end">
                        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
                    </div>
                </x-search.form>
            </div>
        </div>

        <x-table.list class="haul-table">
            <thead>
                <tr>
                    <x-table.th sort="shipped_on" first="desc">Tarih</x-table.th>
                    <x-table.th sort="plate">Plaka</x-table.th>
                    <th>Nakliyeci</th>
                    <th>Ocak → sevkiyat adresi</th>
                    <x-table.th sort="customer">Müşteri</x-table.th>
                    <x-table.th sort="net_kg" first="desc" class="is-end">Net kg</x-table.th>
                    <th class="is-end">Nakliyeci net</th>
                    <x-table.th sort="customer_amount" first="desc">Müşteri net</x-table.th>
                    <th class="is-end">Kâr</th>
                    <th class="is-center is-actions">İşlem</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shipments as $shipment)
                    <tr>
                        <td>{{ $shipment->shipped_on->format('d.m.Y') }}</td>
                        <td class="panel-plaka-cell">{{ $shipment->vehicle?->plate }}</td>
                        <td>{{ $shipment->party?->name }}</td>
                        <td>{{ $shipment->loading_place }} → {{ $shipment->deliveryAddress?->name ?? '—' }}</td>
                        <td>{{ $shipment->customer?->name }}</td>
                        <td class="is-end">{{ \App\Models\Shipment::formatKg($shipment->net_kg) }}</td>
                        <td class="panel-tutar">{{ number_format((float) $shipment->carrier_net_amount, 2, ',', '.') }} TL</td>
                        <td class="panel-tutar">{{ number_format((float) $shipment->customer_amount, 2, ',', '.') }} TL</td>
                        <td class="panel-tutar">{{ number_format($shipment->profit(), 2, ',', '.') }} TL</td>
                        <x-table.actions
                            :show="route('admin.shipments.show', $shipment)"
                            :edit="auth()->user()->can('edit-shipment') ? route('admin.shipments.edit', $shipment) : null"
                            :delete="auth()->user()->can('delete-shipment') ? route('admin.shipments.destroy', $shipment) : null"
                            confirm="Sefer silinsin mi?"
                            confirm-text="Müşteri, nakliyeci ve referans carilerine yazılan satırlar ters kayıtla kapatılır."
                        />
                    </tr>
                @empty
                    @if ($filters === [])
                        <x-table.empty colspan="10" icon="fa-solid fa-truck-moving" :title="'Son '.\App\Repositories\ShipmentRepository::RECENT_DAYS.' günde sefer yok.'" text="Daha eski seferler için Arama panelinden tarih aralığı seçin." />
                    @else
                        <x-table.no-results colspan="10" :reset="route('admin.shipments.index')" />
                    @endif
                @endforelse
                @if ($shipments->isNotEmpty())
                    <tr>
                        <td colspan="5">Toplam {{ $totals['count'] }} sefer</td>
                        <td class="is-end">{{ \App\Models\Shipment::formatKg($totals['net_kg']) }}</td>
                        <td class="panel-tutar">{{ number_format($totals['carrier'], 2, ',', '.') }} TL</td>
                        <td class="panel-tutar">{{ number_format($totals['customer'], 2, ',', '.') }} TL</td>
                        <td class="panel-tutar">{{ number_format($totals['profit'], 2, ',', '.') }} TL</td>
                        <td></td>
                    </tr>
                @endif
            </tbody>
        </x-table.list>
        <x-table.pager :items="$shipments" />
    </x-panel.page>
@endsection
