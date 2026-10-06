<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\RagService;

$rag = new RagService();
$query = "Berapa jumlah penduduk Kota Bandung berdasarkan agama?";
echo "Testing RAG search for: '$query'\n\n";

$results = $rag->search($query, 6);
foreach ($results as $i => $item) {
    echo "Rank " . ($i + 1) . ": [ID: {$item->id}] {$item->topic}\n";
    echo "  Score: {$item->relevance_score}\n";
    echo "  URL: {$item->url}\n";
    echo "  Snippet: " . substr(strip_tags($item->content), 0, 150) . "...\n\n";
}

