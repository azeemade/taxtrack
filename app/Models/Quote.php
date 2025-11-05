<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use App\Traits\ManageLineItemTrait;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nnjeim\World\Models\Currency;

#[ScopedBy([ModelUserScope::class])]
class Quote extends Model
{
    use HasFactory, Companyable, SoftDeletes, ManageLineItemTrait, Auditable;
    protected $guarded = ['id'];

    public function getAllowedActionsAttribute()
    {
        return ['preview', 'download', 'delete', 'duplicate', 'remind'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function parentQuote()
    {
        return $this->belongsTo(Quote::class, 'parent_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function lineItems()
    {
        return $this->morphMany(LineItem::class, 'documentable');
    }


    public function getPreviewablesAttribute()
    {
        return [
            'entity' => 'customer',
            'model' => 'quote',
            'id' => $this->quoteID,
            'additional_reference' => $this->additional_referenceID,
            'entity_data' => [
                'id' => $this->customer->customerID,
                'name' => $this->customer->company_name,
                'address' => $this->customer->address,
                'email' => $this->customer->email,
                'currency' => $this->customer->currency->code,
            ],
            'issued_date' => $this->quote_date,
            'due_date' => null,
            'company' => $this->company,
            'has_vat' => $this->lineItems->where('vat', '>', 0)->count() > 0,
            'line_items' => $this->lineItems->map(function ($item) {
                return [
                    'description' => $item->item_details,
                    'quantity' => $item->quantity,
                    'vat' => $item->vat > 0 ? $item->vat . '%' : null,
                    'discount' => $item->discount > 0 ? $item->discount . '%' : null,
                    'price' => $this->customer->currency->code . $item->price,
                    'amount' => $this->customer->currency->code . $item->amount,
                ];
            }),
            'sub_total' => $this->sub_total,
            'additional_charges' => [
                'shipping_charge' => $this->shipping_charge,
                'additional_charge' => $this->additional_charge
            ],
            'total' => $this->quote_total,
            'terms_and_conditions' => $this->terms_and_conditions,
            'note' => $this->customer_note
        ];
    }
}
