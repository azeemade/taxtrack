<?php

namespace App\Services;

use App\Contracts\BulkUploadContract;
use App\BulkUploads\CustomerBulkUpload;
use App\BulkUploads\InvoiceBulkUpload;
use App\BulkUploads\QuoteBulkUpload;
use App\BulkUploads\CreditNoteBulkUpload;
use App\BulkUploads\VendorBulkUpload;
use App\BulkUploads\PurchaseOrderBulkUpload;
use App\BulkUploads\PurchaseInvoiceBulkUpload;
use App\BulkUploads\BillBulkUpload;
use App\BulkUploads\DebitNoteBulkUpload;
use App\BulkUploads\PaymentMethodBulkUpload;
use App\BulkUploads\UserBulkUpload;
use App\BulkUploads\RoleBulkUpload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class BulkUploadService
{
    protected array $availableModules = [];

    public function __construct()
    {
        $this->initializeModules();
    }

    /**
     * Initialize available bulk upload modules
     */
    protected function initializeModules(): void
    {
        $this->availableModules = [
            'customer' => CustomerBulkUpload::class,
            // 'invoice' => InvoiceBulkUpload::class,
            'sales-invoice' => InvoiceBulkUpload::class,
            // 'quote' => QuoteBulkUpload::class,
            'sales-quote' => QuoteBulkUpload::class,
            'credit-note' => CreditNoteBulkUpload::class,
            // 'vendor' => VendorBulkUpload::class,
            'supplier' => VendorBulkUpload::class,
            'purchase-order' => PurchaseOrderBulkUpload::class,
            'purchase-invoice' => PurchaseInvoiceBulkUpload::class,
            'bill' => BillBulkUpload::class,
            'debit-note' => DebitNoteBulkUpload::class,
            'payment-method' => PaymentMethodBulkUpload::class,
            'user' => UserBulkUpload::class,
            'role' => RoleBulkUpload::class,
        ];
    }

    /**
     * Get all available modules for bulk upload
     */
    public function getAvailableModules(): Collection
    {
        $modules = collect();

        foreach ($this->availableModules as $key => $class) {
            if (class_exists($class)) {
                $instance = new $class();
                $modules->push([
                    'key' => $key,
                    'name' => $instance->getModuleName(),
                    'model_class' => $instance->getModelClass(),
                    'max_rows' => $instance->getMaxRows(),
                    'async_processing' => $instance->shouldProcessAsync(),
                    'template_headers' => $instance->getTemplateHeaders(),
                ]);
            }
        }

        return $modules;
    }

    /**
     * Get bulk upload handler for a specific module
     */
    public function getBulkUploadHandler(string $module): ?BulkUploadContract
    {
        if (!isset($this->availableModules[$module])) {
            return null;
        }

        $class = $this->availableModules[$module];

        if (!class_exists($class)) {
            return null;
        }

        return new $class();
    }

    /**
     * Check if a module supports bulk upload
     */
    public function isModuleSupported(string $module): bool
    {
        return isset($this->availableModules[$module]) && class_exists($this->availableModules[$module]);
    }

    /**
     * Get module configuration
     */
    public function getModuleConfig(string $module): ?array
    {
        $handler = $this->getBulkUploadHandler($module);

        if (!$handler) {
            return null;
        }

        return [
            'name' => $handler->getModuleName(),
            'model_class' => $handler->getModelClass(),
            'max_rows' => $handler->getMaxRows(),
            'async_processing' => $handler->shouldProcessAsync(),
            'validation_rules' => $handler->getValidationRules(),
            'template_headers' => $handler->getTemplateHeaders(),
            'template_sample_data' => $handler->getTemplateSampleData(),
        ];
    }

    /**
     * Validate bulk upload data for a module
     */
    public function validateModuleData(string $module, array $data): array
    {
        $handler = $this->getBulkUploadHandler($module);

        if (!$handler) {
            return [
                'valid' => false,
                'errors' => ['Module not supported'],
            ];
        }

        $validator = Validator::make($data, $handler->getValidationRules(), $handler->getValidationMessages());

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->all(),
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
        ];
    }

    /**
     * Process bulk upload data for a module
     */
    public function processModuleData(string $module, array $data): array
    {
        $handler = $this->getBulkUploadHandler($module);

        if (!$handler) {
            return [
                'success' => false,
                'message' => 'Module not supported',
            ];
        }

        try {
            $results = [];
            $errors = [];
            $warnings = [];

            foreach ($data as $index => $row) {
                $result = $handler->processRow($row, $index + 1);

                if ($result === null) {
                    $errors[] = "Row " . ($index + 1) . ": Processing failed";
                } else {
                    $results[] = $result;
                }
            }

            return [
                'success' => true,
                'processed_data' => $results,
                'errors' => $errors,
                'warnings' => $handler->getWarnings(),
                'statistics' => $handler->getStatistics(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Register a new bulk upload module
     */
    public function registerModule(string $key, string $class): void
    {
        if (class_exists($class) && is_subclass_of($class, BulkUploadContract::class)) {
            $this->availableModules[$key] = $class;
        }
    }

    /**
     * Unregister a bulk upload module
     */
    public function unregisterModule(string $key): void
    {
        unset($this->availableModules[$key]);
    }

    /**
     * Get module statistics
     */
    public function getModuleStatistics(string $module): ?array
    {
        $handler = $this->getBulkUploadHandler($module);

        if (!$handler) {
            return null;
        }

        return [
            'module_name' => $handler->getModuleName(),
            'max_rows' => $handler->getMaxRows(),
            'async_processing' => $handler->shouldProcessAsync(),
            'validation_rules_count' => count($handler->getValidationRules()),
            'template_headers_count' => count($handler->getTemplateHeaders()),
        ];
    }
}
