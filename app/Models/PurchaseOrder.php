<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class PurchaseOrder extends Model
{
    use HasFactory;
}
