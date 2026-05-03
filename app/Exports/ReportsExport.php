<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $reports;

    public function __construct($reports)
    {
        $this->reports = $reports;
    }

    public function collection()
    {
        return $this->reports;
    }

    // Mapping kolom yang ingin dimunculkan di Excel
    public function map($report): array
    {
        return [
            $report->case_id,
            $report->user->nama_lengkap ?? 'Anonymous',
            $report->messages->first()->message ?? '-',
            $report->latest_category ?? 'Umum',
            $report->updated_at->format('d/m/Y H:i'),
        ];
    }

    // Header tabel Excel
    public function headings(): array
    {
        return [
            'ID Kasus',
            'Pengirim',
            'Pesan Terakhir',
            'Kategori Risiko',
            'Waktu Update',
        ];
    }
}
