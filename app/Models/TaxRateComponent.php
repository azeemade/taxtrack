<?php

namespace App\Models;

use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRateComponent extends Model
{
    use HasFactory, Companyable;
    protected $guarded = ['id'];

    protected $casts = [
        'non_recoverable' => 'boolean',
        'compound' => 'boolean'
    ];
}
