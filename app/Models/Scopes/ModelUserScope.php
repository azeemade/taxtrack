<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ModelUserScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * 
     * This scope is a separation of user information concern. 
     * Logged in user can only access information pertaining to 
     * their account across different models. THIS IS A SECURITY MEASURE!
     */
    public function apply(Builder $builder, Model $model): void
    {
        $currentUser = auth()->user();
        if ($currentUser) {
            $currentUserCompany = $currentUser->company;
            if ($currentUser->hasRole('client')) {
                $builder->where('created_by', $currentUser->id)
                    ->orWhere('company_id', $currentUserCompany->id);
            }
        }
    }
}
