<?php

$urls = [
    'https://disdukcapil.bandung.go.id/peta',
    'https://disdukcapil.bandung.go.id/data-demografi/pertumbuhan-penduduk',
    'https://disdukcapil.bandung.go.id/data-demografi/agama',
    'https://disdukcapil.bandung.go.id/data-demografi/golongan-darah',
    'https://disdukcapil.bandung.go.id/data-demografi/jenis-kelamin',
    'https://disdukcapil.bandung.go.id/data-demografi/kepala-keluarga',
    'https://disdukcapil.bandung.go.id/data-demografi/pendidikan-terakhir',
    'https://disdukcapil.bandung.go.id/data-demografi/jenis-pekerjaan',
    'https://disdukcapil.bandung.go.id/data-demografi/status-perkawinan',
];

foreach ($urls as $u) {
    echo ">>> Running scrape for: {$u}\n";
    passthru("php artisan scrape:diskominfo --url=\"{$u}\"");
    echo "\n";
}

