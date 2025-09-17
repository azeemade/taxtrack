<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationRun extends Model
{
    protected $fillable = [
        'company_id','account_id','start_date','end_date','batch_id',
        'title','discrepancies','dual_reflections','no_discrepancies','created_by'
    ];

    public function records()
    {
        return $this->hasMany(ReconciliationRecord::class, 'run_id');
    }
}
