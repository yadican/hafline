@php
    $litres = fn ($value): string => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
@endphp

<div class="panel-form-grid panel-form-grid-after" id="stok">
    <x-form.group legend="Pompa stoğu" icon="fa-solid fa-oil-well" description="Dolumlar girer, yakıt fişleri çıkar, sayım farkı düzeltir; maliyet dolumların ağırlıklı ortalamasıdır">
        <x-form.field label="Stok">
            <div><strong>{{ $litres($tank['stock']['quantity']) }} lt</strong></div>
        </x-form.field>
        <x-form.field label="Ortalama maliyet">
            <div><x-ui.money :value="$tank['stock']['average_cost']" :decimals="4" suffix="TL/lt" /></div>
        </x-form.field>
        <x-form.field label="Stok değeri">
            <div><x-ui.money :value="$tank['stock']['value']" /></div>
        </x-form.field>
    </x-form.group>
</div>

<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Yakıt dolumları" icon="fa-solid fa-truck-droplet" description="Tedarikçi carisine alış tutarı kadar alacak yazılır" class="is-wide">
        @if ($tank['fills']->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Tedarikçi</th>
                        <th>Belge no</th>
                        <th class="is-end">Litre</th>
                        <th class="is-end">Alış fiyatı</th>
                        <th class="is-end">Tutar</th>
                        <th class="is-end"><span class="sr-only">İşlem</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tank['fills'] as $fill)
                        <tr>
                            <td>{{ $fill->filled_on->format('d.m.Y') }}</td>
                            <td>{{ $fill->party?->name }}</td>
                            <td>{{ $fill->document_no ?: '—' }}</td>
                            <td class="is-end">{{ $litres($fill->quantity) }}</td>
                            <td class="is-end"><x-ui.money :value="$fill->unit_price" :decimals="4" /></td>
                            <td class="is-end"><x-ui.money :value="$fill->amount" /></td>
                            @can('delete-fuel-tank')
                                <x-table.actions
                                    :delete="route('admin.fuel-stations.fills.destroy', [$fuelStation, $fill])"
                                    confirm="Yakıt dolumu silinsin mi?"
                                    :confirm-text="$fill->party?->name.' carisine yazılan alacak geri alınır ve stok düşer.'"
                                />
                            @else
                                <td></td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Henüz yakıt dolumu girilmedi.</p>
        @endif

        @can('create-fuel-tank')
            <form method="POST" action="{{ route('admin.fuel-stations.fills.store', $fuelStation) }}" class="panel-inline-form">
                @csrf
                <x-form.input name="filled_on" label="Tarih" type="date" :value="today()" required />
                <x-form.select name="party_id" label="Tedarikçi" :options="$tank['suppliers']" placeholder="Akaryakıt firması seçin" searchable required />
                <x-form.input name="quantity" label="Litre" type="number" step="0.01" min="0.01" required />
                <x-form.money name="unit_price" label="Alış fiyatı" step="0.0001" suffix="TL/lt" required />
                <x-form.input name="document_no" label="Belge no" maxlength="40" />
                <x-ui.button type="submit" variant="create" icon="fa-solid fa-plus">Dolum ekle</x-ui.button>
            </form>
        @endcan
    </x-form.group>
</div>

<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Yakıt sayımları" icon="fa-solid fa-ruler-vertical" description="Sayılan litre ile o günkü kayıtlı stok arasındaki fark stoğu düzeltir; cariye yazılmaz" class="is-wide">
        @if ($tank['counts']->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th class="is-end">Sayılan</th>
                        <th class="is-end">Fark</th>
                        <th>Açıklama</th>
                        <th class="is-end"><span class="sr-only">İşlem</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tank['counts'] as $count)
                        <tr>
                            <td>{{ $count->counted_on->format('d.m.Y') }}</td>
                            <td class="is-end">{{ $litres($count->quantity) }}</td>
                            <td class="is-end">{{ (float) $count->difference > 0 ? '+' : '' }}{{ $litres($count->difference) }}</td>
                            <td>{{ $count->description ?: '—' }}</td>
                            @can('delete-fuel-tank')
                                <x-table.actions
                                    :delete="route('admin.fuel-stations.counts.destroy', [$fuelStation, $count])"
                                    confirm="Yakıt sayımı silinsin mi?"
                                    confirm-text="Sayım farkı stoktan geri alınır."
                                />
                            @else
                                <td></td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Henüz yakıt sayımı girilmedi.</p>
        @endif

        @can('create-fuel-tank')
            <form method="POST" action="{{ route('admin.fuel-stations.counts.store', $fuelStation) }}" class="panel-inline-form">
                @csrf
                <x-form.input name="counted_on" label="Tarih" type="date" :value="today()" required />
                <x-form.input name="quantity" label="Sayılan litre" type="number" step="0.01" min="0" required />
                <x-form.input name="description" label="Açıklama" maxlength="255" />
                <x-ui.button type="submit" variant="create" icon="fa-solid fa-plus">Sayım ekle</x-ui.button>
            </form>
        @endcan
    </x-form.group>
</div>
