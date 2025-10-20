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
    'INVENTORIES' => 'inventories',
    'INVENTORY' => 'inventories',
    'DELIVERY_INCOME' => 'other-income',
    'SHIPPING_INCOME' => 'other-income',  // or create a dedicated 'shipping-income's
    'OTHER_INCOME' => 'other-income',
    'MISC_INCOME' => 'other-income',
    'MATERIALS_SALES' => 'sales',
    'HARDWARE_SALES' => 'sales',
    'SOFTWARE_SALES' => 'sales',
    'EQUIPMENT_SALES' => 'sales',
    'RETAINED_EARNINGS' => 'retained-earnings',
    'ROUNDING_DIFF' => 'rounding-diff',
    'PURCHASES'     => 'purchases',
];
