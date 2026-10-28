<form method="POST" action="{{ route('admin.banks.store') }}">
    @csrf
    <x-form.grid :model="['type' => \App\Enums\AccountType::Bank->value, 'opening_balance' => '0']">
        @include('admin.banks.partials.form-fields')

        <x-form.actions label="Kaydet" :cancel="route('admin.banks.index')" />
    </x-form.grid>
</form>
