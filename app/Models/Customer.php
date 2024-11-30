<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use App\Traits\ContactPersonTrait;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Currency;

#[ScopedBy([ModelUserScope::class])]
class Customer extends Model
{
    use HasFactory, ContactPersonTrait, Companyable, SoftDeletes;
    
    protected $guarded = ['id'];
    protected $casts = ["is_active" => "boolean"];
    protected $append = ['allowed_actions'];


    public function getAllowedActionsAttribute()
    {
        return ['deactivate', 'delete'];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function category()
    {
        return $this->belongsTo(Currency::class);
    }
}
