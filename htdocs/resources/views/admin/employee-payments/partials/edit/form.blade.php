<form method="POST" action="{{ route('admin.employee-payments.update', $payment) }}" data-employee-balance-form data-balance-url="{{ route('admin.employees.balance', ['employee' => '__ID__']) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$payment">
        @include('admin.employee-payments.partials.form.fields')
        <x-form.actions label="Güncelle" :cancel="route('admin.employee-payments.index')" />
    </x-form.grid>
</form>
