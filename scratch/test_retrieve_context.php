<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\RagService;

$rag = new RagService();
$context = $rag->retrieveContext("Berapa jumlah penduduk Kota Bandung berdasarkan agama?", 2);

echo "Formatted Context Length: " . strlen($context) . " characters\n";
echo "=== Context Preview ===\n";
echo substr($context, 0, 1500) . "...\n";

