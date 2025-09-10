<?php

namespace App\Imports\Banking;

use App\Models\Finance\BankStatement;
use App\Models\FinanceBankStatement;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class BankStatementImportQueue implements ToModel, WithHeadingRow, WithChunkReading
{
    protected int $accountId;
    protected int $companyId;
    protected int $createdBy;

    public function __construct(int $accountId, int $companyId, int $createdBy)
    {
        $this->accountId = $accountId;
        $this->companyId = $companyId;
        $this->createdBy = $createdBy;
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

    protected function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Date::excelToDateTimeObject($value)->format('Y-m-d');
            }

            $formats = ['d/m/Y', 'd/n/Y', 'd/m/y', 'd/n/y', 'd-M-Y', 'd M Y', 'Y-m-d'];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, trim($value));
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function model(array $row)
    {
        $row = $this->normalizeRow($row);

        $transaction_date = $this->parseDate($row['transaction_date'] ?? null);
        $value_date = $this->parseDate($row['value_date'] ?? null);
        $referenceID = $row['referenceID'] ?? null;
        $description = $row['description'] ?? null;
        $withdrawals = (float)($row['withdrawals'] ?? 0);
        $lodgments = (float)($row['lodgments'] ?? 0);
        $balance = (float)($row['balance'] ?? 0);

        if (empty($transaction_date) || (empty($withdrawals) && empty($lodgments))) {
            return null;
        }

        $existing = FinanceBankStatement::where('account_id', $this->accountId)
            ->where('transaction_date', $transaction_date)
            ->where('referenceID', $referenceID)
            ->where('withdrawals', $withdrawals)
            ->where('lodgments', $lodgments)
            ->exists();

        if ($existing) {
            return null;
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
            'company_id'       => $this->companyId,
            'created_by'       => $this->createdBy,
        ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}