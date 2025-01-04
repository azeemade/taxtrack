<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
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
    use HasFactory, Companyable, SoftDeletes, ManageLineItemTrait;
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
                'currency' => $this->customer->currency->symbol,
            ],
            'issued_date' => $this->quote_date,
            'due_date' => null,
            'company' => $this->company,
            'line_items' => $this->lineItems,
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
