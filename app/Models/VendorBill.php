<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use App\Traits\ManageLineItemTrait;
use App\Traits\PaymentRecordTrait;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([ModelUserScope::class])]
class VendorBill extends Model
{
    use HasFactory, PaymentRecordTrait, Companyable, SoftDeletes, ManageLineItemTrait, Auditable;

    protected $guarded = ['id'];
    protected $appends = ['amount_due', 'total_amount_paid'];
    protected $total_amount = 'vendor_bill_total';

    public function getAllowedActionsAttribute()
    {
        return ['preview', 'download', 'delete', 'duplicate', 'remind'];
    }

    public function getPreviewablesAttribute()
    {
        return [
            'entity' => 'vendor',
            'model' => 'bills',
            'id' => $this->vendor_billID,
            'additional_reference' => null,
            'entity_data' => [
                'id' => $this->vendor->referenceID,
                'name' => $this->vendor->vendor_name,
                'address' => $this->vendor->primary_address,
                'email' => $this->vendor->primary_email,
                'currency' => $this->vendor->currency->code,
            ],
            'issued_date' => $this->created_at,
            'due_date' => $this->vendor_bill_due_date,
            'company' => $this->company,
            'line_items' => $this->lineItems->map(function ($item) {
                return [
                    'description' => $item->item_details,
                    'quantity' => $item->quantity,
                    'price' => $this->vendor->currency->code . $item->price,
                    'amount' => $this->vendor->currency->code . $item->amount,
                ];
            }),
            'sub_total' => $this->sub_total,
            'additional_charges' => [
                'shipping_charge' => $this->shipping_charge,
                'additional_charge' => $this->additional_charge
            ],
            'total' => $this->vendor_bill_total,
            'terms_and_conditions' => $this->terms_and_conditions,
            'note' => $this->additional_comment
        ];
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentRecords()
    {
        return $this->morphMany(PaymentRecord::class, 'recordable', 'recordable_type', 'recordable_id');
    }

    public function lineItems()
    {
        return $this->morphMany(LineItem::class, 'documentable');
    }
}
