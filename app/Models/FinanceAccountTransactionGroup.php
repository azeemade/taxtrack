<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceAccountTransactionGroup extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];


    public function financeAccountTransactions()
    {
        return $this->hasMany(FinanceAccountTransaction::class, 'trans_group_id');
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by')
            ->select(['id', 'name']);
    }

    public function toArray()
    {
        $array = parent::toArray();

        // Handle editedBy relationship
        if (isset($array['edited_by'])) { // Laravel typically converts to snake_case in arrays
            $attributesToRemove = [
                'user_permissions',
                'user_permissions_count',
                'permissions',
                'roles',
                'pivot' // Remove if exists
            ];

            foreach ($attributesToRemove as $attribute) {
                if (isset($array['edited_by'][$attribute])) {
                    unset($array['edited_by'][$attribute]);
                }
            }
        }

        return $array;
    }
}
