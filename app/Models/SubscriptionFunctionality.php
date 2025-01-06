<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionFunctionality extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function moduleFunctionality()
    {
        return $this->belongsTo(ModuleFunctionality::class, 'module_functionality_id');
    }
}
