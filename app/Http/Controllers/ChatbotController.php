<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatAgent;
use App\Services\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ChatbotController extends Controller
{
    public function __construct(
        protected RagService $ragService
    ) {}

    public function widget()
    {
        $settings = \Illuminate\Support\Facades\Schema::hasTable('settings')
            ? \Illuminate\Support\Facades\DB::table('settings')->pluck('value', 'key')->toArray()
            : [];

        $botName = $settings['bot_name'] ?? 'Pemerintah Kota Bandung';
        $welcomeMessage = $settings['welcome_message'] ?? 'Sampurasun! 🙏 Selamat datang di Pelayanan Informasi Pemerintah Kota Bandung. Ada yang bisa saya bantu hari ini? ☺️';

        return view('widget', compact('botName', 'welcomeMessage'));
    }

    public function index()
    {
        $settings = \Illuminate\Support\Facades\Schema::hasTable('settings')
            ? \Illuminate\Support\Facades\DB::table('settings')->pluck('value', 'key')->toArray()
            : [];

        $botName = $settings['bot_name'] ?? 'Pemerintah Kota Bandung';
        $welcomeMessage = $settings['welcome_message'] ?? 'Sampurasun! 🙏 Selamat datang di Pelayanan Informasi Pemerintah Kota Bandung. Ada yang bisa saya bantu hari ini? 😊';

        return view('chat', compact('botName', 'welcomeMessage'));
    }

    public function health()
    {
        try {
            $kbCount = \App\Models\KnowledgeBase::count();
            return response()->json([
                'status' => 'healthy',
                'database' => 'connected',
                'knowledge_base_records' => $kbCount,
                'ai_provider' => config('ai.default'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'database' => 'disconnected',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $userMessage = trim($request->input('message'));

        try {
            $cacheKey = $this->getCacheKey($userMessage);

            // 1. Cek apakah jawaban untuk pertanyaan ini sudah pernah di-cache / tersimpan di memori
            if (Cache::has($cacheKey)) {
                $cachedReply = Cache::get($cacheKey);
                if (! empty($cachedReply)) {
                    return response()->json([
                        'status' => 'success',
                        'message' => $cachedReply,
                        'cached' => true,
                    ]);
                }
            }

            // Deteksi jika pertanyaan meminta daftar, list, semua, atau rekomendasi
            $isListQuery = (bool) preg_match('/\b(daftar|semua|macam|apa saja|rekomendasi|list|beberapa|kumpulan|sebutkan|cari|puskesmas|hotel|taman|wisata|rs|rumah sakit|klinik|kampus|universitas|sekolah|tujuan|tempat)\b/iu', $userMessage);
            
            // Atur limit lebih tinggi (misal 25) jika user meminta daftar/banyak data
            $limit = $isListQuery ? 25 : 5;

            // 2. Ambil data referensi yang paling relevan dari database via RagService
            $context = $this->ragService->retrieveContext($userMessage, $limit);

            // 3. Suntikkan dokumen relevan ke Agent dan minta respon dari AI
            $agent = new ChatAgent($context);
            $response = $agent->prompt($userMessage);
            $aiReply = (string) $response;

            // 4. Simpan jawaban ke Cache jika respon valid (tersimpan selama 30 hari)
            if (! empty(trim($aiReply))) {
                Cache::put($cacheKey, $aiReply, now()->addDays(30));
            }

            return response()->json([
                'status' => 'success',
                'message' => $aiReply,
                'cached' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Chat error: ' . $e->getMessage());

            $errorMessage = 'Maaf, terjadi kendala saat memproses pertanyaan Anda. ';

            if (str_contains($e->getMessage(), 'API key')) {
                $errorMessage .= 'API key tidak dikonfigurasi dengan benar.';
            } elseif (str_contains($e->getMessage(), 'Connection') || str_contains($e->getMessage(), 'cURL')) {
                $errorMessage .= 'Tidak dapat terhubung ke AI service.';
            } elseif (str_contains($e->getMessage(), 'rate limited') || str_contains($e->getMessage(), 'Resource has been exhausted')) {
                $errorMessage .= 'Batas permintaan API AI sedang penuh. Silakan coba beberapa saat lagi.';
            } else {
                $errorMessage .= 'Silakan coba beberapa saat lagi.';
            }

            return response()->json([
                'status' => 'error',
                'message' => $errorMessage,
                'cached' => false,
            ], 200);
        }
    }

    /**
     * Menghasilkan cache key unik dan ternormalisasi untuk pertanyaan pengguna.
     * Mendukung pemetaan kanonikal untuk pertanyaan gabungan alamat & jam operasional.
     */
    protected function getCacheKey(string $userMessage): string
    {
        // 1. Normalisasi dasar: huruf kecil, bersihkan tanda baca di akhir, dan rapikan spasi
        $normalized = trim(preg_replace('/[?!.,;:"]+$/u', '', mb_strtolower($userMessage, 'UTF-8')));
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized));

        // 2. Deteksi pertanyaan kombinasi Alamat & Jam Operasional instansi secara sekaligus
        $isAskingAddress = (bool) preg_match('/\b(alamat|lokasi|tempat|kantor|gedung)\b/iu', $normalized);
        $isAskingHours = (bool) preg_match('/\b(jam|operasional|jadwal|waktu|buka|tutup|hari kerja)\b/iu', $normalized);

        if ($isAskingAddress && $isAskingHours) {
            $intentWords = [
                'alamat', 'lokasi', 'tempat', 'kantor', 'gedung',
                'jam', 'operasional', 'jadwal', 'waktu', 'buka', 'tutup', 'hari', 'kerja',
                'dan', 'atau', 'di', 'ke', 'dari', 'pada', 'untuk', 'tentang', 'mengenai',
                'apakah', 'apa', 'bagaimana', 'berapa', 'tolong', 'mohon', 'minta', 'info', 'informasi',
                'kota', 'bandung', 'resmi'
            ];
            $words = explode(' ', $normalized);
            $targetWords = array_values(array_filter($words, fn($w) => ! in_array($w, $intentWords) && mb_strlen($w) > 1));
            sort($targetWords);
            $targetEntity = implode('_', $targetWords);

            if (! empty($targetEntity)) {
                return 'chat_response_addr_hours_' . $targetEntity;
            }
        }

        // 3. Normalisasi pertanyaan umum (hilangkan kata tanya pembuka yang sepadan)
        $cleanGeneral = preg_replace('/^(apakah|berapa|siapakah|siapa|bagaimanakah|bagaimana|kapan|dimanakah|dimana|tolong|mohon|info|minta)\s+/iu', '', $normalized);

        return 'chat_response_' . md5($cleanGeneral);
    }
}