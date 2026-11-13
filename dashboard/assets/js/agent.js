// dashboard/assets/js/agent.js - LinkPilot Autonomous Agent Interface

(function () {
    let activeConversationId = null;
    let conversationsList = [];
    let isGenerating = false;
    let abortController = null;
    let attachedFile = null;

    /**
     * Main entry point when routing to #/agent
     */
    window.renderAgentView = async function (container, params = {}) {
        container.innerHTML = `
            <div id="agent-view-root" class="flex h-full w-full overflow-hidden bg-[#f8fafc] text-slate-800 relative">
                <!-- 1. LEFT CONVERSATION HISTORY PANEL -->
                <div id="agent-history-sidebar" class="w-72 bg-white border-r border-slate-200/90 flex flex-col shrink-0 transition-all duration-300 z-30 fixed lg:relative inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 h-full">
                    <!-- Sidebar Top Header -->
                    <div class="p-3.5 border-b border-slate-200/90 flex items-center justify-between bg-slate-50/80">
                        <div class="flex items-center space-x-2">
                            <div class="h-7 w-7 rounded-lg bg-gradient-to-tr from-indigo-600 to-purple-600 flex items-center justify-center text-white shadow-xs">
                                <i data-lucide="bot" class="h-4 w-4 text-white"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-900 tracking-tight">Agent History</span>
                        </div>
                        <button onclick="toggleAgentHistorySidebar()" class="lg:hidden text-slate-500 hover:text-slate-800 p-1">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <!-- New Chat Button & Search -->
                    <div class="p-3 space-y-2 border-b border-slate-200/90 bg-white">
                        <button onclick="createNewAgentChat()" class="w-full py-2.5 px-3 bg-indigo-600 hover:bg-indigo-700 active:scale-[0.99] text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center justify-center space-x-2 cursor-pointer">
                            <i data-lucide="plus" class="h-4 w-4 text-white"></i>
                            <span class="text-white">New Conversation</span>
                        </button>
                        <div class="relative">
                            <input type="text" id="agent-chat-search-input" oninput="filterAgentConversations(this.value)" placeholder="Search conversations..." class="w-full pl-8 pr-3 py-1.5 bg-slate-100/90 border border-slate-200/80 focus:border-indigo-500 focus:bg-white rounded-lg text-xs outline-none transition placeholder-slate-400 text-slate-800">
                            <i data-lucide="search" class="h-3.5 w-3.5 text-slate-400 absolute left-2.5 top-2"></i>
                        </div>
                    </div>

                    <!-- Conversation History List -->
                    <div class="flex-grow overflow-y-auto p-2 space-y-4 agent-custom-scrollbar" id="agent-history-list">
                        <div class="flex items-center justify-center py-10 text-slate-400 text-xs">
                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin mr-2"></i> Loading conversations...
                        </div>
                    </div>

                    <!-- Sidebar Footer -->
                    <div class="p-3 border-t border-slate-200/90 bg-slate-50/80 flex items-center justify-between text-[11px] text-slate-600">
                        <span class="font-medium flex items-center text-slate-600">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 mr-1.5"></span>
                            AI Co-Pilot 2.0
                        </span>
                        <button onclick="openAgentCapabilitiesModal()" class="text-indigo-600 hover:text-indigo-800 hover:underline font-bold cursor-pointer">
                            Capabilities
                        </button>
                    </div>
                </div>

                <!-- Sidebar Backdrop on Mobile -->
                <div id="agent-history-backdrop" onclick="toggleAgentHistorySidebar()" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-20 lg:hidden"></div>

                <!-- 2. RIGHT MAIN CHAT AREA -->
                <main class="flex-grow flex flex-col h-full min-w-0 bg-[#f8fafc] relative">
                    <!-- Top Bar Header -->
                    <header id="agent-chat-header" class="h-14 px-4 bg-white border-b border-slate-200 flex items-center justify-between shrink-0 sticky top-0 z-10 shadow-2xs">
                        <div class="flex items-center space-x-3 truncate">
                            <button onclick="toggleAgentHistorySidebar()" class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-slate-800 transition cursor-pointer" title="Toggle Chat History">
                                <i data-lucide="menu" class="h-4.5 w-4.5"></i>
                            </button>
                            <div class="truncate">
                                <div class="flex items-center space-x-2">
                                    <h2 id="agent-active-title" class="text-xs md:text-sm font-extrabold text-slate-900 truncate">LinkPilot Autonomous Agent</h2>
                                    <span class="bg-indigo-50 text-indigo-700 border border-indigo-200/80 text-[9px] font-black px-1.5 py-0.5 rounded-full uppercase tracking-wider hidden sm:inline-flex">Central Intelligence</span>
                                </div>
                                <div class="text-[10px] text-slate-500 flex items-center space-x-1.5 mt-0.5">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    <span>Online • Unified Controller (CRM, WhatsApp, Email, Deals, Tasks, Automations)</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2 shrink-0">
                            <button onclick="openAgentCapabilitiesModal()" class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center space-x-1 cursor-pointer shadow-2xs" title="What can Agent do?">
                                <i data-lucide="help-circle" class="h-3.5 w-3.5 text-indigo-600"></i>
                                <span class="hidden md:inline text-slate-700 font-semibold">What can I do?</span>
                            </button>
                            <button onclick="createNewAgentChat()" class="p-1.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg text-slate-600 hover:text-slate-900 transition cursor-pointer shadow-2xs" title="New Chat">
                                <i data-lucide="plus-circle" class="h-4.5 w-4.5 text-indigo-600"></i>
                            </button>
                        </div>
                    </header>

                    <!-- Scrollable Messages Area -->
                    <div class="flex-grow overflow-y-auto p-4 md:p-6 space-y-6 agent-custom-scrollbar" id="agent-messages-container">
                        <!-- Messages or Welcome State injected dynamically -->
                    </div>

                    <!-- Bottom Fixed Composer Area -->
                    <div id="agent-composer-bar" class="p-3 md:p-4 bg-white border-t border-slate-200 shrink-0 shadow-2xs">
                        <div class="max-w-4xl mx-auto space-y-2">
                            <!-- Attachment preview pill -->
                            <div id="agent-attachment-preview" class="hidden flex items-center space-x-2 p-1.5 bg-indigo-50 border border-indigo-200 rounded-lg w-max text-xs text-indigo-800">
                                <i data-lucide="paperclip" class="h-3.5 w-3.5 text-indigo-600"></i>
                                <span id="agent-attachment-name" class="font-medium truncate max-w-xs text-indigo-900">file.pdf</span>
                                <button onclick="clearAgentAttachment()" class="hover:text-red-600 p-0.5"><i data-lucide="x" class="h-3 w-3"></i></button>
                            </div>

                            <!-- Input Box -->
                            <form id="agent-composer-form" onsubmit="handleAgentSendMessage(event)" class="relative flex items-end bg-white border border-slate-300 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-100 rounded-2xl shadow-xs transition-all duration-200 p-1.5">
                                <input type="file" id="agent-file-picker" class="hidden" onchange="handleAgentFileSelected(event)">
                                <button type="button" onclick="document.getElementById('agent-file-picker').click()" class="p-2 text-slate-400 hover:text-slate-600 transition rounded-xl hover:bg-slate-100 cursor-pointer" title="Attach file or screenshot">
                                    <i data-lucide="paperclip" class="h-4 w-4"></i>
                                </button>
                                <button type="button" onclick="openAgentCapabilitiesModal()" class="p-2 text-slate-400 hover:text-indigo-600 transition rounded-xl hover:bg-slate-100 mr-1 cursor-pointer" title="Quick capabilities">
                                    <i data-lucide="sparkles" class="h-4 w-4 text-indigo-500"></i>
                                </button>

                                <textarea id="agent-user-prompt-input" rows="1" placeholder="Message LinkPilot Agent (e.g. 'Show stalled deals', 'Summarize unreplied WhatsApp chats', 'Create follow-up task for Rahul')..." onkeydown="handleAgentTextareaKeydown(event)" oninput="autoResizeTextarea(this)" class="flex-grow bg-transparent border-0 focus:outline-none focus:ring-0 text-xs md:text-sm text-slate-800 placeholder-slate-400 font-medium py-2 px-1 max-h-36 resize-none leading-relaxed"></textarea>

                                <button type="submit" id="agent-send-button" class="h-8 w-8 md:h-9 md:w-9 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white rounded-xl transition shadow flex items-center justify-center shrink-0 ml-1 cursor-pointer">
                                    <i data-lucide="arrow-up" class="h-4 w-4 text-white"></i>
                                </button>
                            </form>

                            <!-- Proactive footer hint -->
                            <div class="flex items-center justify-between text-[11px] text-slate-500 px-1 font-normal">
                                <span class="text-slate-500">LinkPilot Agent executes real actions across your business. External & destructive tasks require confirmation.</span>
                                <span class="hidden sm:inline text-slate-400">Press Enter ↵ to send, Shift+Enter for new line</span>
                            </div>
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
     * Toggles mobile/desktop chat history sidebar.
     */
    window.toggleAgentHistorySidebar = function () {
        const sidebar = document.getElementById('agent-history-sidebar');
        const backdrop = document.getElementById('agent-history-backdrop');
        if (!sidebar) return;

        if (sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            if (backdrop) backdrop.classList.remove('hidden');
        } else {
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            if (backdrop) backdrop.classList.add('hidden');
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
                    ? 'bg-indigo-50/90 text-indigo-950 font-bold border-l-4 border-indigo-600 pl-2.5 shadow-2xs agent-conv-active'
                    : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950 border-l-4 border-transparent pl-2.5 agent-conv-item';

                html += `
                    <div onclick="selectAgentConversation('${c.id}')" class="group flex items-center justify-between p-2 rounded-xl text-xs transition cursor-pointer ${activeClasses}">
                        <div class="flex items-center space-x-2 truncate">
                            <i data-lucide="message-square" class="h-3.5 w-3.5 shrink-0 ${isActive ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600'}"></i>
                            <span class="truncate max-w-[155px] ${isActive ? 'text-indigo-950 font-bold' : 'text-slate-800 font-medium'}">${escapeHtml(c.title)}</span>
                        </div>
                        <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition">
                            <button onclick="promptRenameAgentConversation(event, '${c.id}', '${escapeHtml(c.title)}')" class="p-1 hover:text-indigo-600 text-slate-400" title="Rename"><i data-lucide="edit-2" class="h-3 w-3"></i></button>
                            <button onclick="deleteAgentConversation(event, '${c.id}')" class="p-1 hover:text-rose-600 text-slate-400" title="Delete"><i data-lucide="trash-2" class="h-3 w-3"></i></button>
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
    };

    /**
     * Switch active conversation.
     */
    window.selectAgentConversation = async function (convId, updateUrl = true) {
        if (!convId) return;
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
                <i data-lucide="loader-2" class="h-6 w-6 animate-spin text-indigo-600 mb-2"></i>
                <span>Loading conversation messages...</span>
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

        container.innerHTML = `
            <div class="max-w-3xl mx-auto py-8 md:py-12 space-y-8 animate-fade-in">
                <!-- Hero Header -->
                <div class="text-center space-y-3">
                    <div class="inline-flex items-center justify-center h-14 w-14 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/25">
                        <i data-lucide="bot" class="h-8 w-8"></i>
                    </div>
                    <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">How can I assist your business today?</h1>
                    <p class="text-xs md:text-sm text-slate-500 max-w-lg mx-auto leading-relaxed">
                        I am your unified LinkPilot AI Agent. I control and orchestrate your CRM, WhatsApp, Emails, Deals, Tasks, and Automations through natural conversation.
                    </p>
                </div>

                <!-- 4 Core Capability Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div onclick="setAgentComposerPrompt('Find all customers who haven\'t responded to our WhatsApp messages in the last 7 days and create follow-up tasks for them.')" class="agent-welcome-card p-4 bg-white border border-slate-200 hover:border-indigo-400 rounded-2xl hover:shadow-md transition duration-200 cursor-pointer group flex items-start space-x-3.5">
                        <div class="h-9 w-9 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shrink-0 group-hover:scale-105 transition">
                            <i data-lucide="message-square" class="h-4.5 w-4.5"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 group-hover:text-indigo-600 transition">WhatsApp Follow-Up Automation</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Find unreplied chats in the last 7 days & create tasks.</p>
                        </div>
                    </div>

                    <div onclick="setAgentComposerPrompt('Show me my pending deals and identify any deals that haven\'t received follow-ups in the last 14 days.')" class="agent-welcome-card p-4 bg-white border border-slate-200 hover:border-indigo-400 rounded-2xl hover:shadow-md transition duration-200 cursor-pointer group flex items-start space-x-3.5">
                        <div class="h-9 w-9 rounded-xl bg-blue-50 border border-blue-200 flex items-center justify-center text-blue-600 shrink-0 group-hover:scale-105 transition">
                            <i data-lucide="dollar-sign" class="h-4.5 w-4.5"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 group-hover:text-indigo-600 transition">Pipeline & Deal Intelligence</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Audit deals at risk and review expected revenue.</p>
                        </div>
                    </div>

                    <div onclick="setAgentComposerPrompt('Give me a complete daily executive briefing of today\'s meetings, hot leads, and overdue tasks.')" class="agent-welcome-card p-4 bg-white border border-slate-200 hover:border-indigo-400 rounded-2xl hover:shadow-md transition duration-200 cursor-pointer group flex items-start space-x-3.5">
                        <div class="h-9 w-9 rounded-xl bg-purple-50 border border-purple-200 flex items-center justify-center text-purple-600 shrink-0 group-hover:scale-105 transition">
                            <i data-lucide="sparkles" class="h-4.5 w-4.5"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 group-hover:text-indigo-600 transition">Daily Executive Briefing</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Synthesize today's meetings, priority leads & urgent tasks.</p>
                        </div>
                    </div>

                    <div onclick="setAgentComposerPrompt('Summarize my incoming emails and draft a follow-up reply for the most urgent lead.')" class="agent-welcome-card p-4 bg-white border border-slate-200 hover:border-indigo-400 rounded-2xl hover:shadow-md transition duration-200 cursor-pointer group flex items-start space-x-3.5">
                        <div class="h-9 w-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 shrink-0 group-hover:scale-105 transition">
                            <i data-lucide="mail" class="h-4.5 w-4.5"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-900 group-hover:text-indigo-600 transition">Email Correspondence Assistant</h4>
                            <p class="text-[11px] text-slate-500 mt-0.5">Search emails, summarize threads & prepare drafts.</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Prompt Chips -->
                <div class="space-y-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 block text-center">Suggested Commands:</span>
                    <div class="flex flex-wrap gap-2 justify-center text-xs">
                        <button onclick="setAgentComposerPrompt('What can you do?')" class="agent-quick-pill px-3 py-1.5 bg-white hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 rounded-full font-medium transition cursor-pointer">What can you do?</button>
                        <button onclick="setAgentComposerPrompt('Show my pending tasks for today')" class="agent-quick-pill px-3 py-1.5 bg-white hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 rounded-full font-medium transition cursor-pointer">Today's pending tasks</button>
                        <button onclick="setAgentComposerPrompt('Find hot leads with high buying intent')" class="agent-quick-pill px-3 py-1.5 bg-white hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 rounded-full font-medium transition cursor-pointer">🔥 Top hot leads</button>
                        <button onclick="setAgentComposerPrompt('What is our total pipeline revenue by stage?')" class="agent-quick-pill px-3 py-1.5 bg-white hover:bg-indigo-50 hover:text-indigo-600 border border-slate-200 rounded-full font-medium transition cursor-pointer">📊 Pipeline breakdown</button>
                    </div>
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

        let html = '<div class="max-w-4xl mx-auto space-y-6">';

        messages.forEach(msg => {
            if (msg.role === 'user') {
                html += renderUserMessageHTML(msg);
            } else if (msg.role === 'assistant') {
                html += renderAssistantMessageHTML(msg);
            }
        });

        html += '</div>';
        container.innerHTML = html;
        scrollAgentToBottom();
        if (window.lucide) lucide.createIcons();
    }

    /**
     * User speech card HTML.
     */
    function renderUserMessageHTML(msg) {
        return `
            <div class="flex items-start justify-end space-x-2.5">
                <div class="max-w-[85%] md:max-w-[75%] bg-indigo-600 text-white p-3.5 rounded-2xl rounded-tr-none shadow-sm text-xs md:text-sm font-medium leading-relaxed">
                    <p class="whitespace-pre-wrap">${escapeHtml(msg.content)}</p>
                </div>
                <div class="h-7 w-7 rounded-full bg-slate-800 text-white font-bold flex items-center justify-center text-[10px] shrink-0 border border-slate-700">
                    You
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
                        <div class="flex items-center space-x-2">
                            <i data-lucide="${icon}" class="h-4 w-4 shrink-0"></i>
                            <span class="font-bold text-slate-800">${escapeHtml(tc.tool_name || tc.tool_id)}</span>
                        </div>
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase ${isSuccess ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'}">${tc.status}</span>
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
                <div class="agent-approval-card space-y-3">
                    <div class="flex items-center space-x-2 text-amber-800 font-bold text-xs uppercase tracking-wide">
                        <i data-lucide="shield-alert" class="h-4 w-4 text-amber-600"></i>
                        <span>Confirmation Required Before Execution</span>
                    </div>
                    <div class="text-xs text-slate-700 bg-white p-3 rounded-xl border border-amber-200 shadow-xs">
                        <div class="font-bold text-slate-900 mb-1">Action: ${escapeHtml(appr.tool_name)}</div>
                        <p class="text-slate-600 text-[11px] mb-2">${escapeHtml(appr.description)}</p>
                        <div class="bg-slate-50 p-2.5 rounded-lg text-[11px] font-mono border border-slate-200 overflow-x-auto">
                            ${escapeHtml(JSON.stringify(appr.arguments, null, 2))}
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 pt-1">
                        <button onclick="approveAgentAction('${appr.tool_id}', ${escapeHtml(JSON.stringify(appr.arguments))})" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm flex items-center space-x-1.5 cursor-pointer">
                            <i data-lucide="check" class="h-3.5 w-3.5"></i>
                            <span>Approve & Execute</span>
                        </button>
                        <button onclick="rejectAgentAction()" class="agent-btn-secondary px-3.5 py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-xl text-xs font-bold transition cursor-pointer">
                            Cancel
                        </button>
                    </div>
                </div>
            `;
        }

        const renderedMarkdown = renderMarkdown(msg.content);

        return `
            <div class="flex items-start space-x-3">
                <div class="h-8 w-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center text-xs shrink-0 shadow-sm border border-indigo-400/30">
                    <i data-lucide="bot" class="h-4 w-4"></i>
                </div>
                <div class="flex-grow max-w-[90%] md:max-w-[85%] bg-white border border-slate-200/90 rounded-2xl rounded-tl-none p-4 md:p-5 shadow-xs">
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
            container.innerHTML = '<div class="max-w-4xl mx-auto space-y-6"></div>';
        }

        const streamWrapper = container.querySelector('.max-w-4xl') || container;

        // Append User Message immediately
        streamWrapper.insertAdjacentHTML('beforeend', renderUserMessageHTML({ content: message }));
        scrollAgentToBottom();

        // Create Assistant live placeholder
        const assistantPlaceholderId = 'assistant-live-' + Date.now();
        const assistantLiveHTML = `
            <div id="${assistantPlaceholderId}" class="flex items-start space-x-3 animate-fade-in">
                <div class="h-8 w-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center text-xs shrink-0 shadow-sm border border-indigo-400/30">
                    <i data-lucide="bot" class="h-4 w-4"></i>
                </div>
                <div class="flex-grow max-w-[90%] md:max-w-[85%] bg-white border border-slate-200/90 rounded-2xl rounded-tl-none p-4 md:p-5 shadow-xs">
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

    function scrollAgentToBottom() {
        const container = document.getElementById('agent-messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
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
