<?php

namespace App\Traits\AuditLogs;

use App\Services\AuditLog\AuditService;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            AuditService::log('create', $model);
        });

        static::updated(function ($model) {
            AuditService::log('update', $model);
        });

        static::deleted(function ($model) {
            AuditService::log('delete', $model);
        });
    }
}
