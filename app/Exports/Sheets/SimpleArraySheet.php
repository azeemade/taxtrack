<?php

// app/Exports/Sheets/SimpleArraySheet.php
namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class SimpleArraySheet implements FromArray, WithTitle
{
    public function __construct(private string $title, private array $rows) {}
    public function array(): array { return $this->rows; }
    public function title(): string { return $this->title; }
}
