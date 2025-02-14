<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class DebitNote extends Model
{
    use HasFactory, Companyable;

    protected $guarded = ['id'];

    public function getAllowedActionsAttribute()
    {
        return ['preview', 'download', 'delete', 'remind'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }
}
