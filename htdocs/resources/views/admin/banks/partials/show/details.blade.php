<div class="panel-form-grid is-detail">
    <x-form.group legend="Hesap" icon="fa-solid fa-wallet" description="Hesabın türü, adı ve kullanım durumu">
        <x-form.field label="Tür">
            <div><i class="{{ $bank->type->icon() }} panel-muted-icon" aria-hidden="true"></i> {{ $bank->type->label() }}</div>
        </x-form.field>
        <x-form.field label="Ad">
            <div>{{ $bank->name }}</div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$bank->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Bakiye" icon="fa-solid fa-scale-balanced" description="Açılış bakiyesi ve bu hesaptan geçen hareketlerle bugünkü bakiye">
        <x-form.field label="Güncel bakiye">
            <div><strong><x-ui.money :value="$balance" /></strong></div>
        </x-form.field>
        <x-form.field label="Açılış bakiyesi">
            <div><x-ui.money :value="$bank->opening_balance" /></div>
        </x-form.field>
        <x-form.field label="Açılış tarihi">
            <div>{{ $bank->opened_on?->format('d.m.Y') ?? '—' }}</div>
        </x-form.field>
    </x-form.group>

    @if ($bank->type->hasBankDetails())
        <x-form.group legend="Banka bilgileri" icon="fa-solid fa-building-columns" description="Şube, IBAN ve iletişim">
            <x-form.field label="Şube">
                <div>{{ $bank->branch ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="IBAN">
                <div>{{ $bank->iban ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="Hesap / terminal no">
                <div>{{ $bank->account_no ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="Temsilci">
                <div>{{ $bank->representative ?: '—' }}</div>
            </x-form.field>
            <x-form.field label="Telefon">
                <div>{{ $bank->phone ?: '—' }}</div>
            </x-form.field>
        </x-form.group>
    @endif
</div>
