<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use App\Traits\ManageLineItemTrait;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([ModelUserScope::class])]
class PurchaseInvoice extends Model
{
    use HasFactory,
        Companyable,
        SoftDeletes,
        ManageLineItemTrait;

    protected $guarded = ['id'];

    public function getAllowedActionsAttribute()
    {
        return ['preview', 'download', 'delete', 'remind', 'duplicate'];
    }

    public function getPreviewablesAttribute()
    {
        return [
            'entity' => 'supplier',
            'model' => 'purchase invoice',
            'id' => $this->purchase_invoiceID,
            'additional_reference' => null,
            'entity_data' => [
                'id' => $this->vendor->referenceID,
                'name' => $this->vendor->vendor_name,
                'address' => $this->vendor->primary_address,
                'email' => $this->vendor->primary_email,
                'currency' => $this->vendor->currency->symbol,
            ],
            'issued_date' => $this->invoice_start_date,
            'due_date' => $this->invoice_end_date,
            'company' => $this->company,
            'line_items' => $this->lineItems->map(function ($item) {
                return [
                    'description' => $item->item_details,
                    'quantity' => $item->quantity,
                    'price' => $this->vendor->currency->symbol . $item->price,
                    'amount' => $this->vendor->currency->symbol . $item->amount,
                ];
            }),
            'sub_total' => $this->sub_total,
            'additional_charges' => [
                'shipping_charge' => $this->shipping_charge,
                'additional_charge' => $this->additional_charge
            ],
            'total' => $this->purchase_order_value,
            'terms_and_conditions' => $this->terms_and_conditions,
            'note' => $this->additional_comment
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // public function paymentRecords()
    // {
    //     return $this->morphMany(PaymentRecord::class, 'recordable', 'recordable_type', 'recordable_id');
    // }

    public function lineItems()
    {
        return $this->morphMany(LineItem::class, 'documentable');
    }
}
