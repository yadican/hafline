<form method="POST" action="{{ route('admin.employees.store') }}">
    @csrf
    <x-form.grid>
        @include('admin.employees.partials.form.fields')
        <x-form.actions label="Kaydet" :cancel="route('admin.employees.index')" />
    </x-form.grid>
</form>
