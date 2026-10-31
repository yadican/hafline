@php
    use App\Support\PermissionGroups;

    $selectedPermissions = collect($selectedPermissions ?? [])->map(fn ($name) => (string) $name)->values()->all();
    $mainGroups = $permissionGroups->reject(fn ($_, $key) => PermissionGroups::isFullWidthGroup($key));
    $fullGroups = $permissionGroups->filter(fn ($_, $key) => PermissionGroups::isFullWidthGroup($key));
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

    @if ($mainGroups->isNotEmpty())
        <div class="permission-groups-grid">
            @foreach ($mainGroups as $groupKey => $permissions)
                @include('admin.roles.partials.permission-group-card', [
                    'groupKey' => $groupKey,
                    'permissions' => $permissions,
                ])
            @endforeach
        </div>
    @endif

    @if ($fullGroups->isNotEmpty())
        <div class="permission-groups-full">
            @foreach ($fullGroups as $groupKey => $permissions)
                @include('admin.roles.partials.permission-group-card', [
                    'groupKey' => $groupKey,
                    'permissions' => $permissions,
                ])
            @endforeach
        </div>
    @endif
</div>
