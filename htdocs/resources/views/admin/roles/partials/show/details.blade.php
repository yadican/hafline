@php
    use App\Support\PermissionGroups;
@endphp

<div class="panel-form-grid is-detail">
    <x-form.group legend="Rol" icon="fa-solid fa-user-shield" description="Rol adı ve guard">
        <x-form.field label="Ad">
            <div>{{ $role->name }}</div>
        </x-form.field>
        <x-form.field label="Guard">
            <div><span class="panel-badge">{{ $role->guard_name }}</span></div>
        </x-form.field>
        <x-form.field label="İzin sayısı">
            <div>{{ $permissionGroups->flatten()->count() ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="İzinler" icon="fa-solid fa-key" description="Bu role verilmiş yetkiler" class="is-wide">
        @if ($permissionGroups->isNotEmpty())
            @php
                $mainGroups = $permissionGroups->reject(fn ($_, $key) => PermissionGroups::isFullWidthGroup($key));
                $fullGroups = $permissionGroups->filter(fn ($_, $key) => PermissionGroups::isFullWidthGroup($key));
            @endphp
            @if ($mainGroups->isNotEmpty())
                <div class="panel-detail-columns">
                    @foreach ($mainGroups as $groupKey => $permissions)
                        <x-form.field :label="PermissionGroups::labelFor($groupKey)">
                            <div class="panel-chips">
                                @foreach ($permissions as $permission)
                                    <span class="panel-chip" title="{{ $permission->name }}">{{ PermissionGroups::permissionLabel($permission->name) }}</span>
                                @endforeach
                            </div>
                        </x-form.field>
                    @endforeach
                </div>
            @endif
            @foreach ($fullGroups as $groupKey => $permissions)
                <x-form.field :label="PermissionGroups::labelFor($groupKey)" class="is-full">
                    @if ($groupKey === 'cari-entries')
                        <div class="permission-group-body is-rows is-bare">
                            @foreach (PermissionGroups::byResource($permissions) as $resource => $actions)
                                <div class="permission-group-row">
                                    <strong class="permission-group-row-label">{{ PermissionGroups::resourceLabel($resource) }}</strong>
                                    <div class="panel-chips">
                                        @foreach (['view', 'create', 'edit', 'delete'] as $action)
                                            @isset($actions[$action])
                                                <span class="panel-chip" title="{{ $actions[$action]->name }}">{{ PermissionGroups::actionLabel($action) }}</span>
                                            @endisset
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="panel-chips">
                            @foreach ($permissions as $permission)
                                <span class="panel-chip" title="{{ $permission->name }}">{{ PermissionGroups::permissionLabel($permission->name) }}</span>
                            @endforeach
                        </div>
                    @endif
                </x-form.field>
            @endforeach
        @else
            <p class="panel-hint">Bu role henüz izin verilmemiş.</p>
        @endif
    </x-form.group>
</div>
