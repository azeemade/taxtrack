<?php

namespace App\BulkUploads;

use App\Abstracts\BulkUploadAbstract;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;

class UserBulkUpload extends BulkUploadAbstract
{
    public function getValidationRules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'company_name' => 'nullable|string|max:255',
        ];
    }

    public function getTemplateHeaders(?string $subType = null): array
    {
        return [
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Password',
            'Role',
            'Department',
            'Job Title',
            'Is Active',
            'Company Name',
        ];
    }

    public function getTemplateSampleData(?string $subType = null): array
    {
        return [
            [
                'John',
                'Doe',
                'john.doe@company.com',
                '+1-555-0123',
                'SecurePassword123!',
                'manager',
                'Sales',
                'Sales Manager',
                '1',
                'My Company Ltd',
            ],
        ];
    }

    public function processRow(array $row, int $rowNumber): ?array
    {
        // Validate the row first
        $validatedData = $this->validateRow($row, $rowNumber);

        if ($validatedData === false) {
            return null;
        }

        try {
            // Find company
            $company = null;
            if (!empty($validatedData['company_name'])) {
                $company = Company::where('name', $validatedData['company_name'])->first();
                if (!$company) {
                    $this->addError("Row {$rowNumber}: Company '{$validatedData['company_name']}' not found");
                    return null;
                }
            } else {
                // Use current user's company
                $company = Company::find($this->getCurrentCompanyId());
                if (!$company) {
                    $this->addError("Row {$rowNumber}: No company context available");
                    return null;
                }
            }

            // Check for duplicate email
            $existingUser = User::where('email', $validatedData['email'])->first();
            if ($existingUser) {
                $this->addError("Row {$rowNumber}: User with email '{$validatedData['email']}' already exists");
                return null;
            }

            // Prepare data for storage
            $data = [
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'password' => $validatedData['password'] ? Hash::make($validatedData['password']) : Hash::make('TempPassword123!'),
                'is_active' => $validatedData['is_active'] ?? true,
                'company_id' => $company->id,
                'current_company_id' => $company->id,
                'created_by' => $this->getCurrentUserId(),
                'edited_by' => $this->getCurrentUserId(),
            ];

            // Add additional fields if they exist in the User model
            if (method_exists(User::class, 'setAttribute')) {
                $additionalFields = [
                    'role' => $validatedData['role'],
                    'department' => $validatedData['department'],
                    'job_title' => $validatedData['job_title'],
                ];

                foreach ($additionalFields as $field => $value) {
                    if (!empty($value)) {
                        $data[$field] = $value;
                    }
                }
            }

            return $data;
        } catch (\Exception $e) {
            $this->addError("Row {$rowNumber}: " . $e->getMessage());
            return null;
        }
    }

    public function getModelClass(): string
    {
        return User::class;
    }

    public function getModuleName(): string
    {
        return 'User';
    }

    public function getValidationMessages(): array
    {
        return array_merge(parent::getValidationMessages(), [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'A user with this email already exists.',
            'password.min' => 'Password must be at least 8 characters.',
        ]);
    }

    public function getMaxRows(): int
    {
        return 100; // Lower limit for users due to security considerations
    }

    public function shouldProcessAsync(): bool
    {
        return true; // Always process users asynchronously due to security
    }
}
