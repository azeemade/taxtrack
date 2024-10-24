<?php

namespace App\Models;

use App\Traits\UniqueEntityIdentifierTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory, UniqueEntityIdentifierTrait;
}
