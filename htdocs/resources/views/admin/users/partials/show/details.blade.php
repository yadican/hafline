@php
    $webRoles = $user->roles->where('guard_name', 'web')->sortBy('name');
    $apiRoles = $user->roles->where('guard_name', \App\Support\ApiPermissions::GUARD)->sortBy('name');
@endphp

<div class="panel-form-grid">
    <x-form.group legend="Hesap" icon="fa-solid fa-id-card" description="Giriş bilgileri">
        <x-form.field label="Ad soyad">
            <div>{{ $user->name }}</div>
        </x-form.field>
        <x-form.field label="Kullanıcı adı">
            <div>{{ $user->username }}</div>
        </x-form.field>
        <x-form.field label="E-posta">
            <div>{{ $user->email }}</div>
        </x-form.field>
        <x-form.field label="Durum">
            <div><x-ui.active-badge :active="$user->active" /></div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Atama" icon="fa-solid fa-map-location-dot" description="Şantiye ve vardiya">
        <x-form.field label="Şantiye">
            <div>{{ $user->site?->name ?: '—' }}</div>
        </x-form.field>
        <x-form.field label="Vardiya">
            <div>{{ $user->shift?->label() ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="Web roller" icon="fa-solid fa-user-shield" description="Admin panel yetkileri (web guard)">
        <x-form.field label="Roller">
            <div>
                @forelse ($webRoles as $role)
                    <span class="panel-badge">{{ $role->name }}</span>
                @empty
                    —
                @endforelse
            </div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="API roller" icon="fa-solid fa-mobile-screen" description="Mobil / JWT API yetkileri (api guard)">
        <x-form.field label="API roller">
            <div>
                @forelse ($apiRoles as $role)
                    <span class="panel-badge">{{ $role->name }}</span>
                @empty
                    —
                @endforelse
            </div>
        </x-form.field>
    </x-form.group>
</div>
