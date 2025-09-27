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

    public function currentCurrency()
    {
        return $this->currencies()->first() ?? \Nnjeim\World\Models\Country::find($this->physical_address_information['country_id'])?->currency;
    }
}
