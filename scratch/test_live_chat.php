<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Ai\Agents\ChatAgent;
use App\Services\RagService;

$rag = new RagService();
$question = "Berapa jumlah penduduk Kota Bandung berdasarkan agama?";

echo "Testing Live Chat for: '{$question}'\n\n";

$context = $rag->retrieveContext($question, 3);
echo "Context Preview:\n" . substr($context, 0, 300) . "...\n\n";

$agent = new ChatAgent($context);
try {
    $response = $agent->prompt($question);
    echo "=== AI REPLY ===\n";
    echo (string) $response . "\n";
} catch (\Throwable $e) {
    echo "Error prompting AI: " . $e->getMessage() . "\n";
}

