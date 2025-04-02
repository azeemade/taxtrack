<?php

namespace App\Models;

use App\Models\Scopes\ModelUserScope;
use App\Traits\AuditLogs\Auditable;
use App\Traits\Companyable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy([ModelUserScope::class])]
class DebitNoteItem extends Model
{
    use HasFactory, Companyable, Auditable;

    protected $guarded = ['id'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function debitNote()
    {
        return $this->belongsTo(DebitNote::class, 'debit_note_id');
    }

    public function modelable()
    {
        return $this->morphTo('modelable', 'modelable_type');
    }
}
