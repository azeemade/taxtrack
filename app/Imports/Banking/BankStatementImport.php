<?php

namespace App\Imports\Banking;

use App\Models\FinanceBankStatement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class BankStatementImport implements ToModel, WithHeadingRow, WithChunkReading
{
    protected int $accountId;
    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
    }

    protected array $map = [
        'transaction_date' => ['transaction_date', 'date', 'posting_date', 'value_date'],
        'referenceID'      => ['reference_id', 'ref', 'reference'],
        'description'      => ['description', 'narration', 'details'],
        'withdrawals'      => ['withdrawals', 'withdrawal', 'debit', 'dr'],
        'lodgments'        => ['lodgments', 'logdment', 'credit', 'cr'],
        'balance'          => ['balance', 'running_balance', 'closing_balance'],
        'value_date'       => ['value_date', 'val_date', 'posting_date'],
    ];

    /**
     * Normalize row keys dynamically (map synonyms to expected keys)
     */
    protected function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($this->map as $key => $possibleNames) {
            foreach ($possibleNames as $name) {
                if (isset($row[$name]) && $row[$name] !== null) {
                    $normalized[$key] = $row[$name];
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * Handle multiple date formats + Excel numeric dates
     */
    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            // Case 1: Excel serial number
            if (is_numeric($value)) {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            }

            // Case 2: Try known formats
            $formats = ['d/m/Y', 'd/n/Y', 'd/m/y', 'd/n/y', 'd-M-Y', 'd M Y', 'Y-m-d'];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, trim($value));
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }

            // Case 3: Carbon fallback
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            // Log error if needed
            return null;
        }
    }

    /**
     * Convert each row into a BankStatement model, skipping duplicates
     */
    public function model(array $row)
    {
        $row = $this->normalizeRow($row);

        // Parse dates and amounts
        $transaction_date = $this->parseDate($row['transaction_date'] ?? null);
        $value_date = $this->parseDate($row['value_date'] ?? null);
        $referenceID = $row['referenceID'] ?? null;
        $description = $row['description'] ?? null;
        $withdrawals = (float)($row['withdrawals'] ?? 0);
        $lodgments = (float)($row['lodgments'] ?? 0);
        $balance = (float)($row['balance'] ?? 0);

        // Skip if required fields are missing
        if (empty($transaction_date) || (empty($withdrawals) && empty($lodgments))) {
            return null;
        }

        // Check for duplicate based on key fields (adjust criteria as needed, e.g., add description if unique)
        $existing = FinanceBankStatement::where('account_id', $this->accountId)
            ->where('transaction_date', $transaction_date)
            ->where('referenceID', $referenceID)
            ->where('withdrawals', $withdrawals)
            ->where('lodgments', $lodgments)
            ->exists();

        if ($existing) {
            return null; // Skip insertion if duplicate found
        }

        return new FinanceBankStatement([
            'transaction_date' => $transaction_date,
            'referenceID'      => $referenceID,
            'description'      => $description,
            'value_date'       => $value_date,
            'withdrawals'      => $withdrawals,
            'lodgments'        => $lodgments,
            'balance'          => $balance,
            'account_id'       => $this->accountId,
            'company_id'       => Auth::user()->current_company_id,
            'created_by'       => Auth::id(),
        ]);
    }

    /**
     * Chunk size for reading large files (scalable for large records)
     */
    public function chunkSize(): int
    {
        return 1000; // Process 1000 rows at a time; adjust based on memory/server limits
    }
}