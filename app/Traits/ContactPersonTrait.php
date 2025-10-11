<?php

namespace App\Traits;

use App\Helpers\GeneralHelper;
use App\Models\CompanyContactPerson;
use Illuminate\Support\Facades\Auth;

trait ContactPersonTrait
{
    public function contactPersons()
    {
        return $this->morphMany(CompanyContactPerson::class, 'contactable');
    }

    public function contactPerson()
    {
        return $this->hasOne(CompanyContactPerson::class, 'contactable_id')->oldestOfMany();
    }

    public function addContactPerson($request)
    {
        return $this->contactPersons()->create([
            ...$request,
            "entity_reference" => $this->generateReference(),
            "created_by" => Auth::id(),
            "company_id" => Auth::user()->company->id
        ]);
    }

    public function editContactPerson($request)
    {
        $existingIds = $this->contactPersons()->pluck('id')->toArray();
        $idsToKeep = [];

        foreach ($request as $person) {
            if (isset($person['id']) && in_array($person['id'], $existingIds)) {
                $record = $this->contactPersons()->find($person['id']);
                $record->update($person);
            } else {
                $record = $this->addContactPerson($person);
            }
            $idsToKeep[] = $record->id;
        }
        $this->contactPersons()->whereNotIn('id', $idsToKeep)->delete();
    }

    protected function generateReference()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\CompanyContactPerson',
            "modelField" => 'entity_reference',
            "prefix" => 'cecp-',
            "idLength" => 3,
        ]);
    }
}
