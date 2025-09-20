<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([ModelUserScope::class])]
class PaymentRecord extends Model
{
    use HasFactory, Companyable, SoftDeletes, Auditable;

    protected $guarded = ['id'];

    public function getAllowedActionsAttribute()
    {
        return ['delete', 'duplicate', 'remind'];
    }


    public function getPreviewablesAttribute()
    {
        return [
            'entity' => 'supplier',
            'model' => 'bill transactions',
            'id' => $this->paymentID,
            'additional_reference' => $this->referenceID ?? null,
            'entity_data' => [
                'id' => $this->recordable->vendor->referenceID,
                'name' => $this->recordable->vendor->vendor_name,
                'address' => $this->recordable->vendor->primary_address,
                'email' => $this->recordable->vendor->primary_email,
                'currency' => $this->recordable->vendor->currency->code,
            ],
            'issued_date' => $this->paid_on,
            'due_date' => null,
            'company' => $this->company,
            'line_items' => $this->recordable->lineItems->map(function ($item) {
                return [
                    'description' => $item->item_details,
                    'quantity' => $item->quantity,
                    'price' => $this->recordable->vendor->currency->code . $item->price,
                    'amount' => $this->recordable->vendor->currency->code . $item->amount,
                ];
            }),
            'sub_total' => $this->amount_paid,
            'additional_charges' => [
                'shipping_charge' => 0.00,
                'additional_charge' => 0.00,
            ],
            'total' => $this->amount_paid,
            'terms_and_conditions' => null,
            'note' => null
        ];
    }
    public function recordable()
    {
        return $this->morphTo('recordable', 'recordable_type');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
