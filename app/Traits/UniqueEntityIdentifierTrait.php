<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait UniqueEntityIdentifierTrait
{
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uei_id = (string) Str::uuid();
        });
    }
}
