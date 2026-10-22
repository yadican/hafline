<form method="POST" action="{{ route('admin.users.store') }}">
    @csrf
    <x-form.grid>
        @include('admin.users.partials.form-fields')
        <x-form.actions label="Kaydet" :cancel="route('admin.users.index')" />
    </x-form.grid>
</form>
