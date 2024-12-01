<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class CreditNoteInvoice extends Model
{
    use HasFactory, Companyable;

    protected $guarded = ['id'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lineItem()
    {
        return $this->belongsTo(LineItem::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
