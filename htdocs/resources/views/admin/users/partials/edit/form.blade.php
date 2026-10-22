<form method="POST" action="{{ route('admin.users.update', $user) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$user">
        @include('admin.users.partials.form-fields', [
            'selectedRoles' => $selectedRoles,
            'selectedApiRoles' => $selectedApiRoles,
        ])
        <x-form.actions label="Güncelle" :cancel="route('admin.users.index')" />
    </x-form.grid>
</form>
