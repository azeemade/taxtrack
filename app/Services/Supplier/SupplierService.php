<?php

namespace App\Services\Supplier;

use App\Enums\GeneralEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\Vendor;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class SupplierService
{
    public function all()
    {
        return Vendor::query();
    }

    public function list($request)
    {
        $records = Vendor::query()
            ->select('id', 'vendor_name', 'primary_phone_number', 'referenceID', 'is_active', 'terms_and_conditions')
            ->with('contactPerson:id,full_name,company_contact_people.contactable_id')
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy('vendor_name', 'asc');
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('created_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                if ($request->status == GeneralEnums::ACTIVE->value) {
                    return $query->where('is_active', true);
                } else {
                    return $query->where('is_active', false);
                }
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('vendor_name', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('referenceID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('contactPerson', 'full_name', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function view($id)
    {
        $record = Vendor::with([
            'currency:id,name,symbol',
            'category:id,name',
            'contactPersons'
        ])
            ->find($id);
        if (!$record) {
            throw new BadRequestException("Vendor not found.", Response::HTTP_NOT_FOUND);
        }
        return $record;
    }

    public function createSupplier($request)
    {
        if (Auth::check()) {
            $companyCurrency = Auth::user()?->company?->currentCurrency();
        }
        $record = Vendor::updateOrCreate([
            "id" => $request["id"] ?? null
        ], [
            ...$request,
            "currency_id" => $request['currency_id'] ?? $companyCurrency?->id,
            "referenceID" => $request['supplier_reference'] ?? $this->view($request["id"])?->referenceID,
            "zip_code" => $request['post_code'] ?? null
        ]);

        if (isset($request["id"]) && $request["id"]) {
            $this->updateContactPerson($record, $request);
        } else {
            $this->createContactPerson($record, $request);
        }

        return $record->only('id', 'vendor_name', 'primary_phone_number', 'primary_email');
    }

    protected function createContactPerson($record, $request)
    {
        foreach ($request['contact_persons'] as $person) {
            $record->addContactPerson([
                "full_name" => $person['full_name'],
                "primary_email" => $person['primary_email'] ?? null,
                "secondary_email" => $person['secondary_email'] ?? null,
                "primary_phone_number" => $person['primary_phone_number'] ?? null,
                "secondary_phone_number" => $person['secondary_phone_number'] ?? null,
                "country_id" => $person['country_id'] ?? null,
                "county" => $person['county'] ?? null,
                "state_id" => $person['state_id'] ?? null,
                "city_id" => $person['city_id'] ?? null,
                "primary_address" => $person['primary_address'] ?? null,
                "secondary_address" => $person['secondary_address'] ?? null,
                "post_code" => $person['post_code'] ?? null,
            ]);
        }
    }

    protected function updateContactPerson($record, $request)
    {
        $record->editContactPerson($request['contact_persons']);
    }

    public function generateSupplierReference()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Vendor',
            "modelField" => 'referenceID',
            "prefix" => 's-',
            "idLength" => 4,
        ]);
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Vendor ID', 'Company name', 'Balance', 'Status', 'Date created'];
        $records = $records->map(function ($record) {
            return [
                $record->customerID,
                $record->name,
                $record->current_balance,
                $record->status,
                Carbon::parse($record->created_at)->toFormattedDayDateString()
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'customer_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'customer_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }
}
