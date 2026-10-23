<?php

namespace App\Http\Controllers\Admin\User;

use App\Enums\Shift;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Repositories\SiteRepository;
use App\Support\ApiPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
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

    public function __construct(private SiteRepository $sites) {}

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
            'roles' => $this->webRoles(),
            'apiRoles' => $this->apiRoles(),
            'sites' => $this->sites->options(),
            'shifts' => Shift::options(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {

        return $this->complete(
            function () use ($request) {
                $data = $request->userData();
                $user = User::create($data);
                $this->syncUserRoles($user, $request->webRoleNames(), $request->apiRoleNames());
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
            'roles' => $this->webRoles(),
            'apiRoles' => $this->apiRoles(),
            'sites' => $this->sites->options(),
            'shifts' => Shift::options(),
            'selectedRoles' => old(
                'roles',
                $user->roles->where('guard_name', 'web')->pluck('name')->all()
            ),
            'selectedApiRoles' => old(
                'api_roles',
                $user->roles->where('guard_name', ApiPermissions::GUARD)->pluck('name')->all()
            ),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {

        return $this->complete(
            function () use ($request, $user) {
                $user->update($request->userData());
                $this->syncUserRoles($user, $request->webRoleNames(), $request->apiRoleNames());
            },
            'admin.users.index',
            'Kullanıcı güncellendi.',
        );
    }

    public function destroy(User $user): RedirectResponse
    {

        return $this->complete(
            function () use ($user) {
                $user->syncRoles([]);
                $user->delete();
            },
            'admin.users.index',
            'Kullanıcı silindi.',
        );
    }

    /**
     * @param  list<string>  $webNames
     * @param  list<string>  $apiNames
     */
    private function syncUserRoles(User $user, array $webNames, array $apiNames): void
    {
        $roles = Role::query()
            ->where(function ($query) use ($webNames, $apiNames): void {
                $query->where(function ($web) use ($webNames): void {
                    $web->where('guard_name', 'web')->whereIn('name', $webNames);
                })->orWhere(function ($api) use ($apiNames): void {
                    $api->where('guard_name', ApiPermissions::GUARD)->whereIn('name', $apiNames);
                });
            })
            ->get();

        $user->syncRoles($roles);
    }

    /**
     * @return Collection<int, Role>
     */
    private function webRoles(): Collection
    {
        return Role::query()->where('guard_name', 'web')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Role>
     */
    private function apiRoles(): Collection
    {
        return Role::query()->where('guard_name', ApiPermissions::GUARD)->orderBy('name')->get();
    }
}
