<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $append = ['allowed_actions', 'previewables'];

    public function getPreviewablesAttribute()
    {
        return [
            'entity' => 'customer',
            'model' => 'invoice',
            'id' => $this->referenceID,
            'additional_reference' => $this->additional_referenceID,
            'entity_data' => [
                'id' => $this->customer->customerID,
                'name' => $this->customer->full_name,
                'address' => $this->customer->primary_address,
                'email' => $this->customer->primary_email,
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

    public function getAllowedActionsAttribute()
    {
        return ['duplicate', 'delete', 'preview', 'export', 'emailEntity', 'download', 'sendRemainder'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lineItems()
    {
        return $this->morphMany(LineItem::class, 'documentable', 'documentable_type', 'documentable_id');
    }
}
