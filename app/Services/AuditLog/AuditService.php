<?php

namespace App\Services\AuditLog;

class AuditService
{
    public static function log($action, $model)
    {
        \App\Models\AuditLog::create([
            'action' => $action,
            'model' => get_class($model),
            'model_id' => $model->id,
            'old_data' => $action !== 'create' ? $model->getOriginal() : null,
            'new_data' => $action !== 'delete' ? $model->getAttributes() : null,
            'created_by' => auth()->id(),
        ]);
    }
}
