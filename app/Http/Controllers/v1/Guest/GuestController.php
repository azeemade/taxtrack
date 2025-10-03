<?php

namespace App\Http\Controllers\v1\Guest;

use App\Enums\GeneralEnums;
use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Models\CardBrand;
use App\Models\Category;
use App\Models\Company;
use App\Models\EmailTemplate;
use App\Models\ErrorLog;
use App\Models\Module;
use App\Models\ModuleFunctionality;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\ThirdPartyApi\FontServiceApi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\Currency;
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
                'Human Resources and Recruitment',
                'Others'
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
    public function currencies()
    {
        try {
            $records = Currency::whereIn('code', ['USD', 'EUR', 'GBP'])->get()->unique('code')->values();


            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function countries()
    {
        try {
            $records = Country::where('status', 1)->get();


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

    public function fonts()
    {
        try {
            $fontService = new FontServiceApi();
            $records = $fontService->getFonts();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records->json(), Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function allSubscriptionPlans(Request $request)
    {
        try {
            $records = SubscriptionPlan::with(['subscriptionPlanFeature:id,title,subscription_plan_id'])
                ->when(!$request->all, function ($query) {
                    $query
                        ->where('status', GeneralEnums::ACTIVE->value)
                        ->where('is_active', true)
                        ->where('is_free', false);
                })
                ->when(isset($request->status) && $request->status, function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->get();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function modules()
    {
        try {
            $records = Module::with('moduleFunctionality')->get();

            return JsonResponser::send(false, 'Record(s) found successfully!', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    public function customArtisanCommand(Request $request)
    {
        try {
            Artisan::call($request->command);
            return JsonResponser::send(false, 'Command executed successfully!', null, 200);
        } catch (BadRequestException $error) {
            return JsonResponser::send(true, $error->getMessage(), null, $error->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, 500, $th);
        }
    }

    public function customSqlCommand(Request $request)
    {
        try {
            DB::statement($request->command);
            return JsonResponser::send(false, 'Command executed successfully!', null, 200);
        } catch (BadRequestException $error) {
            return JsonResponser::send(true, $error->getMessage(), null, $error->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error', null, 500, $th);
        }
    }


    public function checkMailServer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return JsonResponser::send(true, 'Validation Error', $validator->errors(), 400);
        }

        $data = [
            'title' => 'Mail Server Check',
            'body' => 'This is a test mail to check if the mail server is up and running',
        ];

        try {
            Mail::raw($data['body'], function ($message) use ($request, $data) {
                $message->to($request->email)
                    ->subject($data['title'])
                    ->from(env('MAIL_FROM_ADDRESS'), 'Email Support');
            });

            return JsonResponser::send(false, 'Mail sent successfully', null);
        } catch (\Throwable $e) {
            return JsonResponser::send(true, 'Mail server is down', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
