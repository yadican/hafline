<div class="panel-liste-bar">
    <div class="panel-liste-actions">
        @can('create-user')
            <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.users.create')">Yeni kullanıcı</x-ui.button>
        @endcan
    </div>
</div>
