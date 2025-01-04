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

    public function getAllowedActionsAttribute()
    {
        return ['preview', 'download', 'delete', 'duplicate', 'remind'];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creditNoteInvoices()
    {
        return $this->hasMany(CreditNoteInvoice::class);
    }

    public function lineItems()
    {
        return $this->hasManyThrough(LineItem::class, CreditNoteInvoice::class, 'credit_note_id', 'id', 'id', 'line_item_id');
    }

    public function getPreviewablesAttribute()
    {
        return [
            'entity' => 'customer',
            'model' => 'credit note',
            'id' => $this->referenceID,
            'additional_reference' => $this->additional_referenceID,
            'entity_data' => [
                'id' => $this->customer->customerID,
                'name' => $this->customer->company_name,
                'address' => $this->customer->address,
                'email' => $this->customer->email,
                'currency' => $this->customer->currency->symbol,
            ],
            'issued_date' => $this->issue_date,
            'due_date' => null,
            'company' => $this->company,
            'line_items' => $this->lineItems->map(function ($item) {
                return [
                    'list_item' => $item->item_details,
                    'price' => $item->documentable->currency->symbol . $item->price,
                    'credit_amount' => $item->documentable->currency->symbol . $item->credit_amount,
                ];
            }),
            'sub_total' => $this->creditNoteInvoices()->sum('credit_amount_total'),
            'additional_charges' => [
                'shipping_charge' => 0.00,
                'additional_charge' => 0.00
            ],
            'total' => $this->creditNoteInvoices()->sum('credit_amount_total'),
            'terms_and_conditions' => null,
            'note' => null
        ];
    }
}
