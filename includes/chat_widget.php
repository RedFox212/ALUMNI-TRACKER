<!-- includes/chat_widget.php - Premium Floating Chatbox -->
<?php $chat_role = $_SESSION['user_role'] ?? 'alumni'; ?>

<div id="chat-widget" class="fixed bottom-6 right-6 z-[100] flex flex-col items-end pointer-events-none">
    
    <!-- Chat Window (Hidden by default) -->
    <div id="chat-window" class="mb-4 w-[380px] h-[550px] bg-white/80 dark:bg-slate-900/90 backdrop-blur-2xl rounded-[40px] shadow-2xl border border-slate-100 dark:border-slate-800 flex flex-col overflow-hidden pointer-events-auto transform translate-y-8 opacity-0 scale-95 transition-all duration-300 pointer-events-none hidden">
        
        <!-- Header -->
        <div class="p-6 bg-slate-900 dark:bg-slate-800 text-white flex items-center justify-between shadow-lg">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-2xl flex items-center justify-center font-black text-xl shadow-lg ring-2 ring-white/10">
                    <?php echo $chat_role === 'admin' ? '🛡️' : '👨‍🎓'; ?>
                </div>
                <div>
                    <h3 class="font-black italic tracking-tighter uppercase text-sm"><?php echo $chat_role === 'admin' ? 'Support Inbox' : 'Lyceum Support'; ?></h3>
                    <p class="text-[10px] text-blue-200 font-bold uppercase tracking-widest leading-none mt-1">Live Chat Active</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button onclick="toggleSettings()" class="p-2 hover:bg-white/10 rounded-xl transition-all" title="Chat Settings">
                    <svg class="w-5 h-5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
                <button onclick="toggleChatWindow()" class="p-2 hover:bg-white/10 rounded-xl transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <!-- Conversations List (Admin Only) -->
        <?php if ($chat_role === 'admin'): ?>
        <div id="conv-list" class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50/50 dark:bg-slate-900/50 relative">
            <!-- Populated by JS -->
        </div>
        <?php endif; ?>

        <!-- Settings Overlay -->
        <div id="chat-settings" class="absolute inset-0 bg-slate-900/90 backdrop-blur-md z-40 p-10 flex flex-col items-center justify-center text-center transform translate-y-full transition-transform duration-300 pointer-events-none opacity-0">
            <h4 class="font-black italic text-white text-lg uppercase tracking-tighter mb-8">Chat Settings</h4>
            <div class="space-y-4 w-full">
                <button onclick="toggleMute()" id="mute-btn" class="w-full py-4 bg-white/10 hover:bg-white/20 text-white rounded-3xl text-xs font-black uppercase tracking-widest flex items-center justify-center gap-3 transition-all">
                    <span id="mute-icon">🔔</span> <span id="mute-text">Mute Notifications</span>
                </button>
                <button onclick="clearHistory()" class="w-full py-4 bg-rose-500/20 hover:bg-rose-500 text-rose-500 hover:text-white rounded-3xl text-xs font-black uppercase tracking-widest flex items-center justify-center gap-3 transition-all">
                    🗑️ Delete Conversation
                </button>
                <button onclick="toggleSettings()" class="w-full py-4 text-slate-400 hover:text-white text-[10px] font-black uppercase tracking-widest transition-all mt-6">
                    Back to Messages
                </button>
            </div>
        </div>

        <!-- Messages Area -->
        <div id="msg-area" class="flex-1 overflow-y-auto p-6 space-y-4 <?php echo $chat_role === 'admin' ? 'hidden' : ''; ?> bg-slate-50/30 dark:bg-slate-900/30 scroll-smooth">
            <!-- Messages go here -->
            <div class="text-center py-10 opacity-20">
                <p class="text-xs font-black uppercase tracking-widest">Starting secure channel...</p>
            </div>
        </div>

        <!-- Admin Back Button -->
        <?php if ($chat_role === 'admin'): ?>
        <button id="back-to-conv" class="hidden px-6 py-2 bg-slate-100 dark:bg-slate-800 text-slate-500 font-bold text-[10px] uppercase tracking-widest border-t border-slate-200 dark:border-slate-700 hover:text-blue-600 transition-all text-center">
            Back to Inbox
        </button>
        <?php endif; ?>

        <!-- Input Area -->
        <form id="chat-form" class="p-4 bg-white dark:bg-slate-900 border-t border-slate-100 dark:border-slate-800 flex gap-2 items-center <?php echo $chat_role === 'admin' ? 'hidden' : ''; ?>">
            <input type="hidden" id="chat-receiver-id" value="1">
            <input type="text" id="chat-input" placeholder="Type message..." class="flex-1 h-12 bg-slate-50 dark:bg-slate-800 rounded-2xl px-5 text-sm font-medium outline-none focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900 transition-all text-slate-800 dark:text-white border border-slate-100 dark:border-slate-700">
            <button type="submit" class="w-12 h-12 bg-blue-600 text-white rounded-2xl shadow-lg shadow-blue-200 flex items-center justify-center hover:bg-blue-700 transition-all group overflow-hidden active:scale-95">
                <svg class="w-5 h-5 group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
            </button>
        </form>
    </div>

    <!-- Bubble -->
    <button onclick="toggleChatWindow()" id="chat-bubble" class="w-16 h-16 bg-blue-600 rounded-[28px] shadow-2xl flex items-center justify-center text-white ring-4 ring-white/20 hover:scale-110 active:scale-95 transition-all pointer-events-auto transform hover:rotate-6">
        <svg id="bubble-icon" class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
        </svg>
    </button>
</div>

<script>
let currentChatWith = <?php echo $chat_role === 'admin' ? '0' : '1'; ?>;
let isChatOpen = false;
let pollingInterval = null;

function toggleChatWindow() {
    const win = document.getElementById('chat-window');
    const bubble = document.getElementById('chat-bubble');
    isChatOpen = !isChatOpen;

    if (isChatOpen) {
        win.classList.remove('hidden');
        setTimeout(() => {
            win.classList.remove('translate-y-8', 'opacity-0', 'scale-95', 'pointer-events-none');
            bubble.classList.add('rotate-180', 'scale-90', 'opacity-50');
        }, 10);
        startPolling();
    } else {
        win.classList.add('translate-y-8', 'opacity-0', 'scale-95', 'pointer-events-none');
        bubble.classList.remove('rotate-180', 'scale-90', 'opacity-50');
        setTimeout(() => win.classList.add('hidden'), 300);
        stopPolling();
    }
}

function startPolling() {
    refreshChat();
    pollingInterval = setInterval(refreshChat, 3000);
}

function stopPolling() {
    clearInterval(pollingInterval);
}

async function refreshChat() {
    const role = "<?php echo $chat_role; ?>";
    
    if (role === 'admin' && currentChatWith === 0) {
        // Fetch conversations list
        const res = await fetch('../ajax/chat_handler.php?action=conversations');
        const data = await res.json();
        const list = document.getElementById('conv-list');
        list.innerHTML = data.map(c => `
            <div onclick="openConversation(${c.id}, '${c.name}')" class="p-4 bg-white dark:bg-slate-800 rounded-3xl border border-slate-100 dark:border-slate-700 flex items-center justify-between cursor-pointer hover:border-blue-500 transition-all group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center font-black text-sm uppercase">
                        ${c.name.charAt(0)}
                    </div>
                    <div>
                        <p class="text-xs font-black dark:text-white uppercase tracking-tighter">${c.name}</p>
                        <p class="text-[10px] text-slate-400 truncate w-40 italic">${c.last_msg || 'New conversation'}</p>
                    </div>
                </div>
                ${c.unread_count > 0 ? `<div class="bg-blue-600 text-white text-[10px] font-black w-5 h-5 rounded-full flex items-center justify-center shadow-lg shadow-blue-200">${c.unread_count}</div>` : ''}
            </div>
        `).join('');
    } else {
        // Fetch specific messages
        const res = await fetch(`../ajax/chat_handler.php?action=messages&with=${currentChatWith}`);
        const data = await res.json();
        const area = document.getElementById('msg-area');
        const wasScrolledDown = area.scrollHeight - area.scrollTop <= area.clientHeight + 100;
        
        const myId = <?php echo $_SESSION['user_id']; ?>;
        area.innerHTML = data.length ? data.map(m => `
            <div class="flex ${m.sender_id == myId ? 'justify-end' : 'justify-start'}">
                <div class="max-w-[80%] p-4 rounded-[24px] ${m.sender_id == myId ? 'bg-blue-600 text-white rounded-br-none' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-bl-none'} text-sm font-medium shadow-sm">
                    ${m.message}
                    <p class="text-[8px] opacity-50 mt-1 uppercase font-black text-right">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                </div>
            </div>
        `).join('') : '<p class="text-center py-20 text-[10px] text-slate-300 font-bold uppercase tracking-[2px]">No messages yet.</p>';
        
        if (wasScrolledDown) area.scrollTop = area.scrollHeight;
    }

    // Global Badge Refresh
    const resCount = await fetch('../ajax/chat_handler.php?action=unread_total');
    const unreadTotal = parseInt(await resCount.text()) || 0;
    const badge = document.getElementById('dashboard-unread-count');
    const container = document.getElementById('unread-badge-container');
    if (badge) {
        badge.innerText = unreadTotal;
        if (container) {
            if (unreadTotal > 0) {
                container.className = 'bg-blue-600 text-white w-16 h-16 rounded-3xl flex items-center justify-center shadow-inner animate-pulse';
            } else {
                container.className = 'bg-slate-50 text-slate-400 w-16 h-16 rounded-3xl flex items-center justify-center shadow-inner animate-none';
            }
        }
    }
}

function toggleSettings() {
    const settings = document.getElementById('chat-settings');
    const isVisible = !settings.classList.contains('translate-y-full');
    
    if (isVisible) {
        settings.classList.add('translate-y-full', 'pointer-events-none', 'opacity-0');
    } else {
        settings.classList.remove('translate-y-full', 'pointer-events-none', 'opacity-0');
        updateMuteUI();
    }
}

function updateMuteUI() {
    const isMuted = localStorage.getItem('chat_muted') === 'true';
    document.getElementById('mute-icon').innerText = isMuted ? '🔕' : '🔔';
    document.getElementById('mute-text').innerText = isMuted ? 'Unmute Notifications' : 'Mute Notifications';
    document.getElementById('mute-btn').classList.toggle('bg-blue-600/20', isMuted);
}

function toggleMute() {
    const current = localStorage.getItem('chat_muted') === 'true';
    localStorage.setItem('chat_muted', !current);
    updateMuteUI();
}

async function clearHistory() {
    if (!confirm('This will delete your entire conversation with this user locally. Proceed?')) return;
    
    await fetch(`../ajax/chat_handler.php?action=clear&with=${currentChatWith}`);
    toggleSettings();
    refreshChat();
}

function openConversation(id, name) {
    currentChatWith = id;
    document.getElementById('chat-receiver-id').value = id;
    document.getElementById('conv-list').classList.add('hidden');
    document.getElementById('msg-area').classList.remove('hidden');
    document.getElementById('chat-form').classList.remove('hidden');
    document.getElementById('back-to-conv').classList.remove('hidden');
    refreshChat();
}

document.getElementById('back-to-conv')?.addEventListener('click', () => {
    currentChatWith = 0;
    document.getElementById('conv-list').classList.remove('hidden');
    document.getElementById('msg-area').classList.add('hidden');
    document.getElementById('chat-form').classList.add('hidden');
    document.getElementById('back-to-conv').classList.add('hidden');
    refreshChat();
});

document.getElementById('chat-form').onsubmit = async (e) => {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;
    
    const formData = new FormData();
    formData.append('message', msg);
    formData.append('receiver_id', document.getElementById('chat-receiver-id').value);
    
    input.value = '';
    await fetch('../ajax/chat_handler.php?action=send', {
        method: 'POST',
        body: formData
    });
    refreshChat();
};
</script>
