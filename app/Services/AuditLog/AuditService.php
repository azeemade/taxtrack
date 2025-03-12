<?php

namespace App\Services\AuditLog;

use App\Models\AuditLog;

class AuditService
{
    public static function log($action, $model)
    {
        AuditLog::create([
            'action' => $action,
            'description' => class_basename($model) . ' ' . $action . 'd successfully',
            'model' => get_class($model),
            'model_id' => $model->id,
            'old_data' => $action !== 'create' ? $model->getOriginal() : null,
            'new_data' => $action !== 'delete' ? $model->getAttributes() : null,
            'created_by' => auth()->id(),
            'company_id' => auth()->user()->company->id ?? null,
        ]);
    }

    public function fetch($request)
    {
        return AuditLog::query()
            ->select('id', 'action', 'model', 'description', 'created_at', 'company_id', 'created_by',)
            ->with(['createdBy' => function ($query) {
                $query->without('roles', 'permissions');
            }])
            ->when(!empty($request->start_date) &&  !empty($request->end_date), function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request?->start_date, $request->end_date]);
            })
            ->when(!empty($request->user_id), function ($query) use ($request) {
                return $query->where('created_by', $request->user_id);
            })
            ->when(!empty($request->action), function ($query) use ($request) {
                return $query->where('action', $request->action);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('action', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('description', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('createdBy', 'name', 'LIKE', '%' . $request->q . '%');
            })
            ->latest()
            ->when(!$request->paginate, function ($query) use ($request) {
                return $query->get();
            }, function ($query) use ($request) {
                return $query->paginate($request->limit);
            });
    }
}
