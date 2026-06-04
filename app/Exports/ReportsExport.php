<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    protected $messages;

    public function __construct($messages)
    {
        // Data yang diterima sekarang adalah kumpulan pesan (Collection of Messages)
        $this->messages = $messages;
    }

    public function collection()
    {
        return $this->messages;
    }

    // Mapping kolom yang ingin dimunculkan di Excel per baris
    public function map($message): array
    {
        // Rapihkan penamaan label pengirim
        $roleLabel = 'Warga';
        if ($message['role'] === 'ai') {
            $roleLabel = 'SafeTalk AI';
        } elseif ($message['role'] === 'admin') {
            $roleLabel = 'Admin DP3A';
        }

        return [
            $message['case_id'],
            $message['kategori'],
            $message['nama_warga'],
            $roleLabel,
            $message['pesan'],
            $message['waktu'],
        ];
    }

    // Header tabel Excel
    public function headings(): array
    {
        return [
            'ID Kasus',
            'Kategori Risiko',
            'Nama Pelapor',
            'Pengirim Pesan',
            'Isi Teks / Obrolan',
            'Waktu Dikirim',
        ];
    }

    // Memberikan style tebal (Bold) pada baris pertama (Header)
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
