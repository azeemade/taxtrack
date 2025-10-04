<?php

namespace App\Traits;

use App\Models\LineItem;
use Illuminate\Support\Facades\Auth;

trait ManageLineItemTrait
{
    public function addLineItems($request)
    {
        foreach ($request as $value) {
            $this->lineItems()->create([
                ...$value,
                'created_by' => Auth::id(),
                'company_id' => Auth::user()->current_company_id,
            ]);
        }
    }
    public function editLineItems($request)
    {
        $existingIds = $this->lineItems()->pluck('id')->toArray();
        $idsToKeep = [];
        foreach ($request as $lineItem) {
            if (isset($lineItem['id']) && in_array($lineItem['id'], $existingIds)) {
                $record = $this->lineItems()->find($lineItem['id']);
                $record->update($lineItem);
            } else {
                $record = $this->lineItems()->create([
                    ...$lineItem,
                    'created_by' => Auth::id(),
                    'company_id' => Auth::user()->current_company_id,
                ]);
            }
            $idsToKeep[] = $record->id;
        }
        $this->lineItems()->whereNotIn('id', $idsToKeep)->delete();
    }

    public function lineItems()
    {
        return $this->hasMany(LineItem::class, 'documentable_id');
    }
}
