<?php

namespace App\Services\CreditNotes;

use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\ShareStatusEnums;
use App\Exceptions\BadRequestException;
use App\Helpers\GeneralHelper;
use App\Models\CreditNote;
use App\Services\SharedServices\SharedActionService;
use Illuminate\Http\Response;

class CreditNoteService
{
    protected SharedActionService $sharedActionServices;

    public function __construct(
        SharedActionService $sharedActionServices
    ) {
        $this->sharedActionServices = $sharedActionServices;
    }

    public function create($request)
    {
        $record = CreditNote::create([
            ...$request,
            'issue_date' => $request['issue_date'] ?? now(),
            'referenceID' => $this->generateRefId(),
            'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
            'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
        ]);

        foreach ($request['invoices'] as $value) {
            $creditNoteInvoice = $record->creditNoteInvoices()->create([
                ...$value,
                'credit_amount_total' => $value['credit_amount']
            ]);
            $creditNoteInvoice->lineItem()->update([
                'credit_amount' => $value['credit_amount'],
                'full_credit' => $value['credit_in_full']
            ]);
        }

        if ($request['save_status'] == 'send') {
            $this->sharedActionServices->emailEntity($record);
        }

        return $record;
    }

    public function view(int $id)
    {
        $record = CreditNote::select(
            'id',
            'additional_referenceID',
            'issue_date',
            'customer_id',
            'currency_id',
        )
            ->with([
                'customer:id,company_name',
                'currency:id,name,symbol',
                'creditNoteInvoices:id,credit_note_id,invoice_id' => [
                    'invoice:id,invoiceID,additional_referenceID,due_date' =>
                    [
                        'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id,credit_amount,full_credit' => [
                            'category:id,name'
                        ],
                    ]
                ]
            ])->find($id);

        if (!$record) {
            throw new BadRequestException("Credit note not found!", Response::HTTP_NOT_FOUND);
        }
        return $record;
    }

    public function generateRefId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\CreditNote',
            "modelField" => 'referenceID',
            "prefix" => 'ref-',
            "idLength" => 6,
        ]);
    }
}
