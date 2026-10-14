<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

abstract class AdminController extends Controller
{
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
