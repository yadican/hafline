@php
    use App\Support\PermissionGroups;

    $selectedPermissions = collect($selectedPermissions ?? [])->map(fn ($name) => (string) $name)->values()->all();
@endphp

<div
    x-data="{
        selected: @js($selectedPermissions),
        groupKeys: @js($permissionGroups->map(fn ($perms) => $perms->pluck('name')->values()->all())->all()),
        toggleGroup(key) {
            const names = this.groupKeys[key] || [];
            const allSelected = names.every((name) => this.selected.includes(name));
            if (allSelected) {
                this.selected = this.selected.filter((name) => !names.includes(name));
            } else {
                this.selected = Array.from(new Set([...this.selected, ...names]));
            }
        },
        isGroupChecked(key) {
            const names = this.groupKeys[key] || [];
            return names.length > 0 && names.every((name) => this.selected.includes(name));
        },
        isGroupIndeterminate(key) {
            const names = this.groupKeys[key] || [];
            const count = names.filter((name) => this.selected.includes(name)).length;
            return count > 0 && count < names.length;
        }
    }"
    class="permission-groups"
>
    <div class="permission-groups-head">
        <div>
            <strong>Yetkiler</strong>
            <p class="text-muted">İzinleri gruplara göre seçin veya kaldırın.</p>
        </div>
        <p class="text-muted" x-text="selected.length + ' seçili'"></p>
    </div>

    @error('permissions')
        <p class="panel-field-error">{{ $message }}</p>
    @enderror

    <div class="permission-groups-grid">
        @foreach ($permissionGroups as $groupKey => $permissions)
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
                <div x-show="open" x-cloak class="permission-group-body">
                    @foreach ($permissions as $permission)
                        <label class="panel-choice">
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $permission->name }}"
                                x-model="selected"
                            >
                            <span>{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
