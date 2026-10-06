<?php

$ctx = stream_context_create([
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    'http' => ['header' => "User-Agent: Mozilla/5.0\r\n"]
]);

// Test Agama dataset
$slug = 'jumlah-penduduk-kota-bandung-berdasarkan-agama-4';
$metaRes = file_get_contents("https://opendata.bandung.go.id/api/datasets/{$slug}", false, $ctx);
$meta = json_decode($metaRes, true)['data'] ?? null;
$schema = $meta['schema'];
$table = $meta['table'];

echo "Schema: $schema, Table: $table\n";
$big1 = file_get_contents("https://opendata.bandung.go.id/api/bigdata/{$schema}/{$table}", false, $ctx);
$j1 = json_decode($big1, true);
$pag = $j1['pagination'] ?? [];
echo "Page 1 pagination: " . json_encode($pag) . "\n";
echo "Page 1 sample row year: " . ($j1['data'][0]['tahun'] ?? 'none') . "\n";

$lastPage = $pag['total_page'] ?? 1;
if ($lastPage > 1) {
    $bigLast = file_get_contents("https://opendata.bandung.go.id/api/bigdata/{$schema}/{$table}?page={$lastPage}", false, $ctx);
    $jLast = json_decode($bigLast, true);
    echo "Last page ($lastPage) row count: " . count($jLast['data'] ?? []) . "\n";
    echo "Last page sample row: " . json_encode($jLast['data'][0] ?? []) . "\n";
    $lastRow = end($jLast['data']);
    echo "Last page last row: " . json_encode($lastRow) . "\n";
}

