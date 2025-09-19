<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\Role;

class RoleBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'Name',
            'Display Name',
            'Description',
            'Permissions',
            'Is Active',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'sales_manager',
                'Sales Manager',
                'Manages sales team and processes',
                'create_invoice,edit_invoice,view_reports',
                '1',
            ],
        ];
    }

    public function processRow(array $row, int $rowNumber): ?array
    {
        $validatedData = $this->validateRow($row, $rowNumber);

        if ($validatedData === false) {
            return null;
        }

        try {
            return [
                'name' => $validatedData['name'],
                'display_name' => $validatedData['display_name'] ?? $validatedData['name'],
                'description' => $validatedData['description'],
                'is_active' => $validatedData['is_active'] ?? true,
                'company_id' => $this->getCurrentCompanyId(),
                'created_by' => $this->getCurrentUserId(),
                'edited_by' => $this->getCurrentUserId(),
            ];
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: " . $e->getMessage());
            return null;
        }
    }

    public function getModelClass(): string
    {
        return Role::class;
    }

    public function getModuleName(): string
    {
        return 'Role';
    }

    public function getMaxRows(): int
    {
        return 100; // Roles are typically few in number
    }

    public function shouldProcessAsync(): bool
    {
        return false; // Roles are simple, can be processed synchronously
    }
}
