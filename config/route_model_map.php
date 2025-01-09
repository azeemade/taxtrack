<?php

return [
    'invoices' => \App\Models\Invoice::class,
    'quotes' => \App\Models\Quote::class,
    'customers' => \App\Models\Customer::class,
    'credit-notes' => \App\Models\CreditNote::class,
    'suppliers' => \App\Models\Vendor::class,
    'email-settings' => \App\Models\CompanyEmailTemplate::class,
    'purchase/vendor-bills' => \App\Models\VendorBill::class,
    'purchase/debit-notes' => \App\Models\DebitNote::class,
    'purchase/invoices' => \App\Models\PurchaseInvoice::class,
    'purchase/orders' => \App\Models\PurchaseOrder::class,
    'purchase/transactions' => \App\Models\Transaction::class,
];
