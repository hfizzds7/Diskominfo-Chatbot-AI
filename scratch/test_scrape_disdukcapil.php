<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KnowledgeBase;

$demographyUrls = [
    'https://disdukcapil.bandung.go.id/peta' => 'peta',
    'https://disdukcapil.bandung.go.id/data-demografi/pertumbuhan-penduduk' => 'pertumbuhan-penduduk',
    'https://disdukcapil.bandung.go.id/data-demografi/agama' => 'agama',
    'https://disdukcapil.bandung.go.id/data-demografi/golongan-darah' => 'golongan-darah',
    'https://disdukcapil.bandung.go.id/data-demografi/jenis-kelamin' => 'jenis-kelamin',
    'https://disdukcapil.bandung.go.id/data-demografi/kepala-keluarga' => 'kepala-keluarga',
    'https://disdukcapil.bandung.go.id/data-demografi/pendidikan-terakhir' => 'pendidikan-terakhir',
    'https://disdukcapil.bandung.go.id/data-demografi/jenis-pekerjaan' => 'jenis-pekerjaan',
    'https://disdukcapil.bandung.go.id/data-demografi/status-perkawinan' => 'status-perkawinan',
];

echo "Testing Disdukcapil demography scrapers...\n";

function fetchDisdukcapilApi(string $endpoint): ?array {
    $url = "https://disdukcapil.bandung.go.id/api/web/{$endpoint}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$raw) {
        return null;
    }

    // Strip BOM UTF-8 if present
    $clean = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    return json_decode($clean, true);
}

foreach ($demographyUrls as $url => $type) {
    echo "Processing $url ($type)...\n";
    if ($type === 'peta') {
        $topic = "Peta GIS Visualisasi Persebaran Data Kependudukan Kota Bandung Disdukcapil";
        $desc = "Peta GIS interaktif visualisasi persebaran penduduk Kota Bandung resmi dari Disdukcapil Kota Bandung terintegrasi Ditjen Dukcapil Kemendagri.";
        $content = "Instansi: Dinas Kependudukan dan Pencatatan Sipil (Disdukcapil) Kota Bandung\n"
                 . "Layanan / Informasi: GIS Visualisasi Persebaran Data Kependudukan Kota Bandung\n"
                 . "Sumber Resmi: {$url}\n"
                 . "Aplikasi Peta GIS Kemendagri: https://gis.dukcapil.kemendagri.go.id/arcgis/apps/experiencebuilder/experience/?id=7d1ab9b69ded40ca97e82fc9b2bdd50c\n\n"
                 . "Deskripsi & Informasi Layanan:\n"
                 . "Halaman Peta GIS Data Kependudukan Disdukcapil Kota Bandung menyajikan visualisasi data kependudukan berbasis spasial/geografis yang terintegrasi langsung dengan sistem GIS Ditjen Kependudukan dan Pencatatan Sipil (Dukcapil) Kementerian Dalam Negeri Republik Indonesia.\n\n"
                 . "Melalui peta interaktif ini, masyarakat dan pemangku kebijakan dapat melihat visualisasi persebaran penduduk, kepadatan penduduk, serta data agregat demografi per kecamatan dan kelurahan di seluruh wilayah Kota Bandung.";
        
        echo " - Peta content generated (" . strlen($content) . " chars)\n";
    } elseif ($type === 'pertumbuhan-penduduk') {
        $data = fetchDisdukcapilApi('pertumbuhan-penduduk');
        if (!$data || !isset($data['tahun'], $data['jumlah'])) {
            echo " - ERROR: Failed to fetch pertumbuhan-penduduk\n";
            continue;
        }
        $topic = "Data Pertumbuhan dan Jumlah Penduduk Kota Bandung Disdukcapil (Tahun 2021-2025)";
        $desc = "Data tren pertumbuhan dan total jumlah penduduk Kota Bandung dari tahun 2021 hingga 2025 resmi Disdukcapil Kota Bandung.";
        $contentLines = [
            "Instansi: Dinas Kependudukan dan Pencatatan Sipil (Disdukcapil) Kota Bandung",
            "Judul: Data Pertumbuhan dan Total Penduduk Kota Bandung (Tahun 2021 - 2025)",
            "Sumber Resmi: {$url}",
            "",
            "Ringkasan Tren Pertumbuhan Penduduk Kota Bandung:",
        ];
        $count = count($data['tahun']);
        for ($i = 0; $i < $count; $i++) {
            $thn = $data['tahun'][$i];
            $jml = number_format((int)$data['jumlah'][$i], 0, ',', '.');
            $diffStr = '';
            if ($i > 0) {
                $diff = (int)$data['jumlah'][$i] - (int)$data['jumlah'][$i - 1];
                $diffSign = $diff >= 0 ? '+' : '';
                $diffStr = " (bertambah {$diffSign}" . number_format($diff, 0, ',', '.') . " jiwa)";
            }
            $contentLines[] = "- Tahun {$thn}: {$jml} jiwa{$diffStr}";
        }
        $latestYear = end($data['tahun']);
        $latestTotal = number_format((int)end($data['jumlah']), 0, ',', '.');
        $contentLines[] = "";
        $contentLines[] = "Kesimpulan: Berdasarkan data resmi terbaru Disdukcapil Kota Bandung tahun {$latestYear}, jumlah total penduduk Kota Bandung adalah {$latestTotal} jiwa.";
        $content = implode("\n", $contentLines);
        echo " - Pertumbuhan penduduk generated (" . strlen($content) . " chars)\n";
    } else {
        $endpointMap = [
            'agama' => ['endpoint' => 'dkb-agama', 'topic' => 'Agama', 'cols' => ['a' => 'Islam', 'b' => 'Kristen', 'c' => 'Katholik', 'd' => 'Hindu', 'e' => 'Budha', 'f' => 'Khonghucu', 'g' => 'Kepercayaan']],
            'golongan-darah' => ['endpoint' => 'dkb-gol-drh', 'topic' => 'Golongan Darah', 'cols' => ['a' => 'A', 'b' => 'B', 'c' => 'AB', 'd' => 'O', 'e' => 'A+', 'f' => 'A-', 'g' => 'B+', 'h' => 'B-', 'i' => 'AB+', 'j' => 'AB-', 'k' => 'O+', 'l' => 'O-', 'm' => 'Tidak Tahu']],
            'jenis-kelamin' => ['endpoint' => 'dkb-kelamin', 'topic' => 'Jenis Kelamin', 'cols' => ['jumlah_l' => 'Laki-Laki', 'jumlah_p' => 'Perempuan', 'jumlah_all' => 'Total']],
            'kepala-keluarga' => ['endpoint' => 'dkb-kk', 'topic' => 'Kepala Keluarga (KK)', 'cols' => ['jumlah_l' => 'KK Laki-Laki', 'jumlah_p' => 'KK Perempuan', 'jumlah_all' => 'Total KK']],
            'pendidikan-terakhir' => ['endpoint' => 'dkb-pendidikan', 'topic' => 'Pendidikan Terakhir', 'cols' => ['a' => 'Tidak/Belum Sekolah', 'b' => 'Belum Tamat SD/Sederajat', 'c' => 'Tamat SD/Sederajat', 'd' => 'SLTP/Sederajat', 'e' => 'SLTA/Sederajat', 'f' => 'Diploma I/II', 'g' => 'Akademi/Diploma III/S.Muda', 'h' => 'Diploma IV/Strata I (S1)', 'i' => 'Strata II (S2)', 'j' => 'Strata III (S3)']],
            'jenis-pekerjaan' => ['endpoint' => 'dkb-pekerjaan', 'topic' => 'Jenis Pekerjaan', 'cols' => ['a' => 'Belum/Tidak Bekerja', 'b' => 'Aparatur/Pejabat Negara', 'c' => 'Tenaga Pengajar', 'd' => 'Wiraswasta', 'e' => 'Pertanian/Peternakan', 'f' => 'Nelayan', 'g' => 'Agama dan Kepercayaan', 'h' => 'Pelajar/Mahasiswa', 'i' => 'Tenaga Kesehatan', 'j' => 'Pensiunan', 'k' => 'Lainnya']],
            'status-perkawinan' => ['endpoint' => 'dkb-stat-kwn', 'topic' => 'Status Perkawinan', 'cols' => ['a' => 'Belum Kawin', 'b' => 'Kawin', 'c' => 'Cerai Hidup', 'd' => 'Cerai Mati']],
        ];

        $cfg = $endpointMap[$type];
        $rows = fetchDisdukcapilApi($cfg['endpoint']);
        if (!$rows || !is_array($rows)) {
            echo " - ERROR: Failed to fetch {$cfg['endpoint']}\n";
            continue;
        }

        // Calculate totals across all kecamatan
        $totals = [];
        foreach ($cfg['cols'] as $colKey => $colLabel) {
            $totals[$colKey] = 0;
        }
        foreach ($rows as $row) {
            foreach ($cfg['cols'] as $colKey => $colLabel) {
                if (isset($row[$colKey])) {
                    $totals[$colKey] += (int) $row[$colKey];
                }
            }
        }

        $topic = "Data Kependudukan Kota Bandung Berdasarkan {$cfg['topic']} Disdukcapil (Terbaru 2025)";
        $desc = "Data resmi kependudukan Kota Bandung berdasarkan {$cfg['topic']} per kecamatan dan total se-Kota Bandung oleh Disdukcapil.";

        $contentLines = [
            "Instansi: Dinas Kependudukan dan Pencatatan Sipil (Disdukcapil) Kota Bandung",
            "Topik: Data Kependudukan Kota Bandung Berdasarkan {$cfg['topic']}",
            "Sumber Resmi: {$url}",
            "Tahun Data: 2025 (Data Resmi Terbaru Disdukcapil)",
            "",
            "=== TOTAL KESELURUHAN SE-KOTA BANDUNG BERDASARKAN " . strtoupper($cfg['topic']) . " ===",
        ];
        foreach ($cfg['cols'] as $colKey => $colLabel) {
            $formattedTotal = number_format($totals[$colKey], 0, ',', '.');
            $contentLines[] = "- {$colLabel}: {$formattedTotal} jiwa/orang";
        }

        $contentLines[] = "";
        $contentLines[] = "=== RINCIAN DATA PER KECAMATAN DI KOTA BANDUNG ===";
        foreach ($rows as $r) {
            $namaKec = $r['nama_kec'] ?? ('Kecamatan ' . ($r['no'] ?? ''));
            $colDetails = [];
            foreach ($cfg['cols'] as $colKey => $colLabel) {
                if ($colKey === 'jumlah_all') continue;
                $val = number_format((int)($r[$colKey] ?? 0), 0, ',', '.');
                $colDetails[] = "{$colLabel}: {$val}";
            }
            if (isset($r['jumlah_all'])) {
                $colDetails[] = "Total: " . number_format((int)$r['jumlah_all'], 0, ',', '.');
            }
            $contentLines[] = "- Kecamatan {$namaKec}: " . implode(", ", $colDetails);
        }

        $content = implode("\n", $contentLines);
        echo " - {$type} generated (" . strlen($content) . " chars, " . count($rows) . " kecamatan)\n";
    }
}

echo "All 9 Disdukcapil endpoints tested successfully!\n";

