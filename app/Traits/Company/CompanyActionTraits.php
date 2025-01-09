<?php

namespace App\Traits\Company;

use App\Models\EmailTemplate;

trait CompanyActionTraits
{
    public function attachEmailTemplates()
    {
        $emailTemplatesId = EmailTemplate::all()->pluck('id');
        $this->emailTemplates()->sync($emailTemplatesId);
    }
}
