@php
    use App\Support\PermissionGroups;
@endphp

<div class="permission-group" x-data="{ open: true }">
    <div class="permission-group-head">
        <label class="panel-choice">
            <input
                type="checkbox"
                :checked="isGroupChecked(@js($groupKey))"
                x-effect="$el.indeterminate = isGroupIndeterminate(@js($groupKey))"
                @click.prevent="toggleGroup(@js($groupKey))"
            >
            <span>{{ PermissionGroups::labelFor($groupKey) }}</span>
            <span class="panel-badge">{{ $permissions->count() }}</span>
        </label>
        <button type="button" class="btn-link" @click="open = !open" x-text="open ? 'Gizle' : 'Göster'"></button>
    </div>
    <div
        x-show="open"
        x-cloak
        @class([
            'permission-group-body',
            'is-rows' => $groupKey === 'cari-entries',
            'is-inline' => $groupKey === 'reports',
        ])
    >
        @if ($groupKey === 'cari-entries')
            @foreach (PermissionGroups::byResource($permissions) as $resource => $actions)
                <div class="permission-group-row">
                    <strong class="permission-group-row-label">{{ PermissionGroups::resourceLabel($resource) }}</strong>
                    <div class="permission-group-row-actions">
                        @foreach (['view', 'create', 'edit', 'delete'] as $action)
                            @isset($actions[$action])
                                <label class="panel-choice">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $actions[$action]->name }}"
                                        x-model="selected"
                                    >
                                    <span title="{{ $actions[$action]->name }}">{{ PermissionGroups::actionLabel($action) }}</span>
                                </label>
                            @endisset
                        @endforeach
                    </div>
                </div>
            @endforeach
        @else
            @foreach ($permissions as $permission)
                <label class="panel-choice">
                    <input
                        type="checkbox"
                        name="permissions[]"
                        value="{{ $permission->name }}"
                        x-model="selected"
                    >
                    <span title="{{ $permission->name }}">{{ PermissionGroups::permissionLabel($permission->name) }}</span>
                </label>
            @endforeach
        @endif
    </div>
</div>
