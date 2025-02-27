<?php

namespace App\Services\PurchaseInvoice;

use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\ShareStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\SharedServices\SharedActionService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseInvoiceService
{
    protected SharedActionService $sharedActionServices;
    public function __construct(
        SharedActionService $sharedActionServices,
    ) {
        $this->sharedActionServices = $sharedActionServices;
    }

    public function updateOrCreate($request)
    {
        $record = PurchaseInvoice::updateOrCreate(
            [
                "id" => $request["id"] ?? null
            ],
            [
                ...$request,
                'purchase_order_date' => $request['purchase_order_date'] ?? PurchaseOrder::find($request['purchase_order_id'])?->purchase_order_date,
                'purchase_invoiceID' => $this->generatePurchaseInvoiceId(),
                'share_status' => $request['save_status'] == 'send' ? ShareStatusEnums::SHARED->value : ShareStatusEnums::NOT_SHARED->value,
                'status' => $request['save_status'] === FinancialDocumentStatusEnums::DRAFT->value ? FinancialDocumentStatusEnums::DRAFT->value : FinancialDocumentStatusEnums::ISSUED->value
            ]
        );

        if (isset($request["id"]) && $request["id"]) {
            $record->editLineItems($request['line_items']);
        } else {
            $record->addLineItems($request['line_items']);
        }

        if ($request['save_status'] == 'send') {
            $this->sharedActionServices->emailEntity($record);
        }

        return $record;
    }

    public function view(int $id)
    {
        $record = PurchaseInvoice::select(
            'id',
            'purchase_invoiceID',
            'purchase_order_id',
            'invoice_start_date',
            'invoice_end_date',
            'attachments',
            'terms_and_conditions',
            'additional_comment',
            'sub_total',
            'shipping_charge',
            'additional_charge',
            'purchase_invoices_total',
            'vendor_id',
        )
            ->with([
                'purchaseOrder:id,purchase_order_no,purchase_order_date',
                'vendor:id,vendor_name,primary_email',
                'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id' => [
                    'category:id,name'
                ]
            ])
            ->find($id);

        if (!$record) {
            throw new BadRequestException("Purchase invoice not found!", Response::HTTP_NOT_FOUND);
        }

        return $record;
    }

    public function list($request)
    {
        $records = PurchaseInvoice::query()
            ->select('id', 'purchase_invoiceID', 'invoice_start_date', 'purchase_invoices_total', 'share_status', 'vendor_id', 'purchase_order_id')
            ->with([
                'vendor:id,vendor_name,referenceID',
                'purchaseOrder:id,purchase_order_no,purchase_order_date',
                'lineItems'
            ])
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy(
                        Vendor::select('vendor_name')
                            ->whereColumn('vendor_id', 'vendors.id')
                            ->orderBy('vendor_name')
                            ->limit(1)
                    );
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('created_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->where('purchase_invoiceID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('purchaseOrder', 'purchase_order_no', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('vendor', 'vendor_name', 'LIKE', '%' . $request->q . '%');
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

    public function export($records, $exportType)
    {
        $recordHeadings = ['Supplier details', 'Purchase invoice ID', 'Start date', 'End date', 'Invoice value', 'Invoice status', 'Line item volume'];
        $records = $records->map(function ($record) {
            return [
                $record->vendor->vendor_name ?? null,
                $record->purchase_invoiceID,
                Carbon::parse($record->invoice_start_date)->toFormattedDayDateString(),
                Carbon::parse($record->invoice_end_date)->toFormattedDayDateString(),
                $record->purchase_invoices_total ?? 0.00,
                $record->status,
                count($record->lineItems) ?? 0
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'invoice_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'invoice_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function lineItems(int $id)
    {
        $record = PurchaseInvoice::find($id);

        if (!$record) {
            throw new BadRequestException("Purchase invoice not found!", Response::HTTP_NOT_FOUND);
        }

        return $record->lineItems->load([
            'category:id,name'
        ]);
    }

    public function matchPurchaseOrder($id, $purchase_order_id)
    {
        $purchaseInvoice = PurchaseInvoice::find($id);
        if (!$purchaseInvoice) {
            throw new BadRequestException("Purchase invoice not found!", Response::HTTP_NOT_FOUND);
        }

        $purchaseOrder = PurchaseOrder::find($purchase_order_id);
        if (!$purchaseOrder) {
            throw new BadRequestException("Purchase order not found!", Response::HTTP_NOT_FOUND);
        }

        $invoiceLineItems = $purchaseInvoice->lineItems()->get();
        $orderLineItems = $purchaseOrder->lineItems()->get();

        if ($invoiceLineItems->count() !== $orderLineItems->count()) {
            throw new BadRequestException("Line items count mismatch!", Response::HTTP_BAD_REQUEST);
        }

        $invoiceItemsArray = $invoiceLineItems->map(fn($item) => [
            'item_details' => $item->item_details,
            'quantity' => $item->quantity,
            'price' => $item->price,
            'amount' => $item->amount,
        ])->sortBy('amount')->values()->toArray();

        $orderItemsArray = $orderLineItems->map(fn($item) => [
            'item_details' => $item->item_details,
            'quantity' => $item->quantity,
            'price' => $item->price,
            'amount' => $item->amount,
        ])->sortBy('amount')->values()->toArray();

        if ($invoiceItemsArray !== $orderItemsArray) {
            throw new BadRequestException("Line items details mismatch!", Response::HTTP_BAD_REQUEST);
        }

        $purchaseInvoice->update([
            'purchase_order_id' => $purchase_order_id,
        ]);
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

    public function calculateSubTotal(array $line_items)
    {
        $quoteSubTotal = 0.00;
        foreach ($line_items as $line_item) {
            $quoteSubTotal += $this->calculateLineItemTotal($line_item['total_unit_price'], $line_item['discount'], $line_item['vat']);
        }
        return round($quoteSubTotal, 4);
    }

    public function calculateTotal(float $sub_total, float $shipping_charge, float $additional_charge)
    {
        $quoteTotal = $sub_total + $shipping_charge + $additional_charge;
        return round($quoteTotal, 4);
    }

    public function generatePurchaseInvoiceId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\PurchaseInvoice',
            "modelField" => 'purchase_invoiceID',
            "prefix" => 'PID-',
            "idLength" => 4,
        ]);
    }
}
