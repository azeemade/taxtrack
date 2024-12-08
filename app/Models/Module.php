<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    public function moduleFunctionality()
    {
        return $this->hasMany(ModuleFunctionality::class, 'module_id');
    }
}
