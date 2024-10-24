<?php

namespace App\Models;

use App\Traits\AuditLogs\Auditable;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;
    protected static function booted()
    {
        static::addGlobalScope('companyable', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $currentUser = Auth::user();
            if ($currentUser) {
                $currentUserCompany = $currentUser?->company;
                if ($currentUser->hasRole(['client'])) {
                    $builder->whereRelation('companies', 'company_id', $currentUserCompany?->id);
                }
            }
        });
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_roles', 'role_id', 'company_id')->withPivot(['created_by']);
    }
}
