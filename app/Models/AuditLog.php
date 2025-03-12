<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class AuditLog extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $casts = [
        'old_data' => 'json',
        'new_data' => 'json',
    ];

    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? $value : class_basename($this->model) . ' ' . $this->action . 'd successfully',
            set: fn($value) => $value
        );
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->select(['id', 'name']);
    }
}
