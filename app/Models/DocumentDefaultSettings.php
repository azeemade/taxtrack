<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class DocumentDefaultSettings extends Model
{
    use HasFactory, Companyable;
    protected $guarded = ['id'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
