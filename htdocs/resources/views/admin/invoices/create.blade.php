@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni fatura"
        icon="fa-solid fa-plus"
        description="Kaydedilince alış faturası tedarikçiye alacak, satış faturası müşteriye borç yazar. Taslak cariye yazılmaz; Onayla ile yazılır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Faturalar' => route('admin.invoices.index'), 'Yeni fatura' => null]"
        :back="route('admin.invoices.index')"
    >
        <form method="POST" action="{{ route('admin.invoices.store') }}" enctype="multipart/form-data">
            @csrf
            <x-form.grid :model="['direction' => 'purchase', 'issued_on' => today()->toDateString()]">
                @include('admin.invoices.partials.fields', ['invoice' => null])
                <div class="panel-form-actions">
                    <x-ui.button type="submit" name="is_draft" value="0" variant="create" icon="fa-solid fa-floppy-disk">Faturayı kaydet</x-ui.button>
                    <x-ui.button type="submit" name="is_draft" value="1" variant="secondary" icon="fa-solid fa-file">Taslak kaydet</x-ui.button>
                    <x-ui.button variant="secondary" icon="fa-solid fa-arrow-left" :href="route('admin.invoices.index')">Listeye dön</x-ui.button>
                </div>
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
