<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\RagService;

$rag = new RagService();

$queries = [
    'Berapa jumlah penduduk Kota Bandung berdasarkan agama?',
    'pertumbuhan penduduk kota bandung',
    'jumlah penduduk berdasarkan jenis kelamin',
    'jumlah penduduk berdasarkan golongan darah',
    'jumlah kepala keluarga di kota bandung',
    'pendidikan terakhir penduduk kota bandung',
    'mata pencaharian atau jenis pekerjaan penduduk kota bandung',
    'status perkawinan warga bandung',
    'peta persebaran kependudukan kota bandung',
    'jumlah penduduk lansia di kecamatan cicendo', // fallback test for OpenData
];

foreach ($queries as $q) {
    echo "======================================================================\n";
    echo "QUERY: {$q}\n";
    echo "======================================================================\n";
    $results = $rag->search($q, 3);
    foreach ($results as $i => $item) {
        $rank = $i + 1;
        $url = $item->url ?: '(NO URL / LEGACY)';
        echo "Rank {$rank}: [Score: {$item->relevance_score}] [ID: {$item->id}]\n";
        echo "  Topic: {$item->topic}\n";
        echo "  URL: {$url}\n";
        echo "  Snippet: " . substr(strip_tags($item->content), 0, 160) . "...\n";
    }
    echo "\n";
}

