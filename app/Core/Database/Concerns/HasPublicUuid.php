<?php

namespace App\Core\Database\Concerns;

use Illuminate\Support\Str;

trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = $model->newPublicUuid();
            }
        });
    }

    public function newPublicUuid(): string
    {
        return (string) Str::orderedUuid();
    }
}
