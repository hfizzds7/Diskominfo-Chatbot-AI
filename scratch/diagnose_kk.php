<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KnowledgeBase;
use App\Services\RagService;

$rag = new RagService();
$q = "jumlah kepala keluarga di kota bandung";

$res = $rag->search($q, 5);
foreach ($res as $item) {
    echo "ID: {$item->id} | Score: {$item->relevance_score} | Topic: {$item->topic}\n";
    echo "  URL: {$item->url}\n";
}

