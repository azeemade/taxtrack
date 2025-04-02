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
    
            if ($currentUser && $currentUser->hasRole(['client'])) { // Ensure it's not null
                $tableName = $model->getTable();
                $hasCreatedBy = Schema::hasColumn($tableName, 'created_by');
    
                if ($hasCreatedBy) {
                    $model->created_by = $currentUser->id;
                }
            }
        });

        // static::creating(function (Model $model) {
        //     $currentUser = Auth::user();
        //     if ($currentUser->hasRole(['client'])) {
        //         $tableName = $model->getTable();

        //         $hasCreatedBy = Schema::hasColumn($tableName, 'created_by');
        //         if (
        //             !$model->isDirty('created_by') &&
        //             $hasCreatedBy
        //         ) {
        //             $model->created_by = Auth::id();
        //         }

        //         $hasCompanyId = Schema::hasColumn($tableName, 'company_id');
        //         if (
        //             !$model->isDirty('company_id') &&
        //             $hasCompanyId
        //         ) {
        //             $model->company_id = Auth::user()->company->id ?? null;
        //         }
        //     }
        // });
    }
}
