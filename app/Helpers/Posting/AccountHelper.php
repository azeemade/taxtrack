<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;

class AccountHelper
{
    public static function id(string $slug, int $companyId): ?int
    {
        return DB::table('finance_chart_of_accounts')
            ->where('slug', $slug)
            ->where('company_id', $companyId)
            ->value('id');
    }
}
