@php
    $isPerson = $party->type === \App\Enums\PartyType::Person;
    $location = collect([$party->neighborhood?->name, $party->district?->name, $party->city?->name])->filter()->join(' / ');
@endphp

<div class="panel-form-grid is-detail">
    @if ($isPerson)
        <x-form.group legend="Kişi" icon="fa-solid fa-user" description="Ad, soyad ve kimlik bilgisi">
            <x-form.field label="Ad soyad">
                <div>{{ $party->name }}</div>
            </x-form.field>
            <x-form.field label="T.C. kimlik no">
                <div>{{ $party->person?->national_id ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="Durum">
                <div><x-ui.active-badge :active="$party->active" /></div>
            </x-form.field>
            <x-form.field label="Not">
                <div>{{ $party->notes ?: '—' }}</div>
            </x-form.field>
        </x-form.group>
    @else
        <x-form.group legend="Şirket" icon="fa-solid fa-building" description="Unvan ve vergi bilgisi">
            <x-form.field label="Unvan">
                <div>{{ $party->company?->title ?: $party->name }}</div>
            </x-form.field>
            <x-form.field label="Vergi dairesi">
                <div>{{ $party->company?->tax_office ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="Vergi no">
                <div>{{ $party->company?->tax_number ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="Durum">
                <div><x-ui.active-badge :active="$party->active" /></div>
            </x-form.field>
            <x-form.field label="Not">
                <div>{{ $party->notes ?: '—' }}</div>
            </x-form.field>
        </x-form.group>
    @endif

    <x-form.group legend="İletişim" icon="fa-solid fa-phone" description="Telefon, e-posta ve adres">
        <x-form.field label="Cep telefonu">
            <div>{{ $party->mobile ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Telefon">
            <div>{{ $party->phone ?: '—' }}</div>
        </x-form.field>
        @unless ($isPerson)
            <x-form.field label="Faks">
                <div>{{ $party->company?->fax ?: '—' }}</div>
            </x-form.field>
        @endunless
        <x-form.field label="E-posta">
            <div>{{ $party->email ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Semt / ilçe / il">
            <div>{{ $location ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Adres">
            <div>{{ $party->address ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Roller" icon="fa-solid fa-tags" description="Bu kartın işletmedeki rolleri" class="is-wide">
        <div class="panel-detail-columns">
            <x-form.field label="Roller">
                <div>
                    @if ($party->roles)
                        <span class="panel-chips">
                            @foreach ($party->roles as $role)
                                <span class="panel-chip">{{ $role->label() }}</span>
                            @endforeach
                        </span>
                    @else
                        —
                    @endif
                </div>
            </x-form.field>
            <x-form.field label="Bakiye">
                <div><x-ui.balance :value="$totals['balance']" /></div>
            </x-form.field>
        </div>
    </x-form.group>
</div>
