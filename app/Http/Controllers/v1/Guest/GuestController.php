<?php

namespace App\Http\Controllers\v1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ErrorLog;
use App\Models\User;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function errorLogs()
    {
        try {
            $records = ErrorLog::take(5)->latest()->get();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, 500);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function industries()
    {
        try {
            $records = [
                'Technology and Computing',
                'Healthcare and Biotechnology',
                'Finance and Banking',
                'Education and Training',
                'Engineering and Architecture',
                'Arts and Design',
                'Media and Entertainment',
                'Hospitality and Tourism',
                'Manufacturing and Logistics',
                'Energy and Utilities',
                'Real Estate and Construction',
                'Non-Profit and Social Services',
                'Government and Public Administration',
                'Sales and Marketing',
                'Human Resources and Recruitment'
            ];

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getUserCompanies(int $id)
    {
        try {
            $records = User::find($id)?->companies;

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getCompanyRoles(int $id)
    {
        try {
            $records = Company::find($id)?->roles;

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
