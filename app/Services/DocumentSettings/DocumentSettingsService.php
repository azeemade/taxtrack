<?php

namespace App\Services\DocumentSettings;

use App\Models\DocumentBasicSettings;
use App\Models\DocumentDefaultSettings;
use App\Models\DocumentRemainderSettings;

class DocumentSettingsService
{
    public function getBasicSettings()
    {
        return DocumentBasicSettings::first();
    }

    public function addBasicSettings($data)
    {
        return DocumentBasicSettings::updateOrCreate([
            'company_id' => auth()->user()->company->id
        ], [
            ...$data
        ]);
    }

    public function getDefaultSettings()
    {
        return DocumentDefaultSettings::first();
    }

    public function addDefaultSettings($data)
    {
        return DocumentDefaultSettings::updateOrCreate([
            'company_id' => auth()->user()->company->id
        ], [
            ...$data
        ]);
    }

    public function invoiceRemainderList()
    {
        return DocumentRemainderSettings::latest()
            ->paginate(10);
    }

    public function invoiceRemainder($id)
    {
        return DocumentRemainderSettings::find($id);
    }

    public function addInvoiceRemainder($data)
    {
        return DocumentRemainderSettings::updateOrCreate([
            'company_id' => auth()->user()->company->id,
            'invoice_due_type' => $data['invoice_due_type'],
            'invoice_due_days' => $data['invoice_due_days'],
        ], [
            ...$data
        ]);
    }
}
