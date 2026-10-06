<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\KnowledgeBase;
use Symfony\Component\DomCrawler\Crawler; 
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;
use ZipArchive;
use Smalot\PdfParser\Parser as PdfParser;

class ScrapeDiskominfo extends Command
{
    protected $signature = 'scrape:diskominfo {--cluster= : Hanya scrape klaster tertentu} {--url= : Hanya scrape satu URL spesifik}';
    protected $description = 'Mengambil pengetahuan layanan kependudukan dan perizinan Kota Bandung untuk RAG';

    private const MAX_CONTENT_LENGTH = 60000;

    /** Kamus sinonim layanan publik per klaster — digunakan untuk memperkaya kolom keywords */
    private const SYNONYM_MAP = [
        'Kependudukan'         => ['ktp','e-ktp','ktp-el','kartu keluarga','kk','akta kelahiran','akta kematian','nik','pindah domisili','salaman','disdukcapil','ikd','identitas digital','akta nikah','akta cerai','surat pindah','agama','golongan darah','jenis kelamin','kepala keluarga','pendidikan terakhir','pekerjaan','status perkawinan','pertumbuhan penduduk','jumlah penduduk','demografi','dukcapil'],
        'Perizinan'            => ['izin usaha','nib','oss','rba','dpmptsp','mpp','mal pelayanan publik','pbg','slf','reklame','izin praktik nakes','pertek','umkm','siup','tdp','imb','izin lingkungan'],
        'Pendidikan'           => ['ppdb','pendaftaran sekolah','beasiswa','bawaku','rmp','bop','dana bos', 'bansos', 'bantuan sosial','mutasi siswa','disdik kota bandung','kalender pendidikan','sd','smp','sma','smk','zonasi'],
        'Kesehatan'            => ['puskesmas','rsud bandung kiwari','rshs','hasan sadikin','rsu kota bandung','antrean online','faskes','bpjs pbi','dinkes','ambulans','layanan kesehatan','dokter','obat','vaksin','posyandu'],
        'Dinas Damkar'         => ['damkar','pemadam kebakaran','dkpb','rescue','penyelamatan','sarang tawon','ular','darurat kebakaran','pos damkar','113','fire fighter','kebakaran gedung','evakuasi'],
        '112 Kota Bandung'     => ['panggilan darurat','bandung siaga 112','gawat darurat','ambulans darurat','kecelakaan','bencana','bebas pulsa','darurat','emergency','nomor darurat'],
        'Direktori Kota Bandung' => ['kecamatan','kelurahan','skpd','dinas','badan','alamat kantor','kontak','jam operasional','lurah','camat','kantor','telepon','email'],
        'Wisata'               => ['wisata bandung','tiket masuk','lokasi wisata','destinasi','museum','taman','cagar budaya','heritage','tempat wisata','pariwisata'],
        'Kuliner'              => ['kuliner bandung','makanan khas','restoran','kafe','warung','oleh-oleh','jajanan','batagor','mie kocok','surabi','soto'],
        'Hotel'                => ['penginapan','hotel','homestay','guest house','villa','akomodasi','menginap','bintang','kamar'],
        'Pelayanan Publik'     => ['layanan publik','etalase', 'bansos', 'bantuan sosial', 'portal bandung','aplikasi','sistem','online','digital','pelayanan','coe','city of event'],
        'Pemerintahan'         => ['walikota','wakil walikota','sekda','balaikota','visi misi','pimpinan','struktur organisasi','pemkot bandung','pemerintah kota'],
        'Lainnya'              => ['diskominfo','ppid','lapor','sp4n','casn','open data','pengaduan','keterbukaan informasi','arimbi'],
    ];

    private const STOPWORDS_ID = [
        'yang','di','ke','dari','dan','atau','ini','itu','dengan','untuk','pada','adalah','dalam','tidak','juga',
        'akan','sudah','ada','bisa','serta','oleh','dapat','karena','tersebut','agar','sebagai','lebih','jika',
        'bila','namun','bahwa','telah','hingga','melalui','setelah','sebelum','antara','hanya','semua','setiap',
        'seperti','secara','sangat','tentang','selain','kepada','terhadap','maupun','ketika','seluruh','hal',
        'tahun','nomor','no','surat','pemerintah','kota','bandung','layanan','pelayanan','informasi','data',
    ];

    private const SEED_URLS = [

        'Lainnya' => [
            'https://diskominfo.bandung.go.id',
            'https://diskominfo.bandung.go.id/profile/sejarah',
            'https://diskominfo.bandung.go.id/profile/visi-misi',
            'https://diskominfo.bandung.go.id/profile/struktur-organisasi',
            'https://diskominfo.bandung.go.id/profile/tupoksi',
            'https://diskominfo.bandung.go.id/profile/profil-pimpinan',
            'https://diskominfo.bandung.go.id/ppid/tentang/profile-ppid-diskominfo',
            'https://diskominfo.bandung.go.id/ppid/standar-pelayanan',
            'https://diskominfo.bandung.go.id/ppid/pedoman-pelayanan-publik',
            'https://biroperekonomian.jabarprov.go.id/sp4nlapor',
            'https://prokopim.bandung.go.id/home/profil_pimpinan_walikota',
            'https://prokopim.bandung.go.id/home/profil_pimpinan_wakil_walikota',
            'https://ppid-simonik.bandung.go.id/kontak',
            'https://ppid.bandung.go.id/storage/ppid_utama/V8c4yRAzDskhgPp3xDjfqsSByC4pkfkN1b6P4ltR.pdf',
            'https://ppid-simonik.bandung.go.id/',
            'https://www.lapor.go.id/',
            'https://bkpsdm.bandung.go.id/casn2024/',
            'https://ppid.bandung.go.id/daftar_informasi',
            'https://ppid.bandung.go.id/#layanan',
            'https://www.lapor.go.id/tentang',
            'https://arimbi.bandung.go.id/',
            'https://arimbi.bandung.go.id/market',
            'https://arimbi.bandung.go.id/hospital',
            'https://arimbi.bandung.go.id/pmi',
            'https://arimbi.bandung.go.id/puskesmas',
            'https://arimbi.bandung.go.id/atcs',
            'https://arimbi.bandung.go.id/air-quality',
            'https://opendata.bandung.go.id/dataset/daftar-aplikasi-layanan-administrasi-pemerintahan-di-lingkungan-pemerintah-kota-bandung',
            'https://opendata.bandung.go.id/dataset/daftar-aplikasi-pemerintah-kota-bandung-terintegrasi-dengan-splp',
        ],

        'Pelayanan Publik' => [
            'https://www.bandung.go.id/etalase/7/pelayanan-publik',
            'https://www.bandung.go.id/dashboard-sub-etalase/83/coe-kota-bandung',
        ],

        'Pemerintahan' => [
            'https://www.bandung.go.id/etalase/1/pemerintah-kota-bandung',
            'https://www.bandung.go.id/sub-etalase/42/alamat',
        ],

        'Kuliner' => [
            'https://www.bandung.go.id/dashboard-sub-etalase/84/kuliner',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/83',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/82',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/81',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/80',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/79',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/78',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/77',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/76',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/75',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/74',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/73',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/72',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/70',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/71',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/69',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/68',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/67',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/66',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/65',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/64',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/63',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/62',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/61',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/60',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/59',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/58',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/57',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/56',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/55',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/54',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/54',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/53',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/53',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/52',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/51',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/50',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/49',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/48',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/47',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/46',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/45',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/44',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/43',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/42',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/41',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/40',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/40',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/39',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/38',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/37',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/36',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/35',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/34',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/33',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/32',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/31',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/30',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/29',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/28',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/27',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/26',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/25',
            'https://disbudpar.bandung.go.id/c_home/kuliner_detail/24',
        ],

        'Hotel' => [
            'https://www.bandung.go.id/dashboard-sub-etalase/82/hotel',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/19',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/16',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/23',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/27',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/20',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/18',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/17',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/21',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/22',
            'https://www.disbudpar.bandung.go.id/c_hotel/hotel_detail/9',
        ],

        'Wisata' => [
            'https://www.bandung.go.id/dashboard-sub-etalase/42/destinasi-wisata',
            'https://www.bandung.go.id/dashboard-sub-etalase/81/usaha-pariwisata',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/daya_tarik',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/kawasan_pariwisata',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/transportasi_wisata',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/perjalanan_wisata',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/makanan_dan_minuman',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/akomodasi',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/hiburan_dan_rekreasi',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/mice',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/informasi_pariwisata',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/konsultan_pariwisata',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/pramuwisata',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/tirta',
            'https://www.disbudpar.bandung.go.id/c_usaha_pariwisata/spa',
        ],

        'Direktori Kota Bandung' => [
            'https://www.bandung.go.id/city-directory/info/306/sekretaris-daerah',
            'https://www.bandung.go.id/city-directory/info/206/asisten-administrasi-umum',
            'https://www.bandung.go.id/city-directory/info/205/asisten-pemerintahan-dan-kesejahteraan-rakyat',
            'https://www.bandung.go.id/city-directory/info/207/asisten-perekonomian-dan-pembangunan',
            'https://www.bandung.go.id/city-directory/info/210/badan-kepegawaian-dan-pengembangan-sumber-daya-manusia',
            'https://www.bandung.go.id/city-directory/info/214/badan-kesatuan-bangsa-dan-politik',
            'https://www.bandung.go.id/city-directory/info/248/badan-keuangan-dan-aset-daerah',
            'https://www.bandung.go.id/city-directory/info/311/badan-penanggulangan-bencana-daerah',
            'https://www.bandung.go.id/city-directory/info/215/badan-pendapatan-daerah',
            'https://www.bandung.go.id/city-directory/info/232/badan-perencanaan-pembangunan-riset-dan-inovasi-daerah',
            'https://www.bandung.go.id/city-directory/info/244/bagian-administrasi-pembangunan',
            'https://www.bandung.go.id/city-directory/info/220/bagian-hukum',
            'https://www.bandung.go.id/city-directory/info/261/bagian-kerja-sama',
            'https://www.bandung.go.id/city-directory/info/225/bagian-kesejahteraan-rakyat',
            'https://www.bandung.go.id/city-directory/info/223/bagian-organisasi',
            'https://www.bandung.go.id/city-directory/info/245/bagian-pengadaan-barang-dan-jasa',
            'https://www.bandung.go.id/city-directory/info/243/bagian-perekonomian',
            'https://www.bandung.go.id/city-directory/info/242/bagian-perencanaan-keuangan-dan-kepegawaian',
            'https://www.bandung.go.id/city-directory/info/212/bagian-protokol-dan-komunikasi-pimpinan',
            'https://www.bandung.go.id/city-directory/info/313/bagian-tata-pemerintahan',
            'https://www.bandung.go.id/city-directory/info/221/bagian-umum',
            'https://www.bandung.go.id/city-directory/info/241/dinas-arsip-dan-perpustakaan',
            'https://www.bandung.go.id/city-directory/info/227/dinas-cipta-karya-bina-konstruksi-dan-tata-ruang',
            'https://www.bandung.go.id/city-directory/info/216/dinas-kebakaran-dan-penanggulangan-bencana',
            'https://www.bandung.go.id/city-directory/info/240/dinas-kebudayaan-dan-pariwisata',
            'https://www.bandung.go.id/city-directory/info/234/dinas-kependudukan-dan-pencatatan-sipil',
            'https://www.bandung.go.id/city-directory/info/222/dinas-kesehatan',
            'https://www.bandung.go.id/city-directory/info/218/dinas-ketahanan-pangan-dan-pertanian',
            'https://www.bandung.go.id/city-directory/info/230/dinas-ketenagakerjaan',
            'https://www.bandung.go.id/city-directory/info/204/dinas-komunikasi-dan-informatika',
            'https://www.bandung.go.id/city-directory/info/246/dinas-koperasi-dan-usaha-kecil-dan-menengah',
            'https://www.bandung.go.id/city-directory/info/256/dinas-lingkungan-hidup',
            'https://www.bandung.go.id/city-directory/info/231/dinas-pemberdayaan-perempuan-dan-perlindungan-anak',
            'https://www.bandung.go.id/city-directory/info/257/dinas-pemuda-dan-olahraga',
            'https://www.bandung.go.id/city-directory/info/238/dinas-penanaman-modal-dan-pelayanan-terpadu-satu-pintu',
            'https://www.bandung.go.id/city-directory/info/213/dinas-pendidikan',
            'https://www.bandung.go.id/city-directory/info/233/dinas-pengendalian-penduduk-dan-keluarga-berencana',
            'https://www.bandung.go.id/city-directory/info/247/dinas-perdagangan-dan-perindustrian',
            'https://www.bandung.go.id/city-directory/info/235/dinas-perhubungan',
            'https://www.bandung.go.id/city-directory/info/228/dinas-perumahan-dan-kawasan-permukiman',
            'https://www.bandung.go.id/city-directory/info/229/dinas-sosial',
            'https://www.bandung.go.id/city-directory/info/254/dinas-sumber-daya-air-dan-bina-marga',
            'https://www.bandung.go.id/city-directory/info/209/inspektorat-daerah',
            'https://www.bandung.go.id/city-directory/info/58/kecamatan-andir',
            'https://www.bandung.go.id/city-directory/info/72/kecamatan-antapani',
            'https://www.bandung.go.id/city-directory/info/77/kecamatan-arcamanik',
            'https://www.bandung.go.id/city-directory/info/61/kecamatan-astana-anyar',
            'https://www.bandung.go.id/city-directory/info/60/kecamatan-babakan-ciparay',
            'https://www.bandung.go.id/city-directory/info/64/kecamatan-bandung-kidul',
            'https://www.bandung.go.id/city-directory/info/59/kecamatan-bandung-kulon',
            'https://www.bandung.go.id/city-directory/info/69/kecamatan-bandung-wetan',
            'https://www.bandung.go.id/city-directory/info/71/kecamatan-batununggal',
            'https://www.bandung.go.id/city-directory/info/62/kecamatan-bojongloa-kaler',
            'https://www.bandung.go.id/city-directory/info/63/kecamatan-bojongloa-kidul',
            'https://www.bandung.go.id/city-directory/info/73/kecamatan-buahbatu',
            'https://www.bandung.go.id/city-directory/info/53/kecamatan-cibeunying-kaler',
            'https://www.bandung.go.id/city-directory/info/54/kecamatan-cibeunying-kidul',
            'https://www.bandung.go.id/city-directory/info/79/kecamatan-cibiru',
            'https://www.bandung.go.id/city-directory/info/56/kecamatan-cicendo',
            'https://www.bandung.go.id/city-directory/info/51/kecamatan-cidadap',
            'https://www.bandung.go.id/city-directory/info/75/kecamatan-cinambo',
            'https://www.bandung.go.id/city-directory/info/52/kecamatan-coblong',
            'https://www.bandung.go.id/city-directory/info/57/kecamatan-gedebage',
            'https://www.bandung.go.id/city-directory/info/70/kecamatan-kiaracondong',
            'https://www.bandung.go.id/city-directory/info/67/kecamatan-lengkong',
            'https://www.bandung.go.id/city-directory/info/65/kecamatan-mandalajati',
            'https://www.bandung.go.id/city-directory/info/76/kecamatan-panyileukan',
            'https://www.bandung.go.id/city-directory/info/74/kecamatan-rancasari',
            'https://www.bandung.go.id/city-directory/info/66/kecamatan-regol',
            'https://www.bandung.go.id/city-directory/info/55/kecamatan-sukajadi',
            'https://www.bandung.go.id/city-directory/info/50/kecamatan-sukasari',
            'https://www.bandung.go.id/city-directory/info/68/kecamatan-sumur-bandung',
            'https://www.bandung.go.id/city-directory/info/200/kelurahan-ancol',
            'https://www.bandung.go.id/city-directory/info/181/kelurahan-antapani-kidul',
            'https://www.bandung.go.id/city-directory/info/179/kelurahan-antapani-kulon',
            'https://www.bandung.go.id/city-directory/info/180/kelurahan-antapani-tengah',
            'https://www.bandung.go.id/city-directory/info/197/kelurahan-antapani-wetan',
            'https://www.bandung.go.id/city-directory/info/90/kelurahan-arjuna',
            'https://www.bandung.go.id/city-directory/info/160/kelurahan-babakan',
            'https://www.bandung.go.id/city-directory/info/157/kelurahan-babakan-asih',
            'https://www.bandung.go.id/city-directory/info/115/kelurahan-babakan-ciamis',
            'https://www.bandung.go.id/city-directory/info/159/kelurahan-babakan-ciparay',
            'https://www.bandung.go.id/city-directory/info/282/kelurahan-babakan-penghulu',
            'https://www.bandung.go.id/city-directory/info/128/kelurahan-babakan-sari',
            'https://www.bandung.go.id/city-directory/info/126/kelurahan-babakan-surabaya',
            'https://www.bandung.go.id/city-directory/info/193/kelurahan-babakan-tarogong',
            'https://www.bandung.go.id/city-directory/info/147/kelurahan-balonggede',
            'https://www.bandung.go.id/city-directory/info/267/kelurahan-batununggal',
            'https://www.bandung.go.id/city-directory/info/137/kelurahan-binong',
            'https://www.bandung.go.id/city-directory/info/112/kelurahan-braga',
            'https://www.bandung.go.id/city-directory/info/140/kelurahan-burangrang',
            'https://www.bandung.go.id/city-directory/info/99/kelurahan-campaka',
            'https://www.bandung.go.id/city-directory/info/174/kelurahan-caringin',
            'https://www.bandung.go.id/city-directory/info/149/kelurahan-ciateul',
            'https://www.bandung.go.id/city-directory/info/154/kelurahan-cibadak',
            'https://www.bandung.go.id/city-directory/info/169/kelurahan-cibaduyut-kidul',
            'https://www.bandung.go.id/city-directory/info/170/kelurahan-cibaduyut-wetan',
            'https://www.bandung.go.id/city-directory/info/132/kelurahan-cibangkong',
            'https://www.bandung.go.id/city-directory/info/172/kelurahan-cibuntu',
            'https://www.bandung.go.id/city-directory/info/120/kelurahan-cicadas',
            'https://www.bandung.go.id/city-directory/info/127/kelurahan-cicaheum',
            'https://www.bandung.go.id/city-directory/info/198/kelurahan-cigadung',
            'https://www.bandung.go.id/city-directory/info/300/kelurahan-cigending',
            'https://www.bandung.go.id/city-directory/info/145/kelurahan-cigereleng',
            'https://www.bandung.go.id/city-directory/info/175/kelurahan-cigondewah-kaler',
            'https://www.bandung.go.id/city-directory/info/178/kelurahan-cigondewah-kidul',
            'https://www.bandung.go.id/city-directory/info/177/kelurahan-cigondewah-rahayu',
            'https://www.bandung.go.id/city-directory/info/109/kelurahan-cihapit',
            'https://www.bandung.go.id/city-directory/info/275/kelurahan-cihaurgeulis',
            'https://www.bandung.go.id/city-directory/info/138/kelurahan-cijagra',
            'https://www.bandung.go.id/city-directory/info/274/kelurahan-cijawura',
            'https://www.bandung.go.id/city-directory/info/171/kelurahan-cijerah',
            'https://www.bandung.go.id/city-directory/info/144/kelurahan-cikawao',
            'https://www.bandung.go.id/city-directory/info/119/kelurahan-cikutra',
            'https://www.bandung.go.id/city-directory/info/286/kelurahan-cimincrang',
            'https://www.bandung.go.id/city-directory/info/277/kelurahan-cipadung',
            'https://www.bandung.go.id/city-directory/info/294/kelurahan-cipadung-kidul',
            'https://www.bandung.go.id/city-directory/info/292/kelurahan-cipadung-kulon',
            'https://www.bandung.go.id/city-directory/info/293/kelurahan-cipadung-wetan',
            'https://www.bandung.go.id/city-directory/info/103/kelurahan-cipaganti',
            'https://www.bandung.go.id/city-directory/info/295/kelurahan-cipamokolan',
            'https://www.bandung.go.id/city-directory/info/85/kelurahan-cipedes',
            'https://www.bandung.go.id/city-directory/info/164/kelurahan-cirangrang',
            'https://www.bandung.go.id/city-directory/info/96/kelurahan-ciroyom',
            'https://www.bandung.go.id/city-directory/info/183/kelurahan-cisaranten-bina-harapan',
            'https://www.bandung.go.id/city-directory/info/185/kelurahan-cisaranten-endah',
            'https://www.bandung.go.id/city-directory/info/287/kelurahan-cisaranten-kidul',
            'https://www.bandung.go.id/city-directory/info/184/kelurahan-cisaranten-kulon',
            'https://www.bandung.go.id/city-directory/info/283/kelurahan-cisaranten-wetan',
            'https://www.bandung.go.id/city-directory/info/148/kelurahan-ciseureuh',
            'https://www.bandung.go.id/city-directory/info/276/kelurahan-cisurupan',
            'https://www.bandung.go.id/city-directory/info/111/kelurahan-citarum',
            'https://www.bandung.go.id/city-directory/info/101/kelurahan-ciumbuleuit',
            'https://www.bandung.go.id/city-directory/info/106/kelurahan-dago',
            'https://www.bandung.go.id/city-directory/info/296/kelurahan-derwati',
            'https://www.bandung.go.id/city-directory/info/95/kelurahan-dungus-cariang',
            'https://www.bandung.go.id/city-directory/info/98/kelurahan-garuda',
            'https://www.bandung.go.id/city-directory/info/81/kelurahan-geger-kalong',
            'https://www.bandung.go.id/city-directory/info/176/kelurahan-gempolsari',
            'https://www.bandung.go.id/city-directory/info/130/kelurahan-gumuruh',
            'https://www.bandung.go.id/city-directory/info/100/kelurahan-hegarmanah',
            'https://www.bandung.go.id/city-directory/info/89/kelurahan-husein-sastranegara',
            'https://www.bandung.go.id/city-directory/info/80/kelurahan-isola',
            'https://www.bandung.go.id/city-directory/info/156/kelurahan-jamika',
            'https://www.bandung.go.id/city-directory/info/289/kelurahan-jatihandap',
            'https://www.bandung.go.id/city-directory/info/273/kelurahan-jatisari',
            'https://www.bandung.go.id/city-directory/info/133/kelurahan-kacapiring',
            'https://www.bandung.go.id/city-directory/info/152/kelurahan-karang-anyar',
            'https://www.bandung.go.id/city-directory/info/201/kelurahan-karang-pamulang',
            'https://www.bandung.go.id/city-directory/info/196/kelurahan-karasak',
            'https://www.bandung.go.id/city-directory/info/135/kelurahan-kebon-gedang',
            'https://www.bandung.go.id/city-directory/info/125/kelurahan-kebon-jayanti',
            'https://www.bandung.go.id/city-directory/info/97/kelurahan-kebon-jeruk',
            'https://www.bandung.go.id/city-directory/info/129/kelurahan-kebon-kangkung',
            'https://www.bandung.go.id/city-directory/info/114/kelurahan-kebon-pisang',
            'https://www.bandung.go.id/city-directory/info/134/kelurahan-kebon-waru',
            'https://www.bandung.go.id/city-directory/info/166/kelurahan-kebonlega',
            'https://www.bandung.go.id/city-directory/info/155/kelurahan-kopo',
            'https://www.bandung.go.id/city-directory/info/264/kelurahan-kujangsari',
            'https://www.bandung.go.id/city-directory/info/104/kelurahan-lebak-gede',
            'https://www.bandung.go.id/city-directory/info/108/kelurahan-lebak-siliwangi',
            'https://www.bandung.go.id/city-directory/info/102/kelurahan-ledeng',
            'https://www.bandung.go.id/city-directory/info/139/kelurahan-lingkar-selatan',
            'https://www.bandung.go.id/city-directory/info/143/kelurahan-malabar',
            'https://www.bandung.go.id/city-directory/info/94/kelurahan-maleber',
            'https://www.bandung.go.id/city-directory/info/131/kelurahan-maleer',
            'https://www.bandung.go.id/city-directory/info/297/kelurahan-manjahlega',
            'https://www.bandung.go.id/city-directory/info/162/kelurahan-margahayu-utara',
            'https://www.bandung.go.id/city-directory/info/272/kelurahan-margasari',
            'https://www.bandung.go.id/city-directory/info/163/kelurahan-margasuka',
            'https://www.bandung.go.id/city-directory/info/298/kelurahan-mekarjaya',
            'https://www.bandung.go.id/city-directory/info/291/kelurahan-mekarmulya',
            'https://www.bandung.go.id/city-directory/info/168/kelurahan-mekarwangi',
            'https://www.bandung.go.id/city-directory/info/266/kelurahan-mengger',
            'https://www.bandung.go.id/city-directory/info/113/kelurahan-merdeka',
            'https://www.bandung.go.id/city-directory/info/117/kelurahan-neglasari',
            'https://www.bandung.go.id/city-directory/info/151/kelurahan-nyengseret',
            'https://www.bandung.go.id/city-directory/info/118/kelurahan-padasuka',
            'https://www.bandung.go.id/city-directory/info/91/kelurahan-pajajaran',
            'https://www.bandung.go.id/city-directory/info/281/kelurahan-pakemitan',
            'https://www.bandung.go.id/city-directory/info/278/kelurahan-palasari',
            'https://www.bandung.go.id/city-directory/info/141/kelurahan-paledang',
            'https://www.bandung.go.id/city-directory/info/93/kelurahan-pamoyanan',
            'https://www.bandung.go.id/city-directory/info/153/kelurahan-panjunan',
            'https://www.bandung.go.id/city-directory/info/299/kelurahan-pasanggrahan',
            'https://www.bandung.go.id/city-directory/info/279/kelurahan-pasir-biru',
            'https://www.bandung.go.id/city-directory/info/195/kelurahan-pasir-impun',
            'https://www.bandung.go.id/city-directory/info/92/kelurahan-pasir-kaliki',
            'https://www.bandung.go.id/city-directory/info/303/kelurahan-pasirendah',
            'https://www.bandung.go.id/city-directory/info/301/kelurahan-pasirjati',
            'https://www.bandung.go.id/city-directory/info/123/kelurahan-pasirlayung',
            'https://www.bandung.go.id/city-directory/info/150/kelurahan-pasirluyu',
            'https://www.bandung.go.id/city-directory/info/302/kelurahan-pasirwangi',
            'https://www.bandung.go.id/city-directory/info/84/kelurahan-pasteur',
            'https://www.bandung.go.id/city-directory/info/199/kelurahan-pelindung-hewan',
            'https://www.bandung.go.id/city-directory/info/146/kelurahan-pungkur',
            'https://www.bandung.go.id/city-directory/info/285/kelurahan-rancabolang',
            'https://www.bandung.go.id/city-directory/info/288/kelurahan-rancanumpang',
            'https://www.bandung.go.id/city-directory/info/105/kelurahan-sadang-serang',
            'https://www.bandung.go.id/city-directory/info/136/kelurahan-samoja',
            'https://www.bandung.go.id/city-directory/info/83/kelurahan-sarijadi',
            'https://www.bandung.go.id/city-directory/info/202/kelurahan-sekejati',
            'https://www.bandung.go.id/city-directory/info/107/kelurahan-sekeloa',
            'https://www.bandung.go.id/city-directory/info/290/kelurahan-sindangjaya',
            'https://www.bandung.go.id/city-directory/info/165/kelurahan-situsaeur',
            'https://www.bandung.go.id/city-directory/info/203/kelurahan-sukaasih',
            'https://www.bandung.go.id/city-directory/info/88/kelurahan-sukabungah',
            'https://www.bandung.go.id/city-directory/info/87/kelurahan-sukagalih',
            'https://www.bandung.go.id/city-directory/info/161/kelurahan-sukahaji',
            'https://www.bandung.go.id/city-directory/info/116/kelurahan-sukaluyu',
            'https://www.bandung.go.id/city-directory/info/121/kelurahan-sukamaju',
            'https://www.bandung.go.id/city-directory/info/182/kelurahan-sukamiskin',
            'https://www.bandung.go.id/city-directory/info/284/kelurahan-sukamulya',
            'https://www.bandung.go.id/city-directory/info/122/kelurahan-sukapada',
            'https://www.bandung.go.id/city-directory/info/124/kelurahan-sukapura',
            'https://www.bandung.go.id/city-directory/info/194/kelurahan-sukaraja',
            'https://www.bandung.go.id/city-directory/info/82/kelurahan-sukarasa',
            'https://www.bandung.go.id/city-directory/info/86/kelurahan-sukawarna',
            'https://www.bandung.go.id/city-directory/info/110/kelurahan-tamansari',
            'https://www.bandung.go.id/city-directory/info/142/kelurahan-turangga',
            'https://www.bandung.go.id/city-directory/info/173/kelurahan-warungmuncang',
            'https://www.bandung.go.id/city-directory/info/265/kelurahan-wates',
            'https://www.bandung.go.id/city-directory/info/304/perseroda-bandung-infra-investama-bii',
            'https://www.bandung.go.id/city-directory/info/29/perumda-bank-perkreditan-rakyat-bpr',
            'https://www.bandung.go.id/city-directory/info/40/perumda-pasar-juara',
            'https://www.bandung.go.id/city-directory/info/30/perumda-tirtawening',
            'https://www.bandung.go.id/city-directory/info/262/rumah-sakit-khusus-gigi-dan-mulut',
            'https://www.bandung.go.id/city-directory/info/26/rumah-sakit-umum-daerah',
            'https://www.bandung.go.id/city-directory/info/25/rumah-sakit-umum-daerah-bandung-kiwari',
            'https://www.bandung.go.id/city-directory/info/47/satuan-polisi-pamong-praja',
            'https://www.bandung.go.id/city-directory/info/208/sekretariat-dewan-perwakilan-rakyat-daerah',
            'https://www.bandung.go.id/city-directory/info/259/staff-ahli-bidang-kemasyarakatan-dan-sumber-daya-manusia',
            'https://www.bandung.go.id/city-directory/info/255/staff-ahli-bidang-pembangunan-ekonomi-dan-keuangan',
            'https://www.bandung.go.id/city-directory/info/250/staff-ahli-bidang-pemerintahan-hukum-dan-politik',
        ],

        'Kependudukan' => [
            'https://disdukcapil.bandung.go.id/peta',
            'https://disdukcapil.bandung.go.id/data-demografi/pertumbuhan-penduduk',
            'https://disdukcapil.bandung.go.id/data-demografi/agama',
            'https://disdukcapil.bandung.go.id/data-demografi/golongan-darah',
            'https://disdukcapil.bandung.go.id/data-demografi/jenis-kelamin',
            'https://disdukcapil.bandung.go.id/data-demografi/kepala-keluarga',
            'https://disdukcapil.bandung.go.id/data-demografi/pendidikan-terakhir',
            'https://disdukcapil.bandung.go.id/data-demografi/jenis-pekerjaan',
            'https://disdukcapil.bandung.go.id/data-demografi/status-perkawinan',
            'https://www.bandung.go.id/information/kependudukan',
            'https://www.bandung.go.id/news/kependudukan',
            'https://mpp.bandung.go.id/portfolio/view/32',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/29364',
            'https://disdukcapil.bandung.go.id/layanan',
            'https://disdukcapil.bandung.go.id/layanan-online',
            'https://opendata.bandung.go.id/dataset/laju-pertumbuhan-penduduk-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-kota-bandung-berdasarkan-kecamatan',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-kota-bandung-berdasarkan-kelompok-umur',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-kota-bandung-berdasarkan-jenis-kelamin-3',
            'https://opendata.bandung.go.id/dataset/jumlah-kepadatan-penduduk-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/rata-rata-kepadatan-penduduk-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-wajib-ktp-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-masyarakat-mendapatkan-pelayanan-kartu-tanda-penduduk-ktp-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-cakupan-kepemilikan-e-ktp-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-cakupan-kepemilikan-kartu-keluarga-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kepala-keluarga-di-kota-bandung-berdasarkan-jenis-kelamin',
            'https://opendata.bandung.go.id/dataset/jumlah-kepala-keluarga-di-kota-bandung-berdasarkan-tingkat-pendidikan',
            'https://opendata.bandung.go.id/dataset/jumlah-pemohon-kutipan-akta-kelahiran-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-pemohon-kutipan-akta-perkawinan-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kepemilikan-akta-kelahiran-anak-usia-0-17-tahun-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penerbitan-kutipan-akta-pengesahan-anak-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-kota-bandung-berdasarkan-agama-4',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-kota-bandung-berdasarkan-golongan-darah-3',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-kota-bandung-berdasarkan-jenis-pendidikan-2',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-kota-bandung-berdasarkan-jenis-pekerjaan',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-pindah-datang-ke-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-pindah-keluar-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-kota-bandung-berdasarkan-status-kawin',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-cicendo-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-bojongloa-kaler-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-babakan-ciparay-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-bandung-kulon-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-rancasari-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-coblong-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-cibeunying-kaler-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-cibeunying-kidul-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-penduduk-lansia-di-kecamatan-ujungberung-kota-bandung',
            'https://opendata.bandung.go.id/api/static/upload/20251202105200_buku_profil_anak_kota_bandung_tahun_2025-1-2025-11-17114457.pdf',
            'https://opendata.bandung.go.id/api/static/upload/20251202104609_buku_profil_gender_kota_bandung_tahun_2025-1-2025-11-17114258.pdf',
            'https://opendata.bandung.go.id/api/static/upload/20251202104200_buku_profil_anak_kota_bandung_2024-1-2024-12-05000609.pdf',
            'https://opendata.bandung.go.id/api/static/upload/20251202103945_buku_profil_gender_kota_bandung_2024-1-2024-12-05000628.pdf',
            'https://prokopim.bandung.go.id/home/profil_pimpinan_walikota',
            'https://prokopim.bandung.go.id/home/profil_pimpinan_wakil_walikota',
            'https://disdukcapil.bandung.go.id/siapa-kami',
            'https://disdukcapil.bandung.go.id/struktur-kedinasan',
            'https://disdukcapil.bandung.go.id/struktur-organisasi',
            'https://disdukcapil.bandung.go.id/profil-pejabat',
            'https://disdukcapil.bandung.go.id/tugas-dan-fungsi',
            'https://disdukcapil.bandung.go.id/sejarah-singkat',
            'https://disdukcapil.bandung.go.id/maklumat-pelayanan-gambar',
            'https://disdukcapil.bandung.go.id/inovasi-pelayanan',
            'https://disdukcapil.bandung.go.id/peta',
            'https://disdukcapil.bandung.go.id/data-demografi/pertumbuhan-penduduk',
            'https://disdukcapil.bandung.go.id/data-demografi/agama',
            'https://disdukcapil.bandung.go.id/data-demografi/golongan-darah',
            'https://disdukcapil.bandung.go.id/data-demografi/jenis-kelamin',
            'https://disdukcapil.bandung.go.id/data-demografi/kepala-keluarga',
            'https://disdukcapil.bandung.go.id/data-demografi/pendidikan-terakhir',
            'https://disdukcapil.bandung.go.id/data-demografi/jenis-pekerjaan',
            'https://disdukcapil.bandung.go.id/data-demografi/status-perkawinan',

        ],
        'Perizinan' => [
            'https://mpp.bandung.go.id/portfolio/view/32',
            'https://dpmptsp.bandung.go.id/',
            'https://dpmptsp.bandung.go.id/',
            'https://dpmptsp.bandung.go.id/profil',
            'https://dpmptsp.bandung.go.id/struktur-organisasi',
            'https://dpmptsp.bandung.go.id/mekanisme-perizinan',
            'https://dpmptsp.bandung.go.id/publikasi-data',
            'https://dpmptsp.bandung.go.id/regulasi-dinas-penanaman-modal-dan-perizinan-terpadu-satu-pintu',
            'https://dpmptsp.bandung.go.id/permohonan-informasi-publik-dan-pengajuan-keberatan',
            'https://dpmptsp.bandung.go.id/formulir-informasi-publik-dan-formulir-layanan-perizinan',
            'https://dpmptsp.bandung.go.id/formulir-informasi-publik-dan-formulir-layanan-perizinan',
            'https://dpmptsp.bandung.go.id/syarat-perizinan',
            'https://dpmptsp.bandung.go.id/standar-waktu-pelayanan',
            'https://dpmptsp.bandung.go.id/mekanisme-perizinan',
            'https://dpmptsp.bandung.go.id/prosedur-pengaduan',
            'https://dpmptsp.bandung.go.id/standar-pelayanan-dpmptsp',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/715',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/1386',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/1693',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2702',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2781',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2787',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2805',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2815',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/3011',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/3670',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/23612',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/23818',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/24280',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/29364',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/30083',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/3807',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/23061',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/1993',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2012',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2016',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2354',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2784',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/2923',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/3098',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/3177',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/3260',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/22642',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/22657',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/22658',
            'https://jdih.bandung.go.id/home/produk-hukum/peraturan-perundang-undangan-daerah/23211',
            'https://opendata.bandung.go.id/dataset/jumlah-izin-mendirikan-bangunan-berdasarkan-layanan-perubahan-perizinan-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-izin-mendirikan-bangunan-berdasarkan-layanan-revisi-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-izin-mendirikan-bangunan-berdasarkan-layanan-salinan-perizinan-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-izin-mendirikan-bangunan-berdasarkan-layanan-splitsing-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-izin-mendirikan-bangunan-berdasarkan-layanan-perizinan-baru-di-kota-bandung',
        ],
        'Kesehatan' => [
            'https://dinkes.bandung.go.id/upt-dinas/puskesmas/',
            'https://dinkes.bandung.go.id/upt-dinas/rumah-sakit/',
            'https://dinkes.bandung.go.id/upt-dinas/lab-kesda/',
            'https://dinkes.bandung.go.id/upt-dinas/yankesmob/',
            'https://dinkes.bandung.go.id/sejarah-dinas-kesehatan-kota-bandung-2/',
            'https://dinkes.bandung.go.id/visi-misi/',
            'https://dinkes.bandung.go.id/struktur-organisasi/',
            'https://dinkes.bandung.go.id/tugas-pokok-dan-fungsi/',
            'https://dinkes.bandung.go.id/download/lkip-2025/',
            'https://dinkes.bandung.go.id/download/lkip-tahun-2024/',
            'https://dinkes.bandung.go.id/download/lkip-tahun-2023/',
            'https://dinkes.bandung.go.id/download/lkip-2022/',
            'https://dinkes.bandung.go.id/download/lkip-tahun-2021/',
            'https://dinkes.bandung.go.id/download/lkip-tahun-2022-triwulan-2/',
            'https://dinkes.bandung.go.id/download/lkip-tahun-2020/',
            'https://dinkes.bandung.go.id/download/lkip-tahun-2018/',
            'https://dinkes.bandung.go.id/download/lkip-2017/',
            'https://dinkes.bandung.go.id/download/lkip-2016/',
            'https://dinkes.bandung.go.id/download/lkip-2019/',
            'https://dinkes.bandung.go.id/layanan-pengaduan/',
            'https://dinkes.rsudcicalengka.id/',
            'https://www.bandung.go.id/news/kesehatan',
            'https://www.bandung.go.id/information/kesehatan',
            'https://sdkdinkesbdg.com/',
            'https://dinkes.bandung.go.id/link-penelitian-praktik-kerja-dinas-kesehatan-kota-bandung/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-enumurator-ski-2023/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-administrasi-enumurator-tahap-ii/',
            'https://dinkes.bandung.go.id/hasil-seleksi-pegawai-dengan-perjanjian-kontrak-dak-bok-ta-2022/',
            'https://dinkes.bandung.go.id/hasil-seleksi-administrasi/',
            'https://dinkes.bandung.go.id/penerimaan-calon-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/rekrutmen-contact-tracer-covid-19/',
            'https://dinkes.bandung.go.id/pengumuman-pemenang-sayembara-lomba-video/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-administrasi-sayembara-lomba-video-peduli-cegah-stunting/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-uji-kompetensi-calon-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-administrasi-calon-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/rekrutmen-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/sayembara-video-2021/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-calon-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/hasiladministrasirekeuitmendinkes2021/',
            'https://dinkes.bandung.go.id/rekrutmen-calon-tenaga-dengan-perjanjian-kerja-bagi-puskesmas/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-contact-tracer-gelombang-ii-kota-bandung/',
            'https://dinkes.bandung.go.id/rekruitmen/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-akhir-seleksi-enumerator-rifaskes-dinas-kesehatan-kota-bandung-tahun-2019/',
            'https://dinkes.bandung.go.id/pengumuman-jadwal-wawancara-rifaskes-dinas-kesehatan-kota-bandung-tahun-2019/',
            'https://dinkes.bandung.go.id/open-rekrutmen-enumerator-rifaskes-riset-fasilitas-kesehatan-kota-bandung-2019/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-peserta-yang-lolos-seleksi-akhir-profesi-tenaga-ahli/',
            'https://dinkes.bandung.go.id/jadwal-tes-online-rekrutmen-calon-tenaga-ahli-profesi-tahun-2018/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-lolos-seleksi-administrasi-rekrutmen-calon-tenaga-ahli-profesi-tahun-2018/',
            'https://dinkes.bandung.go.id/pengumuman-rekrutmen-calon-tenaga-ahli-profesi-kontrak-apbd-dan-tenaga-blud-upt-tahun-2018/',
            'https://dinkes.bandung.go.id/pengumuman-seleksi-enumerator-riskesdas/',
            'https://dinkes.bandung.go.id/pengumuman-interview-enumerator-riskesdas-hari-ke-2/',
            'https://dinkes.bandung.go.id/pengumuman-interview-enumerator-riskesdas-hari-ke-1/',
            'https://dinkes.bandung.go.id/generasi-sehat-masa-depan-hebat-peringatan-hari-kesehatan-nasional-ke-61/',
            'https://dinkes.bandung.go.id/launching-logo-dinas-kesehatan-kota-bandung/',
            'https://dinkes.bandung.go.id/kenali-talasemia-deteksi-dini-untuk-cegah-generasi-penderita-baru/',
            'https://dinkes.bandung.go.id/jaga-kesehatan-di-tengah-cuaca-buruk-langkah-antisipasi-dan-perlindungan-diri/',
            'https://dinkes.bandung.go.id/kepala-dinas-kesehatan-kota-bandung-menghadiri-upacara-peringatan-hari-jadi-kota-bandung-ke-215/',
            'https://dinkes.bandung.go.id/perkuat-ketahanan-warga-dinas-kesehatan-kota-bandung-dorong-siskamling-siaga-bencana-di-lingkungan-masyarakat/',
            'https://dinkes.bandung.go.id/dinas-kesehatan-kota-bandung-gelar-evaluasi-pelayanan-kesehatan-balita/',
            'https://dinkes.bandung.go.id/dinas-kesehatan-kota-bandung-gelar-pembinaan-pedagang-jajanan-sekolah-untuk-ciptakan-konsumsi-sehat-bagi-anak/',
            'https://dinkes.bandung.go.id/dinas-kesehatan-kota-bandung-gelar-penyusunan-sop-program-gizi-untuk-tingkatkan-kualitas-layanan-kesehatan/',
            'https://dinkes.bandung.go.id/dinas-kesehatan-kota-bandung-menyelenggarakan-acara-kegiatan-penyuluhan-keamanan-pangan-industri-rumah-tangga-bagi-pelaku-usaha-industri-rumah-tangga-irt/',
            'https://dinkes.bandung.go.id/gerakan-berantas-nyamuk-bersama-3mmengoles-keluarga-sehat-bebas-dbd/',
            'https://dinkes.bandung.go.id/gerakan-bersama-cegah-stunting-anak-sehat-dan-cerdas/',
            'https://dinkes.bandung.go.id/pemeriksaan-kesehatan-dan-skrining-tbc-pada-balita-bermasalah-gizi/',
            'https://dinkes.bandung.go.id/cegah-stunting-wujudkan-anak-bandung-sehat-dan-cerdas/',
            'https://dinkes.bandung.go.id/dinas-kesehatan-kota-bandung-gelar-pembinaan-penyehat-tradisional-untuk-tingkatkan-mutu-dan-keamanan-layanan/',
            'https://dinkes.bandung.go.id/dinas-kesehatan-kota-bandung-perkuat-jejaring-eksternal-untuk-pertahankan-status-bebas-malaria/',
            'https://dinkes.bandung.go.id/pertemuan-koordinasi-dan-validasi-data-pengobatan-arv-bagi-odhiv/',
            'https://dinkes.bandung.go.id/peringatan-hari-anak-nasional-han-dan-hari-kejaksaan-ri-ke-80-tingkat-kota-bandung-tahun-2025/',
            'https://dinkes.bandung.go.id/memperingati-hari-anak-nasional-han-tahun-2025-di-uptd-puskesmas-garuda/',
            'https://dinkes.bandung.go.id/investasi-sehat-jangka-panjang-dinkes-kota-bandung-gelar-program-pengendalian-ggl-untuk-tekan-angka-penyakit-tidak-menular/',
            'https://dinkes.bandung.go.id/gerakan-posyandu-aktif-strategi-dinas-kesehatan-kota-bandung-dalam-merevitalisasi-pelayanan-kesehatan/',
            'https://dinkes.bandung.go.id/integrasi-layanan-primer-ilp/',
            'https://dinkes.bandung.go.id/cek-kesehatan-gratis-ckg-untuk-anak-sekolah/',
            'https://dinkes.bandung.go.id/aksi-bergizi-cerdiku/',
            'https://dinkes.bandung.go.id/menjaga-kesehatan-selama-mudik/',
            'https://dinkes.bandung.go.id/akzi-bergizi/',
            'https://dinkes.bandung.go.id/menjaga-kesehatan-ketika-mudik-2024/',
            'https://dinkes.bandung.go.id/poster-untuk-petugas-2-jaga-kesehatan-saat-bertugas-pemilu-2024/',
            'https://dinkes.bandung.go.id/poster-untuk-pemilih-jaga-kesehatan-pemilu-2024/',
            'https://dinkes.bandung.go.id/jaga-kesehatan-saat-bertugas-pemilu-2024/',
            'https://dinkes.bandung.go.id/ciri-ciri-anemia-dan-dampaknya-pada-remaja-putri/',
            'https://dinkes.bandung.go.id/ciri-ciri-dan-upaya-pencegahan-stunting/',
            'https://dinkes.bandung.go.id/asupan-tablet-tambah-darah-untuk-cegah-anemia-dan-stunting/',
            'https://dinkes.bandung.go.id/masalah-anemia-pada-remaja-putri/',
            'https://dinkes.bandung.go.id/tantangan-kesehatan-pada-remaja-putri/',
            'https://dinkes.bandung.go.id/mencegah-stunting-dengan-isi-piringku/',
            'https://dinkes.bandung.go.id/waspadai-anemia-yang-mengakibatkan-risiko-stunting/',
            'https://dinkes.bandung.go.id/mengenal-stunting-dan-dampaknya-pada-pertumbuhan-anak/',
            'https://dinkes.bandung.go.id/anemia-dan-stunting-tantangan-ganda-dalam-pertumbuhan-dan-kesehatan/',
            'https://dinkes.bandung.go.id/kenali-dampak-anemia-pada-remaja-putri/',
            'https://dinkes.bandung.go.id/lima-manfaat-tablet-tambah-darah-bagi-remaja-putri/',
            'https://dinkes.bandung.go.id/gejala-anemia-pada-remaja-putri/',
            'https://dinkes.bandung.go.id/link-penelitian-praktik-kerja-dinas-kesehatan-kota-bandung/',
            'https://dinkes.bandung.go.id/public-trust-analisis-tingkat-kepuasan-dan-kepercayaan-publik-terhadap-pemerintah/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-enumurator-ski-2023/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-administrasi-enumurator-tahap-ii/',
            'https://dinkes.bandung.go.id/menciptakan-aparatur-sipil-negara-kreatif-inovatif-responsif-dan-berintegritas-dalam-pelayanan-publik-berdasarkan-kepemimpinan-pancasila/',
            'https://dinkes.bandung.go.id/meningkatkan-kepercayaan-masyarakat-terhadap-petugas-pelayanan-publik/',
            'https://dinkes.bandung.go.id/tantangan-peran-asn-era-digital-dalam-memberikan-pelayanantantangan-peran-asn-era-digital-dalam-memberikan-pelayanan/',
            'https://dinkes.bandung.go.id/menuju-tata-kelola-pemerintah-5-0-berstandar-international/',
            'https://dinkes.bandung.go.id/kota-bandung-capai-odf-100-dinkes-genjot-capai-5-pilar-stbm/',
            'https://dinkes.bandung.go.id/dinkes-genjot-capaian-bian-sampai-95/',
            'https://dinkes.bandung.go.id/pantau-ktr-lebih-mudah-dengan-dashboard-e-monev-ktr/',
            'https://dinkes.bandung.go.id/kadinkes-tekankan-capaian-pelaksanaan-program-kesehatan-di-kota-bandung-sebelum-2023/',
            'https://dinkes.bandung.go.id/dinkes-lakukan-persiapan-inventarisasi-dan-pemutakhiran-data-bmd-bersama-bkad-kota-bandung/',
            'https://dinkes.bandung.go.id/dinkes-dukung-penuh-komitmen-klinik-pratama-bio-farma-raih-akreditas-paripurna/',
            'https://dinkes.bandung.go.id/hasil-seleksi-pegawai-dengan-perjanjian-kontrak-dak-bok-ta-2022/',
            'https://dinkes.bandung.go.id/hasil-seleksi-administrasi/',
            'https://dinkes.bandung.go.id/penerimaan-calon-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/dinkes-dan-indomaret-jalin-kolaborasi-program-primanutri-posyandu/',
            'https://dinkes.bandung.go.id/cegah-hipertensi-dengan-skrining-kesehatan/',
            'https://dinkes.bandung.go.id/cegah-hepatitis-akut-misterius-dengan-phbs/',
            'https://dinkes.bandung.go.id/siap-siap-80-posyandu-remaja-akan-hadir-di-kota-bandung-tahun-ini/',
            'https://dinkes.bandung.go.id/rekrutmen-contact-tracer-covid-19/',
            'https://dinkes.bandung.go.id/woro-woro-kesehatan-keliling/',
            'https://dinkes.bandung.go.id/sah-rskia-resmi-alih-status-menjadi-rsud-bandung-kiwari/',
            'https://dinkes.bandung.go.id/570-anak-usia-6-11-tahun-di-kota-bandung-mendapat-vaksinasi-covid-19/',
            'https://dinkes.bandung.go.id/layad-rawat-raih-top-45-kijb-tahun-2021/',
            'https://dinkes.bandung.go.id/angka-stunting-di-kota-bandung-turun-134/',
            'https://dinkes.bandung.go.id/ummi-gaspol-terus-100-odf/',
            'https://dinkes.bandung.go.id/dinkes-ajak-warga-kendalikan-penyakit-asma-dengan-pesat/',
            'https://dinkes.bandung.go.id/5-asn-dinas-kesehatan-kota-bandung-ikut-lomba-asn-berprestasi-tingkat-kota-bandung/',
            'https://dinkes.bandung.go.id/dinkes-dorong-puskesmas-tetap-prima-dalam-memberikan-pelayanan-selama-pandemi-covid-19/',
            'https://dinkes.bandung.go.id/dinkes-kota-bandung-raih-peringkat-ke-2-penanganan-tb-terbaik-pada-tb-summit-2021/',
            'https://dinkes.bandung.go.id/10-sekolah-jadi-sampel-swab-pcr-di-masa-ptmt/',
            'https://dinkes.bandung.go.id/pemkot-bandung-dukung-reaktivasi-rumah-sakit-sukapura/',
            'https://dinkes.bandung.go.id/pengumuman-pemenang-sayembara-lomba-video/',
            'https://dinkes.bandung.go.id/grafik-kasus-covid-19-di-kota-bandung-turun-sampai-95-persen/',
            'https://dinkes.bandung.go.id/capai-zero-kelahiran-talasemia-di-kota-bandung-dengan-deteksi-dini-di-puskesmas/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-administrasi-sayembara-lomba-video-peduli-cegah-stunting/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-uji-kompetensi-calon-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-administrasi-calon-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/rekrutmen-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/dinkes-galang-komitmen-rs-dalam-memberikan-pelaporan-sistem-informasi-kesehatan-di-rs/',
            'https://dinkes.bandung.go.id/sayembara-video-2021/',
            'https://dinkes.bandung.go.id/ummi-pantau-pelaksanaan-vaksinasi-covid-19-di-upt-puskesmas-arcamanik/',
            'https://dinkes.bandung.go.id/peringati-hari-anak-nasional-kota-bandung-berikan-vaksin-kepada-1-000-anak/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-calon-tenaga-dengan-perjanjian-kerja/',
            'https://dinkes.bandung.go.id/2-000-pelajar-smp-dan-sma-di-kota-bandung-dapat-vaksinasi-covid-19/',
            'https://dinkes.bandung.go.id/hasiladministrasirekeuitmendinkes2021/',
            'https://dinkes.bandung.go.id/ahyani-tak-semua-apotek-sediakan-obat-tangani-covid-19-bergejala-ringan/',
            'https://dinkes.bandung.go.id/rekrutmen-calon-tenaga-dengan-perjanjian-kerja-bagi-puskesmas/',
            'https://dinkes.bandung.go.id/presiden-targetkan-1-juta-vaksinasi-per-hari/',
            'https://dinkes.bandung.go.id/percepatan-vaksinasi-dan-disiplin-prokes-efektif-tekan-bor/',
            'https://dinkes.bandung.go.id/demi-lindungi-kesehatan-masyarakat-merokok-sembarangan-kini-denda-500-ribu/',
            'https://dinkes.bandung.go.id/dinkes-pantau-keramaian-di-terminal-dan-pusat-perbelanjaan/',
            'https://dinkes.bandung.go.id/daftar-biaya-lab-dinkes/',
            'https://dinkes.bandung.go.id/tuberkulosis-pandemi-selain-covid-19/',
            'https://dinkes.bandung.go.id/masyarakat-tionghoa-rs-kebonjati-dan-pemkot-bandung-kolaborasi-masifkan-vaksinasi/',
            'https://dinkes.bandung.go.id/pedagang-pasar-modern-dapat-vaksinasi-covid-19-hari-ini/',
            'https://dinkes.bandung.go.id/350-pelayan-publik-jadi-target-vaksinasi-covid-19-massal-di-kota-bandung/',
            'https://dinkes.bandung.go.id/vaksinasi-covid-19-bagi-pedagang-pasar-mulai-hari-ini/',
            'https://dinkes.bandung.go.id/lansia-kota-bandung-mulai-vaksinasi-covid-19-hari-ini/',
            'https://dinkes.bandung.go.id/vaksin-covid-19-ini-kata-mereka/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-seleksi-contact-tracer-gelombang-ii-kota-bandung/',
            'https://dinkes.bandung.go.id/rekruitmen/',
            'https://dinkes.bandung.go.id/kabar-covid-19/',
            'https://dinkes.bandung.go.id/apa-yang-harus-dilakukan-oleh-penyandang-ptm-agar-terhindar-covid-19/',
            'https://dinkes.bandung.go.id/17-kelurahan-dan-2-kecamatan-raih-piagam-emas-odf/',
            'https://dinkes.bandung.go.id/sah-kelurahan-cimincrang-raih-gelar-kelurahan-odf/',
            'https://dinkes.bandung.go.id/dinkes-bagikan-2-700-lembar-masker-untuk-seluruh-opd-kota-bandung/',
            'https://dinkes.bandung.go.id/kesehatan-keluarga-dan-gizi/',
            'https://dinkes.bandung.go.id/sambut-hkn-ke-56-dinkes-kampanye-3m-dengan-berbagi-masker/',
            'https://dinkes.bandung.go.id/dinkes-ajak-gerakan-3m-dengan-sebar-masker/',
            'https://dinkes.bandung.go.id/dinkes-dorong-kecamatan-gedebage-jadi-kecamatan-pertama-dengan-odf-100/',
            'https://dinkes.bandung.go.id/kota-bandung-raih-special-achievement-pencapaian-uhc-dari-bpjs-kesehatan-kedeputian-wilayah-jabar/',
            'https://dinkes.bandung.go.id/kelurahan-derwati-jadi-target-kelurahan-100-odf-ke-8-di-kota-bandung/',
            'https://dinkes.bandung.go.id/visi-misi-dinas-kesehatan-kota-bandung/',
            'https://dinkes.bandung.go.id/600-warga-sekitar-secapa-ad-jadi-target-rapid-test-massal/',
            'https://dinkes.bandung.go.id/sah-kota-bandung-punya-2-rumah-singgah-khusus-nakes/',
            'https://dinkes.bandung.go.id/gugus-tugas-covid-19-sosialisasikan-hotel-gino-feruci-sebagai-rumah-singgah-odp-dan-otg/',
            'https://dinkes.bandung.go.id/protokol-disinfeksi-dan-panduan-pencegahan-covid-19/',
            'https://dinkes.bandung.go.id/waspada-novel-coronavirus/',
            'https://dinkes.bandung.go.id/komitmen-bidan-se-kota-bandung-turunkan-aki-dan-akb-dengan-bandung-salamina-melalui-salam-cantik/',
            'https://dinkes.bandung.go.id/upt-p2kt-kota-bandung-jadi-finalis-5-besar-indohcf-innovation-award-iii-tahun-2019/',
            'https://dinkes.bandung.go.id/igo-dan-dinkes-kota-bandung-siap-kolaborasi/',
            'https://dinkes.bandung.go.id/dinkes-dorong-puskesmas-maksimalkan-koordinasi-untuk-capai-target-odf/',
            'https://dinkes.bandung.go.id/dinkes-adakan-sosialisasi-penggunaan-obat-rasional-por/',
            'https://dinkes.bandung.go.id/ratusan-warga-rusunawa-cingised-rayakan-hari-anak-nasional-2019/',
            'https://dinkes.bandung.go.id/dinkes-kembangkan-sikda-guna-dukung-smart-city/',
            'https://dinkes.bandung.go.id/kemenkes-nilai-kinerja-upt-puskesmas-ibrahim-adjie-dan-sukarasa-pada-survei-penilaian-re-akreditasi/',
            'https://dinkes.bandung.go.id/puskesmas-garuda-masuk-penilaian-lomba-fktp-berprestasi-tingkat-provinsi/',
            'https://dinkes.bandung.go.id/skill-lab-bantu-tingkatkan-kompetensi-pengelola-imunisasi-di-kota-bandung/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-akhir-seleksi-enumerator-rifaskes-dinas-kesehatan-kota-bandung-tahun-2019/',
            'https://dinkes.bandung.go.id/pengumuman-jadwal-wawancara-rifaskes-dinas-kesehatan-kota-bandung-tahun-2019/',
            'https://dinkes.bandung.go.id/beas-beureum-inovasi-kota-bandung-turunkan-angka-stunting/',
            'https://dinkes.bandung.go.id/open-rekrutmen-enumerator-rifaskes-riset-fasilitas-kesehatan-kota-bandung-2019/',
            'https://dinkes.bandung.go.id/omaba-dan-beas-bereum-solusi-bandung-cegah-stunting/',
            'https://dinkes.bandung.go.id/waspada-asma-dinkes-gencar-terapkan-program-healthy-lung/',
            'https://dinkes.bandung.go.id/9886-warga-kota-bandung-sudah-terjamin-uhc/',
            'https://dinkes.bandung.go.id/80-apoteker-aoc-siap-sosialisasikan-gema-cermat/',
            'https://dinkes.bandung.go.id/si-calakan-aplikasi-pengumpulan-data-kecelakaan-kota-bandung/',
            'https://dinkes.bandung.go.id/dinkes-dorong-cegah-dbd-di-sekolah/',
            'https://dinkes.bandung.go.id/1-550-petugas-kebersihan-dan-satpol-pp-dapat-vaksinasi-gratis/',
            'https://dinkes.bandung.go.id/kota-bandung-akan-jadi-tuan-rumah-seminar-ktr/',
            'https://dinkes.bandung.go.id/apoteker-harus-terampil-berkomunikasi-2/',
            'https://dinkes.bandung.go.id/dinkes-lakukan-persiapan-posko-kesehatan-jelang-natal-dan-tahun-baru/',
            'https://dinkes.bandung.go.id/apoteker-harus-terampil-berkomunikasi/',
            'https://dinkes.bandung.go.id/akhir-tahun-kota-bandung-punya-7-sentra-keperawatan/',
            'https://dinkes.bandung.go.id/6-kelurahan-raih-penghargaan-100-odf-pada-gebyar-sanitasi/',
            'https://dinkes.bandung.go.id/bandung-dan-taiwan-bekerja-sama-cegah-demam-berdarah/',
            'https://dinkes.bandung.go.id/pekan-depan-satgas-ktr-kembali-turun/',
            'https://dinkes.bandung.go.id/raperda-ktr-akan-terapkan-sanksi-bagi-pelanggar/',
            'https://dinkes.bandung.go.id/jumlah-perokok-remaja-naik-perda-ktr-jadi-wajib/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-peserta-yang-lolos-seleksi-akhir-profesi-tenaga-ahli/',
            'https://dinkes.bandung.go.id/jadwal-tes-online-rekrutmen-calon-tenaga-ahli-profesi-tahun-2018/',
            'https://dinkes.bandung.go.id/pengumuman-hasil-lolos-seleksi-administrasi-rekrutmen-calon-tenaga-ahli-profesi-tahun-2018/',
            'https://dinkes.bandung.go.id/pendaftaran-online-rekrutmen-calon-tenaga-ahli-profesi-kontrak-apbd-dan-tenaga-blud-upt-tahun-2018/',
            'https://dinkes.bandung.go.id/pengumuman-rekrutmen-calon-tenaga-ahli-profesi-kontrak-apbd-dan-tenaga-blud-upt-tahun-2018/',
            'https://dinkes.bandung.go.id/upt-puskesmas-sukarasa-resmi-membangun-poned/',
            'https://dinkes.bandung.go.id/dinkes-adakan-pertemuan-untuk-kembangkan-penyehat-tradisional-hatra/',
            'https://dinkes.bandung.go.id/kota-bandung-akan-punya-dua-puskesmas-ramah-disabilitas/',
            'https://dinkes.bandung.go.id/dinkes-adakan-semarak-germas-tournamen-73/',
            'https://dinkes.bandung.go.id/puskesmas-garuda-peringati-hari-anak-nasional/',
            'https://dinkes.bandung.go.id/bandung-raih-juara-ke-2-nakes-teladan-tingkat-provinsi/',
            'https://dinkes.bandung.go.id/layad-rawat-sekarang-punya-ambulans-mini-icu/',
            'https://dinkes.bandung.go.id/dinkes-kota-bandung-targetkan-membangun-puskesmas-inklusi-pada-2020/',
            'https://dinkes.bandung.go.id/enam-puskesmas-dikukuhkan-jadi-puskesmas-santun-lansia/',
            'https://dinkes.bandung.go.id/bandung-ikuti-lomba-toga-tingkat-provinsi/',
            'https://dinkes.bandung.go.id/jelang-idul-fitri-dinkes-kota-bandung-siapkan-1-782-paket-obat/',
            'https://dinkes.bandung.go.id/kampanye-antirokok-htts-2018-angkat-7-kebohongan-rokok/',
            'https://dinkes.bandung.go.id/tekan-angka-stunting-kader-gizi-bentuk-forkagi/',
            'https://dinkes.bandung.go.id/dprd-kota-sorong-studi-banding-ke-dinkes-kota-bandung/',
            'https://dinkes.bandung.go.id/persiapan-membuat-perda-satgas-ktr-kota-bandung-kaji-tiru-ke-kota-bogor/',
            'https://dinkes.bandung.go.id/layad-rawat-raih-juara-nasional-kategori-spgdt-terbaik/',
            'https://dinkes.bandung.go.id/cegah-stunting-mulai-dari-janin/',
            'https://dinkes.bandung.go.id/informasi-penerimaan-bantuan-ppds-ppdgs-angkatan-xxi-dan-iii-tahun-2018/',
            'https://dinkes.bandung.go.id/situasi-gawat-darurat-cepat-tertangani-dengan-kolaborasi/',
            'https://dinkes.bandung.go.id/pemberantasan-tbc-perlu-partisipasi-warga/',
            'https://dinkes.bandung.go.id/satgas-ktr-resmi-dilepas-pjs-walikota-bandung/',
            'https://dinkes.bandung.go.id/hari-gizi-nasional-ke-58/',
            'https://dinkes.bandung.go.id/trc-berikan-desinfektan-pasca-banjir-bandang/',
            'https://dinkes.bandung.go.id/esok-satgas-ktr-mulai-beroperasi/',
            'https://dinkes.bandung.go.id/jelang-pilkada-dinkes-kota-bandung-adakan-sosialisasi-pengendalian-kesehatan/',
            'https://dinkes.bandung.go.id/pengumuman-seleksi-enumerator-riskesdas/',
            'https://dinkes.bandung.go.id/ratusan-warga-kujangsari-ikut-sdj-filariasis/',
            'https://dinkes.bandung.go.id/pengumuman-interview-enumerator-riskesdas-hari-ke-2/',
            'https://dinkes.bandung.go.id/pengumuman-interview-enumerator-riskesdas-hari-ke-1/',
            'https://dinkes.bandung.go.id/rekrutmen-enumerator-riskesdas-kota-bandung-tahun-2018/',
            'https://dinkes.bandung.go.id/empat-tempat-akan-jadi-fokus-penerapan-ktr-tahun-2018/',
            'https://dinkes.bandung.go.id/dinkes-kota-bandung-latih-25-satgas-ktr/',
            'https://dinkes.bandung.go.id/mulai-1-februari-2018-kota-bandung-serentak-melaksanakan-ori-difteri/',
            'https://dinkes.bandung.go.id/kesehatan-warga-bandung-dibiayai-oleh-pemerintah/',
            'https://dinkes.bandung.go.id/kepesertaan-jkn-kis-lebih-efektif-melalui-program-uhc/',
            'https://dinkes.bandung.go.id/12-dlp-siap-layani-warga-bandung-di-puskesmas/',
            'https://dinkes.bandung.go.id/cegah-difetri-dengan-imunisasi/',
            'https://dinkes.bandung.go.id/hari-kesehatan-nasional-ke-53/',
            'https://dinkes.bandung.go.id/hari-kesehatan-nasional-ke-53-2/',
            'https://dinkes.bandung.go.id/rapat-kerja-kesehatan-daerah-2017/',
            'https://dinkes.bandung.go.id/rapat-kerja-kesehatan-daerah-kota-bandung-2017/',
            'https://dinkes.bandung.go.id/cegah-kanker-serviks-dengan-tes-iva-rutin/',
            'https://dinkes.bandung.go.id/ciptakan-generasi-muda-sehat-melalui-germas/',
            'https://dinkes.bandung.go.id/gerakan-masyarakat-sehat/',
            'https://dinkes.bandung.go.id/imunisasi-campak-rubella/',
            'https://dinkes.bandung.go.id/launching-kendaraan-konseling-silih-asih/',
            'https://dinkes.bandung.go.id/kekasih-juara-perdana-beroperasi/',
            'https://dinkes.bandung.go.id/perdana-beroperasi-kekasih-juara-sedot-perhatian-warga/',
            'https://dinkes.bandung.go.id/pekan-olahraga-pemerintah-kota-2017/',
            'https://dinkes.bandung.go.id/butuh-teman-curhat-kekasih-juara-solusinya/',
            'https://dinkes.bandung.go.id/tegakkan-implementasi-dinkes-bentuk-tim-satgas-ktr/',
            'https://dinkes.bandung.go.id/pekan-olahraga-pemerintah-kota-bandung-2017/',
            'https://dinkes.bandung.go.id/pelatihan-sistem-informasi-kesehatan/',
            'https://dinkes.bandung.go.id/pelatihan-sistem-informasi-kesehatan-daerah-sikda/',
            'https://dinkes.bandung.go.id/evaluasi-program-dan-kegiatan/',
            'https://dinkes.bandung.go.id/launching-layanan-layad-rawat-2/',
            'https://dinkes.bandung.go.id/evaluasi-program-triwulan-i-ii/',
            'https://dinkes.bandung.go.id/launching-layanan-layad-rawat/',
            'https://dinkes.bandung.go.id/peluncuran-ambulans-mini-icu-pada-peringatan-1-tahun-layad-rawat/',
            'https://dinkes.bandung.go.id/gaya-hidup-cerdik/',
            'https://dinkes.bandung.go.id/vaksin/',
            'https://dinkes.bandung.go.id/layanan-pengaduan/',
            'https://dinkes.bandung.go.id/aksi-bergizi-cerdiku/',
            'https://dinkes.bandung.go.id/menjaga-kesehatan-ketika-mudik-2024/',
            'https://dinkes.bandung.go.id/poster-untuk-petugas-2-jaga-kesehatan-saat-bertugas-pemilu-2024/',
            'https://dinkes.bandung.go.id/poster-untuk-pemilih-jaga-kesehatan-pemilu-2024/',
            'https://dinkes.bandung.go.id/jaga-kesehatan-saat-bertugas-pemilu-2024/',
            'https://dinkes.bandung.go.id/ciri-ciri-anemia-dan-dampaknya-pada-remaja-putri/',
            'https://dinkes.bandung.go.id/ciri-ciri-dan-upaya-pencegahan-stunting/',
            'https://dinkes.bandung.go.id/asupan-tablet-tambah-darah-untuk-cegah-anemia-dan-stunting/',
            'https://dinkes.bandung.go.id/masalah-anemia-pada-remaja-putri/',
            'https://dinkes.bandung.go.id/tantangan-kesehatan-pada-remaja-putri/',
            'https://dinkes.bandung.go.id/download/sk-iku-2020/',
            'https://dinkes.bandung.go.id/download/pk-dinkes-2020/',
            'https://dinkes.bandung.go.id/download/iku-ka-dinkes-2020/',
            'https://dinkes.bandung.go.id/download/iku-2018/',
            'https://dinkes.bandung.go.id/download/pk-2018/',
            'https://dinkes.bandung.go.id/download/renstra-dinkes-2025-2029/',
            'https://dinkes.bandung.go.id/download/renstra-2024-2026-dinas-kesehatan-kota-bandung/',
            'https://dinkes.bandung.go.id/download/renstra-perubahan-2018-2023/',
            'https://dinkes.bandung.go.id/download/renstra-dinkes-2018-2023/',
            'https://dinkes.bandung.go.id/download/renja-2024-dinkes-kota-bandung/',
            'https://dinkes.bandung.go.id/download/renja-2023-dinkes-kota-bandung/',
            'https://dinkes.bandung.go.id/download/rkt-dinkes-2025/',
            'https://dinkes.bandung.go.id/download/rkt-2023-dinkes-kota-bandung/',
            'https://dinkes.bandung.go.id/download/spm/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-2025/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-kota-bandung-2024/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-kota-bandung-2023/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-2022/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-2021/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-kota-bandung-2020/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-kota-bandung-2019/',
            'https://dinkes.bandung.go.id/download/tabel-profil-kesehatan-kota-bandung-tahun-2018/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-kota-bandung-2018/',
            'https://dinkes.bandung.go.id/download/tabel-profil-kesehatan-kota-bandung-tahun-2017/',
            'https://dinkes.bandung.go.id/download/profil-dinas-kesehatan-kota-bandung-2017/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-kota-bandung-2016/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-kota-bandung-2015/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-kota-bandung-2014/',
            'https://dinkes.bandung.go.id/download/profil-kesehatan-tahun-2014/',
            'https://dinkes.bandung.go.id/download/perwal-1381-tahun-2016-kota-bandungtahun-2016/',
            'https://dinkes.bandung.go.id/download/permenkes-rekonsil-no-5-tahun-2011/',
            'https://dinkes.bandung.go.id/download/permenkes-no-028-ttg-kliniktahun-2011/',
            'https://dinkes.bandung.go.id/download/permenkes-no-2052-ttg-izin-praktik-kedokteran-tahun-2011/',
            'https://dinkes.bandung.go.id/download/permenkes-317-tentang-pendayagunaan-tenaga-kesehatan-warga-negara-asing-di-indonesia-tahun-2010/',
            'https://dinkes.bandung.go.id/download/permenkes-registrasi-tenaga-kerja-no-161-tahun-2010/',
            'https://dinkes.bandung.go.id/download/permenkes-sip-no-512menkesperiv-tahun2007/',
            'https://dinkes.bandung.go.id/download/permenkes-1419-menkes-per-x-tahun2005/',
            'https://dinkes.bandung.go.id/download/permenkes-gigi-mulut-nomor-1173-tahun-2004/',
            'https://dinkes.bandung.go.id/download/permenkes-no-304-tahun-1989/',
            'https://dinkes.bandung.go.id/download/sop-konseling-sanitasi-dlm-gedung-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-sanitasi-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-pengukuran-kebugaran-anak-sekolah-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-pengelolaan-sampah-medis-vaksin-covid-19-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-pemeriksaan-laik-hygiene-restoran-rumah-makan-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-pemeriksaan-higienen-jajanan-makanan-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-pembinaan-pos-ukk-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-laik-hygiene-depot-air-minum-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-kunjungan-rumah-klinik-snitasi-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-klinik-sanitasi-2021/',
            'https://dinkes.bandung.go.id/download/sop-inspeksi-sanitasi-dasar-jamban-sehat-tahun-2021/',
            'https://dinkes.bandung.go.id/download/sop-mutasi-pemenuhan-pegawai-tahun-2019/',
            'https://dinkes.bandung.go.id/keuangan/neraca/',
            'https://dinkes.bandung.go.id/keuangan/laporan-arus-kas-dan-catatan-keuangan/',
            'https://dinkes.bandung.go.id/keuangan/daftar-aset-dan-investasi/',
            'https://dinkes.bandung.go.id/kesga-dan-gizi/germas/',
            'https://dinkes.bandung.go.id/kesga-dan-gizi/germas/',
            'https://dinkes.bandung.go.id/yankes/layad-rawat/',
            'https://dinkes.bandung.go.id/laporan-realisasi-i-dan-ii/',
            'https://dinkes.bandung.go.id/pptk/',
            'https://dinkes.bandung.go.id/layanan-informasi-publik/',
            'https://dinkes.bandung.go.id/perizinan/',
            'https://dinkes.bandung.go.id/penyakit-terbanyak/',
            'https://dinkes.bandung.go.id/peta-wilayah-kerja/',
            'https://dinkes.bandung.go.id/sebaran-rumah-sakit/',
            'https://opendata.bandung.go.id/dataset/10-besar-penyakit-terbanyak-di-igd-rsud-kota-bandung-2',
            'https://opendata.bandung.go.id/dataset/jumlah-tenaga-gizi-di-fasilitas-kesehatan-kota-bandung-2',
            'https://opendata.bandung.go.id/dataset/jumlah-bayi-6-bulan-yang-diberi-asi-eksklusif-menurut-puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kematian-bayi-menurut-puskesmas-di-kota-bandung-2',
            'https://opendata.bandung.go.id/dataset/jumlah-kematian-neonatus-bayi-0-28-hari-berdasarkan-penyebab-kematian-di-puskesmas-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kematian-post-neonatal-bayi-29-hari---11-bulan-berdasarkan-penyebab-kematian-di-puskesmas-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-bayi-berat-badan-lahir-rendah-bblr-menurut-puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-bayi-lahir-hidup-menurut-puskesmas-di-kota-bandung-2',
            'https://opendata.bandung.go.id/dataset/jumlah-bayi-lahir-mati-menurut-puskesmas-di-kota-bandung-2',
            'https://opendata.bandung.go.id/dataset/jumlah-kematian-balita-menurut-puskesmas-di-kota-bandung-2',
            'https://opendata.bandung.go.id/dataset/jumlah-kematian-ibu-nifas-menurut-puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kematian-ibu-bersalin-menurut-kecamatan-dan-puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kematian-ibu-hamil-menurut-kecamatan-dan-puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-balita-stunting-indeks-tbu-menurut-kecamatan-dan-puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/rumah-sakit-di-kota-bandung-2',
            'https://opendata.bandung.go.id/dataset/jumlah-kasus-demam-berdarah-dengue-dbd-menurut-puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kasus-meninggal-disebabkan-dbd-menurut-puskesmas-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-pasien-berdasarkan-penyakit-terbanyak-di-puskesmas-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-dokter-berdasarkan-fasilitas-kesehatan-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-bidan-dan-perawat-di-fasilitas-kesehatan-kota-bandung-2',
            'https://opendata.bandung.go.id/dataset/daftar-posyandu-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kasus-baru-hiv-berdasarkan-kelompok-umur-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kasus-baru-tuberkolosis-paru-berdasarkan-fasilitas-kesehatan-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-kasus-positif-malaria-berdasarkan-fasilitas-kesehatan-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-angka-kematian-pasien-berdasarkan-rumah-sakit-di-kota-bandung',
            'https://www.bandung.go.id/etalase/3/kesehatan',
            'https://www.bandung.go.id/dashboard-sub-etalase/19/rumah-sakit',
            'https://www.bandung.go.id/dashboard-sub-etalase/20/data-puskemas',
            'https://www.bandung.go.id/dashboard-sub-etalase/63/perizinan',
        ],

        'Pendidikan' => [
            'https://simdik.bandung.go.id/publik/sekolah/sd',
            'https://simdik.bandung.go.id/publik/sekolah/tk',
            'https://simdik.bandung.go.id/publik/sekolah/smp',
            'https://simdik.bandung.go.id/publik/sekolah/pkbm',
            'https://simdik.bandung.go.id/rapor',
            'https://simdik.bandung.go.id/statistik',
            'https://disdik.bandung.go.id/berita',
            'https://disdik.bandung.go.id/post-menu-view/visi-misi',
            'https://disdik.bandung.go.id/struktur-organisasi',
            'https://disdik.bandung.go.id/post-menu-view/tugas-pokok',
            'https://prod.lapor.go.id/',
            'https://ppid.disdik.bandung.go.id/',
            'https://ppid.disdik.bandung.go.id/sk-ppid-pembantu-disdik',
            'https://ppid.disdik.bandung.go.id/struktur-ppid-disdik',
            'https://ppid.disdik.bandung.go.id/profil-ppid',
            'https://ppid.disdik.bandung.go.id/agenda-ppid',
            'https://ppid.disdik.bandung.go.id/standar-pelayanan',
            'https://ppid.disdik.bandung.go.id/proses-penyusunan-peraturan',
            'https://ppid.disdik.bandung.go.id/produk%20hukum',
            'https://ppid.disdik.bandung.go.id/landasan-disdik',
            'https://ppid.disdik.bandung.go.id/anggaran-ppid',
            'https://ppid.disdik.bandung.go.id/profil-singkat-pejabat-struktural',
            'https://ppid.disdik.bandung.go.id/sertifikasi-guru',
            'https://ppid.disdik.bandung.go.id/hpm',
            'https://ppid.disdik.bandung.go.id/Info-pppk',
            'https://ppid.disdik.bandung.go.id/informasi-kebijakan',
            'https://ppid.disdik.bandung.go.id/perjanjian-pihak-ketiga',
            'https://ppid.disdik.bandung.go.id/daftar-hasil-penelitian',
            'https://ppid.disdik.bandung.go.id/daftar-informasi-publik',
            'https://ppid.disdik.bandung.go.id/Indikator-Kinerja-Individu',
            'https://ppid.disdik.bandung.go.id/Info-darurat',
            'https://ppid.disdik.bandung.go.id/rekrutmen-disdik',
            'https://ppid.disdik.bandung.go.id/alamat-satdik',
            'https://ppid.disdik.bandung.go.id/biaya',
            'https://ppid.disdik.bandung.go.id/media-layanan',
            'https://ppid.disdik.bandung.go.id/waktu-pelayanan',
            'https://ppid.disdik.bandung.go.id/sarana-prasarana',
            'https://ppid.disdik.bandung.go.id/permohonan-keberatan',
            'https://ppid.disdik.bandung.go.id/mekanisme',
            'https://ppid.disdik.bandung.go.id/sop-uji-konsekuensi',
            'https://ppid.disdik.bandung.go.id/sop-melapor',
            'https://spmb.bandung.go.id/school-info',
            'https://disdik.jabarprov.go.id/',
            'https://disdik.jabarprov.go.id/profil/pejabat',
            'https://disdik.jabarprov.go.id/profil/profil-gtk',
            'https://disdik.jabarprov.go.id/profil/profil-pklk',
            'https://disdik.jabarprov.go.id/profil/profil-cabangdinas',
            'https://disdik.jabarprov.go.id/profil/profil-tikomdik',
            'https://disdik.jabarprov.go.id/profil/profil-psma',
            'https://disdik.jabarprov.go.id/profil/sejarah',
            'https://disdik.jabarprov.go.id/profil/brand',
            'https://disdik.jabarprov.go.id/profil/profil-psmk',
            'https://disdik.jabarprov.go.id/profil/sekretariat',
            'https://disdik.jabarprov.go.id/layanan-informasi/layanan-kepegawaian',
            'https://sites.google.com/view/disdikjabar-zi/beranda?authuser=0',
            'https://sync-disdik.jabarprov.go.id/sipdp/index.php',
            'https://disdik.jabarprov.go.id/layanan-informasi/layanan-siswa',
            'https://gtk.jabarprov.go.id/',
            'https://disdik.bandung.go.id/',
            'https://disdik.bandung.go.id/post-menu-view/visi-misi',
            'https://disdik.bandung.go.id/struktur-organisasi',
            'https://disdik.bandung.go.id/post-menu-view/tugas-pokok',
            'https://opendata.bandung.go.id/dataset/jumlah-mahasiswa-sarjana-pendidikan-tinggi-islam-berdasarkan-jenis-kelamin-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-mahasiswa-pascasarjana-pendidikan-tinggi-islam-berdasarkan-jenis-kelamin-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-guru-dan-tenaga-kependidikan-ptk-sekolah-menengah-pertama-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-peserta-didik-di-sekolah-menengah-pertama-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-guru-pns-dan-pppk-di-kota-bandung-berdasarkan-jenis-kelamin',
            'https://opendata.bandung.go.id/dataset/jumlah-peserta-didik-di-sekolah-dasar-kota-bandung',
            'https://opendata.bandung.go.id/dataset/akreditasi-sekolah-dasar-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/akreditasi-sekolah-menengah-pertama-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-guru-dan-tenaga-kependidikan-ptk-sekolah-dasar-di-kota-bandung',
            'https://opendata.bandung.go.id/dataset/jumlah-peserta-didik-di-pendidikan-anak-usia-dini-paud-kota-bandung',
        ],
        'Dinas Damkar' => [
            'https://dkpb.bandung.go.id/',
            'https://dkpb.bandung.go.id/read/sejarah',
            'https://dkpb.bandung.go.id/read/motto/',
            'https://dkpb.bandung.go.id/read/visi-dan-misi/',
            'https://dkpb.bandung.go.id/read/tujuan-dan-sasaran/',
            'https://dkpb.bandung.go.id/program/',
            'https://dkpb.bandung.go.id/read/tupoksi/',
            'https://dkpb.bandung.go.id/organization/',
            'https://dkpb.bandung.go.id/asn/',
            'https://dkpb.bandung.go.id/read/aset',
            'https://id.wikipedia.org/wiki/Dinas_Kebakaran_dan_Penanggulangan_Bencana_Kota_Bandung',
            'https://dkpb.bandung.go.id/news/',
            'https://dkpb.bandung.go.id/read/mekanisme+dan+prosedur+pelayanan/',
            'https://dkpb.bandung.go.id/read/permohonan+informasi/',
            'https://dkpb.bandung.go.id/read/layanan+kebakaran/',
            'https://dkpb.bandung.go.id/read/layanan+kebencanaan/',
            'https://dkpb.bandung.go.id/read/tatacara+pengaduan/',
            'https://dkpb.bandung.go.id/contact/',
            'https://dkpb.bandung.go.id/report/neraca-keuangan',
            'https://damkar.jabarprov.go.id/s=%3Esekilas-damkar-jabar',
            'https://damkar.jabarprov.go.id/s=%3Etugas-dan-fungsi',
            'https://damkar.jabarprov.go.id/s=%3Estruktur-organisasi',
            'https://damkar.jabarprov.go.id/m=%3Eberanda',
            'https://disdamkar.bandungkab.go.id/',
            'https://disdamkar.bandungkab.go.id/page/statis/sejarah',
            'https://disdamkar.bandungkab.go.id/page/statis/visimisi',
            'https://disdamkar.bandungkab.go.id/page/statis/tupoksi',
            'https://disdamkar.bandungkab.go.id/page/statis/struktur',
            'https://disdamkar.bandungkab.go.id/page/statis/sambutan',
            'https://disdamkar.bandungkab.go.id/page/statis/layanan',
            'https://disdamkar.bandungkab.go.id/page/statis/sarana',
            'https://disdamkar.bandungkab.go.id/page/statis/pengaduan',
            'https://disdamkar.bandungkab.go.id/page/statis/skm',

        ],
        '112 Kota Bandung' => [
            'https://layanan112.komdigi.go.id/',
            'https://layanan112.komdigi.go.id/alur',
            'https://layanan112.komdigi.go.id/tentang',
        ],
    ];

    public function handle()
    {
        $saved = 0;
        $failed = 0;
        $visited = [];

        $targetCluster = $this->option('cluster');
        $targetUrl = $this->option('url');

        if ($targetUrl) {
            $cluster = $targetCluster ?: 'Kependudukan';
            $result = $this->scrapePage($targetUrl, $cluster, $visited);
            $this->info("Scraping single URL selesai. Tersimpan: {$result['saved']}; gagal: {$result['failed']}.");
            return self::SUCCESS;
        }

        foreach (self::SEED_URLS as $cluster => $urls) {
            if ($targetCluster && strcasecmp($cluster, $targetCluster) !== 0) {
                continue;
            }
            foreach ($urls as $url) {
                $result = $this->scrapePage($url, $cluster, $visited);
                $saved += $result['saved'];
                $failed += $result['failed'];
            }
        }

        $this->newLine();
        $this->info("Scraping selesai. Tersimpan/diperbarui: {$saved}; gagal: {$failed}.");
        return self::SUCCESS;
    }

    private function scrapePage(string $url, string $cluster, array &$visited): array
    {
        if (isset($visited[$url]) || !$this->isAllowedUrl($url)) {
            return ['saved' => 0, 'failed' => 0];
        }

        $visited[$url] = true;
        $this->line("Mengakses [{$cluster}]: {$url}");

        try {
            if (preg_match('#disdukcapil\.bandung\.go\.id/(data-demografi/([a-zA-Z0-9\-_]+)|peta)#i', $url, $m)) {
                $sub = !empty($m[2]) ? $m[2] : 'peta';
                $saved = $this->scrapeDisdukcapil($url, $cluster, $sub) ? 1 : 0;
                return ['saved' => $saved, 'failed' => $saved === 0 ? 1 : 0];
            }

            if (preg_match('#opendata\.bandung\.go\.id/dataset/([a-zA-Z0-9\-_]+)#i', $url, $m)) {
                $saved = $this->scrapeOpenDataDataset($url, $cluster, $m[1]) ? 1 : 0;
                return ['saved' => $saved, 'failed' => $saved === 0 ? 1 : 0];
            }

            $response = $this->http()->get($url);
            if (!$response->successful()) {
                $this->warn("Dilewati {$url} (HTTP {$response->status()})");
                return ['saved' => 0, 'failed' => 1];
            }

            $contentType = strtolower($response->header('Content-Type', ''));
            if ($this->isDocument($url, $contentType)) {
                return ['saved' => $this->saveDocument($url, $cluster, $response->body(), $contentType) ? 1 : 0, 'failed' => 0];
            }

            $crawler = new Crawler($response->body(), $url);
            $crawler->filter('script, style, noscript')->each(function (Crawler $node) {
                $domNode = $node->getNode(0);
                if ($domNode && $domNode->parentNode) {
                    $domNode->parentNode->removeChild($domNode);
                }
            });
            $text = $this->cleanText($crawler->filter('body')->count() ? $crawler->filter('body')->text(' ', true) : '');
            $pageTitle = $this->pageTitle($crawler, $url);
            $keywords  = $this->buildKeywords($cluster, $pageTitle, $text, $url);
            $saved = $this->saveKnowledge($cluster, $pageTitle, $text, $url, $keywords) ? 1 : 0;
            if ($saved === 0) {
                $this->warn("Tidak ada teks layanan yang bisa diekstrak dari {$url}; kemungkinan halaman dirender oleh JavaScript.");
            }

            $crawler->filter('a[href]')->each(function (Crawler $node) use ($url, $cluster, &$visited, &$saved): void {
                $linkedUrl = $this->resolveUrl($url, $node->attr('href'));
                if ($linkedUrl && $this->isAllowedUrl($linkedUrl) && $this->isDocument($linkedUrl, '')) {
                    $result = $this->downloadAndSaveDocument($linkedUrl, $cluster, $visited);
                    $saved += $result ? 1 : 0;
                }
            });

            $crawler->filter('img')->each(function (Crawler $node) use ($url, $cluster, &$visited, &$saved): void {
                $imageUrl = $this->resolveUrl($url, $node->attr('src') ?: $node->attr('data-src'));
                if ($imageUrl && $this->isAllowedUrl($imageUrl) && $this->ocrImage($imageUrl, $cluster, $visited)) {
                    $saved++;
                }
            });

            return ['saved' => $saved, 'failed' => $saved === 0 ? 1 : 0];
        } catch (Throwable $exception) {
            $this->warn("Gagal {$url}: {$exception->getMessage()}");
            return ['saved' => 0, 'failed' => 1];
        }
    }

    private function scrapeOpenDataDataset(string $url, string $cluster, string $slug): bool
    {
        try {
            $metaRes = $this->http()->get("https://opendata.bandung.go.id/api/datasets/{$slug}");
            if (!$metaRes->successful()) {
                $this->warn("Gagal mengambil metadata dataset OpenData {$slug} (HTTP {$metaRes->status()})");
                return false;
            }

            $data = $metaRes->json('data');
            if (!$data) {
                return false;
            }

            $name = trim((string)($data['name'] ?? $slug));
            $rawDesc = strip_tags((string)($data['deskripsi'] ?? ''));
            $desc = trim((string) preg_replace('/Penjelasan mengenai variabel[\s\S]*/i', '', $rawDesc));
            $org = trim((string)($data['organisasi_name'] ?? ''));
            $topic = trim((string)($data['topik_name'] ?? ''));
            $schema = $data['schema'] ?? null;
            $table = $data['table'] ?? null;

            $textParts = [];
            $textParts[] = "Dataset: {$name}";
            if ($org) $textParts[] = "Organisasi / Produsen Data: {$org}";
            if ($topic) $textParts[] = "Topik: {$topic}";
            if ($desc) $textParts[] = "Deskripsi: {$desc}";

            if ($schema && $table) {
                $bigdataRes = $this->http()->get("https://opendata.bandung.go.id/api/bigdata/{$schema}/{$table}");
                if ($bigdataRes->successful()) {
                    $pagination = $bigdataRes->json('pagination') ?? [];
                    $totalPage = (int) ($pagination['total_page'] ?? 1);

                    // Ambil data dari halaman-halaman terakhir untuk memastikan mendapat data terbaru (tahun 2025/2026)
                    if ($totalPage > 1) {
                        $startPage = max(1, $totalPage - 1);
                        $rows = [];
                        for ($p = $startPage; $p <= $totalPage; $p++) {
                            $pageRes = $this->http()->get("https://opendata.bandung.go.id/api/bigdata/{$schema}/{$table}?page={$p}");
                            if ($pageRes->successful()) {
                                $pageRows = $pageRes->json('data') ?? [];
                                $rows = array_merge($rows, $pageRows);
                            }
                        }
                    } else {
                        $rows = $bigdataRes->json('data') ?? [];
                    }

                    if (!empty($rows)) {
                        // Urutkan baris terbaru di atas (berdasarkan tahun & semester descending jika ada)
                        usort($rows, function ($a, $b) {
                            $thnA = (int) ($a['tahun'] ?? 0);
                            $thnB = (int) ($b['tahun'] ?? 0);
                            if ($thnA !== $thnB) {
                                return $thnB <=> $thnA;
                            }
                            $semA = (int) ($a['semester'] ?? 0);
                            $semB = (int) ($b['semester'] ?? 0);
                            return $semB <=> $semA;
                        });

                        $textParts[] = "\n--- Tabel Data / Statistik (OpenData Cadangan / Fallback) ---";
                        foreach (array_slice($rows, 0, 100) as $row) {
                            $rowItems = [];
                            foreach ($row as $k => $v) {
                                if (in_array($k, ['id', 'kode_provinsi', 'nama_provinsi', 'bps_kode_kabupaten_kota', 'bps_nama_kabupaten_kota', 'cuid', 'cdate', 'mdate'], true)) {
                                    continue;
                                }
                                if ($v !== null && $v !== '') {
                                    $kClean = ucwords(str_replace('_', ' ', $k));
                                    $rowItems[] = "{$kClean}: {$v}";
                                }
                            }
                            if (!empty($rowItems)) {
                                $textParts[] = "- " . implode(", ", $rowItems);
                            }
                        }
                        if (count($rows) > 100) {
                            $textParts[] = "... (total " . count($rows) . " data baris terbaru)";
                        }
                    }
                }
            }

            $content = implode("\n", $textParts);
            $this->info("Berhasil mengekstrak OpenData: {$name} (" . count($textParts) . " bagian teks)");
            $keywords = $this->buildKeywords($cluster, $name, $content, $url);
            return $this->saveKnowledge($cluster, $name, $content, $url, $keywords);
        } catch (Throwable $e) {
            $this->warn("Gagal scrape OpenData dataset {$slug}: {$e->getMessage()}");
            return false;
        }
    }

    private function scrapeDisdukcapil(string $url, string $cluster, string $type): bool
    {
        try {
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

                $keywords = $this->buildKeywords($cluster, $topic, $content, $url);
                return $this->saveKnowledge($cluster, $topic, $content, $url, $keywords, $desc);
            }

            if ($type === 'pertumbuhan-penduduk') {
                $rawRes = $this->http()->get("https://disdukcapil.bandung.go.id/api/web/pertumbuhan-penduduk");
                if (!$rawRes->successful()) {
                    $this->warn("Gagal mengambil API pertumbuhan-penduduk Disdukcapil (HTTP {$rawRes->status()})");
                    return false;
                }
                $cleanJson = preg_replace('/^\xEF\xBB\xBF/', '', $rawRes->body());
                $data = json_decode($cleanJson, true);
                if (!$data || !isset($data['tahun'], $data['jumlah'])) {
                    $this->warn("Format data pertumbuhan-penduduk Disdukcapil tidak valid");
                    return false;
                }

                $topic = "Data Pertumbuhan dan Jumlah Penduduk Kota Bandung Disdukcapil (Tahun 2021-2025)";
                $desc = "Data tren pertumbuhan dan total jumlah penduduk Kota Bandung dari tahun 2021 hingga 2025 resmi Disdukcapil Kota Bandung.";
                $contentLines = [
                    "Instansi: Dinas Kependudukan dan Pencatatan Sipil (Disdukcapil) Kota Bandung",
                    "Judul: Data Pertumbuhan dan Total Penduduk Kota Bandung (Tahun 2021 - 2025)",
                    "Sumber Resmi: {$url}",
                    "Tahun Data: 2025 (Data Resmi Terbaru Disdukcapil)",
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
                $contentLines[] = "Kesimpulan: Berdasarkan data resmi terbaru Disdukcapil Kota Bandung tahun {$latestYear}, total jumlah penduduk Kota Bandung adalah {$latestTotal} jiwa.";
                $content = implode("\n", $contentLines);

                $keywords = $this->buildKeywords($cluster, $topic, $content, $url);
                return $this->saveKnowledge($cluster, $topic, $content, $url, $keywords, $desc);
            }

            $endpointMap = [
                'agama' => ['endpoint' => 'dkb-agama', 'topic' => 'Agama', 'cols' => ['a' => 'Islam', 'b' => 'Kristen', 'c' => 'Katholik', 'd' => 'Hindu', 'e' => 'Budha', 'f' => 'Khonghucu', 'g' => 'Kepercayaan']],
                'golongan-darah' => ['endpoint' => 'dkb-gol-drh', 'topic' => 'Golongan Darah', 'cols' => ['a' => 'A', 'b' => 'B', 'c' => 'AB', 'd' => 'O', 'e' => 'A+', 'f' => 'A-', 'g' => 'B+', 'h' => 'B-', 'i' => 'AB+', 'j' => 'AB-', 'k' => 'O+', 'l' => 'O-', 'm' => 'Tidak Tahu']],
                'jenis-kelamin' => ['endpoint' => 'dkb-kelamin', 'topic' => 'Jenis Kelamin', 'cols' => ['jumlah_l' => 'Laki-Laki', 'jumlah_p' => 'Perempuan', 'jumlah_all' => 'Total']],
                'kepala-keluarga' => ['endpoint' => 'dkb-kk', 'topic' => 'Kepala Keluarga (KK)', 'cols' => ['jumlah_l' => 'KK Laki-Laki', 'jumlah_p' => 'KK Perempuan', 'jumlah_all' => 'Total KK']],
                'pendidikan-terakhir' => ['endpoint' => 'dkb-pendidikan', 'topic' => 'Pendidikan Terakhir', 'cols' => ['a' => 'Tidak/Belum Sekolah', 'b' => 'Belum Tamat SD/Sederajat', 'c' => 'Tamat SD/Sederajat', 'd' => 'SLTP/Sederajat', 'e' => 'SLTA/Sederajat', 'f' => 'Diploma I/II', 'g' => 'Akademi/Diploma III/S.Muda', 'h' => 'Diploma IV/Strata I (S1)', 'i' => 'Strata II (S2)', 'j' => 'Strata III (S3)']],
                'jenis-pekerjaan' => ['endpoint' => 'dkb-pekerjaan', 'topic' => 'Jenis Pekerjaan', 'cols' => ['a' => 'Belum/Tidak Bekerja', 'b' => 'Aparatur/Pejabat Negara', 'c' => 'Tenaga Pengajar', 'd' => 'Wiraswasta', 'e' => 'Pertanian/Peternakan', 'f' => 'Nelayan', 'g' => 'Agama dan Kepercayaan', 'h' => 'Pelajar/Mahasiswa', 'i' => 'Tenaga Kesehatan', 'j' => 'Pensiunan', 'k' => 'Lainnya']],
                'status-perkawinan' => ['endpoint' => 'dkb-stat-kwn', 'topic' => 'Status Perkawinan', 'cols' => ['a' => 'Belum Kawin', 'b' => 'Kawin', 'c' => 'Cerai Hidup', 'd' => 'Cerai Mati']],
            ];

            if (!isset($endpointMap[$type])) {
                $this->warn("Tipe demografi Disdukcapil tidak dikenal: {$type}");
                return false;
            }

            $cfg = $endpointMap[$type];
            $rawRes = $this->http()->get("https://disdukcapil.bandung.go.id/api/web/{$cfg['endpoint']}");
            if (!$rawRes->successful()) {
                $this->warn("Gagal mengambil API {$cfg['endpoint']} Disdukcapil (HTTP {$rawRes->status()})");
                return false;
            }

            $cleanJson = preg_replace('/^\xEF\xBB\xBF/', '', $rawRes->body());
            $rows = json_decode($cleanJson, true);
            if (!is_array($rows) || empty($rows)) {
                $this->warn("Data kosong dari API {$cfg['endpoint']} Disdukcapil");
                return false;
            }

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
            $keywords = $this->buildKeywords($cluster, $topic, $content, $url);
            $this->info("Berhasil mengekstrak data demografi Disdukcapil: {$topic}");
            return $this->saveKnowledge($cluster, $topic, $content, $url, $keywords, $desc);
        } catch (Throwable $e) {
            $this->warn("Gagal scrape Disdukcapil {$type}: {$e->getMessage()}");
            return false;
        }
    }

    private function downloadAndSaveDocument(string $url, string $cluster, array &$visited): bool
    {
        if (isset($visited[$url])) {
            return false;
        }
        $visited[$url] = true;

        try {
            $response = $this->http()->get($url);
            return $response->successful() && $this->saveDocument($url, $cluster, $response->body(), strtolower($response->header('Content-Type', '')));
        } catch (Throwable $exception) {
            $this->warn("Gagal mengambil dokumen {$url}: {$exception->getMessage()}");
            return false;
        }
    }

    private function ocrImage(string $url, string $cluster, array &$visited): bool
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff'], true)) {
            return false;
        }
        if (isset($visited[$url])) {
            return false;
        }
        $visited[$url] = true;
        $executable = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        if (!is_file($executable)) {
            return false;
        }

        $language = is_file('C:\\Program Files\\Tesseract-OCR\\tessdata\\ind.traineddata') ? 'ind' : 'eng';
        if (!is_file("C:\\Program Files\\Tesseract-OCR\\tessdata\\{$language}.traineddata")) {
            return false;
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'bandung_ocr_');
        try {
            $response = $this->http()->get($url);
            if (!$response->successful() || !$temporaryFile || file_put_contents($temporaryFile, $response->body()) === false) {
                return false;
            }

            $text = (new TesseractOCR($temporaryFile))
                ->executable($executable)
                ->lang($language)
                ->run();

            // Gunakan nama file gambar sebagai topik (lebih deskriptif daripada 'Teks gambar layanan')
            $imgFilename = urldecode(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME));
            $imgTopic    = preg_replace('/[_\-]+/', ' ', $imgFilename) ?: "Gambar Layanan {$cluster}";
            $keywords    = $this->buildKeywords($cluster, $imgTopic, (string) $text, $url);
            return $this->saveKnowledge($cluster, $imgTopic, (string) $text, $url, $keywords);
        } catch (Throwable $exception) {
            $this->warn("OCR dilewati {$url}: {$exception->getMessage()}");
            return false;
        } finally {
            if ($temporaryFile && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function saveDocument(string $url, string $cluster, string $body, string $contentType): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $text = match ($extension) {
            'csv' => $this->csvText($body),
            'xlsx' => $this->xlsxText($body),
            'docx' => $this->docxText($body),
            'pdf' => $this->pdfText($body),
            'txt', 'md', 'json', 'xml', 'html', 'htm' => strip_tags($body),
            default => '',
        };

        if ($text === '' && str_contains($contentType, 'text/')) {
            $text = strip_tags($body);
        }

        $filename = urldecode(basename($path));
        $topicTitle = preg_replace('/[_\-]+/', ' ', pathinfo($filename, PATHINFO_FILENAME)) ?: "Dokumen {$cluster}";
        $topicTitle = trim((string) $topicTitle);
        $keywords   = $this->buildKeywords($cluster, $topicTitle, $text, $url);

        return $this->saveKnowledge($cluster, $topicTitle, $text, $url, $keywords);
    }

    /**
     * Ekstrak kata kunci penting dari konten berdasarkan frekuensi kemunculan kata.
     * Menghapus stopwords bahasa Indonesia, lalu mengambil top-15 kata dengan frekuensi tertinggi.
     */
    private function extractContentKeywords(string $content, int $limit = 15): array
    {
        // Ubah ke lowercase & hapus karakter non-huruf (angka dipertahankan)
        $clean = mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $content) ?? '');

        // Tokenisasi per kata
        $words = preg_split('/\s+/', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Buang stopwords dan kata pendek (< 4 huruf)
        $stopwords = array_flip(self::STOPWORDS_ID);
        $filtered = array_filter($words, fn($w) => mb_strlen($w) >= 4 && !isset($stopwords[$w]));

        if (empty($filtered)) {
            return [];
        }

        // Hitung frekuensi
        $freq = array_count_values($filtered);
        arsort($freq);

        // Ambil top-$limit kata
        return array_keys(array_slice($freq, 0, $limit, true));
    }

    /**
     * Bangun array keyword komprehensif:
     * [sinonim klaster] + [kata kunci penting dari konten] + [kata dari topik] + source URL
     */
    private function buildKeywords(string $cluster, string $topic, string $content, string $url): array
    {
        // 1. Sinonim dari kamus klaster
        $synonyms = self::SYNONYM_MAP[$cluster] ?? [];

        // 2. Kata kunci dari konten
        $contentKeywords = $this->extractContentKeywords($content);

        // 3. Kata-kata penting dari judul topik (minimal 4 huruf, bukan stopword)
        $stopwords = array_flip(self::STOPWORDS_ID);
        $topicWords = array_filter(
            preg_split('/\s+/', mb_strtolower($topic), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn($w) => mb_strlen($w) >= 4 && !isset($stopwords[$w])
        );

        // 4. Gabungkan semua dan hapus duplikasi
        $merged = array_unique(array_merge($synonyms, $contentKeywords, array_values($topicWords)));

        // 5. Tambahkan source URL dan cluster di akhir
        $merged[] = "source:{$url}";
        $merged[] = "cluster:{$cluster}";

        return array_values($merged);
    }

    private function saveKnowledge(
    string $cluster,
    string $topic, 
    string $content, 
    string $url, 
    array|string $keywords = [], 
    ?string $description = null
): bool {
    $content = $this->cleanText($content);
    if ($content === '') {
        return false;
    }

    $content = mb_substr($content, 0, self::MAX_CONTENT_LENGTH);

    // Jika caller tidak mengirim keywords, bangun secara otomatis menggunakan buildKeywords
    if (empty($keywords)) {
        $keywords = $this->buildKeywords($cluster, $topic, $content, $url);
    }

    KnowledgeBase::updateOrCreate(
        ['url' => $url], // Unik berdasarkan URL agar data ter-update saat di-scrape ulang
        [
            'cluster'     => $cluster,
            'topic'       => mb_substr($topic, 0, 255),
            'content'     => $content,
            'keywords'    => $keywords,
            'description' => $description,
        ]
    );

    $this->info("Tersimpan: [{$cluster}] {$topic}");
    return true;
}

    private function http()
    {
        return Http::withoutVerifying()
            ->connectTimeout(15)
            ->timeout(180)
            ->retry(1, 1000, throw: false)
            ->withHeaders(['User-Agent' => 'Bandung-RAG-Bot/1.0']);
    }

    private function isAllowedUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        $allowedSuffixes = [
            'bandung.go.id',
            'jabarprov.go.id',
            'komdigi.go.id',
            'lapor.go.id',
            'bandungkab.go.id',
            'rsudcicalengka.id',
            'sdkdinkesbdg.com',
            'wikipedia.org',
            'google.com',
            'ppid.bandung.go.id',
            'lapor.go.id',
            'opendata.bandung.go.id',
            'disbudpar.bandung.go.id',
            'instagram.com',
            'radarbandung.id',
        ];

        foreach ($allowedSuffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isDocument(string $url, string $contentType): bool
    {
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($extension, ['csv', 'xlsx', 'docx', 'txt', 'md', 'json', 'xml', 'pdf'], true)
            || str_contains($contentType, 'application/pdf')
            || str_contains($contentType, 'text/plain')
            || str_contains($contentType, 'text/csv');
    }

    private function resolveUrl(string $baseUrl, ?string $link): ?string
    {
        if (!$link || str_starts_with($link, '#') || str_starts_with(strtolower($link), 'javascript:')) {
            return null;
        }
        if (str_starts_with($link, '//')) {
            return parse_url($baseUrl, PHP_URL_SCHEME) . ':' . $link;
        }
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            return $link;
        }
        $base = parse_url($baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }
        $path = str_starts_with($link, '/') ? $link : rtrim(dirname($base['path'] ?? '/'), '/') . '/' . $link;
        return $base['scheme'] . '://' . $base['host'] . '/' . ltrim($path, '/');
    }

    private function pageTitle(Crawler $crawler, string $url): string
    {
        return $crawler->filter('title')->count() ? trim($crawler->filter('title')->text()) : (parse_url($url, PHP_URL_HOST) ?: 'Layanan Bandung');
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $text);
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);
        $text = preg_replace('/localStorage\..*?;/s', '', $text);
        $text = preg_replace('/\(function\(.*?\)\{.*?\}\)\(.*?\);/s', '', $text);
        $text = strip_tags($text);
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function csvText(string $body): string
    {
        $rows = preg_split('/\r\n|\r|\n/', $body) ?: [];
        return implode("\n", array_map(static fn ($row) => implode(' | ', str_getcsv($row)), $rows));
    }

    private function xlsxText(string $body): string
    {
        if (!class_exists(ZipArchive::class)) {
            return '';
        }
        $zip = new ZipArchive();
        $temporaryFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        file_put_contents($temporaryFile, $body);
        if ($zip->open($temporaryFile) !== true) {
            @unlink($temporaryFile);
            return '';
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false && ($document = simplexml_load_string($xml))) {
            foreach ($document->si as $item) {
                $shared[] = (string) ($item->t ?: implode('', array_map('strval', $item->r->t ?? [])));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();
        @unlink($temporaryFile);
        if (!$sheet || !($document = simplexml_load_string($sheet))) {
            return '';
        }
        $values = [];
        foreach ($document->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $value = (string) $cell->v;
                $cells[] = ((string) $cell['t'] === 's') ? ($shared[(int) $value] ?? $value) : $value;
            }
            $values[] = implode(' | ', $cells);
        }
        return implode("\n", $values);
    }

    private function docxText(string $body): string
    {
        if (!class_exists(ZipArchive::class)) {
            return '';
        }
        $zip = new ZipArchive();
        $temporaryFile = tempnam(sys_get_temp_dir(), 'docx_');
        file_put_contents($temporaryFile, $body);
        $opened = $zip->open($temporaryFile) === true;
        $xml = $opened ? ($zip->getFromName('word/document.xml') ?: '') : '';
        if ($opened) {
            $zip->close();
        }
        @unlink($temporaryFile);
        return $xml ? strip_tags(str_replace('</w:p>', "\n", $xml)) : '';
    }

// Tambahkan parser PDF di dalam kelas ScrapeDiskominfo
    private function parsePdf(string $url, string $cluster): array
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($url);
            $text = trim($pdf->getText());

            // Jika PDF berupa hasil scan image / kosong, gunakan OCR
            if (empty($text) && class_exists(\Thiagoalessio\TesseractOCR\TesseractOCR::class)) {
                // Logika fallback Tesseract OCR jika dibutuhkan
            }

            return [
                'title'       => urldecode(basename($url)),
                'content'     => preg_replace('/\s+/', ' ', $text),
                'keywords'    => [$cluster, 'Dokumen PDF', 'Layanan Resmi'],
                'description' => "Dokumen PDF resmi terkait {$cluster} Kota Bandung.",
            ];
        } catch (\Throwable $e) {
            $this->error("Gagal parse PDF [{$url}]: " . $e->getMessage());
            return [];
        }
    }

    private function pdfText(string $body): string
    {
        if ($body === '') {
            return '';
        }

        if (class_exists(PdfParser::class)) {
            try {
                $parser = new PdfParser();
                $pdf = $parser->parseContent($body);
                $text = $pdf->getText();
                if (is_string($text) && trim($text) !== '') {
                    return $this->cleanText($text);
                }
            } catch (Throwable $exception) {
                $this->warn('Gagal parsing PDF dengan library: ' . $exception->getMessage());
            }
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'bandung_pdf_');
        if ($temporaryFile !== false && file_put_contents($temporaryFile, $body) !== false) {
            $text = $this->pdfTextFromCommand($temporaryFile);
            @unlink($temporaryFile);

            if (trim($text) !== '') {
                return $this->cleanText($text);
            }
        }

        return $this->extractTextFromPdfFallback($body);
    }

    private function pdfTextFromCommand(string $temporaryFile): string
    {
        $commands = [
            'pdftotext -layout "' . escapeshellarg($temporaryFile) . '" - 2>/dev/null',
            'pdftotext "' . escapeshellarg($temporaryFile) . '" - 2>/dev/null',
        ];

        foreach ($commands as $command) {
            $output = [];
            $status = 0;
            exec($command, $output, $status);

            if ($status === 0 && !empty($output)) {
                return implode("\n", $output);
            }
        }

        $where = PHP_OS_FAMILY === 'Windows'
            ? 'where pdftotext 2>NUL'
            : 'command -v pdftotext 2>/dev/null';

        $output = [];
        exec($where, $output, $status);
        if ($status !== 0) {
            return '';
        }

        $command = PHP_OS_FAMILY === 'Windows'
            ? 'pdftotext.exe "' . str_replace('"', '\\"', $temporaryFile) . '" -'
            : 'pdftotext "' . escapeshellarg($temporaryFile) . '" - 2>/dev/null';

        $output = [];
        exec($command, $output, $status);

        return $status === 0 ? implode("\n", $output) : '';
    }

    private function extractTextFromPdfFallback(string $body): string
    {
        $patterns = [
            '/\(.*?\)\s*Tj/ui',
            '/BT.*?ET/si',
            '/\(.*?\)\s*Tj\s*\(.*?\)\s*Tj/ui',
        ];

        $text = '';
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $body, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $match) {
                    $candidate = preg_replace('/\s*\(.*?\)\s*Tj/ui', ' ', $match);
                    $candidate = preg_replace('/[\x00-\x1F\x7F]/', '', $candidate);
                    $candidate = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $text .= ' ' . $candidate;
                }
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}