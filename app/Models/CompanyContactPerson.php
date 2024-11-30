<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class CompanyContactPerson extends Model
{
    use HasFactory;
    protected $guarded = ["id"];
}
