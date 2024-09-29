<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nnjeim\World\Models\Currency;

class Customer extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
