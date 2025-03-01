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

    public function getPreviewablesAttribute()
    {
        return [
            'entity' => 'vendor',
            'model' => 'debit note',
            'id' => $this->noteID,
            'additional_reference' => null,
            'entity_data' => [
                'id' => $this->vendor->referenceID,
                'name' => $this->vendor->vendor_name,
                'address' => $this->vendor->primary_address,
                'email' => $this->vendor->primary_email,
                'currency' => $this->vendor->currency->symbol,
            ],
            'issued_date' => $this->date_issued,
            'due_date' => null,
            'company' => $this->company,
            'line_items' => $this->lineItems->map(function ($item) {
                return [
                    'list_item' => $item->item_details,
                    'price' => $item->documentable->vendor->currency->symbol . $item->price,
                    'credit_amount' => $item->documentable->vendor->currency->symbol . $item->credit_amount,
                ];
            }),
            'sub_total' => $this->lineItems()->sum('credit_amount'),
            'additional_charges' => [
                'shipping_charge' => 0.00,
                'additional_charge' => 0.00
            ],
            'total' => $this->lineItems()->sum('credit_amount'),
            'terms_and_conditions' => null,
            'note' => null
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function debitNoteItems()
    {
        return $this->hasMany(DebitNoteItem::class);
    }

    public function lineItems()
    {
        return $this->hasManyThrough(
            LineItem::class,
            DebitNoteItem::class,
            'debit_note_id',
            'documentable_id',
            'id',
            'id'
        )->whereHasMorph(
            'documentable',
            [VendorBill::class, PurchaseInvoice::class]
        );
    }
}
