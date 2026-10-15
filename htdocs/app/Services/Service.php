<?php

namespace App\Services;

use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class Service
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function transaction(callable $callback): mixed
    {
        DB::beginTransaction();

        try {
            $result = $callback();
            DB::commit();

            return $result;
        } catch (OperationRejectedException $exception) {
            DB::rollBack();

            throw $exception;
        } catch (Throwable $exception) {
            DB::rollBack();
            report($exception);

            throw new OperationException('İşlem tamamlanamadı.', previous: $exception);
        }
    }
}
