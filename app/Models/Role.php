<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

#[ScopedBy([ModelUserScope::class])]
class Role extends SpatieRole
{
    use HasFactory;
}
