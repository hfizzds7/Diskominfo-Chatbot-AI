# 🤖 Panduan Setup Chatbot AI - Diskominfo

## ❌ Masalah yang Diperbaiki

1. **API Key Kosong** - Laravel AI tidak dapat terhubung ke AI provider
2. **Error Handling** - Backend tidak menangani error dengan baik
3. **Frontend Error** - User interface tidak menampilkan error dengan jelas

---

## ✅ Solusi yang Diterapkan

### 1. File `.env` - Ditambahkan AI Configuration

```bash
# Buka file .env dan pastikan ada:
GEMINI_API_KEY=your_gemini_api_key_here
OPENAI_API_KEY=
```

### 2. ChatbotController - Ditambahkan Error Handling

- Try-catch untuk menangkap error dari AI provider
- Logging untuk debugging
- User-friendly error messages

### 3. Frontend (chat.blade.php) - Ditambahkan Error Display

- Better error feedback
- Loading state pada tombol "Kirim"
- Console logging untuk debugging

---

## 🔧 LANGKAH-LANGKAH SETUP

### **LANGKAH 1: Dapatkan Gemini API Key**

1. Buka https://aistudio.google.com
2. Login dengan akun Google Anda
3. Klik "Get API Key" → "Create API key in new project"
4. Copy API key yang dibuat

### **LANGKAH 2: Tambahkan API Key ke `.env`**

1. Buka file: `chatbot_ai/.env`
2. Cari baris: `GEMINI_API_KEY=`
3. Ganti dengan: `GEMINI_API_KEY=sk-...` (paste API key Anda)
4. Simpan file

### **LANGKAH 3: Clear Laravel Cache**

Buka terminal di folder `chatbot_ai/` dan jalankan:

```bash
php artisan cache:clear
php artisan config:clear
```

### **LANGKAH 4: Test Aplikasi**

1. Buka: http://localhost:8000/chat
2. Ketik pesan di form chat
3. Klik tombol "Kirim"
4. Tunggu respon dari AI

---

## 🐛 Debugging Jika Masih Error

Jika masih mendapat error 500, cek:

1. **Check Laravel Log** (untuk melihat error detail):
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Verifikasi API Key** - Pastikan:
   - API key sudah dikopy dengan benar
   - Tidak ada spasi di awal/akhir
   - API key aktif di Google Gemini

3. **Check Database Connection** (untuk session):
   ```bash
   php artisan migrate
   ```

4. **Browser Console** (tekan F12):
   - Lihat Network tab → chat/send request
   - Lihat Response untuk detail error

---

## 📚 Alternatif Provider AI

Jika ingin menggunakan provider lain:

### **OpenAI (ChatGPT)**
```env
OPENAI_API_KEY=sk-... (dari https://platform.openai.com)
```

### **Ollama (Lokal - Gratis)**
```bash
# Install Ollama: https://ollama.ai
# Jalankan: ollama run llama2
# Tidak perlu API key
```

### **Anthropic (Claude)**
```env
ANTHROPIC_API_KEY=... (dari https://console.anthropic.com)
```

---

## 📞 Troubleshooting

| Error | Solusi |
|-------|--------|
| `Incorrect API key provided` | Cek API key di `.env` dan pastikan aktif |
| `Connection timeout` | Pastikan internet stabil |
| `Method not found` | Jalankan `composer update` |
| `Database error` | Jalankan `php artisan migrate` |
| `CSRF token mismatch` | Refresh page dan coba lagi |

---

## 🎯 Next Steps

- [ ] Dapatkan API Key Gemini
- [ ] Update file `.env`
- [ ] Clear cache: `php artisan cache:clear`
- [ ] Test di browser
- [ ] Cek Laravel log jika error

**Good luck! 🚀**
