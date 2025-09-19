<?php

namespace App\Enums;

enum PermissionEnums: string
{
        //APP Level Permissions
    case ACCESS_ADMIN_APP = 'access_admin_app';
    case ACCESS_CLIENT_APP = 'access_client_app';

        // Module Level Permissions
    case ACCESS_DASHBOARD_MODULE = 'access_dashboard_module';
    case ACCESS_SALES_MODULE = 'access_sales_module';
    case ACCESS_PURCHASE_MODULE = 'access_purchase_module';
    case ACCESS_BANKING_MODULE = 'access_banking_module';
    case ACCESS_ACCOUNTING_MODULE = 'access_accounting_module';
    case ACCESS_TOOLS_MODULE = 'access_tools_module';
    case ACCESS_BUDGET_MODULE = 'access_budget_module';
    case ACCESS_REPORT_MODULE = 'access_report_module';
    case ACCESS_USER_MANAGEMENT_MODULE = 'access_user_management_module';

        // Sales Module Permissions
        // -- Customer
    case ACCESS_CUSTOMERS = 'access_customers';
    case VIEW_CUSTOMERS = 'view_customers';
    case CREATE_CUSTOMER = 'create_customer';
    case MANAGE_CUSTOMER = 'manage_customer';
        // case EDIT_CUSTOMER = 'edit_customer';
        // case DELETE_CUSTOMER = 'delete_customer';
        // case DEACTIVATE_CUSTOMER = 'deactivate_customer';

        // -- Sales Quote
    case ACCESS_SALES_QUOTES = 'access_sales_quotes';
    case VIEW_SALES_QUOTES = 'view_sales_quotes';
    case CREATE_SALES_QUOTE = 'create_sales_quote';
    case MANAGE_SALES_QUOTE = 'manage_sales_quote';
        // case EDIT_QUOTE = 'edit_quote';
        // case DELETE_QUOTE = 'delete_quote';
        // case PREVIEW_QUOTE = 'preview_quote';
        // case PRINT_QUOTE = 'print_quote';
        // case EMAIL_QUOTE = 'email_quote';
        // case DUPLICATE_QUOTE = 'duplicate_quote';
    case CONVERT_SALES_QUOTE_TO_INVOICE = 'convert_sales_quote_to_invoice';
        // case SEND_QUOTE_REMINDER = 'send_quote_reminder';

        // -- Sales Invoice
    case ACCESS_SALES_INVOICES = 'access_sales_invoices';
    case VIEW_SALES_INVOICES = 'view_sales_invoices';
    case CREATE_SALES_INVOICE = 'create_sales_invoice';
    case MANAGE_SALES_INVOICE = 'manage_sales_invoice';
        // case EDIT_INVOICE = 'edit_invoice';
        // case DELETE_INVOICE = 'delete_invoice';
        // case PREVIEW_INVOICE = 'preview_invoice';
        // case EMAIL_INVOICE = 'email_invoice';
    case WRITE_OFF_SALES_INVOICE_BAD_DEBT = 'write_off_sales_invoice_bad_debt';
    case RECORD_SALES_INVOICE_PAYMENT = 'record_sales_invoice_payment';
        // case DOWNLOAD_INVOICE = 'download_invoice';

        // -- Credit Note
    case ACCESS_CREDIT_NOTES = 'access_credit_notes';
    case VIEW_CREDIT_NOTES = 'view_credit_notes';
    case CREATE_CREDIT_NOTE = 'create_credit_note';
    case MANAGE_CREDIT_NOTE = 'manage_credit_note';
        // case EDIT_CREDIT_NOTE = 'edit_credit_note';
        // case DELETE_CREDIT_NOTE = 'delete_credit_note';
        // case PREVIEW_CREDIT_NOTE = 'preview_credit_note';
        // case PRINT_CREDIT_NOTE = 'print_credit_note';
        // case EMAIL_CREDIT_NOTE = 'email_credit_note';

        // Purchase Module Permissions
        // -- Vendor
    case ACCESS_VENDORS = 'access_vendors';
    case VIEW_VENDORS = 'view_vendors';
    case CREATE_VENDOR = 'create_vendor';
    case MANAGE_VENDOR = 'manage_vendor';
        // case EDIT_VENDOR = 'edit_vendor';
        // case DELETE_VENDOR = 'delete_vendor';
        // case DEACTIVATE_VENDOR = 'deactivate_vendor';

        // -- Purchase Order
    case ACCESS_PURCHASE_ORDERS = 'access_purchase_orders';
    case VIEW_PURCHASE_ORDERS = 'view_purchase_orders';
    case CREATE_PURCHASE_ORDER = 'create_purchase_order';
    case MANAGE_PURCHASE_ORDER = 'manage_purchase_order';
        // case EDIT_PURCHASE_ORDER = 'edit_purchase_order';
        // case DELETE_PURCHASE_ORDER = 'delete_purchase_order';
        // case PREVIEW_PURCHASE_ORDER = 'preview_purchase_order';
        // case EMAIL_PURCHASE_ORDER = 'email_purchase_order';
        // case DUPLICATE_PURCHASE_ORDER = 'duplicate_purchase_order';
        // case SEND_PURCHASE_ORDER_REMINDER = 'send_purchase_order_reminder';

        // -- Purchase Invoice
    case ACCESS_PURCHASE_INVOICES = 'access_purchase_invoices';
    case VIEW_PURCHASE_INVOICES = 'view_purchase_invoices';
    case CREATE_PURCHASE_INVOICE = 'create_purchase_invoice';
    case MANAGE_PURCHASE_INVOICE = 'manage_purchase_invoice';
        // case EDIT_PURCHASE_INVOICE = 'edit_purchase_invoice';
        // case DELETE_PURCHASE_INVOICE = 'delete_purchase_invoice';
        // case PREVIEW_PURCHASE_INVOICE = 'preview_purchase_invoice';
        // case EMAIL_PURCHASE_INVOICE = 'email_purchase_invoice';
        // case DUPLICATE_PURCHASE_INVOICE = 'duplicate_purchase_invoice';
    case RECORD_PURCHASE_INVOICE_PAYMENT = 'record_purchase_invoice_payment';
    case MATCH_PURCHASE_INVOICE_TO_PURCHASE_ORDER = 'match_purchase_invoice_to_purchase_order';
    case CONVERT_PURCHASE_INVOICE_TO_VENDOR_BILL = 'convert_purchase_invoice_to_vendor_bill';

        // -- Vendor Bill
    case ACCESS_VENDOR_BILLS = 'access_vendor_bills';
    case VIEW_VENDOR_BILLS = 'view_vendor_bills';
    case CREATE_VENDOR_BILL = 'create_vendor_bill';
    case MANAGE_VENDOR_BILL = 'manage_vendor_bill';
        // case EDIT_VENDOR_BILL = 'edit_vendor_bill';
        // case DELETE_VENDOR_BILL = 'delete_vendor_bill';
        // case PREVIEW_VENDOR_BILL = 'preview_vendor_bill';
        // case EMAIL_VENDOR_BILL = 'email_vendor_bill';
        // case DUPLICATE_VENDOR_BILL = 'duplicate_vendor_bill';
    case RECORD_VENDOR_BILL_PAYMENT = 'record_vendor_bill_payment';
        // case VIEW_PAYMENT_HISTORY = 'view_payment_history';
    case CONVERT_VENDOR_BILL_TO_RECURRING = 'convert_vendor_bill_to_recurring';

        // -- Debit Note
    case ACCESS_DEBIT_NOTES = 'access_debit_notes';
    case VIEW_DEBIT_NOTES = 'view_debit_notes';
    case CREATE_DEBIT_NOTE = 'create_debit_note';
    case MANAGE_DEBIT_NOTE = 'manage_debit_note';
        // case EDIT_DEBIT_NOTE = 'edit_debit_note';
        // case DELETE_DEBIT_NOTE = 'delete_debit_note';
        // case PREVIEW_DEBIT_NOTE = 'preview_debit_note';
        // case PRINT_DEBIT_NOTE = 'print_debit_note';
        // case EMAIL_DEBIT_NOTE = 'email_debit_note';

        // Banking Module Permissions
    case ACCESS_CARDS = 'access_cards';
    case VIEW_CARDS = 'view_cards';
    case CREATE_CARD = 'create_card';
    case MANAGE_CARD = 'manage_card';
        // case EDIT_CARD = 'edit_card';
        // case DEACTIVATE_CARD = 'deactivate_card';

    case ACCESS_BANKS = 'access_banks';
    case VIEW_BANKS = 'view_banks';
    case CREATE_BANK = 'create_bank';
    case MANAGE_BANK = 'manage_bank';
        // case EDIT_BANK = 'edit_bank';
        // case DEACTIVATE_BANK = 'deactivate_bank';

    case ACCESS_RECONCILIATION = 'access_reconciliation';
    case VIEW_RECONCILIATION = 'view_reconciliation';
    case PERFORM_RECONCILIATION = 'perform_reconciliation';

    case ACCESS_TRANSACTION = 'access_transaction';
    case VIEW_TRANSACTION = 'view_transaction';

        // Accounting Module Permissions
    case ACCESS_CHART_OF_ACCOUNTS = 'access_chart_of_accounts';
    case VIEW_CHART_OF_ACCOUNTS = 'view_chart_of_accounts';
    case CREATE_CHART_OF_ACCOUNT = 'create_chart_of_account';
    case MANAGE_CHART_OF_ACCOUNT = 'manage_chart_of_account';
        // case EDIT_CHART_OF_ACCOUNT = 'edit_chart_of_account';
        // case DELETE_CHART_OF_ACCOUNT = 'delete_chart_of_account';
        // case DEACTIVATE_CHART_OF_ACCOUNT = 'deactivate_chart_of_account';

    case ACCESS_JOURNAL_ENTRY = 'access_journal_entry';
    case VIEW_JOURNAL_ENTRY = 'view_journal_entry';
    case CREATE_JOURNAL_ENTRY = 'create_journal_entry';
    case MANAGE_JOURNAL_ENTRY = 'manage_journal_entry';
        // case EDIT_JOURNAL_ENTRY = 'edit_journal_entry';
        // case DELETE_JOURNAL_ENTRY = 'delete_journal_entry';
        // case DEACTIVATE_JOURNAL_ENTRY = 'deactivate_journal_entry';

        // Budget Module Permissions
    case ACCESS_BUDGETS = 'access_budgets';
    case VIEW_BUDGETS = 'view_budgets';
    case CREATE_BUDGET = 'create_budget';
    case MANAGE_BUDGET = 'manage_budget';
        // case EDIT_BUDGET = 'edit_budget';
        // case DEACTIVATE_BUDGET = 'deactivate_budget';


        // Manage User Module Permissions
    case ACCESS_USERS = 'access_users';
    case VIEW_USERS = 'view_users';
    case CREATE_USERS = 'create_users';
    case MANAGE_USERS = 'manage_users';

        // Manage Role Module Permissions
    case ACCESS_ROLES = 'access_roles';
    case VIEW_ROLES = 'view_roles';
    case CREATE_ROLES = 'create_roles';
    case MANAGE_ROLES = 'manage_roles';
    
    case ACCESS_AUDIT_LOGS = 'access_audit_logs';


        /// ADMIN PERMISSIONS

        // Module Level Permissions
    case ACCESS_ADMIN_DASHBOARD_MODULE = 'access_admin_dashboard_module';
    case ACCESS_ADMIN_SUBSCRIPTION_MODULE = 'access_admin_subscription_module';
    case ACCESS_ADMIN_REFUND_MODULE = 'access_admin_refund_module';
    case ACCESS_ADMIN_USER_MANAGEMENT_MODULE = 'access_admin_user_management_module';

        //Subscription Module Permissions
    case VIEW_ADMIN_SUBSCRIPTION_DASHBOARD = 'view_admin_subscription_dashboard';
    case VIEW_ADMIN_SUBSCRIPTION_PLANS = 'view_admin_subscription_plans';
    case CREATE_ADMIN_SUBSCRIPTION_PLANS = 'create_admin_subscription_plans';
    case MANAGE_ADMIN_SUBSCRIPTION_PLANS = 'manage_admin_subscription_plans';

    case VIEW_ADMIN_SUBSCRIBERS = 'view_admin_subscribers';
    case APPROVE_ADMIN_REFUND_REQUEST = 'approve_admin_refund_request';

        // Manage User Module Permissions
    case ACCESS_ADMIN_USERS = 'access_admin_users';
    case VIEW_ADMIN_USERS = 'view_admin_users';
    case CREATE_ADMIN_USERS = 'create_admin_users';
    case MANAGE_ADMIN_USERS = 'manage_admin_users';

        // Manage Role Module Permissions
    case ACCESS_ADMIN_ROLES = 'access_admin_roles';
    case VIEW_ADMIN_ROLES = 'view_admin_roles';
    case CREATE_ADMIN_ROLES = 'create_admin_roles';
    case MANAGE_ADMIN_ROLES = 'manage_admin_roles';


    // Helper method to get all permissions for a module
    public static function getModulePermissions(string $module): array
    {
        return match ($module) {
            'dashboard' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_dashboard')),
            'audit_logs' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_audit')),
            'admin_dashboard' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_dashboard')),
            'sales' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_customer') ||
                str_contains($permission->value, '_sales_quote') ||
                str_contains($permission->value, '_sales_invoice') ||
                str_contains($permission->value, '_credit_note')),
            'purchase' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_vendor') ||
                str_contains($permission->value, '_purchase_order') ||
                str_contains($permission->value, '_purchase_invoice') ||
                str_contains($permission->value, '_vendor_bill') ||
                str_contains($permission->value, '_debit_note')),
            'banking' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_card') ||
                str_contains($permission->value, '_bank') ||
                str_contains($permission->value, '_transaction') ||
                str_contains($permission->value, '_reconciliation')),
            'accounting' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_chart_of_account') ||
                str_contains($permission->value, '_journal_entr')),
            'budget' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_budget')),
            'admin_refund' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_refund')),
            'user_management' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_user') ||
                str_contains($permission->value, '_role')),
            'admin_user_management' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_user') ||
                str_contains($permission->value, '_admin_role')),
            'admin_subscription' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_subscription') ||
                str_contains($permission->value, '_admin_subscribers')),
            default => [],
        };
    }

    public static function getSubmodulePermissions(string $submodule): array
    {
        return match ($submodule) {
            'customer' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_customer')),
            'sales_quote' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_sales_quote')),
            'sales_invoice' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_sales_invoice')),
            'credit_note' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_credit_note')),
            'vendor' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_vendor')),
            'purchase_order' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_purchase_order')),
            'purchase_invoice' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_purchase_invoice')),
            'vendor_bill' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_vendor_bill')),
            'debit_note' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_debit_note')),
            'card' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_card')),
            'bank' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_bank')),
            'transaction' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_transaction')),
            'reconciliation' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_reconciliation')),
            'chart_of_account' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_chart_of_account')),
            'journal_entry' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_journal_entr')),
            'budget' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_budget')),
            'user' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_user')),
            'role' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_role')),
            'admin_subscribers' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_subscribers')),
            'admin_subscription' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_subscription')),
            'admin_role' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_role')),
            'admin_user' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_user')),
            'admin_refund' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_refund')),
            'admin_dashboard' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_admin_dashboard')),
            'dashboard' => array_filter(self::cases(), fn($permission) =>
            str_contains($permission->value, '_dashboard')),
            default => [],
        };
    }

    public static function getSubmoduleByPermission(string $permission)
    {
        $availableSubmodules = [
            'customer',
            'sales_quote',
            'sales_invoice',
            'credit_note',
            'vendor',
            'purchase_order',
            'purchase_invoice',
            'vendor_bill',
            'debit_note',
            'card',
            'bank',
            'reconciliation',
            'chart_of_account',
            'journal_entr',
            'transaction',
            'budget',
            'user',
            'role',
            'admin_subscribers',
            'admin_subscription',
            'admin_role',
            'admin_user',
            'admin_refund',
            'admin_dashboard',
            'dashboard',
        ];

        foreach ($availableSubmodules as $submodule) {
            if (str_contains($permission, $submodule)) {
                return $submodule;
            }
        }
    }

    public static function getModuleByPermission(string $permission)
    {
        $availableModules = [
            'sales' => [
                'customer',
                'sales_quote',
                'sales_invoice',
                'credit_note'
            ],
            'purchase' => [
                'vendor',
                'purchase_order',
                'purchase_invoice',
                'vendor_bill',
                'debit_note'
            ],
            'banking' => [
                'card',
                'bank',
                'reconciliation',
                'transaction'
            ],
            'accounting' => [
                'chart_of_account',
                'journal_entr'
            ],
            'tools' => [],
            'budget' => ['budget'],
            'report' => [],
            'user_management' => [
                'user',
                'role'
            ],
            'admin_subscription' => [
                'admin_subscription',
                'admin_subscribers'
            ],
            'admin_user_management' => [
                'admin_user',
                'admin_role'
            ],
            'admin_refund' => ['admin_refund'],
            'admin_dashboard' => ['admin_dashboard'],
            'dashboard' => ['dashboard'],
            'audit_logs' => ['audit_logs'],
        ];

        foreach ($availableModules as $key => $module) {
            foreach ($module as $submodule) {
                if (str_contains($permission, $submodule)) {
                    return $key;
                }
            }
        }
        return null;
    }
}
