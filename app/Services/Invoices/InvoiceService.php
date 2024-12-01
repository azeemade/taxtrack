<?php

namespace App\Services\Invoices;

use App\Enums\DocumentableTypeEnums;
use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\GeneralEnums;
use App\Enums\ShareStatusEnums;
use App\Exceptions\BadRequestException;
use App\Helpers\GeneralHelper;
use App\Models\Invoice;
use App\Services\PaymentRecords\PaymentRecordService;
use App\Services\SharedServices\SharedActionService;
use Illuminate\Http\Response;

class InvoiceService
{
    protected SharedActionService $sharedActionServices;
    protected PaymentRecordService $paymentRecordService;
    public function __construct(
        SharedActionService $sharedActionServices,
        PaymentRecordService $paymentRecordService
    ) {
        $this->sharedActionServices = $sharedActionServices;
        $this->paymentRecordService = $paymentRecordService;
    }

    public function create($request)
    {
        $record = Invoice::create([
            ...$request,
            'quote_date' => $request['quote_date'] ?? now(),
            'referenceID' => $this->generateRefId(),
            'invoiceID' => $this->generateInvoiceId(),
            'is_recurring' => $request['save_status'] == 'recur' ? true : false,
            'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
            'status' => $request['save_status'] == FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
        ]);

        foreach ($request['line_items'] as $value) {
            $record->lineItems()->create([
                'documentable_type' => DocumentableTypeEnums::INVOICE->value,
                'item_details' => $value['name'],
                'category_id' => $value['category_id'],
                'quantity' => $value['quantity'],
                'price' => $value['unit_price'],
                'discount' => $value['discount'],
                'vat' => $value['vat'],
                'amount' => $value['line_total']
            ]);
        }

        if ($request['save_status'] == 'send') {
            $this->sharedActionServices->emailEntity($record);
        }

        return $record;
    }

    public function view(int $id)
    {
        $record = Invoice::select(
            'id',
            'invoiceID',
            'additional_referenceID',
            'start_date',
            'due_date',
            'terms_and_conditions',
            'customer_note',
            'sub_total',
            'shipping_charge',
            'additional_charge',
            'invoice_value',
            'customer_id',
            'currency_id',
        )
            ->with([
                'customer:id,company_name',
                'currency:id,name,symbol',
                'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id' => [
                    'category:id,name'
                ]
            ])
            ->find($id);

        if (!$record) {
            throw new BadRequestException("Invoice not found!", Response::HTTP_NOT_FOUND);
        }

        return $record;
    }

    public function calculateLineItemTotalUnitPrice(float $unit, int $quantity)
    {
        $lineItemTotalUnitPrice = $unit * $quantity;
        return round($lineItemTotalUnitPrice, 4);
    }

    public function calculateLineItemTotal(float $total_unit_price, float $discount, float $vat)
    {
        $lineItemTotal = ($total_unit_price - (($total_unit_price * $discount) / 100))  * (1 + $vat / 100);
        return round($lineItemTotal, 4);
    }

    public function calculateQuoteSubTotal(array $line_items)
    {
        $quoteSubTotal = 0.00;
        foreach ($line_items as $line_item) {
            $quoteSubTotal += $this->calculateLineItemTotal($line_item['total_unit_price'], $line_item['discount'], $line_item['vat']);
        }
        return round($quoteSubTotal, 4);
    }

    public function calculateQuoteTotal(float $sub_total, float $shipping_charge, float $additional_charge)
    {
        $quoteTotal = $sub_total + $shipping_charge + $additional_charge;
        return round($quoteTotal, 4);
    }

    public function generateInvoiceId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Invoice',
            "modelField" => 'invoiceID',
            "prefix" => 'Inv-',
            "idLength" => 4,
        ]);
    }

    public function generateRefId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Invoice',
            "modelField" => 'referenceID',
            "prefix" => 'ref-',
            "idLength" => 6,
        ]);
    }
}
