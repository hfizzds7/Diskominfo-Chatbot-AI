<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KnowledgeBase;

echo "=== Verifikasi Data Disdukcapil di Knowledge Base ===\n\n";

$disdukcapilRecords = KnowledgeBase::where('url', 'like', '%disdukcapil.bandung.go.id%')->get();
echo "Total Disdukcapil records: " . $disdukcapilRecords->count() . "\n";
foreach ($disdukcapilRecords as $r) {
    echo "ID: {$r->id}\n";
    echo "URL: {$r->url}\n";
    echo "Topic: {$r->topic}\n";
    echo "Cluster: {$r->cluster}\n";
    echo "Content Length: " . strlen($r->content) . "\n";
    echo "Content Preview:\n" . substr($r->content, 0, 250) . "...\n";
    echo "--------------------------------------------------\n";
}

echo "\n=== Verifikasi OpenData Agama Dataset Terbaru ===\n";
$openDataRecord = KnowledgeBase::where('url', 'like', '%jumlah-penduduk-kota-bandung-berdasarkan-agama-4%')->first();
if ($openDataRecord) {
    echo "ID: {$openDataRecord->id}\n";
    echo "Topic: {$openDataRecord->topic}\n";
    echo "Content Preview (contains 2025?):\n";
    // Check if 2025 is in content
    echo "Contains 2025? " . (str_contains($openDataRecord->content, '2025') ? 'YES' : 'NO') . "\n";
    echo substr($openDataRecord->content, 0, 400) . "...\n";
}

