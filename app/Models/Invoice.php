<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use App\Traits\ManageLineItemTrait;
use App\Traits\PaymentRecordTrait;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Currency;

#[ScopedBy([ModelUserScope::class])]
class Invoice extends Model
{
    use HasFactory, Companyable, SoftDeletes, PaymentRecordTrait, ManageLineItemTrait;

    protected $guarded = ['id'];
    protected $appends = ['amount_due', 'total_amount_paid'];
    protected $total_amount = 'invoice_value';

    public function getAllowedActionsAttribute()
    {
        return ['preview', 'download', 'delete', 'duplicate', 'remind'];
    }

    public function getPreviewablesAttribute()
    {
        return [
            'entity' => 'customer',
            'model' => 'invoice',
            'id' => $this->referenceID,
            'additional_reference' => $this->additional_referenceID,
            'entity_data' => [
                'id' => $this->customer->customerID,
                'name' => $this->customer->company_name,
                'address' => $this->customer->address,
                'email' => $this->customer->email,
                'currency' => $this->customer->currency->symbol,
            ],
            'issued_date' => $this->created_at,
            'due_date' => $this->due_date,
            'company' => $this->company,
            'line_items' => $this->lineItems,
            'sub_total' => $this->sub_total,
            'additional_charges' => [
                'shipping_charge' => $this->shipping_charge,
                'additional_charge' => $this->additional_charge
            ],
            'total' => $this->invoice_value,
            'terms_and_conditions' => $this->terms_and_conditions,
            'note' => $this->customer_note
        ];
    }

    public function exportables()
    {
        return [
            'invoice_number' => $this->number,
            'customer_name' => $this->customer->name,
            'total' => $this->total,
            'items' => $this->items,
            // Add any other data needed for the preview
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
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

    public function badDebt()
    {
        return $this->morphOne(BadDebt::class, 'documentable');
    }
}
