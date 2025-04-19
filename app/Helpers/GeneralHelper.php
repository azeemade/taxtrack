<?php

namespace App\Helpers;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class GeneralHelper
{
    // Here we have all the general Helpers needed for this application

    //Get Current User Instance
    public static function userInstance()
    {
        $userInstance = Auth::user();
        return $userInstance;
    }

    public static function getModelUniqueOrderlyId($data)
    {
        $modelClass = $data['modelNamespace'];
        $modelField = $data['modelField'];
        $prefix = $data['prefix'] ?? "";
        $suffix = $data['suffix'] ?? "";
        $idLength = $data['idLength'] ?? 6;



        if (!class_exists($modelClass)) {
            return ['error' => true, 'message' => 'Model class not found'];
        }

        $record = $modelClass::latest()->first();
        // $fieldId = $record->{$modelField} ?? '';
        $fieldId = $record->id ?? '';
        if (!$fieldId) {
            return $prefix . str_pad(1, $idLength, '0', STR_PAD_LEFT) . $suffix;
        }

        // $escapedPrefix = preg_quote($prefix, '/'); 
        // $escapedSuffix = preg_quote($suffix, '/');
        // $pattern = "/^{$escapedPrefix}(.*?){$escapedSuffix}$/";

        // $currentId = preg_replace($pattern, '$1', $fieldId);
        // $idLength = strlen($currentId);
        $incrementedId = intval($fieldId) + 1;

        return $prefix . str_pad($incrementedId, $idLength, '0', STR_PAD_LEFT) . $suffix;
    }

    public static function getModelUniqueRandomId($data)
    {
        $modelClass = $data['modelNamespace'];
        $modelField = $data['modelField'];


        if (!class_exists($modelClass)) {
            return ['error' => true, 'message' => 'Model class not found'];
        }

        $uniqueId = self::generateUniqueRandomId($data);
        $record = $modelClass::where($modelField, $uniqueId)->first();

        if ($record) {
            return self::getModelUniqueRandomId($data);
        }

        return $uniqueId;
    }

    public static function generateUniqueRandomId($data)
    {
        $prefix = $data['prefix'] ?? "";
        $suffix = $data['suffix'] ?? "";
        $idLength = $data['idLength'] ?? 5;
        $idType = $data['idType'] ?? 'num'; //num, numalpha

        if ($idType == 'num') {
            $uniqueId = rand(0, pow(10, $idLength) - 1);
        } elseif ($idType == 'numalpha') {
            $uniqueId = strtoupper(str_random($idLength));
        } else {
            $uniqueId = str_random($idLength);
        }

        return $prefix . str_pad($uniqueId, $idLength, '0', STR_PAD_LEFT) . $suffix;
    }

    public static function generateCompanyUUID(): string
    {
        $uuid = Str::uuid();
        $company = Company::where('companyUUID', $uuid)->first();
        if ($company) {
            self::generateCompanyUUID();
        }
        return $uuid;
    }

    public static function dateFilter(?string $period = null, ?array $customDate = null): array|bool
    {
        $currentUserCompany = auth()->user()->company;
        if (in_array($period, ["today", "Today"])) {
            $carbonDateFilter = [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period === "3 days") {
            // Last 3 days
            $carbonDateFilter = [Carbon::now()->subDays(3)->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period == "7 days") {
            // Last 7 days
            $carbonDateFilter = [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()];
        } elseif ($period == "14 days") {
            // Last 14 days
            $carbonDateFilter = [Carbon::now()->subWeeks(2)->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period == "this month") {
            // This month
            $carbonDateFilter = [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
        } elseif ($period == "last month") {
            // Last month
            $carbonDateFilter = [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()];
        } elseif ($period == "30 days") {
            // Last 30 days
            $carbonDateFilter = [Carbon::now()->subDays(30), Carbon::now()];
        } elseif ($period == "3 months") {
            // Last 3 months
            $carbonDateFilter = [Carbon::now()->subMonths(3)->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period == "last quarter") {
            // Last month
            $carbonDateFilter = [Carbon::now()->subQuarter()->startOfQuarter(), Carbon::now()->subQuarter()->endOfQuarter()];
        } elseif ($period == "this year") {
            $carbonDateFilter = [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];
        } elseif ($period == "last financial year") {
            $start = $currentUserCompany->fiscal_year_start ? Carbon::createFromFormat('m-d', $currentUserCompany->fiscal_year_start, Carbon::now()->year)->subYear()->format("Y-m-d") : Carbon::now()->subYear()->startOfYear();
            $end = $currentUserCompany->fiscal_year_end ? Carbon::createFromFormat('m-d', $currentUserCompany->fiscal_year_end, Carbon::now()->year)->subYear()->format("Y-m-d") : Carbon::now()->subYear()->endOfYear();

            $carbonDateFilter = [$start, $end];
        } elseif ($period && strpos($period, ',') !== false) {
            $dates = explode('|', $period);
            $carbonDateFilter = [Carbon::parse(trim($dates[0])), Carbon::parse(trim($dates[1]))];
        } elseif (preg_match('/^\d{4}$/', $period)) {
            // If period is a specific year (e.g., 2024, 2025, etc.)
            $carbonDateFilter = [
                Carbon::createFromFormat('Y', $period)->startOfYear(),
                Carbon::createFromFormat('Y', $period)->endOfYear()
            ];
        } elseif ($period == "custom date") {
            $carbonDateFilter = [Carbon::parse($customDate[0]), Carbon::parse($customDate[1])];
        } else {
            $carbonDateFilter = false;
        }

        return $carbonDateFilter;
    }
}
