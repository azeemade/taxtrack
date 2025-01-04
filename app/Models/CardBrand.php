<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardBrand extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'code',
        'type',
    ];

    protected $casts = [
        'type' => 'array',
    ];
}
