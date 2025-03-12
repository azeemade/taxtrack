<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class DocumentBasicSettings extends Model
{
    use HasFactory, Companyable, Auditable;
    protected $guarded = ['id'];

    protected $casts = [
        'content_display' => 'json',
        'payment_term_for_invoice_and_bills' => 'json',
        'payment_term_for_quote' => 'json',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
