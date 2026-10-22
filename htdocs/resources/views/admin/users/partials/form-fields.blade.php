@php
    $roleOptions = collect($roles)->mapWithKeys(fn ($role) => [$role->name => $role->name])->all();
    $apiRoleOptions = collect($apiRoles ?? [])->mapWithKeys(fn ($role) => [$role->name => $role->name])->all();
    $selectedRoles = $selectedRoles ?? old('roles', isset($user) ? $user->roles->where('guard_name', 'web')->pluck('name')->all() : []);
    $selectedApiRoles = $selectedApiRoles ?? old('api_roles', isset($user) ? $user->roles->where('guard_name', 'api')->pluck('name')->all() : []);
@endphp

<x-form.group legend="Hesap" icon="fa-solid fa-id-card" description="Giriş bilgileri">
    <x-form.input name="name" label="Ad soyad" maxlength="255" required autofocus />
    <x-form.input name="username" label="Kullanıcı adı" maxlength="100" required />
    <x-form.input name="email" label="E-posta" type="email" maxlength="255" required />
    <x-form.checkbox name="active" label="Durum" text="Giriş yapabilsin" :checked="$user->active ?? true" />
</x-form.group>

<x-form.group legend="Şifre" icon="fa-solid fa-lock" description="{{ isset($user) ? 'Boş bırakılırsa değişmez' : 'İlk giriş şifresi' }}">
    <x-form.input name="password" label="Şifre" type="password" :required="! isset($user)" autocomplete="new-password" />
    <x-form.input name="password_confirmation" label="Şifre tekrar" type="password" :required="! isset($user)" autocomplete="new-password" />
</x-form.group>

<x-form.group legend="Atama" icon="fa-solid fa-map-location-dot" description="Şantiye ve vardiya">
    <x-form.select searchable name="site_id" label="Şantiye" placeholder="Şantiye yok" :options="$sites" />
    <x-form.select searchable hide-search name="shift" label="Vardiya" placeholder="Vardiya yok" :options="$shifts" />
</x-form.group>

<x-form.group legend="Web roller" icon="fa-solid fa-user-shield" description="Admin panel yetkileri (web guard)" class="is-wide">
    <x-form.checkboxes name="roles" label="Roller" :options="$roleOptions" :value="$selectedRoles" />
</x-form.group>

<x-form.group legend="API roller" icon="fa-solid fa-mobile-screen" description="Mobil / JWT API yetkileri (api guard)" class="is-wide">
    <x-form.checkboxes name="api_roles" label="API roller" :options="$apiRoleOptions" :value="$selectedApiRoles" />
</x-form.group>
