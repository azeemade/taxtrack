<?php

namespace App\Http\Controllers\v1\Guest;

use App\Http\Controllers\Controller;
use App\Models\CardBrand;
use App\Models\Category;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\ErrorLog;
use App\Models\User;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class GuestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function errorLogs()
    {
        try {
            $records = ErrorLog::take(5)->latest()->get();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR);
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

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getUserCompanies(int $id)
    {
        try {
            $records = User::find($id)?->companies;

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function categories(Request $request)
    {
        try {
            $records = Category::select('id', 'name', 'slug')
                ->where('table', $request->table)
                ->get();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getCompanyRoles(int $id)
    {
        try {
            $records = Company::find($id)?->roles;

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getUserPermissions(Request $request, int $id)
    {
        try {
            $records = User::find($id)
                ->getAllPermissions();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
    /**
     * Display a listing of the resource.
     */
    public function allPermissions(Request $request)
    {
        try {
            $records = Permission::when(isset($request->module), function ($query) use ($request) {
                $query->where('module', $request->module);
            })
                ->when(isset($request->submodule), function ($query) use ($request) {
                    $query->where('submodule', $request->submodule);
                })
                ->get()
                ->map((function ($record) {
                    $record->display_name = ucwords(str_replace('_', ' ', $record->name));
                    return $record;
                }));

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function allEmailTemplate(Request $request)
    {
        try {
            $records = EmailTemplate::when(isset($request->q), function ($query) use ($request) {
                $query->where('name', $request->q);
            })
                ->get();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function cardBrands()
    {
        try {
            $records = CardBrand::get();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function allBanks(Request $request)
    {
        try {
            $records = DB::table('banks')
                ->select('banks.id', 'banks.name', 'countries.id as country_id', 'countries.iso2 as country_iso2', 'countries.name as country_name')
                ->join('countries', 'banks.country_id', '=', 'countries.id')
                ->when(isset($request->q) && $request->q, function ($query) use ($request) {
                    $query->where('banks.name', 'LIKE', '%' . $request->q . '%');
                })
                ->when(isset($request->country_id) && $request->country_id, function ($query) use ($request) {
                    $query->where('country_id', $request->country_id);
                })
                ->when(isset($request->alphabetically) && $request->alphabetically, function ($query) use ($request) {
                    $query->orderBy('banks.name', 'asc');
                })
                ->get();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
