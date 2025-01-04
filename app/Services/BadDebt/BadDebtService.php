<?php

namespace App\Services\BadDebt;

use App\Models\BadDebt;

class BadDebtService
{
    public function create($request)
    {
        $record = BadDebt::create($request);

        return $record;
    }
}
