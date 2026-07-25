<?php

namespace App\Core\Database\Concerns;

use Illuminate\Support\Facades\Auth;

trait TracksAuthors
{
    public static function bootTracksAuthors(): void
    {
        static::creating(function ($model): void {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model): void {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
