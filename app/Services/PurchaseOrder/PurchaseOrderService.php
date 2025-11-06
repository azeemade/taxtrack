<?php

namespace App\Services\PurchaseOrder;

use App\Enums\FinancialDocumentStatusEnums;
use App\Enums\ShareStatusEnums;
use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\SharedServices\SharedActionService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseOrderService
{
    protected SharedActionService $sharedActionServices;
    public function __construct(
        SharedActionService $sharedActionServices,
    ) {
        $this->sharedActionServices = $sharedActionServices;
    }

    public function updateOrCreate($request)
    {
        //code...
        $record = PurchaseOrder::updateOrCreate(
            [
                "id" => $request["id"] ?? null
            ],
            [
                ...$request,
                'purchase_order_date' => $request['purchase_order_date'] ?? now(),
                'purchase_order_no' => $request['purchase_order_no'] ?? $this->generateRefId(),
                'purchase_orderID' => $this->generatePurchaseOrderId(),
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
        $record = PurchaseOrder::select(
            'id',
            'purchase_order_no',
            'purchase_order_date',
            'terms_and_conditions',
            'additional_comment',
            'sub_total',
            'shipping_charge',
            'additional_charge',
            'purchase_order_value',
            'vendor_id',
            'purchase_orderID',
            'share_status',
            'status'
        )
            ->with([
                'vendor:id,vendor_name,primary_email',
                'lineItems:id,item_details,category_id,quantity,price,discount,vat,amount,documentable_type,documentable_id,account_id' => [
                    'category:id,name'
                ]
            ])
            ->find($id);

        if (!$record) {
            throw new BadRequestException("Purchase order not found!", Response::HTTP_NOT_FOUND);
        }

        return $record;
    }

    public function lineItems(int $id)
    {
        $record = PurchaseOrder::find($id);

        if (!$record) {
            throw new BadRequestException("Purchase order not found!", Response::HTTP_NOT_FOUND);
        }

        return $record->lineItems->load([
            'category:id,name'
        ]);
    }

    public function list($request)
    {
        $records = PurchaseOrder::query()
            // ->select('id', 'recordable_id', 'recordable_type', 'purchase_order_value', 'share_status', 'vendor_id', 'invoice_id')
            ->select('id', 'vendor_id', 'purchase_order_value', 'share_status', 'vendor_id', 'invoice_id', 'purchase_orderID', 'purchase_order_date')
            ->with([
                // 'recordable:id,vendor_id' => ['vendor:id,vendor_name,referenceID'],
                'vendor:id,vendor_name,referenceID',
                'purchaseInvoice:id,purchase_invoiceID',
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
                return $query->where('purchase_orderID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('purchaseInvoice', 'purchase_invoiceID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereRelation('vendor', 'vendor_name', 'LIKE', '%' . $request->q . '%');
            })
            ->when(isset($request->start_date) && $request->start_date && $request->end_date, function ($query) use ($request) {
                return $query->where('created_at', [$request?->start_date, $request->end_date]);
            })
            ->when($request->vendor_id, function ($query) use ($request) {
                return $query->where('vendor_id', $request->vendor_id);
            })
            ->latest();

        if (isset($request->filter_uninvoiced_shared) && $request->filter_uninvoiced_shared) {
            $records->whereNull('invoice_id')
                ->where('status', 'issued');
        }

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    public function dropdown($request)
    {
        $records = PurchaseOrder::select(
            'id',
            'vendor_id',
            'purchase_order_value',
            'status',
            'share_status',
            'invoice_id',
            'purchase_orderID',
            'purchase_order_date'
        )
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->where(function ($query) {
                $query->whereNull('invoice_id')
                    ->where('status', 'issued')
                    ->orWhereHas('purchaseInvoice', function ($q) {
                        $q->where('status', '!=', 'issued');
                    });
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }


    public function export($records, $exportType)
    {
        $recordHeadings = ['Supplier details', 'Purchase order ID', 'Order date', 'Order value', 'Associated PO invoice', 'Invoice status', 'Line item volume'];
        $records = $records->map(function ($record) {
            return [
                $record->vendor->vendor_name ?? null,
                $record->purchase_orderID,
                Carbon::parse($record->purchase_order_date)->toFormattedDayDateString(),
                $record->purchase_order_value ?? 0.00,
                $record->purchaseInvoice->purchase_invoiceID ?? null,
                $record->status,
                count($record->lineItems) ?? 0
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'invoice_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'invoice_report.csv', \Maatwebsite\Excel\Excel::CSV);
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

    public function generatePurchaseOrderId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\PurchaseOrder',
            "modelField" => 'purchase_orderID',
            "prefix" => 'PO-',
            "idLength" => 4,
        ]);
    }

    public function generateRefId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\PurchaseOrder',
            "modelField" => 'purchase_order_no',
            "prefix" => 'ref-',
            "idLength" => 6,
        ]);
    }
}
