<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Base service providing the shared conventions for write operations:
 * each service method performs its work inside a database transaction and
 * returns the affected model(s).
 */
abstract class Service
{
    /**
     * Run a closure inside a database transaction, catching and rethrowing
     * exceptions after a rollback.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws Throwable
     */
    protected function transaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (Throwable $e) {
            report($e);

            throw $e;
        }
    }

    /**
     * Persist a model, throwing on failure so callers never see a silent
     * save() === false.
     *
     * @template T of Model
     *
     * @param  T  $model
     * @return T
     */
    protected function save(Model $model): Model
    {
        if (! $model->save()) {
            throw new \RuntimeException('Failed to save '.class_basename($model).'.');
        }

        return $model;
    }

    /**
     * Soft-delete (or hard-delete) a model.
     *
     * @template T of Model
     *
     * @param  T  $model
     * @return T
     */
    protected function delete(Model $model, bool $force = false): Model
    {
        if ($force) {
            $model->forceDelete();
        } else {
            $model->delete();
        }

        return $model;
    }
}
