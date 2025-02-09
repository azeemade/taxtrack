<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait Companyable
{
    protected static function bootCompanyable()
    {
        static::creating(function (Model $model) {
            $currentUser = Auth::user();
            if ($currentUser->hasRole(['client'])) {
                if (!$model->isDirty('created_by') && Auth::check()) {
                    $model->created_by = Auth::id();
                }

                $tableName = $model->getTable();
                $hasCompanyId = Schema::hasColumn($tableName, 'company_id');
                if (
                    !$model->isDirty('company_id') &&
                    Auth::check() && $hasCompanyId
                ) {
                    $model->company_id = Auth::user()->company->id ?? null;
                }
            }
        });
    }
}
