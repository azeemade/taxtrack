<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $casts = [
        'old_data' => 'json',
        'new_data' => 'json',
    ];
}
