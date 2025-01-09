<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([ModelUserScope::class])]
class CompanyEmailTemplate extends Model
{
    use HasFactory, SoftDeletes, Companyable;
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function getAllowedActionsAttribute()
    {
        return ['delete', 'toggle'];
    }

    public function emailTemplate()
    {
        return $this->belongsTo(EmailTemplate::class, 'email_templates_id');
    }
}
