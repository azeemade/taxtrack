<?php

namespace App\Models;

use App\Traits\Company\CompanyActionTraits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nnjeim\World\Models\Currency;

class Company extends Model
{
    use HasFactory, CompanyActionTraits;
    protected $guarded = ['id'];
    protected $casts = [
        'postal_address_information' => 'array',
        'physical_address_information' => 'array',
        'social_media' => 'array',
    ];

    public function staff()
    {
        return $this->belongsToMany(User::class, 'clients', 'user_id', 'company_id');
    }

    // public function companyAdmin()
    // {
    //     return $this->staff->where('contact_person', true)->first();
    // }

    public function currencies()
    {
        return $this->belongsToMany(Currency::class, 'company_currencies', 'company_id', 'currency_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'company_roles', 'company_id', 'role_id')->withPivot(['created_by']);
    }

    public function emailTemplates()
    {
        return $this->belongsToMany(EmailTemplate::class, 'company_email_templates', 'company_id', 'email_templates_id');
    }

    public function subscriber()
    {
        return $this->hasOne(Subscriber::class, 'company_id');
    }
}
