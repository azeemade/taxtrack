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
                if (
                    !$model->isDirty('company_id') &&
                    Auth::check() &&
                    method_exists(Auth::user(), 'company')
                    // &&
                    // $model->hasAttribute('company_id')
                ) {
                    $model->company_id = Auth::user()->company->id ?? null;
                }
            }
        });
    }
}
