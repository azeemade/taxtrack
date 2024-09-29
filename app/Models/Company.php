<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function staff()
    {
        return $this->hasMany(User::class);
    }

    public function companyAdmin()
    {
        return $this->staff->where('contact_person', true)->first();
    }
}
