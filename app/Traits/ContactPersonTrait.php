<?php

namespace App\Traits;

use App\Helpers\GeneralHelper;
use App\Models\CompanyEntityContactPerson;

trait ContactPersonTrait
{
    public function contactPersons()
    {
        return $this->morphMany(CompanyEntityContactPerson::class, 'entitiable');
    }

    public function contactPerson()
    {
        return $this->contactPersons()->first();
    }

    public function addContactPerson($request)
    {
        return $this->contactPersons()->create([
            ...$request,
            "entity_reference" => $this->generateReference(),
            "created_by" => auth()->id(),
            "company_id" => auth()->user()->company
        ]);
    }

    protected function generateReference()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\CompanyEntityContactPerson',
            "modelField" => 'entity_reference',
            "prefix" => 'cecp-',
            "idLength" => 3,
        ]);
    }
}
