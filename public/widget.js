(function() {
    // Ambil URL dasar secara otomatis dari lokasi script ini dimuat
    var scriptUrl = document.currentScript.src;
    var BASE_URL = scriptUrl.substring(0, scriptUrl.lastIndexOf('/'));
    
    // Jika user tidak sengaja membuka via double-click (file:///) maka paksa ke localhost:8000
    if (BASE_URL.startsWith('file://') || !BASE_URL.startsWith('http')) {
        BASE_URL = 'http://localhost:8000';
    }

    // Buat elemen tombol melayang (Floating Button)
    var btn = document.createElement('div');
    btn.innerHTML = `
        <div id="chatbot-widget-btn" style="position:fixed; bottom:20px; right:20px; width:60px; height:60px; background-color:#0a3663; color:white; border-radius:50%; display:flex; justify-content:center; align-items:center; cursor:pointer; box-shadow:0 4px 6px rgba(0,0,0,0.3); z-index:999999; transition:transform 0.3s;">
            <svg id="chatbot-icon-open" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            <svg id="chatbot-icon-close" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </div>
    `;
    document.body.appendChild(btn);

    // Buat elemen Iframe (jendela chat)
    var iframeContainer = document.createElement('div');
    iframeContainer.innerHTML = `
        <div id="chatbot-widget-window" style="position:fixed; bottom:90px; right:20px; width:350px; height:500px; max-height:80vh; max-width:90vw; background-color:white; border-radius:15px; box-shadow:0 10px 25px rgba(0,0,0,0.2); z-index:999999; display:none; overflow:hidden; border: 1px solid #ddd;">
            <iframe src="${BASE_URL}/widget" width="100%" height="100%" style="border:none;"></iframe>
        </div>
    `;
    document.body.appendChild(iframeContainer);

    var isChatOpen = false;
    var chatBtn = document.getElementById('chatbot-widget-btn');
    var chatWindow = document.getElementById('chatbot-widget-window');

    var iconOpen = document.getElementById('chatbot-icon-open');
    var iconClose = document.getElementById('chatbot-icon-close');

    chatBtn.addEventListener('click', function() {
        isChatOpen = !isChatOpen;
        if(isChatOpen) {
            chatWindow.style.display = 'block';
            iconOpen.style.display = 'none';
            iconClose.style.display = 'block';
            chatBtn.style.transform = 'rotate(90deg)';
        } else {
            chatWindow.style.display = 'none';
            iconOpen.style.display = 'block';
            iconClose.style.display = 'none';
            chatBtn.style.transform = 'rotate(0deg)';
        }
    });
})();
