<form method="POST" action="{{ route('admin.banks.update', $bank) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$bank">
        @include('admin.banks.partials.form-fields')

        <x-form.actions label="Güncelle" :cancel="route('admin.banks.index')" />
    </x-form.grid>
</form>
