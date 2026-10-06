<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Ai\Agents\ChatAgent;
use App\Services\RagService;

$rag = new RagService();

$testQuestions = [
    'Berapa jumlah penduduk Kota Bandung berdasarkan agama?',
    'Berapa pertumbuhan penduduk Kota Bandung?',
    'Berapa jumlah kepala keluarga di Kota Bandung?',
];

foreach ($testQuestions as $q) {
    echo "======================================================================\n";
    echo "QUESTION: {$q}\n";
    echo "======================================================================\n";

    $context = $rag->retrieveContext($q, 3);
    $agent = new ChatAgent($context);
    $prompt = $agent->instructions();

    echo "Instructions Length: " . strlen($prompt) . " characters\n";
    
    // Verify that Disdukcapil URL is present in the prompt
    $hasDisdukcapil = str_contains($prompt, 'disdukcapil.bandung.go.id');
    echo "Contains Disdukcapil URL? " . ($hasDisdukcapil ? 'YES (VALID)' : 'NO') . "\n";

    // Verify that 2025 is present in the prompt
    $has2025 = str_contains($prompt, '2025');
    echo "Contains Year 2025? " . ($has2025 ? 'YES (VALID)' : 'NO') . "\n";

    // Check first 500 chars of instructions
    echo "Instructions Preview:\n" . substr($prompt, 0, 400) . "...\n\n";
}

