<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use Illuminate\Support\Collection;

class RagService
{
    private const STOP_WORDS = [
        'yang', 'untuk', 'pada', 'ke', 'di', 'dari', 'dan', 'atau', 'ini', 'itu',
        'dengan', 'adalah', 'yaitu', 'yakni', 'sebagai', 'oleh', 'karena', 'agar',
        'supaya', 'akan', 'telah', 'sudah', 'sedang', 'bisa', 'dapat', 'harus',
        'bagaimana', 'apa', 'apakah', 'siapa', 'kapan', 'dimana', 'kenapa', 'mengapa',
        'berapa', 'mana', 'mohon', 'tolong', 'minta', 'tahu', 'mau', 'ingin',
        'saya', 'anda', 'kamu', 'kami', 'kita', 'mereka', 'dia', 'beliau',
        'ada', 'tidak', 'bukan', 'halo', 'hai', 'selamat', 'pagi', 'siang', 'sore',
        'malam', 'terima', 'kasih', 'info', 'informasi', 'layanan', 'kota', 'bandung',
        'siapakah', 'apa', 'apakah', 'dimana', 'bagaimana', 'tolong', 'carikan', 
        'tahu', 'kah', 'informasi', 'mengenai', 'nya', 'kan', 'pun', 'lah', 'deh', 'dong', 'sih', 'an'
    ];

    private const SYNONYMS = [
        'hotel' => ['penginapan', 'akomodasi', 'kamar', 'disbudpar', 'staycation', 'losmen', 'resort'],
        'penginapan' => ['hotel', 'akomodasi', 'kamar', 'disbudpar', 'losmen'],
        'akomodasi' => ['hotel', 'penginapan', 'disbudpar'],
        'wisata' => ['pariwisata', 'destinasi', 'disbudpar', 'rekreasi', 'objek wisata', 'daya tarik', 'taman', 'museum', 'hiburan', 'liburan', 'bermain', 'kebun binatang'],
        'pariwisata' => ['wisata', 'disbudpar', 'destinasi', 'hotel', 'kuliner', 'rekreasi', 'daya tarik'],
        'bermain' => ['wisata', 'rekreasi', 'taman', 'hiburan', 'taman bermain', 'wahana'],
        'liburan' => ['wisata', 'destinasi', 'rekreasi', 'taman', 'objek wisata', 'piknik'],
        'disbudpar' => ['kebudayaan', 'pariwisata', 'hotel', 'wisata', 'kuliner', 'destinasi'],
        'kuliner' => ['makanan', 'disbudpar', 'restoran', 'kafe', 'oleh-oleh'],
        'kebakaran' => ['damkar', 'pemadam', 'dkpb', '113', 'kebencanaan', 'bencana', 'pos wilayah'],
        'damkar' => ['kebakaran', 'pemadam', 'dkpb', 'bencana', '113', 'tawon', 'ular', 'evakuasi'],
        'pemadam' => ['damkar', 'kebakaran', 'dkpb', '113'],
        'tawon' => ['damkar', 'dkpb', 'penyelamatan', 'evakuasi'],
        'ular' => ['damkar', 'dkpb', 'penyelamatan', 'evakuasi'],
        'bencana' => ['dkpb', 'damkar', 'kebencanaan', 'banjir', 'longsor', '112', '113'],
        'ktp' => ['kependudukan', 'disdukcapil', 'identitas', 'e-ktp', 'dukcapil', 'mpp', 'rekam', 'cetak', 'ambon'],
        'disdukcapil' => ['dukcapil', 'kependudukan', 'ambon', 'ktp', 'kk', 'akta', 'catatan sipil', 'salaman'],
        'dukcapil' => ['disdukcapil', 'kependudukan', 'ambon', 'ktp', 'kk', 'akta'],
        'kk' => ['kependudukan', 'kartu keluarga', 'kepala keluarga', 'disdukcapil', 'mpp', 'keluarga'],
        'keluarga' => ['kepala keluarga', 'kartu keluarga', 'kk', 'disdukcapil', 'kependudukan'],
        'darah' => ['golongan darah', 'disdukcapil', 'kependudukan'],
        'kelamin' => ['jenis kelamin', 'laki-laki', 'perempuan', 'disdukcapil', 'kependudukan'],
        'pekerjaan' => ['jenis pekerjaan', 'profesi', 'mata pencaharian', 'disdukcapil', 'kependudukan'],
        'perkawinan' => ['status perkawinan', 'status kawin', 'nikah', 'cerai', 'disdukcapil', 'kependudukan'],
        'peta' => ['gis', 'visualisasi', 'persebaran', 'disdukcapil', 'kependudukan'],
        'akta' => ['kependudukan', 'kelahiran', 'kematian', 'disdukcapil', 'mpp', 'perkawinan'],
        'nik' => ['kependudukan', 'disdukcapil', 'ktp', 'identitas'],
        'kia' => ['kartu identitas anak', 'anak', 'disdukcapil', 'kependudukan'],
        'pindah' => ['skpwni', 'domisili', 'disdukcapil', 'kependudukan', 'surat pindah'],
        'sekolah' => ['pendidikan', 'disdik', 'sd', 'smp', 'sma', 'spmb', 'ppdb', 'zonasi', 'guru', 'siswa'],
        'pendidikan' => ['sekolah', 'disdik', 'guru', 'siswa', 'simdik', 'ppid', 'ppdb', 'zonasi'],
        'ppdb' => ['pendidikan', 'sekolah', 'disdik', 'zonasi', 'afirmasi', 'prestasi', 'sd', 'smp'],
        'guru' => ['pendidikan', 'disdik', 'sekolah', 'tenaga pendidik'],
        'kesehatan' => ['dinkes', 'puskesmas', 'rumah sakit', 'rsud', 'yankes', 'kematian', 'bayi', 'dokter', 'posyandu', 'vaksin', 'obat'],
        'puskesmas' => ['kesehatan', 'dinkes', 'posyandu', 'kematian', 'bayi', 'dokter', 'upt'],
        'dokter' => ['kesehatan', 'dinkes', 'puskesmas', 'faskes', 'tenaga kesehatan', 'medis'],
        'posyandu' => ['kesehatan', 'dinkes', 'balita', 'gizi', 'puskesmas'],
        'bayi' => ['kematian', 'balita', 'puskesmas', 'kesehatan', 'neonatus', 'bblr', 'gizi', 'anak'],
        'kematian' => ['bayi', 'meninggal', 'pasien', 'ibu', 'rsud', 'puskesmas'],
        'penduduk' => ['kependudukan', 'disdukcapil', 'warga', 'laju', 'pertumbuhan', 'kepadatan', 'jumlah', 'agama', 'pekerjaan'],
        'kependudukan' => ['penduduk', 'disdukcapil', 'warga', 'ktp', 'kk', 'akta', 'laju', 'pertumbuhan', 'agama'],
        'stunting' => ['gizi', 'balita', 'kesehatan', 'dinkes', 'puskesmas', 'posyandu'],
        'dbd' => ['kesehatan', 'dinkes', 'demam berdarah', 'puskesmas', 'nyamuk'],
        'tbc' => ['kesehatan', 'dinkes', 'tb paru', 'puskesmas', 'paru'],
        'izin' => ['perizinan', 'dpmptsp', 'mpp', 'oss', 'nib', 'izin usaha', 'pbg', 'reklame'],
        'perizinan' => ['izin', 'dpmptsp', 'mpp', 'oss', 'nib', 'persyaratan'],
        'usaha' => ['perizinan', 'dpmptsp', 'nib', 'umkm', 'oss', 'izin usaha'],
        'nib' => ['perizinan', 'dpmptsp', 'oss', 'nomor induk berusaha', 'izin usaha'],
        'mpp' => ['mal pelayanan publik', 'mall pelayanan publik', 'dpmptsp', 'cianjur', 'pelayanan terpadu', 'gerai', 'investasi'],
        'mal pelayanan publik' => ['mpp', 'dpmptsp', 'cianjur', 'pelayanan terpadu'],
        'mall pelayanan publik' => ['mpp', 'dpmptsp', 'cianjur', 'pelayanan terpadu'],
        'dpmptsp' => ['mpp', 'mal pelayanan publik', 'perizinan', 'cianjur', 'penanaman modal', 'izin'],
        'lapor' => ['pengaduan', 'sp4n', 'aspirasi', 'keluhan', '112', 'call center'],
        'pengaduan' => ['lapor', 'keluhan', 'aspirasi', 'tatacara', '112', 'call center'],
        'darurat' => ['112', 'emergency', 'damkar', '113', 'ambulans', 'bencana'],
        'diskominfo' => ['kominfo', 'simonik', 'ppid', 'wastukancana', 'balai kota', 'komunikasi'],
        'kominfo' => ['diskominfo', 'simonik', 'ppid', 'wastukancana'],
        'simonik' => ['diskominfo', 'ppid', 'balai kota', 'wastukancana', 'informasi publik'],
        'balai kota' => ['simonik', 'wastukancana', 'ppid', 'walikota', 'setda', 'kantor walikota'],
        'balaikota' => ['balai kota', 'simonik', 'wastukancana', 'ppid'],
        'walikota' => ['wali kota', 'pimpinan', 'prokopim', 'erwin', 'pejabat', 'pemimpin', 'wakil walikota'],
        'wali kota' => ['walikota', 'pimpinan', 'prokopim', 'erwin', 'pejabat', 'pemimpin'],
        'pimpinan' => ['walikota', 'wali kota', 'prokopim', 'sekretaris daerah', 'pejabat'],
        'agama' => ['islam', 'kristen', 'katholik', 'hindu', 'budha', 'konghucu', 'penduduk', 'kependudukan'],
        'camat' => ['kecamatan', 'kantor kecamatan', 'pemerintahan', 'direktori'],
        'lurah' => ['kelurahan', 'kantor kelurahan', 'pemerintahan', 'direktori'],
        'kecamatan' => ['camat', 'kantor kecamatan'],
        'kelurahan' => ['lurah', 'kantor kelurahan'],
        'tari' => ['tarian', 'kesenian', 'seni', 'budaya', 'merak', 'jaipong', 'wisata'],
        'tarian' => ['tari', 'kesenian', 'seni', 'budaya', 'merak', 'jaipong', 'wisata'],
        'merak' => ['tari merak', 'tarian', 'kesenian', 'tjetje soemantri', 'wisata'],
        'suling' => ['alat musik', 'kesenian', 'waditra', 'bambu', 'degung', 'sunda', 'wisata'],
        'kesenian' => ['seni', 'budaya', 'tari', 'waditra', 'alat musik', 'tradisional', 'sunda', 'wisata'],
        'budaya' => ['kebudayaan', 'kesenian', 'tradisi', 'adat', 'sunda', 'cagar budaya', 'wisata'],
    ];

    private const CLUSTER_MAP = [
        'Direktori Kota Bandung' => ['camat', 'kecamatan', 'lurah', 'kelurahan', 'kepala bagian', 'sekretariat', 'direktori', 'bagian protokol', 'bagian umum', 'dinas'],
        'Kesehatan' => ['kesehatan', 'dinkes', 'puskesmas', 'rsud', 'rumah sakit', 'dokter', 'bidan', 'perawat', 'obat', 'penyakit', 'vaksin', 'imunisasi', 'stunting', 'gizi', 'dbd', 'malaria', 'tbc', 'hiv', 'posyandu', 'kelahiran', 'kematian bayi', 'neonatus', 'bblr'],
        'Kependudukan' => ['ktp', 'kk', 'kartu keluarga', 'akta', 'nik', 'kia', 'disdukcapil', 'kependudukan', 'penduduk', 'domisili', 'pindah', 'agama', 'pekerjaan', 'lansia', 'wajib ktp'],
        'Pendidikan' => ['sekolah', 'pendidikan', 'disdik', 'sd', 'smp', 'sma', 'smk', 'guru', 'siswa', 'murid', 'ppdb', 'zonasi', 'spmb', 'beasiswa', 'ijazah'],
        'Perizinan' => ['izin', 'perizinan', 'dpmptsp', 'oss', 'nib', 'reklame', 'usaha', 'umkm', 'toko', 'restoran', 'kafe', 'pbg', 'imb', 'trayek'],
        'Dinas Damkar' => ['damkar', 'kebakaran', 'pemadam', 'api', 'tawon', 'ular', 'penyelamatan', 'bencana', 'dkpb', 'diskar', '113', 'evakuasi'],
        'Hotel' => ['hotel', 'penginapan', 'staycation', 'kamar', 'losmen', 'guest house', 'homestay', 'resort', 'akomodasi', 'permalam', 'bintang', 'pullman', 'mercure', 'preanger', 'papandayan', 'lenora', 'raffleshom', 'sheraton', 'west point', 'pasar baru square', 'aston', 'ibis', 'harris', 'padma'],
        'Wisata' => ['wisata', 'destinasi', 'tempat wisata', 'objek wisata', 'rekreasi', 'taman', 'museum', 'heritage', 'disbudpar', 'daya tarik', 'hiburan', 'bermain', 'wahana', 'liburan', 'piknik', 'taman bermain', 'kebun binatang', 'zoo', 'biliar', 'billiard', 'karaoke', 'kolam renang', 'park', 'kiara artha', 'saung', 'angklung', 'udjo', 'alun-alun', 'alun alun', 'monumen', 'gedung merdeka', 'asia afrika', 'seni', 'budaya', 'tari', 'tarian', 'kesenian', 'merak', 'suling', 'jaipong', 'wayang', 'golek', 'gamelan', 'degung', 'calung', 'sisingaan', 'karinding', 'kecapi', 'tradisional', 'sunda'],
        'Kuliner' => ['kuliner', 'makanan', 'kafe', 'cafe', 'resto', 'restoran', 'kulineran', 'jajanan', 'pasar kreatif'],
        'Pelayanan Publik' => ['pelayanan publik', 'layanan publik', 'mpp', 'mal pelayanan publik', 'mall pelayanan publik'],
        'Lainnya' => ['balai kota', 'balaikota', 'walikota', 'wali kota', 'diskominfo', 'kominfo', 'simonik', 'ppid', 'prokopim', '112', 'lapor', 'pengaduan', 'call center']
    ];

    private const MAX_CONTEXT_LENGTH = 60000;
    private const MAX_RECORD_LENGTH = 15000;

    /**
     * Mengambil konteks pengetahuan terformat berdasarkan pesan/pertanyaan pengguna.
     */
    public function retrieveContext(string $query, int $limit = 4): string
    {
        $records = $this->search($query, $limit);

        if ($records->isEmpty()) {
            return "Tidak ditemukan data referensi spesifik dari database untuk pertanyaan ini.\n";
        }

        $keywords = $this->extractKeywords($query);
        $contextParts = [];
        $totalLength = 0;

        foreach ($records as $item) {
            $topic = trim((string) ($item->topic ?? 'Umum'));
            $cluster = trim((string) ($item->cluster ?? 'Umum'));
            $source = '';

            // 1. Ekstraksi Sumber URL secara langsung dari kolom DB
            if (! empty($item->url)) {
                $source = " (Sumber: {$item->url})";
            } else if (! empty($item->keywords)) {
                $kwStr = is_array($item->keywords) ? implode(' ', $item->keywords) : (string) $item->keywords;
                if (str_contains($kwStr, 'source:')) {
                    preg_match('/source:(https?:\/\/[^\s,]+)/', $kwStr, $matches);
                    if (! empty($matches[1])) {
                        $source = " (Sumber: {$matches[1]})";
                    }
                }
            }

            $content = $this->extractPassage((string) $item->content, $keywords, self::MAX_RECORD_LENGTH, $query);

            $section = "--- [Klaster: {$cluster}] {$topic}{$source} ---\n";
            if (! empty($item->description)) {
                $section .= "Deskripsi Singkat: {$item->description}\n";
            }
            $section .= "{$content}\n\n";

            $sectionLength = mb_strlen($section);
            if ($totalLength + $sectionLength > self::MAX_CONTEXT_LENGTH) {
                break;
            }

            $contextParts[] = $section;
            $totalLength += $sectionLength;
        }

        return implode("", $contextParts);
    }

    /**
     * Mengekstrak cuplikan paragraf paling relevan di sekitar kata kunci pengguna.
     * Secara otomatis memusatkan potongan pada entitas spesifik (misal nama Puskesmas/Instansi)
     * agar detail alamat dan jam operasional tidak terpotong di tengah.
     */
    private function extractPassage(string $content, array $keywords, int $maxLength = 2000, string $query = ''): string
    {
        $clean = $this->cleanContent($content);

        if (mb_strlen($clean) <= $maxLength || empty($keywords)) {
            return mb_substr($clean, 0, $maxLength);
        }

        $len = mb_strlen($clean);
        $lowerClean = mb_strtolower($clean);

        // 1. Deteksi entitas spesifik ber-prefix dari query (misal: "hotel lenora", "puskesmas garuda", "mpp kota bandung", "dinas kominfo")
        if (!empty($query) && preg_match('/(?:hotel|penginapan|puskesmas|kecamatan|kelurahan|dinas|kantor|uptd?|mpp|mal pelayanan publik|mall pelayanan publik|balai kota|balaikota|diskominfo|dpmptsp|disdukcapil)\s+([a-z0-9\s]+)/iu', $query, $matches)) {
            $candidateName = trim($matches[1]);
            $candidateName = trim(preg_replace('/\b(di|dan|atau|alamat|jam|operasional|buka|tutup|jadwal|kota|bandung|terkait|info)\b.*/iu', '', $candidateName));
            if (mb_strlen($candidateName) >= 3) {
                $searchPhrases = [
                    $matches[0],
                    $candidateName,
                ];
                $parts = explode(' ', $candidateName);
                if (count($parts) > 1) {
                    $searchPhrases[] = 'puskesmas ' . $parts[0];
                    $searchPhrases[] = $parts[0];
                }

                foreach ($searchPhrases as $sp) {
                    $pos = mb_stripos($lowerClean, $sp);
                    if ($pos !== false) {
                        $startPos = max(0, $pos - 50);
                        if ($startPos + $maxLength > $len) {
                            $startPos = max(0, $len - $maxLength);
                        }
                        $passage = mb_substr($clean, $startPos, $maxLength);
                        if ($startPos > 0) {
                            $passage = '... ' . $passage;
                        }
                        if ($startPos + $maxLength < $len) {
                            $passage .= ' ... [informasi berlanjut]';
                        }
                        return $passage;
                    }
                }
            }
        }

        // 2. Deteksi kata kunci spesifik non-generik (misal nama tempat/hotel tanpa kata generik)
        $genericWords = [
            'alamat', 'lokasi', 'tempat', 'kantor', 'jam', 'operasional', 'jadwal', 
            'waktu', 'buka', 'tutup', 'hari', 'kerja', 'kontak', 'telepon', 'nomor', 'email', 
            'puskesmas', 'kecamatan', 'kelurahan', 'dinas', 'kota', 'bandung', 
            'data', 'informasi', 'layanan', 'daftar', 'berapa', 'siapa', 'dimana',
            'hotel', 'penginapan', 'wisata', 'pariwisata', 'destinasi', 'rekreasi', 'taman', 'museum', 'hiburan'
        ];

        $specificKeywords = array_values(array_filter(
            $keywords, 
            fn($k) => !in_array(mb_strtolower($k), $genericWords) && mb_strlen($k) >= 3
        ));

        if (!empty($specificKeywords)) {
            usort($specificKeywords, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            foreach ($specificKeywords as $skw) {
                $pos = mb_stripos($lowerClean, 'puskesmas ' . $skw);
                if ($pos === false) {
                    $pos = mb_stripos($lowerClean, $skw);
                }
                if ($pos !== false) {
                    $startPos = max(0, $pos - 50);
                    if ($startPos + $maxLength > $len) {
                        $startPos = max(0, $len - $maxLength);
                    }
                    $passage = mb_substr($clean, $startPos, $maxLength);
                    if ($startPos > 0) {
                        $passage = '... ' . $passage;
                    }
                    if ($startPos + $maxLength < $len) {
                        $passage .= ' ... [informasi berlanjut]';
                    }
                    return $passage;
                }
            }
        }

        // 3. Fallback: Algoritma Sliding Window standar dengan pembobotan alamat & jadwal
        $bestPos = 0;
        $maxMatches = -1;
        $step = 150;

        for ($i = 0; $i < $len; $i += $step) {
            $chunk = mb_strtolower(mb_substr($clean, $i, $maxLength));
            $matches = 0;
            foreach ($keywords as $kw) {
                $kwLower = mb_strtolower($kw);
                if (str_contains($chunk, $kwLower)) {
                    $matches += 10;
                    $matches += min(6, substr_count($chunk, $kwLower) * 2);
                }
            }

            if (preg_match('/(jl\.|jalan|no\.|gedung|lantai|alamat|jam|wib|kontak|telp|persyaratan|biaya)/i', $chunk)) {
                $matches += 25;
            }

            if ($matches > $maxMatches) {
                $maxMatches = $matches;
                $bestPos = $i;
            }
        }

        $passage = mb_substr($clean, $bestPos, $maxLength);
        if ($bestPos > 0) {
            $passage = '... ' . $passage;
        }
        if ($bestPos + $maxLength < $len) {
            $passage .= ' ... [informasi berlanjut]';
        }

        return $passage;
    }

    /**
     * Mencari dokumen KnowledgeBase berdasarkan kata kunci relevan dan entitas frasa.
     * Mendukung dekomposisi query multi-entitas (misal: "lokasi mpp dengan diskominfo").
     */
    public function search(string $query, int $limit = 4): Collection
    {
        $subqueries = $this->decomposeQuery($query);
        if (count($subqueries) > 1) {
            $perSubLimit = (int) ceil($limit / count($subqueries)) + 1;
            $subResults = [];
            foreach ($subqueries as $sub) {
                $subResults[] = $this->executeSingleSearch($sub, $perSubLimit);
            }

            $merged = collect();
            $maxCount = max(array_map('count', $subResults));
            for ($i = 0; $i < $maxCount; $i++) {
                foreach ($subResults as $resCol) {
                    if (isset($resCol[$i])) {
                        $item = $resCol[$i];
                        if (!$merged->contains('id', $item->id)) {
                            $merged->push($item);
                        }
                    }
                }
            }
            return $merged->take($limit)->values();
        }

        return $this->executeSingleSearch($query, $limit);
    }

    /**
     * Mendekomposisi pertanyaan pengguna jika terdapat konjungsi multi-entitas
     * seperti 'dan', 'dengan', 'serta', 'vs' agar masing-masing entitas mendapatkan porsi konteks berimbang.
     *
     * @return string[]
     */
    private function decomposeQuery(string $query): array
    {
        $pattern = '/\s+(?:dan|dengan|serta|vs\.?|versus|dibandingkan?\s+(?:dengan)?)\s+/iu';
        if (!preg_match($pattern, $query)) {
            return [$query];
        }

        $parts = preg_split($pattern, $query);
        if (count($parts) < 2) {
            return [$query];
        }

        $intentPrefix = '';
        if (preg_match('/^(?:dimana|di mana|berapa|bagaimana|apa|apakah|lokasi|alamat|jadwal|jam|tarif|kontak|siapa)\s+(?:lokasi|alamat|jadwal|jam|kantor|tarif|kontak|pimpinan)?\s*/iu', $parts[0], $m)) {
            $intentPrefix = trim($m[0]) . ' ';
        }

        $intentSuffix = '';
        $lastIdx = count($parts) - 1;
        if (preg_match('/\s+(?:dimana|di mana|lokasinya\s+dimana|ada\s+dimana|alamatnya|buka\s+jam\s+berapa)$/iu', $parts[$lastIdx], $m)) {
            $intentSuffix = ' ' . trim($m[0]);
        }

        $subqueries = [];
        foreach ($parts as $idx => $part) {
            $clean = trim($part);
            if (empty($clean)) {
                continue;
            }

            if ($idx > 0 && !empty($intentPrefix) && !preg_match('/^(?:dimana|di mana|lokasi|alamat|jam|jadwal|tarif|kontak)/iu', $clean)) {
                $clean = $intentPrefix . $clean;
            }
            if ($idx < $lastIdx && !empty($intentSuffix) && !preg_match('/(?:dimana|di mana|alamat|jam|jadwal)$/iu', $clean)) {
                $clean = $clean . $intentSuffix;
            }

            $subqueries[] = $clean;
        }

        return count($subqueries) >= 2 ? $subqueries : [$query];
    }

    /**
     * Eksekusi pencarian dokumen untuk satu query / entitas.
     */
    private function executeSingleSearch(string $query, int $limit): Collection
    {
        $lowerQuery = strtolower($query);
        $keywords = $this->extractKeywords($query);

        if (empty($keywords)) {
            return KnowledgeBase::query()
                ->whereNotNull('content')
                ->where('content', '!=', '')
                ->inRandomOrder()
                ->limit($limit)
                ->get();
        }

        $knownPhrases = [
            'balai kota', 'balai kota bandung', 'kantor walikota', 'dinas kominfo', 'diskominfo',
            'dinas kesehatan', 'dinas pendidikan', 'dinas damkar', 'diskar pb',
            'disdukcapil kota bandung', 'dinas kependudukan dan pencatatan sipil', 'kantor disdukcapil', 'alamat disdukcapil', 'lokasi disdukcapil', 'disdukcapil',
            'simonik', 'kematian bayi', 'laju pertumbuhan', 'pertumbuhan penduduk',
            'kepadatan penduduk', 'angka kematian', 'mpp kota bandung', 'mall pelayanan publik',
            'mal pelayanan publik', 'mpp', 'dpmptsp',
            'izin usaha', 'akta kelahiran', 'kartu keluarga', 'posyandu', 'vaksin',
            'kepala keluarga', 'golongan darah', 'jenis kelamin', 'pendidikan terakhir',
            'jenis pekerjaan', 'status perkawinan', 'status kawin', 'peta persebaran', 'peta kependudukan'
        ];
        $matchedPhrases = [];
        foreach ($knownPhrases as $kp) {
            if (str_contains($lowerQuery, $kp)) {
                $matchedPhrases[] = $kp;
            }
        }

        $questionWords = ['siapa', 'siapakah', 'apa', 'apakah', 'dimana', 'di mana', 'bagaimana', 'kapan', 'kenapa', 'mengapa', 'tolong', 'minta', 'info', 'informasi', 'nama', 'carikan', 'daftar'];
        $cleanQuery = trim(preg_replace('/\b(' . implode('|', $questionWords) . ')\b/iu', '', $lowerQuery));
        $cleanQuery = trim(preg_replace('/\s+/u', ' ', $cleanQuery));

        $clusterScores = [];
        foreach (self::CLUSTER_MAP as $clusterName => $terms) {
            $count = 0;
            foreach ($terms as $term) {
                if (preg_match('/\b' . preg_quote($term, '/') . '\b/iu', $lowerQuery)) {
                    $count++;
                }
            }
            if ($count > 0) {
                $clusterScores[$clusterName] = $count;
            }
        }
        $detectedCluster = !empty($clusterScores) ? array_keys($clusterScores, max($clusterScores))[0] : null;

        $intentWords = ['alamat', 'lokasi', 'tempat', 'jam', 'operasional', 'jadwal', 'waktu', 'buka', 'tutup', 'kontak', 'nomor', 'telepon', 'email', 'biaya', 'tarif', 'syarat', 'cara', 'rekomendasi', 'daftar', 'pilihan', 'buat', 'membuat', 'bikin', 'ganti', 'hilang', 'rusak', 'ulang', 'urus', 'mengurus', 'pengurusan', 'alur', 'prosedur'];

        $categoryWords = [
            'hotel', 'penginapan', 'losmen', 'staycation', 'resort', 'akomodasi', 'kamar',
            'wisata', 'pariwisata', 'destinasi', 'rekreasi', 'liburan', 'piknik',
            'dinas', 'kantor', 'gedung', 'instansi', 'layanan', 'pelayanan', 'badan', 'bagian',
            'data', 'jumlah', 'tabel', 'statistik', 'informasi', 'portal', 'usaha'
        ];

        // Ekstraksi entitas nama subjek spesifik (misal: "pullman", "mercure", "kiara artha", "papandayan", "saung angklung udjo")
        $specificEntityWords = array_values(array_filter($keywords, function ($w) use ($categoryWords, $intentWords) {
            return !in_array($w, $categoryWords, true)
                && !in_array($w, $intentWords, true)
                && !in_array($w, self::STOP_WORDS, true)
                && mb_strlen($w) >= 3;
        }));
        $hasSpecificEntity = !empty($specificEntityWords);

        $isAskingAddress = (bool) preg_match('/\b(alamat|lokasi|tempat|dimana|di mana|kantor|gedung)\b/i', $lowerQuery);
        $isAskingHours = (bool) preg_match('/\b(jam|operasional|jadwal|waktu|buka|tutup|hari)\b/i', $lowerQuery);
        $isAskingContact = (bool) preg_match('/\b(kontak|telepon|nomor|call|hubungi|email)\b/i', $lowerQuery);
        $isAskingCityLeader = (bool) preg_match('/\b(walikota|wali kota|wakil walikota|wakil wali kota|sekda|sekretaris daerah|prokopim)\b/i', $lowerQuery)
            || (bool) preg_match('/\b(pimpinan|pemimpin|pejabat)\s+(daerah|kota|pemkot|bandung)\b/i', $lowerQuery)
            || (bool) preg_match('/\b(siapa|daftar)\s+(pimpinan|pemimpin)\b/i', $lowerQuery);
        $isAskingHotel = ($detectedCluster === 'Hotel')
            || (bool) preg_match('/\b(hotel|penginapan|losmen|staycation|resort|akomodasi|kamar|permalam)\b/i', $lowerQuery);
        $isAskingWisata = ($detectedCluster === 'Wisata' || $detectedCluster === 'Kuliner')
            || (bool) preg_match('/\b(wisata|destinasi|rekreasi|kuliner|makanan|daya tarik|taman|museum|bermain|liburan|piknik|kebun binatang|zoo|biliar|billiard|karaoke|seni|budaya|tari|tarian|kesenian|alat musik|waditra|merak|suling|jaipong|wayang|golek|calung|angklung)\b/i', $lowerQuery);

        $allSearchWords = $keywords;
        foreach ($keywords as $kw) {
            if (isset(self::SYNONYMS[$kw])) {
                $allSearchWords = array_merge($allSearchWords, self::SYNONYMS[$kw]);
            }
        }
        foreach ($matchedPhrases as $mp) {
            if (isset(self::SYNONYMS[$mp])) {
                $allSearchWords = array_merge($allSearchWords, self::SYNONYMS[$mp]);
            }
        }
        $allSearchWords = array_values(array_unique($allSearchWords));

        $knowledgeQuery = KnowledgeBase::query()
            ->whereNotNull('content')
            ->where('content', '!=', '');

        $knowledgeQuery->where(function ($q) use ($allSearchWords, $matchedPhrases, $query, $cleanQuery, $detectedCluster, $isAskingHotel, $isAskingWisata, $specificEntityWords, $hasSpecificEntity) {
            $q->where('topic', 'LIKE', '%' . $query . '%');

            if (!empty($cleanQuery)) {
                $q->orWhere('topic', 'LIKE', '%' . $cleanQuery . '%')
                  ->orWhere('description', 'LIKE', '%' . $cleanQuery . '%');
            }

            if ($detectedCluster) {
                $q->orWhere('cluster', $detectedCluster);
            }

            if ($isAskingHotel) {
                $q->orWhere('cluster', 'Hotel');
            }

            if ($isAskingWisata) {
                $q->orWhere('cluster', 'Wisata')
                  ->orWhere('cluster', 'Kuliner');
            }

            if ($hasSpecificEntity) {
                foreach ($specificEntityWords as $sew) {
                    $q->orWhere('topic', 'LIKE', '%' . $sew . '%')
                      ->orWhere('content', 'LIKE', '%' . $sew . '%');
                }
            }

            foreach ($matchedPhrases as $phrase) {
                $q->orWhere('topic', 'LIKE', '%' . $phrase . '%')
                  ->orWhere('description', 'LIKE', '%' . $phrase . '%')
                  ->orWhere('content', 'LIKE', '%' . $phrase . '%')
                  ->orWhere('keywords', 'LIKE', '%' . $phrase . '%');
            }

            foreach ($allSearchWords as $word) {
                $q->orWhere('topic', 'LIKE', '%' . $word . '%')
                  ->orWhere('cluster', 'LIKE', '%' . $word . '%')
                  ->orWhere('description', 'LIKE', '%' . $word . '%')
                  ->orWhere('keywords', 'LIKE', '%' . $word . '%')
                  ->orWhere('content', 'LIKE', '%' . $word . '%');
            }
        });

        $candidates = $knowledgeQuery->get();

        if ($candidates->isEmpty() && $detectedCluster) {
            $candidates = KnowledgeBase::where('cluster', $detectedCluster)->take(10)->get();
        }

        if ($candidates->isEmpty()) {
            return collect();
        }

        $scored = $candidates->map(function ($item) use ($keywords, $allSearchWords, $matchedPhrases, $intentWords, $lowerQuery, $cleanQuery, $isAskingAddress, $isAskingHours, $isAskingContact, $isAskingCityLeader, $isAskingHotel, $isAskingWisata, $detectedCluster, $specificEntityWords, $hasSpecificEntity) {
            $score = 0;
            $cluster = strtolower((string) $item->cluster);
            $topic = strtolower((string) $item->topic);
            $description = strtolower((string) ($item->description ?? ''));
            $content = strtolower((string) $item->content);

            // 2. Pembacaan Aman Kolom Keywords (Mendukung Tipe Array/JSON maupun String)
            if (is_array($item->keywords)) {
                $keywordsField = strtolower(implode(' ', $item->keywords));
            } else {
                $keywordsField = strtolower((string) $item->keywords);
            }

            // Prioritas Utama: Kesesuaian Frasa Entitas Spesifik
            foreach ($matchedPhrases as $phrase) {
                if (str_contains($topic, $phrase)) {
                    $score += 600;
                }
                if (str_contains($description, $phrase)) {
                    $score += 500;
                }
                if (str_contains($keywordsField, $phrase)) {
                    $score += 450;
                }
                if (str_contains($content, $phrase)) {
                    $score += 400;
                }
            }

            // Kecocokan Query Penuh atau Clean Query pada Topik & Konten
            if (str_contains($topic, $lowerQuery)) {
                $score += 400;
            } elseif (!empty($cleanQuery) && str_contains($topic, $cleanQuery)) {
                $score += 450;
            }

            if (!empty($cleanQuery) && str_contains($content, $cleanQuery)) {
                $score += 200;
            }

            if ($detectedCluster) {
                if ($cluster === strtolower($detectedCluster)) {
                    $score += 400;
                } elseif (!in_array($cluster, ['pelayanan publik', 'direktori kota bandung', 'pemerintahan', 'lainnya'])) {
                    $score -= 500;
                }
            }

            if ($isAskingAddress) {
                if (preg_match('/\b(jl\.|jalan|no\.|gedung|komplek)\b/i', $content)) {
                    $score += 200;
                }
                if (str_contains($topic, 'kontak') || str_contains($topic, 'lokasi') || str_contains($topic, 'profil') || str_contains($topic, 'prokopim') || str_contains($topic, 'mpp') || str_contains($topic, 'disdukcapil')) {
                    $score += 250;
                }
            }

            if ($isAskingHours) {
                if (preg_match('/\b(senin|selasa|rabu|kamis|jumat|08\.00|08:00|09\.00|wib|s\/d|s\.d\.)\b/i', $content)) {
                    $score += 200;
                }
                if (str_contains($topic, 'kontak') || str_contains($topic, 'jadwal') || str_contains($topic, 'layanan')) {
                    $score += 150;
                }
            }

            if ($isAskingContact) {
                if (preg_match('/\b(telp|telepon|whatsapp|email|call center|hubungi)\b/i', $content)) {
                    $score += 200;
                }
                if (str_contains($topic, 'kontak')) {
                    $score += 200;
                }
            }

            if ($isAskingCityLeader) {
                if (str_contains($topic, 'prokopim') || str_contains($topic, 'profil pimpinan') || str_contains($topic, 'pimpinan')) {
                    $score += 800;
                }
                if (str_contains($content, 'wakil wali kota') || str_contains($content, 'wali kota') || str_contains($content, 'erwin') || str_contains($content, 'sekretaris daerah')) {
                    $score += 400;
                }
                if (str_contains($topic, 'peraturan') || str_contains($topic, 'jdih') || str_contains($topic, 'nomor') || str_contains($topic, 'keputusan') || str_contains($topic, 'sop') || preg_match('/^\d{10,}/', $topic)) {
                    $score -= 700;
                }
            }

            $entityMatched = 0;
            $intentMatched = 0;

            foreach ($keywords as $word) {
                $isIntent = in_array($word, $intentWords, true);
                $weight = $isIntent ? 15 : 60;
                $matched = false;

                if (str_contains($topic, $word)) {
                    $score += ($weight * 3.0);
                    $matched = true;
                }
                if (str_contains($description, $word)) {
                    $score += ($weight * 2.5);
                    $matched = true;
                }
                if (str_contains($keywordsField, $word)) {
                    $score += ($weight * 2.0);
                    $matched = true;
                }
                if (str_contains($cluster, $word)) {
                    $score += ($weight * 1.5);
                    $matched = true;
                }
                if (str_contains($content, $word)) {
                    $score += min(30, substr_count($content, $word) * 4);
                    $matched = true;
                }

                if ($matched) {
                    if ($isIntent) {
                        $intentMatched++;
                    } else {
                        $entityMatched++;
                    }
                }
            }

            foreach ($allSearchWords as $synWord) {
                if (in_array($synWord, $keywords, true)) {
                    continue;
                }
                if (str_contains($cluster, $synWord)) {
                    $score += 20;
                }
                if (str_contains($topic, $synWord)) {
                    $score += 25;
                }
                if (str_contains($description, $synWord)) {
                    $score += 15;
                }
                if (str_contains($content, $synWord)) {
                    $score += 8;
                }
            }

            $score += ($entityMatched * $entityMatched * 50);
            $score += ($intentMatched * 10);

            if (($isAskingAddress || $isAskingHours) && (str_contains($topic, 'peringatan') || str_contains($topic, 'upacara') || str_contains($topic, 'lomba'))) {
                $score -= 300;
            }

            $itemUrl = strtolower((string) ($item->url ?? ''));

            // Penalti untuk dataset tabel statistik OpenData saat pengguna menanyakan alamat/jam/kontak atau alur pelayanan/prosedur
            $isAskingServiceOrProcedure = (bool) preg_match('/\b(cara|ganti|hilang|rusak|bikin|buat|urus|syarat|prosedur|alur|biaya|tarif|pelayanan|layanan|lokasi|alamat|kantor|kontak|jam)\b/i', $lowerQuery);
            $isExplicitlyAskingStats = (bool) preg_match('/\b(jumlah|data|statistik|angka|total|berapa|tabel)\b/i', $lowerQuery);

            if (($isAskingAddress || $isAskingHours || $isAskingContact || $isAskingServiceOrProcedure) && !$isExplicitlyAskingStats) {
                if (str_starts_with($topic, 'jumlah ') || str_contains($itemUrl, 'opendata.bandung.go.id') || str_starts_with($topic, 'rata-rata ') || str_starts_with($topic, 'laju ')) {
                    $score -= 2500;
                }
            }

            // 3. Prioritas Sumber Data Kependudukan (Disdukcapil Utama > OpenData Cadangan > Penalti Dokumen Usang)
            $isDisdukcapil = str_contains($itemUrl, 'disdukcapil.bandung.go.id') || str_contains($topic, 'disdukcapil') || str_contains($content, 'jl. ambon') || str_contains($content, 'disdukcapil.bandung.go.id');
            $isOpenData = str_contains($itemUrl, 'opendata.bandung.go.id');
            $isLegacyNoUrl = empty($item->url);

            $isDemographicQuery = ($detectedCluster === 'Kependudukan')
                || (bool) preg_match('/\b(penduduk|kependudukan|demografi|agama|golongan darah|jenis kelamin|kepala keluarga|kk|kelahiran|kematian|pendidikan|pekerjaan|perkawinan|nik|ktp|kia|pindah|laju|pertumbuhan)\b/i', $lowerQuery);

            if ($isDemographicQuery) {
                if ($isDisdukcapil) {
                    // Disdukcapil = PRIORITAS UTAMA (+1200 poin)
                    $score += 1200;
                } elseif ($isOpenData) {
                    // OpenData = CADANGAN / FALLBACK (+200 poin)
                    $score += 200;
                } elseif ($isLegacyNoUrl) {
                    // Record usang tanpa URL (-500 poin)
                    $score -= 500;
                }
            } else {
                if (!empty($item->url) && str_contains($itemUrl, 'bandung.go.id')) {
                    $score += 200;
                }
            }

            // 4. Novelty / Kebaruan Tahun
            if ($isDemographicQuery || str_contains($topic, 'jumlah') || str_contains($topic, 'data') || str_contains($topic, 'pertumbuhan')) {
                if (str_contains($content, '2026') || str_contains($topic, '2026')) {
                    $score += 300;
                } elseif (str_contains($content, '2025') || str_contains($topic, '2025')) {
                    $score += 250;
                } elseif (str_contains($content, '2024') || str_contains($topic, '2024')) {
                    $score += 150;
                }

                // Penalti record statistik kependudukan yang hanya memuat data usang (2017-2019) tanpa tahun baru
                $hasOldYear = preg_match('/\b(2017|2018|2019)\b/', $content);
                $hasNewYear = preg_match('/\b(2024|2025|2026)\b/', $content);
                if ($hasOldYear && !$hasNewYear) {
                    $score -= 350;
                }
            }

            // 5. Pencocokan Kata Kunci Unik dari Topik ke Query Pengguna (misal "Lenora Hotel" di query "hotel lenora")
            $topicTokens = array_filter(
                preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $topic)),
                fn($w) => mb_strlen($w) >= 3 && !in_array($w, self::STOP_WORDS, true) && !in_array($w, ['kota', 'bandung', 'disbudpar', 'dinas', 'website', 'resmi', 'layanan', 'hotel', 'wisata', 'usaha', 'destinasi', 'daerah', 'katalog', 'data'], true)
            );
            if (!empty($topicTokens)) {
                $matchedTopicTokens = 0;
                foreach ($topicTokens as $token) {
                    if (str_contains($lowerQuery, $token)) {
                        $matchedTopicTokens++;
                    }
                }
                if ($matchedTopicTokens === count($topicTokens)) {
                    $score += 1000;
                } elseif ($matchedTopicTokens > 0) {
                    $score += ($matchedTopicTokens * 350);
                }
            }

            // 6. Pencocokan Entitas Subjek Spesifik (Prioritas Tertinggi untuk Pertanyaan Tempat/Hotel Tertentu)
            if ($hasSpecificEntity) {
                $entityMatchedInTopic = 0;
                $entityMatchedInContent = 0;

                foreach ($specificEntityWords as $sew) {
                    if (str_contains($topic, $sew)) {
                        $entityMatchedInTopic++;
                    }
                    if (str_contains($content, $sew)) {
                        $entityMatchedInContent++;
                    }
                }

                // a) Kecocokan entitas spesifik pada TOPIK (misal "Pullman" pada "Pullman Bandung Grand Central")
                if ($entityMatchedInTopic > 0) {
                    $score += ($entityMatchedInTopic * 2000);
                    if ($entityMatchedInTopic === count($specificEntityWords)) {
                        $score += 2500; // Seluruh kata entitas cocok di topik
                    }
                }

                // b) Kecocokan frase lengkap entitas spesifik pada KONTEN (misal "kiara artha park" atau "saung angklung udjo")
                $entityPhrase = implode(' ', $specificEntityWords);
                if (mb_strlen($entityPhrase) >= 5 && str_contains($content, $entityPhrase)) {
                    $score += 3000;
                } elseif ($entityMatchedInContent > 0) {
                    $score += ($entityMatchedInContent * 600);
                    if ($entityMatchedInContent === count($specificEntityWords)) {
                        $score += 1200;
                    }
                }

                // c) Penalti bagi dokumen yang sama sekali TIDAK memuat entitas spesifik yang dicari
                if ($entityMatchedInTopic === 0 && $entityMatchedInContent === 0) {
                    $score -= 2500;
                }
            }

            // 7. Prioritas Khusus Pertanyaan Hotel / Penginapan
            if ($isAskingHotel) {
                if ($cluster === 'hotel') {
                    $score += 800;
                }
                if (str_contains($itemUrl, 'disbudpar.bandung.go.id') || str_contains($topic, 'disbudpar')) {
                    $score += 400;
                }
                // Hanya beri bonus judul 'hotel' jika pengguna TIDAK sedang mencari hotel spesifik
                if (!$hasSpecificEntity && str_contains($topic, 'hotel')) {
                    $score += 300;
                }
                if (str_contains($content, 'kamar') || str_contains($content, 'fasilitas') || str_contains($content, 'rp.')) {
                    $score += 250;
                }
                // Penalti record junk / gambar / caption / data tes
                if (str_contains($topic, 'banner') || str_contains($topic, 'foto') || str_contains($topic, 'logo') || str_contains($topic, 'hotel tes') || mb_strlen($content) < 100) {
                    $score -= 2500;
                }
                if ($cluster === 'kesehatan' || $cluster === 'dinas damkar' || $cluster === 'kependudukan') {
                    $score -= 700;
                }
            }

            // 8. Prioritas Khusus Pertanyaan Wisata / Kuliner
            if ($isAskingWisata) {
                if ($cluster === 'wisata' || $cluster === 'kuliner') {
                    $score += 600;
                }
                if (str_contains($itemUrl, 'disbudpar.bandung.go.id')) {
                    $score += 350;
                }

                if (!$hasSpecificEntity) {
                    $isGeneralDestinationQuery = (bool) preg_match('/\b(tempat wisata|destinasi|objek wisata|rekreasi|taman|museum|bermain|liburan|piknik|daya tarik|rekomendasi)\b/i', $lowerQuery)
                        && !preg_match('/\b(sewa|rental|bus|elf|biro|travel|agen|tour|paket tour|umroh|konsultan|pemandu|guide)\b/i', $lowerQuery);

                    if ($isGeneralDestinationQuery) {
                        // Prioritaskan destinasi nyata: Daya Tarik (museum & sejarah), Kawasan Pariwisata (taman kota & zoo), Hiburan & Rekreasi
                        if (str_contains($topic, 'daya tarik') || str_contains($topic, 'kawasan pariwisata') || str_contains($topic, 'destinasi wisata')) {
                            $score += 2500;
                        } elseif (str_contains($topic, 'hiburan dan rekreasi')) {
                            $score += 1800;
                        }

                        // Turunkan skor biro perjalanan umroh, sewa bus/elf, dan konsultan pariwisata
                        if (str_contains($topic, 'perjalanan wisata') || str_contains($topic, 'transportasi wisata') || str_contains($topic, 'konsultan') || str_contains($topic, 'pramuwisata')) {
                            $score -= 1800;
                        }
                    }
                }
            }

            $item->relevance_score = $score;
            return $item;
        });

        // Urutkan kandidat berdasarkan skor tertinggi, lalu id record terbaru
        $sorted = $scored->sort(function ($a, $b) {
            if ($a->relevance_score !== $b->relevance_score) {
                return $b->relevance_score <=> $a->relevance_score;
            }
            return $b->id <=> $a->id;
        });

        // Deduplikasi cerdas berdasarkan topik ternormalisasi:
        // Memastikan record terbaik (skor tertinggi) yang terpilih untuk topik yang sama
        $seenTopics = [];
        $deduped = $sorted->filter(function ($item) use (&$seenTopics) {
            $normKey = $this->normalizeTopic((string) $item->topic);
            if ($normKey === '') {
                return true;
            }
            if (isset($seenTopics[$normKey])) {
                return false;
            }
            $seenTopics[$normKey] = true;
            return true;
        });

        return $deduped->take($limit)->values();
    }

    /**
     * Ekstraksi kata-kata kunci utama dari query pengguna.
     *
     * @return string[]
     */
    public function extractKeywords(string $query): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', strtolower($query));
        $words = preg_split('/\s+/', trim((string) $clean)) ?: [];

        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) >= 3 && ! in_array($word, self::STOP_WORDS, true)) {
                $keywords[] = $word;
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Membersihkan konten dari tag HTML, skrip, dan teks navigasi/boilerplate yang tidak relevan.
     */
    private function cleanContent(string $content): string
    {
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
        $content = preg_replace('/localStorage\..*?;/s', '', $content);
        $content = preg_replace('/\(function\b[\s\S]*?\)\s*\([\s\S]*?\)\s*;?/s', '', $content);
        $content = preg_replace('/\(function\b[\s\S]*?\}/s', '', $content);
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 1. Hilangkan boilerplate header & aksesibilitas situs OPD
        $content = preg_replace('/Tutup Pilih Mode Tampilan.*?Open main menu/iu', ' ', $content);
        $content = preg_replace('/SIMONIK LAPOR 112 CCTV CHAT PORTAL BANDUNG/iu', ' ', $content);
        $content = preg_replace('/Beranda Tentang Fitur Event Makanan Seni dan Budaya Hiburan Arsitektur Taman Citizen Siaran Pers Direktori Kota PPID JDIH.*?Open Data/iu', ' ', $content);

        // 2. Hilangkan popup survei kepuasan & masukan
        $content = preg_replace('/[×x]\s*Buka\s*[×x]\s*Berikan Masukan untuk Website Kota Bandung.*?Kritik dan saran\b/isu', ' ', $content);
        $content = preg_replace('/Seberapa Puas Anda dengan Website Kota Bandung\?.*?Kirim Masukan/isu', ' ', $content);
        $content = preg_replace('/Apakah Anda dapat menemukan berita\/informasi.*?Kritik dan saran/isu', ' ', $content);

        // 3. Hilangkan blok menu navigasi PPID berulang di situs Pemkot / OPD (dibatasi agar tidak melompati isi halaman)
        $content = preg_replace('/\b(?:Tentang PPID|Profil PPID|Informasi Setiap Saat|Daftar Informasi Publik)\b.{1,150}?\b(?:Hubungi Kami|SOP\s*Peringatan\s*Dini)\b/isu', ' ', $content);
        $content = preg_replace('/\bBeranda\s+(?:Profile|Profil)\s+Sejarah\s+Visi\s+Misi.{1,200}?\bHubungi Kami\b/isu', ' ', $content);
        $content = preg_replace('/\bBeranda\s+(?:Profil|Profile|Tentang|Layanan)\s+(?:Sejarah|Visi|Misi|Struktur|Tupoksi|Profil Pimpinan|Berita|Galeri).{1,250}?\b(?:Realisasi|Pelayanan Publik|PPID Utama|Standar Pelayanan)\b/isu', ' ', $content);

        // 4. Hilangkan deretan logo instansi mitra pada MPP yang sangat panjang
        $content = preg_replace('/Instansi Tergabung\s+See all our works.*?Beri Penilaian/isu', ' ', $content);
        $content = preg_replace('/Beri Penilaian Untuk MPP Kota Bandung.*?Recent Post/isu', ' ', $content);

        // 5. Hilangkan header & footer navigasi Disbudpar agar tidak membuang kuota konteks & tidak tertukar dengan alamat hotel/tempat wisata
        $content = preg_replace('/Dinas Kebudayaan dan Pariwisata Kota Bandung.{1,600}?Permohonan Informasi Masuk\s*/isu', ' ', $content);
        $content = preg_replace('/Dinas Kebudayaan dan Pariwisata Kota Bandung\s+Jl\.\s*Ahmad Yani No\.?\s*277.*?Bandung Smart City/isu', ' ', $content);

        // 6. Hilangkan footer hak cipta / disclaimer
        $content = preg_replace('/(?:Hak Cipta|Copyright)\s*(?:©|\(c\))\s*\d{4}.*?(?:All [Rr]ights [Rr]eserved|\.|$)/iu', ' ', $content);

        return trim(preg_replace('/\s+/u', ' ', $content) ?? '');
    }

    /**
     * Normalisasi nama topik untuk deduplikasi cerdas.
     */
    private function normalizeTopic(string $topic): string
    {
        $clean = mb_strtolower($topic);
        $clean = preg_replace('/\s*[\(\[]\s*(?:bagian|part)\s+\d+\s*[\)\]]/iu', '', $clean);
        $clean = preg_replace('/\s*-\s*(?:bagian|part)\s+\d+/iu', '', $clean);
        $clean = preg_replace('/\s*[\(\[]\s*(?:terbaru|tahun)\s+[0-9\-\s]+[\)\]]/iu', '', $clean);
        $clean = preg_replace('/\s*[\(\[]\s*[a-z0-9\-]{1,6}\s*[\)\]]/iu', '', $clean);
        $clean = preg_replace('/\s*-\s*\d+$/u', '', $clean);
        $clean = preg_replace('/\bdisdukcapil\b/iu', '', $clean);
        $clean = preg_replace('/\bdata\b/iu', '', $clean);
        $clean = preg_replace('/\bjumlah\b/iu', '', $clean);
        $clean = preg_replace('/\bkependudukan\b/iu', 'penduduk', $clean);
        $clean = preg_replace('/\blaju\b/iu', '', $clean);
        $clean = preg_replace('/\bdan\b/iu', '', $clean);
        $clean = preg_replace('/\s+/u', ' ', $clean);
        return trim($clean);
    }
}