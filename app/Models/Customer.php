<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\ContactPersonTrait;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nnjeim\World\Models\Currency;

#[ScopedBy([ModelUserScope::class])]
class Customer extends Model
{
    use HasFactory, ContactPersonTrait;
    protected $guarded = ['id'];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
