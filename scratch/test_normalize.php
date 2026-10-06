<?php

function normalizeTopic(string $topic): string
{
    $clean = mb_strtolower($topic);
    $clean = preg_replace('/\s*[\(\[]\s*(?:bagian|part)\s+\d+\s*[\)\]]/iu', '', $clean);
    $clean = preg_replace('/\s*-\s*(?:bagian|part)\s+\d+/iu', '', $clean);
    $clean = preg_replace('/\s*[\(\[]\s*(?:terbaru|tahun)\s+[0-9\-\s]+[\)\]]/iu', '', $clean);
    $clean = preg_replace('/\s*-\s*\d+$/u', '', $clean);
    $clean = preg_replace('/\bdisdukcapil\b/iu', '', $clean);
    $clean = preg_replace('/\bdata\b/iu', '', $clean);
    $clean = preg_replace('/\bjumlah\b/iu', '', $clean);
    $clean = preg_replace('/\bkependudukan\b/iu', 'penduduk', $clean);
    $clean = preg_replace('/\blaju\b/iu', '', $clean);
    $clean = preg_replace('/\bdan\b/iu', '', $clean);
    $clean = preg_replace('/\s+/u', ' ', $clean);
    return trim($clean);
}

$samples = [
    'Jumlah Penduduk Kota Bandung Berdasarkan Agama (Bagian 1)',
    'Jumlah Penduduk Kota Bandung Berdasarkan Agama',
    'Data Kependudukan Kota Bandung Berdasarkan Agama Disdukcapil (Terbaru 2025)',
    'Data Pertumbuhan dan Jumlah Penduduk Kota Bandung Disdukcapil (Tahun 2021-2025)',
    'Laju Pertumbuhan Penduduk Kota Bandung',
    'Data Kependudukan Kota Bandung Berdasarkan Jenis Kelamin Disdukcapil (Terbaru 2025)',
    'Jumlah Penduduk Kota Bandung Berdasarkan Jenis Kelamin - 3',
    'Data Kependudukan Kota Bandung Berdasarkan Golongan Darah Disdukcapil (Terbaru 2025)',
    'Jumlah Penduduk Kota Bandung Berdasarkan Golongan Darah - 3',
    'Data Kependudukan Kota Bandung Berdasarkan Pendidikan Terakhir Disdukcapil (Terbaru 2025)',
    'Jumlah Penduduk Kota Bandung Berdasarkan Jenis Pendidikan - 2',
    'Data Kependudukan Kota Bandung Berdasarkan Status Perkawinan Disdukcapil (Terbaru 2025)',
    'Jumlah Penduduk Kota Bandung Berdasarkan Status Kawin',
];

echo "Testing enhanced normalizeTopic:\n";
foreach ($samples as $s) {
    echo sprintf("%-75s => %s\n", "'{$s}'", "'" . normalizeTopic($s) . "'");
}

