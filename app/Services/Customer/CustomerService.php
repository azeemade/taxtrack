<?php

namespace App\Services\Customer;

use App\Enums\GeneralEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class CustomerService
{
    public function all()
    {
        return Customer::query();
    }

    public function list($request)
    {
        $records = Customer::query()
            ->select('id', 'company_name', 'customerID', 'current_balance', 'is_active', 'currency_id', 'terms_and_conditions')
            ->with('contactPerson:id,full_name,company_contact_people.contactable_id')
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy('company_name', 'asc');
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
                return $query->where('company_name', 'LIKE', '%' . $request->q . '%')
                    ->orWhere('customerID', 'LIKE', '%' . $request->q . '%')
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

    public function stats($request)
    {
        $records = Customer::query()
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            });

        return [
            'total' => (clone $records)->count(), // Count total records
            'active' => (clone $records)->where('is_active', true)->count(), // Count active records
            'inactive' => (clone $records)->where('is_active', false)->count(),
        ];
    }

    public function view($id)
    {
        $record = Customer::select(
            'id',
            'company_name',
            'category_id',
            'customer_type',
            'business_type',
            'currency_id',
            'customer_logo',
            'is_active',
            'terms_and_conditions',
            'industry',
            'vat_number',
            'vat_date',
            'tax_type',
            'payment_term'
        )
            ->with(['currency:id,name,symbol', 'category:id,name', 'contactPersons'])
            ->find($id);
        if (!$record) {
            throw new BadRequestException("Customer not found.", Response::HTTP_NOT_FOUND);
        }
        return $record;
    }

    public function createCustomer($request)
    {
        $record = Customer::updateOrCreate([
            "id" => $request["id"] ?? null
        ], [
            "company_name" => $request['company_name'],
            "customerID" => $request['customerID'] ?? $this->generateCompanyReference(),
            "business_registration_number" => $request['business_registration_number'] ?? null,
            "vat_number" => $request['vat_number'] ?? null,
            "category_id" => $request['category_id'] ?? null,
            "customer_type" => $request['customer_type'] ?? null,
            "business_type" => $request['business_type'] ?? null,
            "industry" => $request['industry'] ?? null,
            "phone_ext" => $request['phone_ext'] ?? null,
            "phone_number" => $request['phone_number'] ?? null,
            "email" => $request['email'],
            "employee_count" => $request['employee_count'] ?? 0,
            "currency_id" => $request['currency_id'],
            "state_id" => $request['state_id'] ?? null,
            "country_id" => $request['country_id'] ?? null,
            "address" => $request['address'] ?? null,
            "city_id" => $request['city_id'] ?? null,
            "vat_date" => $request['vat_date'] ?? null,
            "tax_type" => $request['tax_type'] ?? null,
            "customer_logo" => $request['customer_logo'] ?? null,
            "payment_term" => $request['payment_term'] ?? null,
            "special_instruction" => $request['special_instruction'] ?? null,
            "terms_and_conditions" => $request['terms_and_conditions'] ?? null,
        ]);

        if (isset($request["id"]) && $request["id"]) {
            $this->updateContactPerson($record, $request);
        } else {
            $this->createContactPerson($record, $request);
        }

        return $record->only('id', 'company_name', 'phone_number', 'email');
    }

    protected function createContactPerson($record, $request)
    {
        foreach ($request['contact_persons'] as $person) {
            $record->addContactPerson([
                "full_name" => $person['full_name'],
                "salutation" => $person['salutation'] ?? null,
                "primary_email" => $person['primary_email'],
                "secondary_email" => $person['secondary_email'],
                "primary_phone_number" => $person['primary_phone_number'],
                "secondary_phone_number" => $person['secondary_phone_number'],
                "country_id" => $person['country_id'],
                "state_id" => $person['state_id'] ?? null,
                "city_id" => $person['city_id'],
                "primary_address" => $person['primary_address'],
                "secondary_address" => $person['secondary_address'],
                "post_code" => $person['post_code'],
            ]);
        }
    }

    protected function updateContactPerson($record, $request)
    {
        $record->editContactPerson($request['contact_persons']);
    }

    public function generateCompanyReference()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Customer',
            "modelField" => 'customerID',
            "prefix" => 'c-',
            "idLength" => 3,
        ]);
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Customer ID', 'Company name', 'Balance', 'Status', 'Date created'];
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

    public function generateCustomerStatement($id)
    {
        $record = Customer::select(
            'id',
            'company_name',
            'address',
            'customerID',
            'email',
            'currency_id',
            'company_id'
        )
            ->with([
                'currency:id,name,symbol',
                'company:id,name,address',
                'quotes:id,quote_date,quote_total,status,quoteID,customer_id' => [
                    'lineItems:id,item_details,quantity,price,discount,vat,amount,documentable_id,documentable_type'
                ],
                'invoices:id,due_date,invoice_value,status,invoiceID,customer_id' => [
                    'lineItems:id,item_details,quantity,price,discount,vat,amount,credit_amount,documentable_id,documentable_type',
                    'paymentRecords:id,amount_paid,amount_due,paymentID,paid_on,payment_method_id' => [
                        'paymentMethod:id,method_type'
                    ]
                ]
            ])
            ->withSum('invoices as total_invoice_value', 'invoice_value')
            ->find($id);
        if (!$record) {
            throw new BadRequestException("Customer not found.", Response::HTTP_NOT_FOUND);
        }

        $record['total_credit_amount'] = $record->invoices->sum(function ($invoice) {
            return $invoice->lineItems->sum('credit_amount');
        });
        $record['total_amount_paid'] = $record->invoices->sum(function ($invoice) {
            return $invoice->paymentRecords->sum('amount_paid');
        });
        $record['generated_on'] = Carbon::parse(now())->toFormattedDateString();

        // return $record;


        $pdf = Pdf::loadView('company.sales.customer.statement', ['record' => $record])->setPaper('a4', 'portrait');
        return $pdf->download('statement.pdf');
    }
}
