<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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

                $hasCompanyId = $model->attributesToArray()['company_id'] ?? false;
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
