<?php

namespace App\Traits;

use App\Services\SharedServices\SharedActionService;

trait SharedActionTrait
{
    public function duplicate()
    {
        return app(SharedActionService::class)->duplicate($this);
    }

    public function sendReminder()
    {
        return app(SharedActionService::class)->sendReminder($this);
    }

    public function emailCustomer()
    {
        return app(SharedActionService::class)->emailEntity($this, 'Customer');
    }

    public function emailVendor()
    {
        return app(SharedActionService::class)->emailEntity($this, 'Vendor');
    }

    public function deactivate()
    {
        return app(SharedActionService::class)->deactivate($this);
    }

    public function safeDelete()
    {
        return app(SharedActionService::class)->delete($this);
    }

    public function preview()
    {
        return app(SharedActionService::class)->preview($this);
    }

    public function download()
    {
        return app(SharedActionService::class)->download($this);
    }

    abstract public function getPreviewData();
}