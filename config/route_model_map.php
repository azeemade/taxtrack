<?php

return [
    'sales/invoices' => \App\Models\Invoice::class,
    'sales/quotes' => \App\Models\Quote::class,
    'sales/customers' => \App\Models\Customer::class,
    'sales/credit-notes' => \App\Models\CreditNote::class,
    'purchases/suppliers' => \App\Models\Vendor::class,
    'settings/email' => \App\Models\CompanyEmailTemplate::class,
    'settings/taxes' => \App\Models\TaxRate::class,
    'purchases/vendor-bills' => \App\Models\VendorBill::class,
    'purchases/debit-notes' => \App\Models\DebitNote::class,
    'purchases/invoices' => \App\Models\PurchaseInvoice::class,
    'purchases/orders' => \App\Models\PurchaseOrder::class,
    'purchases/bills' => \App\Models\VendorBill::class,
    'banking/transactions' => \App\Models\Transaction::class,
    'purchases/record-payments' => \App\Models\PaymentRecord::class,
];
