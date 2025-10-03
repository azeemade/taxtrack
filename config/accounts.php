<?php

return [
    'AR'          => 'trade-account-receivables',
    'SALES'       => 'sales',
    'VAT_PAYABLE' => 'vat-payable',
    'DISCOUNTS'   => 'discounts',

    'AP'              => 'trade-accounts-payable',
    'CASH_DEFAULT'    => 'cash',                 // will be auto-created if missing
    'RECEIPTS_CLR'    => 'suspense-account',     // fallback if payment method mapping missing
    'BAD_DEBT_EXP'    => 'bad-debt-expense',     // will be auto-created if missing

    'VAT_INPUT'   => 'input-vat',
    'COGS'        => 'cost-of-goods-sold',
    'FREIGHT_COSTS' => 'freight-costs',
    'ADMIN_EXP'   => 'administrative-expenses',
];