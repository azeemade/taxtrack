<?php

// app/Helpers/helpers.php
use Illuminate\Support\Facades\DB;

if (! function_exists('accountId')) {
    function accountId(string $slug, int $companyId): ?int
    {
        return DB::table('finance_chart_of_accounts')
            ->where('slug', $slug)
            ->where('company_id', $companyId)
            ->value('id');
    }
}