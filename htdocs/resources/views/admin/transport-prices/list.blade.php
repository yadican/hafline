@php
    $canEdit = auth()->user()->can($kind === \App\Enums\TransportPriceKind::Sale ? 'edit-delivery-address' : 'edit-quarry');
@endphp

<div class="panel-form-grid panel-form-grid-after" id="fiyat-{{ $kind->value }}">
    <x-form.group :legend="$legend" icon="fa-solid fa-tags" :description="$description.'. Yeni fiyat başlangıç tarihinden itibaren geçerlidir; eski seferler kendi fiyatında kalır.'" class="is-wide">
        @if ($prices->isNotEmpty())
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>{{ $keyLabel }}</th>
                        <th>Geçerlilik başlangıcı</th>
                        <th class="is-end">Birim fiyat</th>
                        <th class="is-end"><span class="sr-only">İşlem</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($prices as $price)
                        <tr>
                            <td>{{ $keyOf($price) }}</td>
                            <td>{{ $price->valid_from->format('d.m.Y') }}</td>
                            <td class="is-end"><x-ui.money :value="$price->price" :decimals="4" /></td>
                            @if ($canEdit)
                                <x-table.actions
                                    :delete="route('admin.transport-prices.destroy', $price)"
                                    confirm="Fiyat silinsin mi?"
                                    confirm-text="Kayıtlı seferler etkilenmez; yeni seferlerde bu fiyat önerilmez."
                                />
                            @else
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="panel-hint">Henüz fiyat girilmedi.</p>
        @endif

        @if ($canEdit)
            <form method="POST" action="{{ route('admin.transport-prices.store') }}" class="panel-inline-form">
                @csrf
                <input type="hidden" name="kind" value="{{ $kind->value }}">
                @foreach ($hidden as $hiddenName => $hiddenValue)
                    <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
                @endforeach
                <x-form.select :name="$keyName" :label="$keyLabel" :options="$keyOptions" placeholder="Seçin" searchable required />
                <x-form.input name="valid_from" label="Geçerlilik başlangıcı" type="date" :value="today()" required />
                <x-form.money name="price" label="Birim fiyat" step="0.0001" suffix="TL" required />
                <x-ui.button type="submit" variant="create" icon="fa-solid fa-plus">Fiyat ekle</x-ui.button>
            </form>
        @endif
    </x-form.group>
</div>
