<?php

namespace App\Http\Controllers\Admin\User;

use App\Enums\Shift;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends AdminController
{
    protected array $permissionMap = [
        'index' => 'view-users',
        'create' => 'create-user',
        'store' => 'create-user',
        'edit' => 'edit-user',
        'update' => 'edit-user',
        'destroy' => 'delete-user',
    ];

    public function index(): View
    {

        return view('admin.users.index', [
            'title' => 'Kullanıcılar',
            'users' => User::query()->with('roles')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {

        return view('admin.users.create', [
            'title' => 'Yeni kullanıcı',
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
            'sites' => Site::query()->orderBy('name')->pluck('name', 'id')->all(),
            'shifts' => Shift::options(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {

        return $this->complete(
            function () use ($request) {
                $data = $request->userData();
                $user = User::create($data);
                $user->syncRoles($request->roleNames());
            },
            'admin.users.index',
            'Kullanıcı oluşturuldu.',
        );
    }

    public function edit(User $user): View
    {

        $user->load('roles');

        return view('admin.users.edit', [
            'title' => 'Kullanıcıyı düzenle',
            'user' => $user,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
            'sites' => Site::query()->orderBy('name')->pluck('name', 'id')->all(),
            'shifts' => Shift::options(),
            'selectedRoles' => old('roles', $user->roles->pluck('name')->all()),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {

        return $this->complete(
            function () use ($request, $user) {
                $user->update($request->userData());
                $user->syncRoles($request->roleNames());
            },
            'admin.users.index',
            'Kullanıcı güncellendi.',
        );
    }

    public function destroy(User $user): RedirectResponse
    {

        return $this->complete(
            fn () => $user->delete(),
            'admin.users.index',
            'Kullanıcı silindi.',
        );
    }
}
