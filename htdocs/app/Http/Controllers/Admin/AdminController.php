<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

abstract class AdminController extends Controller
{
    /**
     * Spatie resource slug for CRUD mapping (e.g. bank → view-bank, create-bank).
     */
    protected ?string $permissionResource = null;

    /**
     * Fixed ability for every method (reports, lookups).
     */
    protected ?string $permissionAbility = null;

    /**
     * Per-method ability overrides (e.g. index => view-users); a list requires every ability in it.
     *
     * @var array<string, string|list<string>>
     */
    protected array $permissionMap = [];

    /**
     * @param  string  $method
     * @param  array<int|string, mixed>  $parameters
     */
    public function callAction($method, $parameters)
    {
        $this->authorizeIncoming($method);

        return $this->{$method}(...array_values($parameters));
    }

    protected function authorizeIncoming(string $method): void
    {
        if (isset($this->permissionMap[$method])) {
            foreach ((array) $this->permissionMap[$method] as $ability) {
                $this->authorize($ability);
            }

            return;
        }

        if ($this->permissionAbility !== null) {
            $this->authorize($this->permissionAbility);

            return;
        }

        if ($this->permissionResource === null) {
            return;
        }

        $permission = match ($method) {
            'index', 'show', 'balance' => "view-{$this->permissionResource}",
            'create', 'store' => "create-{$this->permissionResource}",
            'edit', 'update' => "edit-{$this->permissionResource}",
            'destroy' => "delete-{$this->permissionResource}",
            default => "view-{$this->permissionResource}",
        };

        $this->authorize($permission);
    }

    /**
     * @param  callable(): mixed  $action
     * @param  array<string, mixed>  $parameters
     */
    protected function complete(callable $action, string $route, string $message, array $parameters = []): RedirectResponse
    {
        try {
            $action();
        } catch (OperationRejectedException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (OperationException) {
            return back()
                ->withInput()
                ->with('error', 'İşlem tamamlanamadı. Değişiklikler geri alındı.');
        }

        return redirect()
            ->route($route, $parameters)
            ->with('success', $message);
    }
}
