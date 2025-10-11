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
        $currency = $this->currencies()->first();

        if ($currency) {
            return $currency;
        }

        $countryId = $this->physical_address_information['country_id'] ?? null;

        if ($countryId) {
            return \Nnjeim\World\Models\Country::find($countryId)?->currency;
        }

        return null;

        // return $this->currencies()->first() ?? \Nnjeim\World\Models\Country::find($this->physical_address_information['country_id'])?->currency ?? null;
    }
}
