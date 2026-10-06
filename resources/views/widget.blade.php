<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Widget</title>

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
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
        }

        .chat-header {
            background: linear-gradient(135deg, var(--diskominfo-primary) 0%, #004b8d 100%);
            padding: 12px 16px;
            flex-shrink: 0;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: #22c55e;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { box-shadow: 0 0 0 4px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        #chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background-color: #ffffff;
            scroll-behavior: smooth;
        }

        .message-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 16px;
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
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
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

        .message-wrapper {
            max-width: 85%;
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
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.85rem;
            line-height: 1.4;
            position: relative;
            word-break: break-word;
        }

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
            font-size: 0.65rem;
            color: #94a3b8;
            margin-top: 4px;
            display: block;
        }

        .user .message-time {
            text-align: right;
        }

        .bot-message p { margin-bottom: 0.5rem; }
        .bot-message p:last-child { margin-bottom: 0; }
        .bot-message ul, .bot-message ol { margin-bottom: 0.5rem; padding-left: 1.2rem; }
        .bot-message code { background: #e2e8f0; padding: 2px 4px; border-radius: 4px; color: #0f172a; }

        .quick-chips-wrapper {
            padding: 8px 12px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            white-space: nowrap;
            overflow-x: auto;
            flex-shrink: 0;
        }

        .chip-btn {
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: var(--diskominfo-primary);
            border-radius: 20px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .chip-btn:hover {
            background-color: var(--diskominfo-primary);
            color: #ffffff;
            border-color: var(--diskominfo-primary);
        }

        .typing-indicator {
            display: flex;
            gap: 4px;
            align-items: center;
            padding: 6px 4px;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
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
            padding: 10px 12px;
            border-top: 1px solid #e2e8f0;
            flex-shrink: 0;
        }

        .chat-input {
            border-radius: 20px 0 0 20px;
            padding: 8px 15px;
            border: 1px solid #cbd5e1;
            font-size: 0.85rem;
        }

        .chat-input:focus {
            box-shadow: none;
            border-color: var(--diskominfo-primary);
        }

        .send-btn {
            border-radius: 0 20px 20px 0;
            background-color: var(--diskominfo-primary);
            border-color: var(--diskominfo-primary);
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .send-btn:hover {
            background-color: #002855;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Header -->
        <div class="chat-header text-white d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-white p-1 rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="bi bi-robot text-primary fs-5"></i>
                </div>
                <div>
                    <h6 class="mb-0 fw-bold" style="font-size: 0.85rem;">{{ $botName ?? 'Pemerintah Kota Bandung' }}</h6>
                    <small class="text-white-50 d-flex align-items-center gap-1" style="font-size: 0.65rem;">
                        <span class="status-dot"></span> Online 24 jam
                    </small>
                </div>
            </div>
            <button id="clear-chat" class="btn btn-outline-light btn-sm border-0 rounded-circle p-1" title="Bersihkan Percakapan">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
        </div>

        <!-- Chat Messages Area -->
        <div id="chat-messages">
            <!-- Welcome Message -->
            <div class="message-row bot">
                <div class="avatar bot-avatar"><i class="bi bi-robot"></i></div>
                <div class="message-wrapper">
                    <div class="message-content bot-message">
                        {{ $welcomeMessage ?? 'Sampurasun! 🙏 Selamat datang di Pelayanan Informasi Pemerintah Kota Bandung. Ada yang bisa saya bantu hari ini? ☺️' }}
                    </div>
                    <span class="message-time" id="welcome-time"></span>
                </div>
            </div>
        </div>

        <!-- Quick Suggestions / Chips -->
        <div class="quick-chips-wrapper d-flex gap-2">
            <button class="chip-btn" data-query="Informasi PPID">
                <i class="bi bi-file-earmark-text"></i> Info PPID
            </button>
            <button class="chip-btn" data-query="Alur Pengaduan">
                <i class="bi bi-megaphone"></i> Pengaduan
            </button>
            <button class="chip-btn" data-query="Jam Operasional">
                <i class="bi bi-clock"></i> Jam Kerja
            </button>
            <button class="chip-btn" data-query="Berita terkini Kota Bandung">
                <i class="bi bi-newspaper"></i> Berita Terkini
            </button>
        </div>

        <!-- Input Form -->
        <div class="chat-input-area">
            <form id="chat-form">
                <div class="input-group">
                    <input type="text" id="user-input" class="form-control chat-input" placeholder="Tuliskan pesan..." autocomplete="off" required>
                    <button type="submit" id="send-btn" class="btn btn-primary send-btn">
                        <span id="btn-text"><i class="bi bi-send-fill"></i></span>
                    </button>
                </div>
            </form>
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

            document.getElementById('welcome-time').textContent = getCurrentTime();

            chatForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const message = userInput.value.trim();
                if (!message) return;
                processUserMessage(message);
            });

            chipBtns.forEach(chip => {
                chip.addEventListener('click', () => {
                    processUserMessage(chip.getAttribute('data-query'));
                });
            });

            async function processUserMessage(message) {
                addMessage(message, 'user');
                userInput.value = '';

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
                        const errorMsg = data.message || 'Maaf, terjadi gangguan sistem.';
                        addMessage('⚠️ ' + errorMsg, 'bot');
                    }
                } catch (error) {
                    removeTypingIndicator();
                    addMessage('⚠️ Gagal terhubung ke server.', 'bot');
                } finally {
                    setLoadingState(false);
                }
            }

            function addMessage(text, side) {
                const messageRow = document.createElement('div');
                messageRow.className = `message-row ${side}`;
                const avatarHTML = side === 'bot' 
                    ? `<div class="avatar bot-avatar"><i class="bi bi-robot"></i></div>` 
                    : `<div class="avatar user-avatar"><i class="bi bi-person-fill"></i></div>`;
                const formattedContent = side === 'bot' ? marked.parse(text) : escapeHtml(text);

                messageRow.innerHTML = `
                    ${avatarHTML}
                    <div class="message-wrapper">
                        <div class="message-content ${side}-message">${formattedContent}</div>
                        <span class="message-time">${getCurrentTime()}</span>
                    </div>
                `;
                chatMessages.appendChild(messageRow);
                scrollToBottom();
            }

            function showTypingIndicator() {
                const typingRow = document.createElement('div');
                typingRow.id = 'typing-indicator-row';
                typingRow.className = 'message-row bot';
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

            function removeTypingIndicator() {
                const indicator = document.getElementById('typing-indicator-row');
                if (indicator) indicator.remove();
            }

            function setLoadingState(isLoading) {
                sendBtn.disabled = isLoading;
                userInput.disabled = isLoading;
                if (isLoading) {
                    btnText.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span>`;
                } else {
                    btnText.innerHTML = `<i class="bi bi-send-fill"></i>`;
                    userInput.focus();
                }
            }

            clearChatBtn.addEventListener('click', () => {
                if (confirm('Bersihkan percakapan?')) {
                    chatMessages.innerHTML = '';
                    addMessage('Sampurasun! 🙏 Percakapan telah dibersihkan.', 'bot');
                }
            });

            function scrollToBottom() {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            function getCurrentTime() {
                return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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