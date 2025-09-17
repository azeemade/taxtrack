<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReconciliationRecord extends Model
{
    protected $fillable = [
        'company_id','account_id','date','batch_id',
        'bank_id','app_id','bank_reference','app_reference',
        'bank_debit','bank_credit','app_debit','app_credit',
        'classification','matched_by','context'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(FinanceChartOfAccount::class);
    }  

    public function bank()
    {
        return $this->belongsTo(FinanceBankStatement::class);
    }

    public function app()
    {
        return $this->belongsTo(FinanceAccountEntry::class, 'app_id');
    }
    
}
