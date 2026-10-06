<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot AI - Diskominfo Kota Bandung</title>

    <!-- Google Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Marked.js for Markdown Formatting -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <style>
        :root {
            --diskominfo-primary: #0a3663;
            --diskominfo-secondary: #008891;
            --diskominfo-accent: #f5a623;
            --bg-light: #f4f7f6;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            background-image: radial-gradient(#d1d5db 0.75px, transparent 0.75px);
            background-size: 16px 16px;
            min-height: 100vh;
        }

        .chat-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(10, 54, 99, 0.1);
        }

        .chat-header {
            background: linear-gradient(135deg, var(--diskominfo-primary) 0%, #004b8d 100%);
            padding: 18px 24px;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            background-color: #22c55e;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        #chat-messages {
            height: 480px;
            overflow-y: auto;
            padding: 20px;
            background-color: #ffffff;
            scroll-behavior: smooth;
        }

        .message-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 18px;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-row.user {
            flex-direction: row-reverse;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .bot-avatar {
            background-color: var(--diskominfo-primary);
            color: #ffffff;
        }

        .user-avatar {
            background-color: var(--diskominfo-secondary);
            color: #ffffff;
        }

        /* --- PERBAIKAN CSS DIMULAI DI SINI --- */
        .message-wrapper {
            max-width: 75%;
            display: flex;
            flex-direction: column;
        }

        .message-row.user .message-wrapper {
            align-items: flex-end; 
        }

        .message-row.bot .message-wrapper {
            align-items: flex-start; 
        }

        .message-content {
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 0.93rem;
            line-height: 1.5;
            position: relative;
            word-break: break-word;
        }
        /* --- PERBAIKAN CSS SELESAI DI SINI --- */

        .bot-message {
            background-color: #f0f4f8;
            color: #1e293b;
            border-top-left-radius: 2px;
        }

        .user-message {
            background-color: var(--diskominfo-primary);
            color: #ffffff;
            border-top-right-radius: 2px;
        }

        .message-time {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 4px;
            display: block;
        }

        .user .message-time {
            text-align: right;
        }

        /* Markdown Styling inside Bot Messages */
        .bot-message p { margin-bottom: 0.5rem; }
        .bot-message p:last-child { margin-bottom: 0; }
        .bot-message ul, .bot-message ol { margin-bottom: 0.5rem; padding-left: 1.2rem; }
        .bot-message code { background: #e2e8f0; padding: 2px 4px; border-radius: 4px; color: #0f172a; }

        /* Quick Chips */
        .quick-chips-wrapper {
            padding: 10px 20px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            white-space: nowrap;
            overflow-x: auto;
        }

        .chip-btn {
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: var(--diskominfo-primary);
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 0.82rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .chip-btn:hover {
            background-color: var(--diskominfo-primary);
            color: #ffffff;
            border-color: var(--diskominfo-primary);
        }

        /* Typing Indicator */
        .typing-indicator {
            display: flex;
            gap: 4px;
            align-items: center;
            padding: 6px 4px;
        }

        .typing-dot {
            width: 7px;
            height: 7px;
            background-color: #94a3b8;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        .chat-input-area {
            background-color: #ffffff;
            padding: 15px 20px;
            border-top: 1px solid #e2e8f0;
        }

        .chat-input {
            border-radius: 25px 0 0 25px;
            padding: 10px 20px;
            border: 1px solid #cbd5e1;
        }

        .chat-input:focus {
            box-shadow: none;
            border-color: var(--diskominfo-primary);
        }

        .send-btn {
            border-radius: 0 25px 25px 0;
            background-color: var(--diskominfo-primary);
            border-color: var(--diskominfo-primary);
            padding: 10px 22px;
            font-weight: 600;
        }

        .send-btn:hover {
            background-color: #002855;
        }
    </style>
</head>
<body>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="card chat-card">
                    
                    <!-- Header -->
                    <div class="chat-header text-white d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-white p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-robot text-primary fs-4"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">{{ $botName ?? 'Pemerintah Kota Bandung' }}</h6>
                                <small class="text-white-50 d-flex align-items-center gap-2" style="font-size: 0.78rem;">
                                    <span class="status-dot"></span> Layanan Informasi Publik 24 jam
                                </small>
                            </div>
                        </div>
                        <button id="clear-chat" class="btn btn-outline-light btn-sm border-0 rounded-circle" title="Bersihkan Percakapan">
                            <i class="bi bi-trash3 fs-6"></i>
                        </button>
                    </div>

                    <!-- Chat Messages Area -->
                    <div id="chat-messages">
                        <!-- Welcome Message -->
                        <div class="message-row bot">
                            <div class="avatar bot-avatar"><i class="bi bi-robot"></i></div>
                            <!-- PERBAIKAN: Penambahan div.message-wrapper -->
                            <div class="message-wrapper">
                                <div class="message-content bot-message">
                                    {{ $welcomeMessage ?? 'Sampurasun! 🙏 Selamat datang di Pelayanan Informasi Pemerintah Kota Bandung. Ada yang bisa saya bantu hari ini? 😊' }}
                                </div>
                                <span class="message-time" id="welcome-time"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Suggestions / Chips -->
                    <div class="quick-chips-wrapper d-flex gap-2">
                        <button class="chip-btn" data-query="Bagaimana cara mengajukan permohonan informasi PPID?">
                            <i class="bi bi-file-earmark-text"></i> Informasi PPID
                        </button>
                        <button class="chip-btn" data-query="Bagaimana alur pengaduan via SP4N-LAPOR!?">
                            <i class="bi bi-megaphone"></i> Alur Pengaduan
                        </button>
                        <button class="chip-btn" data-query="Di mana alamat dan jam operasional Kantor Diskominfo Bandung?">
                            <i class="bi bi-clock"></i> Jam Operasional
                        </button>
                        <button class="chip-btn" data-query="Berita terkini Kota Bandung">
                            <i class="bi bi-newspaper"></i> Berita Terkini
                        </button>
                    </div>

                    <!-- Input Form -->
                    <div class="chat-input-area">
                        <form id="chat-form">
                            <div class="input-group">
                                <input type="text" id="user-input" class="form-control chat-input" placeholder="Tuliskan pertanyaan Anda..." autocomplete="off" required>
                                <button type="submit" id="send-btn" class="btn btn-primary send-btn">
                                    <span id="btn-text"><i class="bi bi-send-fill me-1"></i> Kirim</span>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
                
                <!-- Footer Info -->
                <div class="text-center mt-3 text-muted small">
                    &copy; {{ date('Y') }} Dinas Komunikasi dan Informatika Kota Bandung.
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chatForm = document.getElementById('chat-form');
            const userInput = document.getElementById('user-input');
            const chatMessages = document.getElementById('chat-messages');
            const sendBtn = document.getElementById('send-btn');
            const btnText = document.getElementById('btn-text');
            const clearChatBtn = document.getElementById('clear-chat');
            const chipBtns = document.querySelectorAll('.chip-btn');

            // Set waktu pesan selamat datang
            document.getElementById('welcome-time').textContent = getCurrentTime();

            // Handle Form Submit
            chatForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const message = userInput.value.trim();
                if (!message) return;

                processUserMessage(message);
            });

            // Handle Quick Chips Click
            chipBtns.forEach(chip => {
                chip.addEventListener('click', () => {
                    const query = chip.getAttribute('data-query');
                    processUserMessage(query);
                });
            });

            // Process and Send Message
            async function processUserMessage(message) {
                addMessage(message, 'user');
                userInput.value = '';

                // Disable UI state
                setLoadingState(true);
                showTypingIndicator();

                try {
                    const response = await fetch('{{ route('chat.send') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ message })
                    });

                    if (response.status === 429) {
                        removeTypingIndicator();
                        addMessage('⚠️ Anda telah mencapai batas pengiriman pesan (10 pertanyaan per jam). Silakan coba beberapa saat lagi.', 'bot');
                        setLoadingState(false);
                        return;
                    }

                    const data = await response.json();
                    removeTypingIndicator();

                    if (data.status === 'success') {
                        addMessage(data.message, 'bot');
                    } else {
                        const errorMsg = data.message || 'Mohon maaf, terjadi gangguan sistem. Silakan coba beberapa saat lagi.';
                        addMessage('⚠️ ' + errorMsg, 'bot');
                    }
                } catch (error) {
                    removeTypingIndicator();
                    console.error('Error:', error);
                    addMessage('⚠️ Gagal terhubung ke server. Pastikan koneksi internet Anda stabil.', 'bot');
                } finally {
                    setLoadingState(false);
                }
            }

            // Append Message to UI
            function addMessage(text, side) {
                const messageRow = document.createElement('div');
                messageRow.className = `message-row ${side}`;

                const avatarHTML = side === 'bot' 
                    ? `<div class="avatar bot-avatar"><i class="bi bi-robot"></i></div>` 
                    : `<div class="avatar user-avatar"><i class="bi bi-person-fill"></i></div>`;

                // Render Markdown for Bot, Plain Text for User
                const formattedContent = side === 'bot' ? marked.parse(text) : escapeHtml(text);

                // PERBAIKAN: Penambahan div.message-wrapper
                messageRow.innerHTML = `
                    ${avatarHTML}
                    <div class="message-wrapper">
                        <div class="message-content ${side}-message">
                            ${formattedContent}
                        </div>
                        <span class="message-time">${getCurrentTime()}</span>
                    </div>
                `;

                chatMessages.appendChild(messageRow);
                scrollToBottom();
            }

            // Show Typing Indicator
            function showTypingIndicator() {
                const typingRow = document.createElement('div');
                typingRow.id = 'typing-indicator-row';
                typingRow.className = 'message-row bot';
                
                // PERBAIKAN: Penambahan div.message-wrapper
                typingRow.innerHTML = `
                    <div class="avatar bot-avatar"><i class="bi bi-robot"></i></div>
                    <div class="message-wrapper">
                        <div class="message-content bot-message">
                            <div class="typing-indicator">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                            </div>
                        </div>
                    </div>
                `;
                chatMessages.appendChild(typingRow);
                scrollToBottom();
            }

            // Remove Typing Indicator
            function removeTypingIndicator() {
                const indicator = document.getElementById('typing-indicator-row');
                if (indicator) indicator.remove();
            }

            // Toggle Loading State
            function setLoadingState(isLoading) {
                sendBtn.disabled = isLoading;
                userInput.disabled = isLoading;
                if (isLoading) {
                    btnText.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span>`;
                } else {
                    btnText.innerHTML = `<i class="bi bi-send-fill me-1"></i> Kirim`;
                    userInput.focus();
                }
            }

            // Clear Chat History
            clearChatBtn.addEventListener('click', () => {
                if (confirm('Bersihkan seluruh percakapan?')) {
                    chatMessages.innerHTML = '';
                    addMessage('Sampurasun! 🙏 Percakapan telah dibersihkan. Ada yang bisa saya bantu?', 'bot');
                }
            });

            // Helper Functions
            function scrollToBottom() {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            function getCurrentTime() {
                const now = new Date();
                return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        });
    </script>

</body>
</html>