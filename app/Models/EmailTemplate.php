<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function companiesEmailTemplates()
    {
        return $this->hasMany(CompanyEmailTemplate::class);
    }

    public function companyEmailTemplate()
    {
        return $this->companiesEmailTemplates->where('company_id', auth()->user()->current_company_id)->first();
    }
}
