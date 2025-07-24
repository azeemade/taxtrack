<?php

namespace App\Models;

use App\Traits\Company\CompanyActionTraits;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\Currency;
use Nnjeim\World\Models\State;

class Company extends Model
{
    use HasFactory, CompanyActionTraits;
    protected $guarded = ['id'];
    protected $casts = [
        'postal_address_information' => 'array',
        'physical_address_information' => 'array',
        'social_media' => 'array',
    ];

    // protected $appends = [
    //     'physical_city',
    //     'physical_state',
    //     'physical_country'
    // ];

    public function staff()
    {
        return $this->belongsToMany(User::class, 'clients', 'company_id', 'user_id');
    }

    public function companyAdmin()
    {
        return $this->belongsTo(User::class, 'created_by');
        // return $this->staff->where('contact_person', true)->first();
    }

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


    // Relationships for physical address
    // public function physicalCity()
    // {
    //     if (!isset($this->physical_address_information['city_id'])) {
    //         return null;
    //     }
    //     return $this->belongsTo(City::class, 'physical_address_information->city_id');
    // }

    // public function physicalState()
    // {
    //     if (!isset($this->physical_address_information['state_id'])) {
    //         return null;
    //     }
    //     return $this->belongsTo(State::class, 'physical_address_information->state_id');
    // }

    // public function physicalCountry()
    // {
    //     if (!isset($this->physical_address_information['country_id'])) {
    //         return null;
    //     }
    //     return $this->belongsTo(Country::class, 'physical_address_information->country_id');
    // }

    // // Accessors for physical address names
    // public function getPhysicalCityAttribute()
    // {
    //     return $this->physicalCity ? $this->physicalCity->name : null;
    // }

    // public function getPhysicalStateAttribute()
    // {
    //     return $this->physicalState ? $this->physicalState->name : null;
    // }

    // public function getPhysicalCountryAttribute()
    // {
    //     return $this->physicalCountry ? $this->physicalCountry->name : null;
    // }
}
