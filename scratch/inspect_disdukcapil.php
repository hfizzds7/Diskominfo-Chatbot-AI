<?php

$chunks = [
    'golongan-darah' => 'https://disdukcapil.bandung.go.id/assets/golongan-darah.e9e115d5.js',
    'pendidikan-terakhir' => 'https://disdukcapil.bandung.go.id/assets/pendidikan-terakhir.3e8918de.js',
    'jenis-pekerjaan' => 'https://disdukcapil.bandung.go.id/assets/jenis-pekerjaan.b3fc73a7.js',
    'status-perkawinan' => 'https://disdukcapil.bandung.go.id/assets/status-perkawinan.6a14d5d8.js',
];

$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);

foreach ($chunks as $name => $url) {
    echo "========================================\n";
    echo "CHUNK: $name\n";
    $content = file_get_contents($url, false, $ctx);
    $pos = strpos($content, 'columns =');
    if ($pos !== false) {
        $sub = substr($content, $pos, 2500);
        // Extract all key: { label: "..." }
        preg_match_all('/([a-zA-Z0-9_]+):\s*\{\s*label:\s*["\']([^"\']+)["\']/i', $sub, $matches);
        for ($i = 0; $i < count($matches[0]); $i++) {
            echo "  " . $matches[1][$i] . " => " . $matches[2][$i] . "\n";
        }
    }
}

