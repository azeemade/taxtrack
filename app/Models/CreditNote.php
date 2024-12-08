<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nnjeim\World\Models\Currency;

#[ScopedBy([ModelUserScope::class])]
class CreditNote extends Model
{
    use HasFactory, Companyable;

    protected $guarded = ['id'];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditNoteInvoices()
    {
        return $this->hasMany(CreditNoteInvoice::class);
    }
}
