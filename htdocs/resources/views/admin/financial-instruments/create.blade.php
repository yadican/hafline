@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni çek / senet"
        icon="fa-solid fa-money-check"
        description="Alınan ya da verilen çeki / senedi girin; carinin hareketi kendiliğinden yazılır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Çek / Senet' => route('admin.financial-instruments.index'), 'Yeni' => null]"
        :back="route('admin.financial-instruments.index')"
    >
        @php($counterparty = old('counterparty'))
        <form method="POST" action="{{ route('admin.financial-instruments.store') }}" enctype="multipart/form-data" x-data="{ method: @js(old('payment_method', $form['payment_method'])) }">
            @csrf
            <x-form.grid :model="$form">
                <x-form.group legend="Çek / senet" icon="fa-solid fa-money-check" description="Yön, tür, tutar ve vade">
                    <x-form.radios name="direction" label="Yön" :options="$directions" inline />
                    <x-form.field label="Tür" name="payment_method">
                        <div class="panel-choices is-inline" role="radiogroup">
                            @foreach ($methods as $value => $label)
                                <label class="panel-choice">
                                    <input type="radio" name="payment_method" value="{{ $value }}" x-model="method">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </x-form.field>
                    <x-form.money name="amount" label="Tutar" required />
                    <x-form.input name="occurred_on" label="İşlem tarihi" type="date" required />
                    <x-form.input name="due_on" label="Vade" type="date" required />
                    <x-form.input name="description" label="Açıklama" maxlength="255" />
                </x-form.group>

                <x-form.group legend="Cari ve kâğıt" icon="fa-solid fa-address-card" description="Çeki veren ya da alan cari, kâğıt üzerindeki bilgiler">
                    <x-form.field label="Cari" for="counterparty" name="counterparty">
                        <select data-searchable-select id="counterparty" name="counterparty" class="slct" data-placeholder="Cari seçin">
                            <option value="">Cari seçin</option>
                            @foreach ($groups as $role => $group)
                                <optgroup label="{{ $group['label'] }}">
                                    @foreach ($group['parties'] as $id => $name)
                                        <option value="{{ $role }}|{{ $id }}" @selected($counterparty === $role.'|'.$id)>{{ $name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </x-form.field>
                    <x-form.input name="cheque_no" label="Çek / senet no" maxlength="20" />
                    <div x-show="method === 'cheque'">
                        <x-form.input name="instrument_bank" label="Çekin bankası" maxlength="100" />
                    </div>
                    <x-form.input name="instrument_drawer" label="Keşideci" maxlength="120" />
                    <x-form.field label="Resim" for="instrument-photos" name="photos">
                        <input id="instrument-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="panel-file">
                    </x-form.field>
                    <p class="panel-hint">Alınan: cari alacaklanır (borcunu çekle ödedi). Verilen: cari borçlandırılır (ona çekle ödedik). Kasa ve banka, çek tahsil ya da ödenince değişir.</p>
                    <p class="panel-hint">Cari, seçildiği gruptaki türle yazılır: Müşteri → Alıcı Cari, Tedarikçi → Satıcı Cari, Akaryakıt → Satıcı Petrol, Piyasa → Piyasa, Araç sahibi → Araç Sahibi Ödemesi. Plakaya, döküm yerine ya da şantiyeye çekle ödeme Cari hareketleri ekranından girilir.</p>
                </x-form.group>

                <x-form.actions label="Kaydet" :cancel="route('admin.financial-instruments.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
