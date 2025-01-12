<?php

namespace App\Services\EmailSettings;

use App\Enums\EmailTemplateModelEnums;
use App\Exceptions\BadRequestException;
use App\Models\CompanyEmailTemplate;
use App\Models\EmailTemplate;
use Illuminate\Http\Response;

class EmailSettingsService
{

    public function list($request)
    {
        $records = CompanyEmailTemplate::query()
            ->select('id', 'email_templates_id', 'is_active', 'is_default', 'created_at')
            ->with([
                'emailTemplate:id,name',
            ])
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy(
                        EmailTemplate::select('name')
                            ->whereColumn('email_templates_id', 'email_templates.id')
                            ->orderBy('name')
                            ->limit(1)
                    );
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('created_at', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('is_default', $request->status);
            })
            ->when($request->q, function ($query) use ($request) {
                return $query->whereRelation('emailTemplate', 'name', 'LIKE', '%' . $request->q . '%');
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
        $record = CompanyEmailTemplate::with([
            'emailTemplate:id,name',
        ])->find($id);
        if (!$record) {
            throw new BadRequestException("Record not found", Response::HTTP_NOT_FOUND);
        }
        return $record;
    }

    public function updateOrCreate($request)
    {
        if (isset($request['is_default']) && $request['is_default']) {
            CompanyEmailTemplate::where('is_default', true)
                ->where('email_templates_id', $request['email_templates_id'])
                ->update([
                    'is_default' => false
                ]);
        }
        $record = CompanyEmailTemplate::updateOrCreate(
            [
                'id' =>  $request['id'] ?? null
            ],
            [
                ...$request
            ]
        );

        return $record;
    }

    public function mapModelsToEmailTemplates($model)
    {
        match ($model) {
            'invoice' => $this->findEmailTemplate(EmailTemplateModelEnums::SALES_INVOICE->value),
            'quote' => $this->findEmailTemplate(EmailTemplateModelEnums::QUOTE->value),
            'purchase invoice' => $this->findEmailTemplate(EmailTemplateModelEnums::PURCHASE_INVOICE->value),
            'credit note' => $this->findEmailTemplate(EmailTemplateModelEnums::CREDIT_NOTE->value),
            'purchase order' => $this->findEmailTemplate(EmailTemplateModelEnums::PURCHASE_ORDER->value),
            'recurring invoice' => $this->findEmailTemplate(EmailTemplateModelEnums::RECURRING_INVOICE->value),
            'debit note' => $this->findEmailTemplate(EmailTemplateModelEnums::DEBIT_NOTE->value),
            'receipt' => $this->findEmailTemplate(EmailTemplateModelEnums::RECEIPT->value),
            default => null
        };
    }

    protected function findEmailTemplate($model)
    {
        return CompanyEmailTemplate::where('is_default', true)
            ->whereRelation('emailTemplate', 'name', $model)
            ->first();
    }
}
