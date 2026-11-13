// dashboard/assets/js/agent.js - LinkPilot Autonomous Agent Interface

(function () {
    let activeConversationId = null;
    let conversationsList = [];
    let isGenerating = false;
    let abortController = null;
    let attachedFile = null;
    let isHistoryOpen = false; // Collapsed by default for conversation-first focus

    /**
     * Main entry point when routing to #/agent
     */
    window.renderAgentView = async function (container, params = {}) {
        // Hide floating AI chatbot widget on agent tab to prevent competing UI
        const floatingChatbotBtn = document.getElementById('ai-chat-trigger-btn');
        if (floatingChatbotBtn) floatingChatbotBtn.style.display = 'none';
        const floatingDrawer = document.getElementById('ai-chat-assistant-drawer');
        if (floatingDrawer) floatingDrawer.classList.add('translate-x-full');

        // Collapsed by default
        isHistoryOpen = false;

        container.innerHTML = `
            <div id="agent-view-root" class="flex h-full w-full overflow-hidden bg-[#f8fafc] text-slate-800 relative">
                <!-- 1. COLLAPSIBLE CONVERSATION HISTORY PANEL (Collapsed by default, ~260px when open) -->
                <div id="agent-history-sidebar" class="w-0 opacity-0 pointer-events-none border-r-0 overflow-hidden bg-white border-slate-200/90 flex flex-col shrink-0 transition-all duration-200 z-30 fixed lg:relative inset-y-0 left-0 -translate-x-full lg:translate-x-0 h-full">
                    <div class="w-[260px] flex flex-col h-full">
                        <!-- Sidebar Top Header -->
                        <div class="h-13 px-3.5 border-b border-slate-200/80 flex items-center justify-between shrink-0 bg-white">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="clock" class="h-4 w-4 text-slate-500"></i>
                                <span class="text-xs font-semibold text-slate-800 tracking-tight">Chat History</span>
                            </div>
                            <button onclick="toggleAgentHistorySidebar(false)" class="p-1 text-slate-400 hover:text-slate-700 rounded-md hover:bg-slate-100 transition cursor-pointer" title="Close history">
                                <i data-lucide="chevron-left" class="h-4 w-4"></i>
                            </button>
                        </div>

                        <!-- New Chat & Search in History -->
                        <div class="p-2.5 space-y-2 border-b border-slate-200/80 bg-white shrink-0">
                            <button onclick="createNewAgentChat()" class="w-full py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white rounded-lg text-xs font-semibold transition shadow-xs flex items-center justify-center space-x-1.5 cursor-pointer">
                                <i data-lucide="plus" class="h-3.5 w-3.5 text-white"></i>
                                <span>New Chat</span>
                            </button>
                            <div class="relative">
                                <input type="text" id="agent-chat-search-input" oninput="filterAgentConversations(this.value)" placeholder="Search chats..." class="w-full pl-7 pr-2.5 py-1.5 bg-slate-100/80 border border-slate-200/80 focus:border-indigo-500 focus:bg-white rounded-lg text-xs outline-none transition placeholder-slate-400 text-slate-800">
                                <i data-lucide="search" class="h-3.5 w-3.5 text-slate-400 absolute left-2 top-2"></i>
                            </div>
                        </div>

                        <!-- Conversation History List -->
                        <div class="flex-grow overflow-y-auto p-2 space-y-3.5 agent-custom-scrollbar" id="agent-history-list">
                            <div class="flex items-center justify-center py-8 text-slate-400 text-xs">
                                <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin mr-2 text-indigo-500"></i> Loading...
                            </div>
                        </div>

                        <!-- Sidebar Footer -->
                        <div class="p-2.5 border-t border-slate-200/80 bg-slate-50/70 flex items-center justify-between text-[11px] text-slate-600 shrink-0">
                            <span class="font-medium flex items-center text-slate-600">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                LinkPilot Agent
                            </span>
                            <button onclick="openAgentCapabilitiesModal()" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold cursor-pointer">
                                Capabilities
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Backdrop on Mobile -->
                <div id="agent-history-backdrop" onclick="toggleAgentHistorySidebar(false)" class="hidden fixed inset-0 bg-slate-900/30 backdrop-blur-xs z-20 lg:hidden"></div>

                <!-- 2. RIGHT MAIN CHAT AREA (Conversation-First) -->
                <main class="flex-1 flex flex-col h-full min-w-0 bg-[#f8fafc] relative overflow-hidden">
                    <!-- Top Bar Header (Compact: 52px height) -->
                    <header id="agent-chat-header" class="h-13 px-4 md:px-6 bg-white border-b border-slate-200/80 flex items-center justify-between shrink-0 z-20">
                        <!-- Left: Sidebar toggle, Conversation Title, Online status -->
                        <div class="flex items-center space-x-3 min-w-0">
                            <button onclick="toggleAgentHistorySidebar()" class="agent-history-toggle-btn p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-slate-800 transition cursor-pointer border border-slate-200/80" title="Toggle chat history" aria-expanded="false">
                                <i data-lucide="panel-left" class="h-4 w-4"></i>
                            </button>
                            <div class="flex items-center space-x-2.5 min-w-0">
                                <h2 id="agent-active-title" class="text-sm font-semibold text-slate-900 truncate">New Conversation</h2>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/60 shrink-0">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Online
                                </span>
                            </div>
                        </div>

                        <!-- Right: What Can I Do & New Conversation buttons -->
                        <div class="flex items-center space-x-2 shrink-0">
                            <button onclick="openAgentCapabilitiesModal()" class="px-2.5 py-1.5 text-xs text-slate-600 hover:text-slate-900 hover:bg-slate-100 rounded-lg border border-slate-200/80 flex items-center gap-1.5 transition cursor-pointer" title="View autonomous agent tools & capabilities">
                                <i data-lucide="sparkles" class="h-3.5 w-3.5 text-indigo-500"></i>
                                <span class="hidden sm:inline">Capabilities</span>
                            </button>
                            <button onclick="createNewAgentChat()" class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-2xs flex items-center gap-1.5 transition cursor-pointer" title="Start new conversation">
                                <i data-lucide="plus" class="h-3.5 w-3.5 text-white"></i>
                                <span class="hidden sm:inline">New Chat</span>
                            </button>
                        </div>
                    </header>

                    <!-- Scrollable Messages Area -->
                    <div class="flex-1 min-h-0 overflow-y-auto px-4 md:px-6 py-6 agent-custom-scrollbar" id="agent-messages-container">
                        <!-- Messages or Welcome State injected dynamically -->
                    </div>

                    <!-- Floating Bottom Composer Area -->
                    <div id="agent-composer-bar" class="shrink-0 px-4 md:px-6 pb-4 pt-1 z-10">
                        <div class="max-w-3xl mx-auto space-y-1.5">
                            <!-- Attachment preview pill -->
                            <div id="agent-attachment-preview" class="hidden flex items-center space-x-2 px-2.5 py-1 bg-indigo-50 border border-indigo-200/90 rounded-lg w-max text-xs text-indigo-900 shadow-2xs">
                                <i data-lucide="paperclip" class="h-3.5 w-3.5 text-indigo-600"></i>
                                <span id="agent-attachment-name" class="font-medium truncate max-w-xs">file.pdf</span>
                                <button onclick="clearAgentAttachment()" class="hover:text-rose-600 p-0.5 cursor-pointer" title="Remove attachment"><i data-lucide="x" class="h-3 w-3"></i></button>
                            </div>

                            <!-- Floating Composer Card -->
                            <form id="agent-composer-form" onsubmit="handleAgentSendMessage(event)" class="relative flex flex-col bg-white border border-slate-200/90 hover:border-slate-300 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/15 rounded-2xl shadow-sm transition-all duration-150 p-2 md:p-2.5">
                                <input type="file" id="agent-file-picker" class="hidden" onchange="handleAgentFileSelected(event)">

                                <textarea id="agent-user-prompt-input" rows="1" placeholder="Ask LinkPilot Agent anything or enter a business command..." onkeydown="handleAgentTextareaKeydown(event)" oninput="autoResizeTextarea(this)" class="w-full bg-transparent border-0 focus:outline-none focus:ring-0 text-xs md:text-sm text-slate-800 placeholder-slate-400 font-normal px-1 py-1 max-h-36 resize-none leading-relaxed"></textarea>

                                <div class="flex items-center justify-between pt-1.5 mt-0.5 border-t border-slate-100">
                                    <div class="flex items-center space-x-1">
                                        <button type="button" onclick="document.getElementById('agent-file-picker').click()" class="p-1.5 text-slate-400 hover:text-slate-600 transition rounded-lg hover:bg-slate-100 cursor-pointer" title="Attach file or screenshot">
                                            <i data-lucide="paperclip" class="h-4 w-4"></i>
                                        </button>
                                        <button type="button" onclick="openAgentCapabilitiesModal()" class="px-2 py-1 text-slate-400 hover:text-indigo-600 transition rounded-lg hover:bg-slate-100 flex items-center space-x-1 cursor-pointer" title="Agent Capabilities & Tools">
                                            <i data-lucide="sparkles" class="h-3.5 w-3.5 text-indigo-500"></i>
                                            <span class="text-[11px] font-medium text-slate-500">Tools</span>
                                        </button>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <span class="text-[11px] text-slate-400 hidden sm:inline-block">↵ to send</span>
                                        <button type="submit" id="agent-send-button" class="h-7 w-7 md:h-8 md:w-8 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white rounded-lg transition shadow-xs flex items-center justify-center shrink-0 cursor-pointer disabled:opacity-50" title="Send message">
                                            <i data-lucide="arrow-up" class="h-4 w-4 text-white"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </main>
            </div>

            <!-- Capabilities Modal Root -->
            <div id="agent-capabilities-modal-root"></div>
        `;

        if (window.lucide) lucide.createIcons();

        // Load conversation list and active conversation
        await loadAgentConversations(params.id || null);
    };

    /**
     * Toggles collapsible chat history panel.
     */
    window.toggleAgentHistorySidebar = function (forceState = null) {
        isHistoryOpen = (forceState !== null) ? forceState : !isHistoryOpen;
        applyHistorySidebarState();
    };

    function applyHistorySidebarState() {
        const sidebar = document.getElementById('agent-history-sidebar');
        const backdrop = document.getElementById('agent-history-backdrop');
        const toggleBtns = document.querySelectorAll('.agent-history-toggle-btn');
        if (!sidebar) return;

        if (isHistoryOpen) {
            sidebar.classList.remove('w-0', 'opacity-0', 'pointer-events-none', 'border-r-0', '-translate-x-full');
            sidebar.classList.add('w-[260px]', 'opacity-100', 'pointer-events-auto', 'border-r', 'translate-x-0');
            if (backdrop) backdrop.classList.remove('hidden');
            toggleBtns.forEach(btn => btn.setAttribute('aria-expanded', 'true'));
        } else {
            sidebar.classList.remove('w-[260px]', 'opacity-100', 'pointer-events-auto', 'border-r', 'translate-x-0');
            sidebar.classList.add('w-0', 'opacity-0', 'pointer-events-none', 'border-r-0', '-translate-x-full');
            if (backdrop) backdrop.classList.add('hidden');
            toggleBtns.forEach(btn => btn.setAttribute('aria-expanded', 'false'));
        }
    };

    /**
     * Auto-resizes textarea as content expands.
     */
    window.autoResizeTextarea = function (textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 140) + 'px';
    };

    /**
     * Enter to send, Shift+Enter for newline.
     */
    window.handleAgentTextareaKeydown = function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleAgentSendMessage(e);
        }
    };

    /**
     * Load conversations list from backend.
     */
    async function loadAgentConversations(targetConvId = null) {
        try {
            const res = await apiCall('crm/agent.php?action=list_conversations');
            conversationsList = (res && res.data && res.data.conversations) ? res.data.conversations : [];
            renderConversationsList();

            if (targetConvId) {
                await selectAgentConversation(targetConvId, false);
            } else if (conversationsList.length > 0) {
                await selectAgentConversation(conversationsList[0].id, false);
            } else {
                await createNewAgentChat(false);
            }
        } catch (err) {
            console.error("Failed to load conversations:", err);
            renderEmptyWelcomeState();
        }
    }

    /**
     * Filter conversations by search term.
     */
    window.filterAgentConversations = function (query) {
        const q = query.toLowerCase().trim();
        const filtered = conversationsList.filter(c => c.title.toLowerCase().includes(q) || (c.last_message && c.last_message.toLowerCase().includes(q)));
        renderConversationsList(filtered);
    };

    /**
     * Render conversation history grouped by date.
     */
    function renderConversationsList(list = conversationsList) {
        const container = document.getElementById('agent-history-list');
        if (!container) return;

        if (list.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-slate-400 text-xs">
                    <p>No conversations found</p>
                </div>
            `;
            return;
        }

        // Group into Today, Yesterday, Previous 7 Days, Older
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        const sevenDaysAgo = new Date(today);
        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

        const groups = {
            'Today': [],
            'Yesterday': [],
            'Previous 7 Days': [],
            'Older': []
        };

        list.forEach(c => {
            const d = new Date(c.updated_at || c.created_at);
            if (d >= today) groups['Today'].push(c);
            else if (d >= yesterday) groups['Yesterday'].push(c);
            else if (d >= sevenDaysAgo) groups['Previous 7 Days'].push(c);
            else groups['Older'].push(c);
        });

        let html = '';
        for (const [groupName, items] of Object.entries(groups)) {
            if (items.length === 0) continue;

            html += `
                <div>
                    <h6 class="px-2 mb-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">${groupName}</h6>
                    <div class="space-y-0.5">
            `;

            items.forEach(c => {
                const isActive = (c.id === activeConversationId);
                const activeClasses = isActive
                    ? 'bg-indigo-50/90 text-indigo-950 font-semibold border-l-3 border-indigo-600 pl-2.5 shadow-2xs agent-conv-active'
                    : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950 border-l-3 border-transparent pl-2.5 agent-conv-item';

                html += `
                    <div onclick="selectAgentConversation('${c.id}')" class="group flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs transition cursor-pointer ${activeClasses}">
                        <div class="flex items-center space-x-2 truncate">
                            <i data-lucide="message-square" class="h-3.5 w-3.5 shrink-0 ${isActive ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600'}"></i>
                            <span class="truncate max-w-[145px] ${isActive ? 'text-indigo-950 font-semibold' : 'text-slate-700 font-normal'}" title="${escapeHtml(c.title)}">${escapeHtml(c.title)}</span>
                        </div>
                        <div class="flex items-center space-x-0.5 opacity-0 group-hover:opacity-100 transition">
                            <button onclick="promptRenameAgentConversation(event, '${c.id}', '${escapeHtml(c.title)}')" class="p-1 hover:text-indigo-600 text-slate-400 rounded hover:bg-slate-200/60" title="Rename"><i data-lucide="edit-2" class="h-3 w-3"></i></button>
                            <button onclick="deleteAgentConversation(event, '${c.id}')" class="p-1 hover:text-rose-600 text-slate-400 rounded hover:bg-slate-200/60" title="Delete"><i data-lucide="trash-2" class="h-3 w-3"></i></button>
                        </div>
                    </div>
                `;
            });

            html += `</div></div>`;
        }

        container.innerHTML = html;
        if (window.lucide) lucide.createIcons();
    }

    /**
     * Start a fresh conversation.
     */
    window.createNewAgentChat = async function (updateUrl = true) {
        if (window.innerWidth < 1024 && isHistoryOpen) {
            toggleAgentHistorySidebar(false);
        }
        const titleEl = document.getElementById('agent-active-title');
        if (titleEl) titleEl.textContent = 'New Conversation';

        try {
            const res = await apiCall('crm/agent.php', 'POST', { action: 'create_conversation', title: 'New Conversation' });
            if (res && res.data && res.data.conversation) {
                const newConv = res.data.conversation;
                activeConversationId = newConv.id;
                window.activeAgentConversationId = newConv.id;
                conversationsList.unshift(newConv);
                renderConversationsList();
                renderEmptyWelcomeState();
                if (updateUrl) {
                    const targetHash = `#/agent?id=${encodeURIComponent(newConv.id)}`;
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState(null, '', targetHash);
                    } else if (window.location.hash !== targetHash) {
                        window.location.hash = targetHash;
                    }
                }
            }
        } catch (err) {
            console.error("Failed to create new conversation:", err);
            renderEmptyWelcomeState();
        }

        const input = document.getElementById('agent-user-prompt-input');
        if (input) input.focus();
    };

    /**
     * Switch active conversation.
     */
    window.selectAgentConversation = async function (convId, updateUrl = true) {
        if (!convId) return;
        if (window.innerWidth < 1024 && isHistoryOpen) {
            toggleAgentHistorySidebar(false);
        }
        activeConversationId = convId;
        window.activeAgentConversationId = convId;
        renderConversationsList();

        if (updateUrl) {
            const targetHash = `#/agent?id=${encodeURIComponent(convId)}`;
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', targetHash);
            } else if (window.location.hash !== targetHash) {
                window.location.hash = targetHash;
            }
        }

        const messagesContainer = document.getElementById('agent-messages-container');
        if (!messagesContainer) return;

        messagesContainer.innerHTML = `
            <div class="flex flex-col items-center justify-center py-20 text-slate-400 text-xs">
                <i data-lucide="loader-2" class="h-5 w-5 animate-spin text-indigo-600 mb-2"></i>
                <span>Loading messages...</span>
            </div>
        `;
        if (window.lucide) lucide.createIcons();

        try {
            const res = await apiCall(`crm/agent.php?action=get_conversation&id=${encodeURIComponent(convId)}`);
            if (res && res.data) {
                const conv = res.data.conversation;
                const messages = res.data.messages || [];

                const titleEl = document.getElementById('agent-active-title');
                if (titleEl) titleEl.textContent = conv.title || 'LinkPilot Autonomous Agent';

                if (messages.length === 0) {
                    renderEmptyWelcomeState();
                } else {
                    renderMessagesStream(messages);
                }
            }
        } catch (err) {
            console.error("Failed to load conversation:", err);
            renderEmptyWelcomeState();
        }

        const input = document.getElementById('agent-user-prompt-input');
        if (input) input.focus();
    };

    /**
     * Rename conversation modal/prompt.
     */
    window.promptRenameAgentConversation = async function (event, convId, currentTitle) {
        event.stopPropagation();
        const newTitle = prompt("Enter new conversation title:", currentTitle);
        if (newTitle && newTitle.trim() && newTitle.trim() !== currentTitle) {
            try {
                await apiCall('crm/agent.php', 'POST', { action: 'rename_conversation', id: convId, title: newTitle.trim() });
                const item = conversationsList.find(c => c.id === convId);
                if (item) item.title = newTitle.trim();
                renderConversationsList();
                if (activeConversationId === convId) {
                    const titleEl = document.getElementById('agent-active-title');
                    if (titleEl) titleEl.textContent = newTitle.trim();
                }
            } catch (err) {
                showNotification('error', 'Failed to rename conversation.');
            }
        }
    };

    /**
     * Delete conversation.
     */
    window.deleteAgentConversation = async function (event, convId) {
        event.stopPropagation();
        if (!confirm("Are you sure you want to delete this conversation? This action cannot be undone.")) return;

        try {
            await apiCall('crm/agent.php', 'POST', { action: 'delete_conversation', id: convId });
            conversationsList = conversationsList.filter(c => c.id !== convId);
            renderConversationsList();

            if (activeConversationId === convId) {
                if (conversationsList.length > 0) {
                    await selectAgentConversation(conversationsList[0].id);
                } else {
                    await createNewAgentChat();
                }
            }
        } catch (err) {
            showNotification('error', 'Failed to delete conversation.');
        }
    };

    /**
     * Renders clean welcome hero state when chat is empty.
     */
    function renderEmptyWelcomeState() {
        const container = document.getElementById('agent-messages-container');
        if (!container) return;

        // Reset scroll position to ensure content starts cleanly below header
        container.scrollTop = 0;

        container.innerHTML = `
            <div class="max-w-2xl mx-auto py-6 md:py-10 space-y-6 animate-fade-in">
                <!-- Hero Header -->
                <div class="text-center space-y-2">
                    <div class="inline-flex items-center justify-center h-11 w-11 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 shadow-2xs">
                        <i data-lucide="bot" class="h-6 w-6"></i>
                    </div>
                    <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">How can I help you today?</h1>
                    <p class="text-xs md:text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
                        I am your LinkPilot AI Agent. I control and orchestrate your CRM, WhatsApp, Emails, Deals, Tasks, and Automations through natural conversation.
                    </p>
                </div>

                <!-- 4 Compact Core Capability Cards (2x2 Grid) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div onclick="setAgentComposerPrompt('Find all customers who haven\\'t responded to our WhatsApp messages in the last 7 days and create follow-up tasks for them.')" class="agent-welcome-card p-3.5 bg-white border border-slate-200/90 hover:border-indigo-300 rounded-xl hover:shadow-xs transition duration-150 cursor-pointer group flex items-start space-x-3">
                        <div class="h-8 w-8 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shrink-0 group-hover:scale-105 transition">
                            <i data-lucide="message-square" class="h-4 w-4"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition">WhatsApp Follow-Ups</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2">Audit unreplied customer chats in last 7 days & create tasks.</p>
                        </div>
                    </div>

                    <div onclick="setAgentComposerPrompt('Show me my pending deals and identify any deals that haven\\'t received follow-ups in the last 14 days.')" class="agent-welcome-card p-3.5 bg-white border border-slate-200/90 hover:border-indigo-300 rounded-xl hover:shadow-xs transition duration-150 cursor-pointer group flex items-start space-x-3">
                        <div class="h-8 w-8 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0 group-hover:scale-105 transition">
                            <i data-lucide="dollar-sign" class="h-4 w-4"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition">Pipeline & Deals</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2">Identify stalled deals at risk and review expected revenue.</p>
                        </div>
                    </div>

                    <div onclick="setAgentComposerPrompt('Give me a complete daily executive briefing of today\\'s meetings, hot leads, and overdue tasks.')" class="agent-welcome-card p-3.5 bg-white border border-slate-200/90 hover:border-indigo-300 rounded-xl hover:shadow-xs transition duration-150 cursor-pointer group flex items-start space-x-3">
                        <div class="h-8 w-8 rounded-lg bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600 shrink-0 group-hover:scale-105 transition">
                            <i data-lucide="sparkles" class="h-4 w-4"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition">Daily Executive Briefing</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2">Synthesize today's meetings, priority leads & urgent tasks.</p>
                        </div>
                    </div>

                    <div onclick="setAgentComposerPrompt('Summarize my incoming emails and draft a follow-up reply for the most urgent lead.')" class="agent-welcome-card p-3.5 bg-white border border-slate-200/90 hover:border-indigo-300 rounded-xl hover:shadow-xs transition duration-150 cursor-pointer group flex items-start space-x-3">
                        <div class="h-8 w-8 rounded-lg bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 shrink-0 group-hover:scale-105 transition">
                            <i data-lucide="mail" class="h-4 w-4"></i>
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-semibold text-slate-800 group-hover:text-indigo-600 transition">Email Correspondence</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2">Search inbox, draft follow-up replies & summarize threads.</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Prompt Chips -->
                <div class="flex flex-wrap gap-1.5 justify-center pt-1">
                    <button onclick="setAgentComposerPrompt('What can you do?')" class="agent-quick-pill px-3 py-1 bg-white hover:bg-slate-50 hover:text-indigo-600 border border-slate-200/90 rounded-full text-xs font-medium text-slate-600 transition shadow-2xs cursor-pointer">What can you do?</button>
                    <button onclick="setAgentComposerPrompt('Show my pending tasks for today')" class="agent-quick-pill px-3 py-1 bg-white hover:bg-slate-50 hover:text-indigo-600 border border-slate-200/90 rounded-full text-xs font-medium text-slate-600 transition shadow-2xs cursor-pointer">Today's pending tasks</button>
                    <button onclick="setAgentComposerPrompt('Find hot leads with high buying intent')" class="agent-quick-pill px-3 py-1 bg-white hover:bg-slate-50 hover:text-indigo-600 border border-slate-200/90 rounded-full text-xs font-medium text-slate-600 transition shadow-2xs cursor-pointer">Hot leads</button>
                    <button onclick="setAgentComposerPrompt('What is our total pipeline revenue by stage?')" class="agent-quick-pill px-3 py-1 bg-white hover:bg-slate-50 hover:text-indigo-600 border border-slate-200/90 rounded-full text-xs font-medium text-slate-600 transition shadow-2xs cursor-pointer">Pipeline breakdown</button>
                </div>
            </div>
        `;

        if (window.lucide) lucide.createIcons();
    }

    /**
     * Sets prompt in composer and focuses textarea.
     */
    window.setAgentComposerPrompt = function (text) {
        const textarea = document.getElementById('agent-user-prompt-input');
        if (textarea) {
            textarea.value = text;
            autoResizeTextarea(textarea);
            textarea.focus();
        }
    };

    /**
     * Render message list stream.
     */
    function renderMessagesStream(messages) {
        const container = document.getElementById('agent-messages-container');
        if (!container) return;

        let html = '<div class="max-w-3xl mx-auto space-y-5 pb-4">';

        messages.forEach(msg => {
            if (msg.role === 'user') {
                html += renderUserMessageHTML(msg);
            } else if (msg.role === 'assistant') {
                html += renderAssistantMessageHTML(msg);
            }
        });

        html += '</div>';
        container.innerHTML = html;
        scrollAgentToBottom(false);
        if (window.lucide) lucide.createIcons();
    }

    /**
     * User speech card HTML.
     */
    function renderUserMessageHTML(msg) {
        return `
            <div class="flex items-start justify-end">
                <div class="max-w-[85%] md:max-w-[75%] bg-slate-900 text-white px-4 py-3 rounded-2xl rounded-tr-xs shadow-2xs text-xs md:text-sm font-normal leading-relaxed">
                    <p class="whitespace-pre-wrap">${escapeHtml(msg.content)}</p>
                </div>
            </div>
        `;
    }

    /**
     * Assistant card with tools progress, approval cards & markdown.
     */
    function renderAssistantMessageHTML(msg) {
        let toolsHTML = '';
        if (msg.tool_calls && Array.isArray(msg.tool_calls) && msg.tool_calls.length > 0) {
            toolsHTML += '<div class="space-y-1.5 mb-3">';
            msg.tool_calls.forEach(tc => {
                const isSuccess = tc.status === 'completed';
                const icon = isSuccess ? 'check-circle-2 text-emerald-600' : 'alert-circle text-rose-500';
                toolsHTML += `
                    <div class="agent-tool-card flex items-center justify-between text-xs">
                        <div class="flex items-center space-x-2 min-w-0">
                            <i data-lucide="${icon}" class="h-3.5 w-3.5 shrink-0"></i>
                            <span class="font-semibold text-slate-800 truncate">${escapeHtml(tc.tool_name || tc.tool_id)}</span>
                        </div>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase shrink-0 ${isSuccess ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'}">${tc.status}</span>
                    </div>
                `;
            });
            toolsHTML += '</div>';
        }

        // Approval required card if waiting
        let approvalHTML = '';
        if (msg.status === 'waiting_approval' && msg.tool_results && msg.tool_results.tool_id) {
            const appr = msg.tool_results;
            approvalHTML = `
                <div class="agent-approval-card space-y-2.5">
                    <div class="flex items-center space-x-2 text-amber-800 font-bold text-xs uppercase tracking-wide">
                        <i data-lucide="shield-alert" class="h-4 w-4 text-amber-600"></i>
                        <span>Confirmation Required Before Execution</span>
                    </div>
                    <div class="text-xs text-slate-700 bg-white p-3 rounded-xl border border-amber-200/90 shadow-2xs">
                        <div class="font-bold text-slate-900 mb-1">Action: ${escapeHtml(appr.tool_name)}</div>
                        <p class="text-slate-600 text-[11px] mb-2">${escapeHtml(appr.description)}</p>
                        <div class="bg-slate-50 p-2.5 rounded-lg text-[11px] font-mono border border-slate-200 overflow-x-auto">
                            ${escapeHtml(JSON.stringify(appr.arguments, null, 2))}
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 pt-1">
                        <button onclick="approveAgentAction('${appr.tool_id}', ${escapeHtml(JSON.stringify(appr.arguments))})" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-2xs flex items-center space-x-1.5 cursor-pointer">
                            <i data-lucide="check" class="h-3.5 w-3.5"></i>
                            <span>Approve & Execute</span>
                        </button>
                        <button onclick="rejectAgentAction()" class="agent-btn-secondary px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-lg text-xs font-bold transition cursor-pointer">
                            Cancel
                        </button>
                    </div>
                </div>
            `;
        }

        const renderedMarkdown = renderMarkdown(msg.content);

        return `
            <div class="flex items-start space-x-3">
                <div class="h-7 w-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs shrink-0 shadow-2xs mt-0.5">
                    <i data-lucide="bot" class="h-4 w-4"></i>
                </div>
                <div class="flex-1 min-w-0 bg-white border border-slate-200/80 rounded-2xl rounded-tl-xs p-4 md:p-5 shadow-2xs">
                    ${toolsHTML}
                    ${approvalHTML}
                    <div class="agent-markdown-content">${renderedMarkdown}</div>
                </div>
            </div>
        `;
    }

    /**
     * Submit user message & stream response.
     */
    window.handleAgentSendMessage = async function (e) {
        if (e && e.preventDefault) e.preventDefault();
        if (isGenerating) return;

        const input = document.getElementById('agent-user-prompt-input');
        if (!input) return;
        const message = input.value.trim();
        if (!message) return;

        input.value = '';
        input.style.height = 'auto';

        if (!activeConversationId) {
            await createNewAgentChat();
        }

        const container = document.getElementById('agent-messages-container');
        if (!container) return;

        // If currently in empty welcome state, clear it
        if (container.querySelector('.text-center')) {
            container.innerHTML = '<div class="max-w-3xl mx-auto space-y-5 pb-4"></div>';
        }

        const streamWrapper = container.querySelector('.max-w-3xl') || container;

        // Append User Message immediately
        streamWrapper.insertAdjacentHTML('beforeend', renderUserMessageHTML({ content: message }));
        scrollAgentToBottom();

        // Create Assistant live placeholder
        const assistantPlaceholderId = 'assistant-live-' + Date.now();
        const assistantLiveHTML = `
            <div id="${assistantPlaceholderId}" class="flex items-start space-x-3 animate-fade-in">
                <div class="h-7 w-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs shrink-0 shadow-2xs mt-0.5">
                    <i data-lucide="bot" class="h-4 w-4"></i>
                </div>
                <div class="flex-1 min-w-0 bg-white border border-slate-200/80 rounded-2xl rounded-tl-xs p-4 md:p-5 shadow-2xs">
                    <div class="agent-live-tools space-y-1.5 mb-2"></div>
                    <div class="agent-live-approval"></div>
                    <div class="agent-live-content agent-markdown-content agent-streaming-cursor">
                        <span class="inline-flex items-center text-xs text-slate-400 font-medium">
                            <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin mr-1.5 text-indigo-600"></i> Thinking & accessing business data...
                        </span>
                    </div>
                    <div class="agent-live-proactive flex flex-wrap gap-1.5 mt-3 pt-3 border-t border-slate-100 hidden"></div>
                </div>
            </div>
        `;
        streamWrapper.insertAdjacentHTML('beforeend', assistantLiveHTML);
        scrollAgentToBottom();
        if (window.lucide) lucide.createIcons();

        const card = document.getElementById(assistantPlaceholderId);
        const toolsContainer = card.querySelector('.agent-live-tools');
        const approvalContainer = card.querySelector('.agent-live-approval');
        const contentContainer = card.querySelector('.agent-live-content');
        const proactiveContainer = card.querySelector('.agent-live-proactive');

        setGeneratingState(true);

        let accumulatedContent = '';

        try {
            const token = getAuthToken();
            const endpoint = `${API_BASE_URL}/crm/agent.php?action=chat&stream=true`;

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    conversation_id: activeConversationId,
                    message: message
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status} error.`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop(); // Keep partial line in buffer

                let currentEvent = null;

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed.startsWith('event:')) {
                        currentEvent = trimmed.replace('event:', '').trim();
                    } else if (trimmed.startsWith('data:')) {
                        const jsonStr = trimmed.replace('data:', '').trim();
                        try {
                            const data = JSON.parse(jsonStr);

                            if (currentEvent === 'tool_start') {
                                const toolHtml = `
                                    <div class="agent-tool-card flex items-center justify-between text-xs animate-fade-in" id="tool-step-${escapeHtml(data.tool_id)}">
                                        <div class="flex items-center space-x-2">
                                            <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin text-indigo-600"></i>
                                            <span class="font-bold text-slate-800">Running: ${escapeHtml(data.tool_name || data.tool_id)}...</span>
                                        </div>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase bg-indigo-50 text-indigo-700">Executing</span>
                                    </div>
                                `;
                                toolsContainer.insertAdjacentHTML('beforeend', toolHtml);
                                if (window.lucide) lucide.createIcons();
                                scrollAgentToBottom();
                            } else if (currentEvent === 'tool_complete') {
                                const toolEl = document.getElementById(`tool-step-${escapeHtml(data.tool_id)}`);
                                if (toolEl) {
                                    toolEl.innerHTML = `
                                        <div class="flex items-center space-x-2">
                                            <i data-lucide="check-circle-2" class="h-3.5 w-3.5 text-emerald-600"></i>
                                            <span class="font-bold text-slate-800">${escapeHtml(data.tool_name || data.tool_id)}</span>
                                        </div>
                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">Completed ✓</span>
                                    `;
                                    if (window.lucide) lucide.createIcons();
                                }
                            } else if (currentEvent === 'approval_required') {
                                approvalContainer.innerHTML = `
                                    <div class="agent-approval-card space-y-3 animate-fade-in">
                                        <div class="flex items-center space-x-2 text-amber-800 font-bold text-xs uppercase tracking-wide">
                                            <i data-lucide="shield-alert" class="h-4 w-4 text-amber-600"></i>
                                            <span>Confirmation Required Before Execution</span>
                                        </div>
                                        <div class="text-xs text-slate-700 bg-white p-3 rounded-xl border border-amber-200 shadow-xs">
                                            <div class="font-bold text-slate-900 mb-1">Action: ${escapeHtml(data.tool_name)}</div>
                                            <p class="text-slate-600 text-[11px] mb-2">${escapeHtml(data.description)}</p>
                                            <div class="bg-slate-50 p-2.5 rounded-lg text-[11px] font-mono border border-slate-200 overflow-x-auto">
                                                ${escapeHtml(JSON.stringify(data.arguments, null, 2))}
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2 pt-1">
                                            <button onclick="approveAgentAction('${data.tool_id}', ${escapeHtml(JSON.stringify(data.arguments))})" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center space-x-1.5 cursor-pointer">
                                                <i data-lucide="check" class="h-3.5 w-3.5"></i>
                                                <span>Approve & Execute</span>
                                            </button>
                                            <button onclick="rejectAgentAction()" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition cursor-pointer">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                `;
                                if (window.lucide) lucide.createIcons();
                            } else if (currentEvent === 'token') {
                                accumulatedContent = data.content;
                                contentContainer.innerHTML = renderMarkdown(accumulatedContent);
                                scrollAgentToBottom();
                            } else if (currentEvent === 'proactive_suggestions') {
                                if (data.suggestions && data.suggestions.length > 0) {
                                    let suggHtml = '<span class="text-[10px] font-extrabold uppercase text-slate-400 mr-1 self-center">Next Steps:</span>';
                                    data.suggestions.forEach(s => {
                                        suggHtml += `<button onclick="setAgentComposerPrompt('${escapeHtml(s)}'); handleAgentSendMessage(event);" class="px-2.5 py-1 bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 text-slate-600 border border-slate-200 rounded-lg text-xs font-semibold transition cursor-pointer">${escapeHtml(s)}</button>`;
                                    });
                                    proactiveContainer.innerHTML = suggHtml;
                                    proactiveContainer.classList.remove('hidden');
                                }
                            }
                        } catch (err) {}
                    }
                }
            }

            contentContainer.classList.remove('agent-streaming-cursor');
            if (accumulatedContent) {
                contentContainer.innerHTML = renderMarkdown(accumulatedContent);
            }

            // Refresh conversation titles in sidebar
            loadAgentConversations(activeConversationId);

        } catch (err) {
            console.error("Streaming chat failed, attempting JSON fallback:", err);
            // Fallback non-streaming call
            try {
                const res = await apiCall('crm/agent.php', 'POST', {
                    action: 'chat',
                    conversation_id: activeConversationId,
                    message: message
                });
                if (res && res.data) {
                    contentContainer.classList.remove('agent-streaming-cursor');
                    contentContainer.innerHTML = renderMarkdown(res.data.content);
                }
            } catch (fallbackErr) {
                contentContainer.classList.remove('agent-streaming-cursor');
                contentContainer.innerHTML = `<span class="text-rose-600 font-bold">Error: ${escapeHtml(fallbackErr.message || 'Operation failed.')}</span>`;
            }
        } finally {
            setGeneratingState(false);
            scrollAgentToBottom();
        }
    };

    /**
     * Executes approved action.
     */
    window.approveAgentAction = async function (toolId, argumentsObj) {
        showNotification('info', `Executing approved action: ${toolId}...`);
        try {
            const res = await apiCall('crm/agent.php', 'POST', {
                action: 'approve_action',
                conversation_id: activeConversationId,
                tool_id: toolId,
                arguments: argumentsObj
            });

            showNotification('success', 'Action executed successfully!');
            // Reload active conversation stream
            if (activeConversationId) {
                selectAgentConversation(activeConversationId);
            }
        } catch (err) {
            showNotification('error', `Action execution failed: ${err.message}`);
        }
    };

    window.rejectAgentAction = function () {
        showNotification('warning', 'Action cancelled.');
        if (activeConversationId) {
            selectAgentConversation(activeConversationId);
        }
    };

    function setGeneratingState(generating) {
        isGenerating = generating;
        const btn = document.getElementById('agent-send-button');
        if (!btn) return;

        if (generating) {
            btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>`;
            btn.disabled = true;
            btn.classList.add('opacity-75');
        } else {
            btn.innerHTML = `<i data-lucide="arrow-up" class="h-4 w-4"></i>`;
            btn.disabled = false;
            btn.classList.remove('opacity-75');
        }
        if (window.lucide) lucide.createIcons();
    }

    function scrollAgentToBottom(smooth = false) {
        const container = document.getElementById('agent-messages-container');
        if (container) {
            if (smooth) {
                container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
            } else {
                container.scrollTop = container.scrollHeight;
            }
        }
    }

    /**
     * Lightweight, secure markdown parser.
     */
    function renderMarkdown(md) {
        if (!md) return '';

        let text = md;

        // Code blocks ```code```
        text = text.replace(/```([a-z0-9]*)\n([\s\S]*?)```/gim, function (match, lang, code) {
            return `<pre><code class="language-${lang}">${escapeHtml(code.trim())}</code></pre>`;
        });

        // Inline code `code`
        text = text.replace(/`([^`]+)`/g, function (match, code) {
            return `<code>${escapeHtml(code)}</code>`;
        });

        // Tables
        text = text.replace(/((?:\|[^\n]+\|\n?)+)/g, function (match) {
            const lines = match.trim().split('\n');
            if (lines.length < 2) return match;

            let tableHtml = '<table>';
            lines.forEach((line, idx) => {
                if (line.includes('---')) return; // separator line

                const cells = line.split('|').slice(1, -1);
                if (idx === 0) {
                    tableHtml += '<thead><tr>';
                    cells.forEach(c => tableHtml += `<th>${c.trim()}</th>`);
                    tableHtml += '</tr></thead><tbody>';
                } else {
                    tableHtml += '<tr>';
                    cells.forEach(c => tableHtml += `<td>${c.trim()}</td>`);
                    tableHtml += '</tr>';
                }
            });
            tableHtml += '</tbody></table>';
            return tableHtml;
        });

        // Headers
        text = text.replace(/^### (.*$)/gim, '<h3>$1</h3>');
        text = text.replace(/^## (.*$)/gim, '<h2>$1</h2>');
        text = text.replace(/^# (.*$)/gim, '<h1>$1</h1>');

        // Blockquotes
        text = text.replace(/^\> (.*$)/gim, '<blockquote>$1</blockquote>');

        // Bold & Italic
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Lists
        text = text.replace(/^\s*\-\s+(.*$)/gim, '<ul><li>$1</li></ul>');
        text = text.replace(/<\/ul>\s*<ul>/gim, '');

        text = text.replace(/^\s*([0-9]+)\.\s+(.*$)/gim, '<ol><li>$2</li></ol>');
        text = text.replace(/<\/ol>\s*<ol>/gim, '');

        // Clickable Links
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

        // Paragraphs
        text = text.replace(/\n\n+/g, '</p><p>');
        text = '<p>' + text + '</p>';
        text = text.replace(/<p><\/p>/g, '');
        text = text.replace(/<p>(<h[1-3]>)/g, '$1');
        text = text.replace(/(<\/h[1-3]>)<\/p>/g, '$1');
        text = text.replace(/<p>(<pre>[\s\S]*?<\/pre>)<\/p>/g, '$1');
        text = text.replace(/<p>(<table>[\s\S]*?<\/table>)<\/p>/g, '$1');
        text = text.replace(/<p>(<ul>[\s\S]*?<\/ul>)<\/p>/g, '$1');
        text = text.replace(/<p>(<ol>[\s\S]*?<\/ol>)<\/p>/g, '$1');

        return text;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * File attachments handling.
     */
    window.handleAgentFileSelected = function (e) {
        const file = e.target.files[0];
        if (!file) return;

        attachedFile = file;
        const preview = document.getElementById('agent-attachment-preview');
        const nameEl = document.getElementById('agent-attachment-name');
        if (preview && nameEl) {
            nameEl.textContent = file.name;
            preview.classList.remove('hidden');
        }
    };

    window.clearAgentAttachment = function () {
        attachedFile = null;
        const preview = document.getElementById('agent-attachment-preview');
        const picker = document.getElementById('agent-file-picker');
        if (preview) preview.classList.add('hidden');
        if (picker) picker.value = '';
    };

    /**
     * Capabilities Modal.
     */
    window.openAgentCapabilitiesModal = async function () {
        const root = document.getElementById('agent-capabilities-modal-root');
        if (!root) return;

        root.innerHTML = `
            <div id="agent-cap-backdrop" onclick="closeAgentCapabilitiesModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 animate-fade-in">
                <div onclick="event.stopPropagation()" class="bg-white rounded-2xl max-w-2xl w-full border border-slate-200 shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                    <div class="px-6 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                        <div class="flex items-center space-x-2.5">
                            <div class="h-8 w-8 rounded-xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-indigo-400">
                                <i data-lucide="sparkles" class="h-4.5 w-4.5"></i>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-sm text-white">LinkPilot Agent Capabilities</h3>
                                <p class="text-[10px] text-slate-400">Autonomous Business Intelligence & Omnichannel Operations</p>
                            </div>
                        </div>
                        <button onclick="closeAgentCapabilitiesModal()" class="text-slate-400 hover:text-white p-1 rounded-lg">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <div class="p-6 overflow-y-auto space-y-4 text-xs text-slate-700 agent-custom-scrollbar">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                                <div class="font-bold text-slate-900 flex items-center space-x-1.5">
                                    <i data-lucide="contact" class="h-4 w-4 text-indigo-600"></i>
                                    <span>CRM & Contacts</span>
                                </div>
                                <p class="text-[11px] text-slate-500">Search, resolve, create, update, tag contacts, and calculate AI lead scores.</p>
                                <button onclick="setAgentComposerPrompt('Find contacts in Kolkata tagged as Leads'); closeAgentCapabilitiesModal();" class="text-[10px] text-indigo-600 font-bold hover:underline">Try: "Find contacts in Kolkata..."</button>
                            </div>

                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                                <div class="font-bold text-slate-900 flex items-center space-x-1.5">
                                    <i data-lucide="dollar-sign" class="h-4 w-4 text-emerald-600"></i>
                                    <span>Deals & Pipeline</span>
                                </div>
                                <p class="text-[11px] text-slate-500">Manage pipeline stages, calculate forecast revenue, and evaluate stalled deals.</p>
                                <button onclick="setAgentComposerPrompt('Show me deals at risk of closing late'); closeAgentCapabilitiesModal();" class="text-[10px] text-indigo-600 font-bold hover:underline">Try: "Show deals at risk..."</button>
                            </div>

                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                                <div class="font-bold text-slate-900 flex items-center space-x-1.5">
                                    <i data-lucide="message-square" class="h-4 w-4 text-green-600"></i>
                                    <span>WhatsApp Operations</span>
                                </div>
                                <p class="text-[11px] text-slate-500">Read messages, detect unreplied chats, send messages, and check campaigns.</p>
                                <button onclick="setAgentComposerPrompt('Find WhatsApp chats waiting for a response'); closeAgentCapabilitiesModal();" class="text-[10px] text-indigo-600 font-bold hover:underline">Try: "Chats waiting for response..."</button>
                            </div>

                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                                <div class="font-bold text-slate-900 flex items-center space-x-1.5">
                                    <i data-lucide="mail" class="h-4 w-4 text-blue-600"></i>
                                    <span>Business Email</span>
                                </div>
                                <p class="text-[11px] text-slate-500">Search inbox, summarize correspondence, prepare drafts, and send approved emails.</p>
                                <button onclick="setAgentComposerPrompt('Summarize emails from high-priority leads'); closeAgentCapabilitiesModal();" class="text-[10px] text-indigo-600 font-bold hover:underline">Try: "Summarize lead emails..."</button>
                            </div>

                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                                <div class="font-bold text-slate-900 flex items-center space-x-1.5">
                                    <i data-lucide="check-square" class="h-4 w-4 text-amber-600"></i>
                                    <span>Tasks & Calendar</span>
                                </div>
                                <p class="text-[11px] text-slate-500">Create, complete, and assign tasks. Schedule Google Meet & Zoom appointments.</p>
                                <button onclick="setAgentComposerPrompt('Schedule a meeting with Rahul tomorrow at 3 PM on Google Meet'); closeAgentCapabilitiesModal();" class="text-[10px] text-indigo-600 font-bold hover:underline">Try: "Schedule meeting..."</button>
                            </div>

                            <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
                                <div class="font-bold text-slate-900 flex items-center space-x-1.5">
                                    <i data-lucide="bar-chart-3" class="h-4 w-4 text-purple-600"></i>
                                    <span>Executive Analytics</span>
                                </div>
                                <p class="text-[11px] text-slate-500">Instant KPI summaries, daily briefings, and conversion funnel analytics.</p>
                                <button onclick="setAgentComposerPrompt('Give me a daily briefing of our pipeline and overdue tasks'); closeAgentCapabilitiesModal();" class="text-[10px] text-indigo-600 font-bold hover:underline">Try: "Give me a daily briefing..."</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
    };

    window.closeAgentCapabilitiesModal = function () {
        const root = document.getElementById('agent-capabilities-modal-root');
        if (root) root.innerHTML = '';
    };

})();
