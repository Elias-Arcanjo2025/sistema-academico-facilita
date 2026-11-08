<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->public_uuid)) {
                $model->public_uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_uuid';
    }
}
