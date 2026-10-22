<div class="panel-liste-bar">
    <div class="panel-liste-actions">
        @can('create-role')
            <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.roles.create')">Yeni rol</x-ui.button>
        @endcan
    </div>
</div>
