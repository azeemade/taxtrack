<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nnjeim\World\Models\Currency;

class Company extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    // public function staff()
    // {
    //     return $this->hasMany(User::class);
    // }

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
}
