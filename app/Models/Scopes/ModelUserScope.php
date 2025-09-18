<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class ModelUserScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $currentUser = Auth::user();
        if ($currentUser) {
            $currentUserCompany = $currentUser?->company;
            if ($currentUser->hasRole(['client'])) {
                $userIdKey = $model->userIdKey ?? 'created_by';
                $companyIdKey = $model->companyIdKey ?? 'company_id';
                $builder->where($model->getTable() . '.' . $userIdKey, $currentUser?->id)
                    ->orWhere($model->getTable() . '.' . $companyIdKey, $currentUserCompany?->id);
            }
        }
    }
}
