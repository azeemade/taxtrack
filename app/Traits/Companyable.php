<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Companyable
{
    protected static function bootCompanyable()
    {
        static::creating(function (Model $model) {
            if (!$model->isDirty('created_by') && Auth::check()) {
                $model->created_by = Auth::id();
            }
            if (!$model->isDirty('company_id') && Auth::check() && method_exists(Auth::user(), 'company')) {
                $model->company_id = Auth::user()->company->id ?? null;
            }
        });
    }
}
