// dashboard/assets/js/agent.js - LinkPilot Autonomous Agent Interface

(function () {
    const AGENT_AVATAR_STATIC = 'assets/img/image.png';
    const AGENT_AVATAR_THINKING = 'assets/img/Purple Penguin Laptop Animation.gif';

    let activeConversationId = null;
    let conversationsList = [];
    let isGenerating = false;
    let abortController = null;
    let attachedFile = null;
    let isHistoryOpen = false; // Collapsed by default for conversation-first focus
    let isProfileOpen = true;  // Open by default matching screenshot
    let agentCustomName = localStorage.getItem('linkpilot_agent_name') || 'Muse';
    let selectedComposerModel = localStorage.getItem('linkpilot_agent_model') || 'Model X';
    let speechRecognitionInstance = null;
    let isVoiceListening = false;
    let scheduledTasksList = [];

    // Helper functions for capsule composer
    window.toggleAgentPlusMenu = function (e) {
        if (e && e.stopPropagation) e.stopPropagation();
        const menu = document.getElementById('agent-plus-menu');
        const modelMenu = document.getElementById('agent-model-dropdown');
        if (modelMenu) modelMenu.classList.add('hidden');
        if (menu) menu.classList.toggle('hidden');
    };

    window.toggleAgentModelDropdown = function (e) {
        if (e && e.stopPropagation) e.stopPropagation();
        const modelMenu = document.getElementById('agent-model-dropdown');
        const plusMenu = document.getElementById('agent-plus-menu');
        if (plusMenu) plusMenu.classList.add('hidden');
        if (modelMenu) modelMenu.classList.toggle('hidden');
    };

    window.setComposerModel = function (modelName) {
        selectedComposerModel = modelName;
        localStorage.setItem('linkpilot_agent_model', modelName);
        const label = document.getElementById('agent-active-model-name');
        if (label) label.textContent = modelName;
        const modelMenu = document.getElementById('agent-model-dropdown');
        if (modelMenu) modelMenu.classList.add('hidden');
        showNotification('info', `Active model set to: ${modelName}`);
    };

    window.toggleAgentVoiceInput = function () {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            showNotification('warning', 'Speech recognition is not supported in this browser. Please use Chrome, Edge, or Safari.');
            return;
        }

        const micBtn = document.getElementById('agent-mic-button');
        const inputEl = document.getElementById('agent-user-prompt-input');

        if (isVoiceListening && speechRecognitionInstance) {
            speechRecognitionInstance.stop();
            return;
        }

        try {
            speechRecognitionInstance = new SpeechRecognition();
            speechRecognitionInstance.continuous = false;
            speechRecognitionInstance.interimResults = true;
            speechRecognitionInstance.lang = 'en-US';

            speechRecognitionInstance.onstart = function () {
                isVoiceListening = true;
                if (micBtn) {
                    micBtn.classList.add('bg-rose-100', 'text-rose-600', 'animate-pulse');
                    micBtn.title = 'Listening... click to stop';
                }
                showNotification('info', 'Listening... Speak your prompt.');
            };

            speechRecognitionInstance.onresult = function (event) {
                let transcript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    transcript += event.results[i][0].transcript;
                }
                if (inputEl) {
                    inputEl.value = transcript;
                    autoResizeTextarea(inputEl);
                }
            };

            speechRecognitionInstance.onerror = function (event) {
                isVoiceListening = false;
                if (micBtn) {
                    micBtn.classList.remove('bg-rose-100', 'text-rose-600', 'animate-pulse');
                    micBtn.title = 'Voice input (Dictate)';
                }
                if (event.error !== 'no-speech') {
                    showNotification('warning', `Voice input notice: ${event.error}`);
                }
            };

            speechRecognitionInstance.onend = function () {
                isVoiceListening = false;
                if (micBtn) {
                    micBtn.classList.remove('bg-rose-100', 'text-rose-600', 'animate-pulse');
                    micBtn.title = 'Voice input (Dictate)';
                }
            };

            speechRecognitionInstance.start();
        } catch (err) {
            showNotification('error', `Microphone access error: ${err.message}`);
        }
    };

    // Close floating popups when clicking outside
    document.addEventListener('click', function (e) {
        const plusMenu = document.getElementById('agent-plus-menu');
        const plusBtn = document.getElementById('agent-plus-btn');
        if (plusMenu && !plusMenu.classList.contains('hidden')) {
            if (!plusMenu.contains(e.target) && !plusBtn?.contains(e.target)) {
                plusMenu.classList.add('hidden');
            }
        }
        const modelMenu = document.getElementById('agent-model-dropdown');
        const modelBtn = document.getElementById('agent-model-pill-btn');
        if (modelMenu && !modelMenu.classList.contains('hidden')) {
            if (!modelMenu.contains(e.target) && !modelBtn?.contains(e.target)) {
                modelMenu.classList.add('hidden');
            }
        }
    });

    const defaultScheduledTasks = [
        {
            id: 'st_1',
            title: 'Daily hot-leads WhatsApp pack',
            schedule: 'Every day at 17:45',
            next_run: '14 Nov, 17:45',
            category: 'whatsapp',
            icon: 'calendar',
            badge: 'J',
            badgeColor: 'bg-amber-500',
            status: 'active',
            description: 'Automatically scans CRM contacts for high-buying-intent leads with unreplied WhatsApp conversations in the past 7 days and prepares follow-ups.'
        },
        {
            id: 'st_2',
            title: 'Heartbeat',
            schedule: 'Every 30 minutes',
            next_run: 'Every 30 minutes',
            category: 'system',
            icon: 'heart',
            badge: '',
            badgeColor: '',
            status: 'active',
            description: 'System background heartbeat monitoring active webhooks, WhatsApp sync health, and incoming business lead alerts.'
        },
        {
            id: 'st_3',
            title: 'International lead research (45/day)',
            schedule: 'Every day at 08:45',
            next_run: '14 Nov, 8:45',
            category: 'enrichment',
            icon: 'calendar',
            badge: 'J',
            badgeColor: 'bg-amber-500',
            status: 'active',
            description: 'Autonomous enrichment of prospective international contacts with verified company emails, phone codes, and company sizes.'
        },
        {
            id: 'st_4',
            title: 'Executive Morning Briefing',
            schedule: 'Weekdays at 09:00 AM',
            next_run: 'Tomorrow, 9:00 AM',
            category: 'briefing',
            icon: 'sparkles',
            badge: '',
            badgeColor: '',
            status: 'active',
            description: 'Synthesizes today\'s scheduled meetings, at-risk deals in pipeline, and overdue CRM tasks into an executive summary.'
        },
        {
            id: 'st_5',
            title: 'Stalled Deals Reviver',
            schedule: 'Every Monday at 10:00 AM',
            next_run: 'Next Monday, 10:00 AM',
            category: 'deals',
            icon: 'dollar-sign',
            badge: '',
            badgeColor: '',
            status: 'active',
            description: 'Detects deals in pipeline with no activity or note added in over 14 days and automatically creates urgent follow-up tasks.'
        },
        {
            id: 'st_6',
            title: 'Run batch script',
            schedule: 'Executed Nov 11, 14:20',
            next_run: 'Completed',
            category: 'crm',
            icon: 'check',
            badge: '',
            badgeColor: '',
            status: 'completed',
            description: 'Batch customer data normalization and conversation index update executed successfully across channels.'
        }
    ];

    /**
     * Main entry point when routing to #/agent
     */
    window.renderAgentView = async function (container, params = {}) {
        // Hide floating AI chatbot widget on agent tab to prevent competing UI
        const floatingChatbotBtn = document.getElementById('ai-chat-trigger-btn');
        if (floatingChatbotBtn) floatingChatbotBtn.style.display = 'none';
        const floatingDrawer = document.getElementById('ai-chat-assistant-drawer');
        if (floatingDrawer) floatingDrawer.classList.add('translate-x-full');

        // Collapsed by default for conversation-first focus
        isHistoryOpen = false;
        // On desktop matching screenshot, open profile sidebar
        isProfileOpen = (window.innerWidth >= 1024);

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
                            <button onclick="createNewAgentChat()" class="w-full py-1.5 px-3 bg-slate-900 hover:bg-black active:scale-[0.99] text-white rounded-lg text-xs font-semibold transition shadow-xs flex items-center justify-center space-x-1.5 cursor-pointer">
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
                                ${escapeHtml(agentCustomName)}
                            </span>
                            <button onclick="openAgentAppsModal()" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold cursor-pointer">
                                Apps
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Backdrop on Mobile -->
                <div id="agent-history-backdrop" onclick="toggleAgentHistorySidebar(false)" class="hidden fixed inset-0 bg-slate-900/30 backdrop-blur-xs z-20 lg:hidden"></div>

                <!-- 2. CENTER MAIN CHAT AREA (Conversation-First) -->
                <main class="flex-1 flex flex-col h-full min-w-0 bg-[#f8fafc] relative overflow-hidden">
                    <!-- Top Bar Header (Compact: 52px height) -->
                    <header id="agent-chat-header" class="h-13 px-4 md:px-5 bg-white border-b border-slate-200/80 flex items-center justify-between shrink-0 z-20">
                        <!-- Left: Sidebar toggle, Conversation Title, Online status -->
                        <div class="flex items-center space-x-3 min-w-0">
                            <button onclick="toggleAgentHistorySidebar()" class="agent-history-toggle-btn p-1.5 hover:bg-slate-100 rounded-lg text-slate-500 hover:text-slate-800 transition cursor-pointer border border-slate-200/80" title="Toggle chat history" aria-expanded="false">
                                <i data-lucide="panel-left" class="h-4 w-4"></i>
                            </button>
                            <div class="flex items-center space-x-2 truncate">
                                <h2 id="agent-active-title" class="text-sm font-bold text-slate-900 truncate">Chats</h2>
                            </div>
                        </div>

                        <!-- Right: Actions -->
                        <div class="flex items-center space-x-2 shrink-0">
                            <button onclick="createNewAgentChat()" class="px-2.5 py-1 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200/90 rounded-lg shadow-2xs flex items-center gap-1.5 transition cursor-pointer" title="New conversation">
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                                <span class="hidden sm:inline">New Chat</span>
                            </button>
                            <button onclick="toggleAgentProfileSidebar()" id="agent-profile-toggle-btn" class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-600 hover:text-slate-900 border border-slate-200/80 transition cursor-pointer flex items-center gap-1.5" title="Toggle AI Agent profile & scheduled tasks">
                                <img src="${AGENT_AVATAR_STATIC}" alt="Profile" class="h-5 w-5 rounded-full object-cover">
                                <span class="text-xs font-medium text-slate-700 hidden md:inline" id="agent-header-name">${escapeHtml(agentCustomName)}</span>
                            </button>
                        </div>
                    </header>

                    <!-- Scrollable Messages Area -->
                    <div class="flex-1 min-h-0 overflow-y-auto px-4 md:px-6 py-6 agent-custom-scrollbar" id="agent-messages-container">
                        <!-- Messages or Welcome State injected dynamically -->
                    </div>

                    <!-- Floating Bottom Composer Area (Exact match to User Screenshot) -->
                    <div id="agent-composer-bar" class="shrink-0 px-4 md:px-6 pb-4 pt-1 z-10">
                        <div class="max-w-3xl mx-auto space-y-1.5 relative">
                            <!-- Attachment preview pill -->
                            <div id="agent-attachment-preview" class="hidden flex items-center space-x-2 px-3 py-1.5 bg-violet-50/90 border border-violet-200/80 rounded-xl w-max text-xs text-violet-900 shadow-2xs backdrop-blur-xs mb-1">
                                <i data-lucide="paperclip" class="h-3.5 w-3.5 text-violet-600"></i>
                                <span id="agent-attachment-name" class="font-medium truncate max-w-xs">file.pdf</span>
                                <button type="button" onclick="clearAgentAttachment()" class="hover:text-rose-600 p-0.5 rounded cursor-pointer transition" title="Remove attachment"><i data-lucide="x" class="h-3 w-3"></i></button>
                            </div>

                            <!-- Plus Action Popover Menu (Above Plus Button) -->
                            <div id="agent-plus-menu" class="hidden absolute left-2 bottom-16 bg-white rounded-2xl shadow-xl border border-slate-200/90 py-1.5 px-1 min-w-[210px] z-40 animate-fade-in">
                                <button type="button" onclick="document.getElementById('agent-file-picker').click(); toggleAgentPlusMenu(false);" class="w-full flex items-center space-x-2.5 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-violet-50 hover:text-violet-700 rounded-xl transition cursor-pointer">
                                    <div class="w-7 h-7 rounded-lg bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
                                        <i data-lucide="paperclip" class="h-4 w-4"></i>
                                    </div>
                                    <div class="text-left">
                                        <div class="font-semibold text-slate-800">Attach File</div>
                                        <div class="text-[10px] text-slate-400 font-normal">Documents, images, CSV</div>
                                    </div>
                                </button>
                                <button type="button" onclick="openAgentAppsModal(); toggleAgentPlusMenu(false);" class="w-full flex items-center space-x-2.5 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-violet-50 hover:text-violet-700 rounded-xl transition cursor-pointer">
                                    <div class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center shrink-0">
                                        <i data-lucide="layout-grid" class="h-4 w-4"></i>
                                    </div>
                                    <div class="text-left">
                                        <div class="font-semibold text-slate-800">AI Tools & Apps</div>
                                        <div class="text-[10px] text-slate-400 font-normal">WhatsApp, CRM, Search</div>
                                    </div>
                                </button>
                            </div>

                            <!-- Pill Input Capsule (Screenshot Match) -->
                            <form id="agent-composer-form" onsubmit="handleAgentSendMessage(event)" class="agent-capsule-composer relative flex items-center bg-white rounded-full px-2 py-1.5 md:px-2.5 md:py-2 border border-slate-200/90 transition-all duration-200">
                                <input type="file" id="agent-file-picker" class="hidden" onchange="handleAgentFileSelected(event)">

                                <!-- Far Left: Soft Round Plus Button (+) -->
                                <button type="button" id="agent-plus-btn" onclick="toggleAgentPlusMenu(event)" class="agent-round-plus-btn h-9 w-9 md:h-10 md:w-10 rounded-full bg-[#f4f4f5] hover:bg-[#e4e4e7] active:scale-95 text-[#18181b] transition flex items-center justify-center shrink-0 cursor-pointer shadow-2xs" title="Add file or tools">
                                    <i data-lucide="plus" class="h-4 w-4 stroke-[2.4]"></i>
                                </button>

                                <!-- Center Textarea (Exact placeholder: "What can I help you with today?") -->
                                <div class="flex-1 min-w-0 px-3 md:px-4 flex items-center">
                                    <textarea id="agent-user-prompt-input" rows="1" placeholder="What can I help you with today?" onkeydown="handleAgentTextareaKeydown(event)" oninput="autoResizeTextarea(this)" class="w-full bg-transparent border-0 focus:outline-none focus:ring-0 text-[14.5px] md:text-[15px] text-slate-800 placeholder-[#71717a] font-normal px-0 py-1.5 max-h-36 resize-none leading-normal"></textarea>
                                </div>

                                <!-- Far Right Action Group: Mic and Violet Arrow-Up Send Button -->
                                <div class="flex items-center space-x-1.5 md:space-x-2 shrink-0">
                                    <!-- Microphone Button (Dictation) -->
                                    <button type="button" id="agent-mic-button" onclick="toggleAgentVoiceInput()" class="h-9 w-9 md:h-10 md:w-10 rounded-full text-[#18181b] hover:bg-[#f4f4f5] active:scale-95 transition flex items-center justify-center cursor-pointer" title="Voice input (Dictate)">
                                        <i data-lucide="mic" class="h-4.5 w-4.5 stroke-[1.9]"></i>
                                    </button>

                                    <!-- Purple Circular Send Button -->
                                    <button type="submit" id="agent-send-button" class="agent-purple-send-btn h-9 w-9 md:h-10 md:w-10 rounded-full bg-[#a259ff] hover:bg-[#9333ea] active:scale-95 text-white transition-all duration-200 shadow-md flex items-center justify-center cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed" title="Send message">
                                        <i data-lucide="arrow-up" class="h-4.5 w-4.5 text-white stroke-[2.4]"></i>
                                    </button>
                                </div>
                            </form>

                            <!-- Bottom Right Toolbar: Model X & Sliders Controls (Exact Screenshot Match) -->
                            <div class="flex items-center justify-end space-x-2 pt-1 px-2">
                                <!-- Model X Dropdown -->
                                <div class="relative">
                                    <button type="button" id="agent-model-pill-btn" onclick="toggleAgentModelDropdown(event)" class="flex items-center space-x-1 px-3 py-1 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 rounded-full border border-slate-200/80 hover:border-slate-300 text-xs font-medium transition shadow-2xs cursor-pointer">
                                        <span id="agent-active-model-name">${escapeHtml(selectedComposerModel)}</span>
                                        <i data-lucide="chevron-down" class="h-3.5 w-3.5 text-slate-400"></i>
                                    </button>
                                    <!-- Model Selector Dropdown Menu -->
                                    <div id="agent-model-dropdown" class="hidden absolute right-0 bottom-full mb-1.5 w-56 bg-white rounded-2xl shadow-xl border border-slate-200/90 py-1.5 z-40 animate-fade-in">
                                        <div class="px-3.5 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Select AI Model</div>
                                        <button type="button" onclick="setComposerModel('Model X')" class="w-full text-left px-3.5 py-2 text-xs text-slate-700 hover:bg-violet-50 hover:text-violet-700 font-semibold flex items-center justify-between cursor-pointer transition">
                                            <div class="flex items-center space-x-2">
                                                <span class="w-2 h-2 rounded-full bg-violet-500"></span>
                                                <span>Model X (Auto)</span>
                                            </div>
                                            <span class="text-[10px] bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded-full font-bold">Smart</span>
                                        </button>
                                        <button type="button" onclick="setComposerModel('Gemini 3 Flash')" class="w-full text-left px-3.5 py-2 text-xs text-slate-700 hover:bg-violet-50 hover:text-violet-700 font-medium flex items-center justify-between cursor-pointer transition">
                                            <div class="flex items-center space-x-2">
                                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                                <span>Gemini 3 Flash</span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-mono">Google</span>
                                        </button>
                                        <button type="button" onclick="setComposerModel('Llama 3.1 8B')" class="w-full text-left px-3.5 py-2 text-xs text-slate-700 hover:bg-violet-50 hover:text-violet-700 font-medium flex items-center justify-between cursor-pointer transition">
                                            <div class="flex items-center space-x-2">
                                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                                <span>Llama 3.1 8B</span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-mono">Groq</span>
                                        </button>
                                        <button type="button" onclick="setComposerModel('GPT-4o Mini')" class="w-full text-left px-3.5 py-2 text-xs text-slate-700 hover:bg-violet-50 hover:text-violet-700 font-medium flex items-center justify-between cursor-pointer transition">
                                            <div class="flex items-center space-x-2">
                                                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                                                <span>GPT-4o Mini</span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-mono">OpenAI</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Sliders Equalizer Button (Opens Connected Tools popup) -->
                                <button type="button" id="agent-tools-slider-btn" onclick="openAgentAppsModal()" class="h-7 w-7 rounded-lg text-slate-600 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-200/80 hover:border-slate-300 transition shadow-2xs cursor-pointer flex items-center justify-center" title="AI Tools & Connected Apps">
                                    <i data-lucide="sliders" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </main>

                <!-- 3. RIGHT SIDE AGENT PROFILE & SCHEDULED TASKS PANEL (Matching Screenshot) -->
                <div id="agent-profile-sidebar" class="${isProfileOpen ? 'w-[280px] lg:w-[320px] opacity-100 pointer-events-auto border-l' : 'w-0 opacity-0 pointer-events-none border-l-0 overflow-hidden'} bg-white border-slate-200/80 flex flex-col shrink-0 h-full overflow-hidden transition-all duration-300 z-20 fixed lg:relative inset-y-0 right-0">
                    <div class="w-[280px] lg:w-[320px] flex flex-col h-full">
                        <!-- Top bar with close button -->
                        <div class="h-12 px-4 border-b border-slate-100 flex items-center justify-end shrink-0">
                            <button onclick="toggleAgentProfileSidebar(false)" class="p-1 text-slate-400 hover:text-slate-700 rounded-md hover:bg-slate-100 transition cursor-pointer" title="Close profile">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>

                        <!-- Profile Content Body -->
                        <div class="flex-grow overflow-y-auto p-4 space-y-4 agent-custom-scrollbar">
                            <!-- Center Avatar & Editable Name -->
                            <div class="text-center">
                                <div class="relative inline-block mx-auto">
                                    <div class="h-24 w-24 rounded-full overflow-hidden border border-slate-200/80 shadow-xs bg-slate-50 mx-auto">
                                        <img id="agent-profile-large-avatar" src="${AGENT_AVATAR_STATIC}" alt="Agent Avatar" class="h-full w-full object-cover">
                                    </div>
                                    <button onclick="promptRenameAgent()" class="h-6 w-6 rounded-full bg-white hover:bg-slate-50 border border-slate-300 text-slate-600 shadow-xs absolute bottom-0 right-0 flex items-center justify-center cursor-pointer transition" title="Rename AI Agent">
                                        <i data-lucide="edit-2" class="h-3 w-3"></i>
                                    </button>
                                </div>

                                <h3 id="agent-profile-name-display" class="text-base font-bold text-slate-900 mt-2 flex items-center justify-center gap-1.5">
                                    ${escapeHtml(agentCustomName)}
                                    <button onclick="promptRenameAgent()" class="text-slate-400 hover:text-slate-700 p-0.5" title="Rename AI Agent">
                                        <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                                    </button>
                                </h3>

                                <div class="flex items-center justify-center space-x-1.5 text-xs text-slate-600 mt-0.5">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span class="font-medium text-slate-700">Connected</span>
                                </div>
                            </div>

                            <!-- Tab Pills (Tasks, Shield, Fingerprint) -->
                            <div class="flex items-center justify-around p-1 bg-slate-100/90 rounded-xl text-slate-500">
                                <button class="p-1.5 bg-white text-slate-900 rounded-lg shadow-2xs w-full flex justify-center cursor-pointer" title="Scheduled Tasks">
                                    <i data-lucide="list-todo" class="h-4 w-4"></i>
                                </button>
                                <button onclick="openAgentAppsModal()" class="p-1.5 hover:text-slate-900 rounded-lg w-full flex justify-center cursor-pointer" title="Connected Apps & Security">
                                    <i data-lucide="shield-check" class="h-4 w-4"></i>
                                </button>
                                <button onclick="openAgentCapabilitiesModal()" class="p-1.5 hover:text-slate-900 rounded-lg w-full flex justify-center cursor-pointer" title="Agent Identity & Tools">
                                    <i data-lucide="fingerprint" class="h-4 w-4"></i>
                                </button>
                            </div>

                            <!-- 9 Scheduled Tasks Accordion Section -->
                            <div class="border-t border-slate-100 pt-3 space-y-2">
                                <div onclick="toggleScheduledTasksList()" class="flex items-center justify-between text-xs font-bold text-slate-800 cursor-pointer hover:text-slate-950 py-1">
                                    <span class="flex items-center gap-1">
                                        <span id="scheduled-tasks-count">9</span> scheduled tasks
                                    </span>
                                    <i data-lucide="chevron-down" id="scheduled-tasks-chevron" class="h-4 w-4 text-slate-400 transition-transform"></i>
                                </div>

                                <div id="scheduled-tasks-container" class="space-y-1.5">
                                    <!-- Scheduled task items rendered dynamically -->
                                </div>

                                <button onclick="openAllScheduledTasksModal()" class="w-full text-center text-xs font-semibold text-slate-500 hover:text-slate-800 py-1.5 transition block cursor-pointer">
                                    See all scheduled tasks
                                </button>
                            </div>

                            <!-- Date Grouped History: Nov 11 -->
                            <div class="border-t border-slate-100 pt-3 space-y-2">
                                <div class="flex items-center justify-between text-xs font-bold text-slate-800 cursor-pointer py-1">
                                    <span>Nov 11</span>
                                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400"></i>
                                </div>
                                <div class="p-2.5 bg-slate-50 hover:bg-slate-100/80 border border-slate-200/70 rounded-xl flex items-center space-x-2.5 transition cursor-pointer" onclick="openScheduledTaskDetailModal('st_6')">
                                    <div class="h-7 w-7 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shrink-0">
                                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h5 class="text-xs font-semibold text-slate-800 truncate">Run batch script</h5>
                                        <p class="text-[10px] text-slate-400">Completed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modals Root -->
            <div id="agent-task-detail-modal-root"></div>
            <div id="agent-apps-modal-root"></div>
            <div id="agent-capabilities-modal-root"></div>
        `;

        if (window.lucide) lucide.createIcons();

        // Load settings and render initial scheduled tasks
        renderScheduledTasksList();
        await loadAgentSettings();

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
    }

    /**
     * Toggles right side Agent Profile & Scheduled Tasks panel.
     */
    window.toggleAgentProfileSidebar = function (forceState = null) {
        isProfileOpen = (forceState !== null) ? forceState : !isProfileOpen;
        applyProfileSidebarState();
    };

    function applyProfileSidebarState() {
        const sidebar = document.getElementById('agent-profile-sidebar');
        if (!sidebar) return;

        if (isProfileOpen) {
            sidebar.classList.remove('w-0', 'opacity-0', 'pointer-events-none', 'border-l-0');
            sidebar.classList.add('w-[280px]', 'lg:w-[320px]', 'opacity-100', 'pointer-events-auto', 'border-l');
        } else {
            sidebar.classList.remove('w-[280px]', 'lg:w-[320px]', 'opacity-100', 'pointer-events-auto', 'border-l');
            sidebar.classList.add('w-0', 'opacity-0', 'pointer-events-none', 'border-l-0');
        }
    }

    /**
     * Rename Agent (persists in DB via crm/agent.php?action=update_agent_settings).
     */
    window.promptRenameAgent = async function () {
        const currentName = agentCustomName || 'Muse';
        const newName = prompt("Name your AI Agent:", currentName);
        if (newName && newName.trim() && newName.trim() !== currentName) {
            const cleanName = newName.trim();
            agentCustomName = cleanName;
            localStorage.setItem('linkpilot_agent_name', cleanName);

            // Update UI elements
            const nameDisplay = document.getElementById('agent-profile-name-display');
            if (nameDisplay) {
                nameDisplay.innerHTML = `
                    ${escapeHtml(cleanName)}
                    <button onclick="promptRenameAgent()" class="text-slate-400 hover:text-slate-700 p-0.5" title="Rename AI Agent">
                        <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                    </button>
                `;
            }
            const headerName = document.getElementById('agent-header-name');
            if (headerName) headerName.textContent = cleanName;

            if (window.lucide) lucide.createIcons();

            // Persist to backend
            try {
                await apiCall('crm/agent.php', 'POST', {
                    action: 'update_agent_settings',
                    agent_name: cleanName
                });
                if (typeof showNotification === 'function') {
                    showNotification('success', `AI Agent named "${cleanName}"!`);
                }
            } catch (err) {
                console.warn("Failed to persist agent name in backend:", err);
            }
        }
    };

    /**
     * Load Agent settings and custom name from backend.
     */
    async function loadAgentSettings() {
        try {
            const res = await apiCall('crm/agent.php?action=get_agent_settings');
            if (res && res.data) {
                if (res.data.agent_name) {
                    agentCustomName = res.data.agent_name;
                    localStorage.setItem('linkpilot_agent_name', agentCustomName);
                    const nameDisplay = document.getElementById('agent-profile-name-display');
                    if (nameDisplay) {
                        nameDisplay.innerHTML = `
                            ${escapeHtml(agentCustomName)}
                            <button onclick="promptRenameAgent()" class="text-slate-400 hover:text-slate-700 p-0.5" title="Rename AI Agent">
                                <i data-lucide="edit-2" class="h-3.5 w-3.5"></i>
                            </button>
                        `;
                    }
                    const headerName = document.getElementById('agent-header-name');
                    if (headerName) headerName.textContent = agentCustomName;
                    if (window.lucide) lucide.createIcons();
                }

                if (res.data.scheduled_tasks && Array.isArray(res.data.scheduled_tasks) && res.data.scheduled_tasks.length > 0) {
                    scheduledTasksList = res.data.scheduled_tasks;
                } else {
                    scheduledTasksList = [...defaultScheduledTasks];
                }
                renderScheduledTasksList();
            }
        } catch (err) {
            console.warn("Could not load agent settings:", err);
            scheduledTasksList = [...defaultScheduledTasks];
            renderScheduledTasksList();
        }
    }

    /**
     * Toggle Scheduled tasks accordion
     */
    let isScheduledTasksAccordionOpen = true;
    window.toggleScheduledTasksList = function () {
        isScheduledTasksAccordionOpen = !isScheduledTasksAccordionOpen;
        const container = document.getElementById('scheduled-tasks-container');
        const chevron = document.getElementById('scheduled-tasks-chevron');
        if (container) {
            if (isScheduledTasksAccordionOpen) {
                container.classList.remove('hidden');
                if (chevron) chevron.classList.remove('-rotate-90');
            } else {
                container.classList.add('hidden');
                if (chevron) chevron.classList.add('-rotate-90');
            }
        }
    };

    /**
     * Render the top scheduled tasks matching screenshot
     */
    function renderScheduledTasksList() {
        const container = document.getElementById('scheduled-tasks-container');
        const countEl = document.getElementById('scheduled-tasks-count');
        if (!container) return;

        const tasks = scheduledTasksList.length > 0 ? scheduledTasksList : defaultScheduledTasks;
        if (countEl) countEl.textContent = tasks.length;

        // Render first 3 active tasks
        const activeTasks = tasks.filter(t => t.status !== 'completed').slice(0, 3);

        let html = '';
        activeTasks.forEach(task => {
            const isHeart = task.icon === 'heart';
            const iconBg = isHeart ? 'bg-pink-50 border-pink-100 text-pink-500' : 'bg-slate-100 border-slate-200 text-slate-700';
            const iconName = isHeart ? 'heart' : 'calendar';

            html += `
                <div onclick="openScheduledTaskDetailModal('${escapeHtml(task.id)}')" class="p-2.5 bg-slate-50 hover:bg-slate-100/90 border border-slate-200/70 rounded-xl flex items-center justify-between transition cursor-pointer group">
                    <div class="flex items-center space-x-2.5 min-w-0">
                        <div class="relative h-8 w-8 rounded-lg ${iconBg} border flex items-center justify-center shrink-0">
                            <i data-lucide="${iconName}" class="h-4 w-4"></i>
                            ${task.badge ? `<span class="absolute -bottom-1 -right-1 text-[8px] font-bold text-white ${task.badgeColor || 'bg-amber-500'} h-3.5 w-3.5 rounded-full flex items-center justify-center">${escapeHtml(task.badge)}</span>` : ''}
                        </div>
                        <div class="min-w-0">
                            <h5 class="text-xs font-semibold text-slate-800 truncate group-hover:text-indigo-600 transition">${escapeHtml(task.title)}</h5>
                            <p class="text-[10px] text-slate-400 truncate">${escapeHtml(task.schedule)}</p>
                        </div>
                    </div>
                    <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-400 group-hover:text-slate-600 group-hover:translate-x-0.5 transition shrink-0 ml-1"></i>
                </div>
            `;
        });

        container.innerHTML = html;
        if (window.lucide) lucide.createIcons();
    }

    /**
     * Modal popup for scheduled task details
     */
    window.openScheduledTaskDetailModal = function (taskId) {
        const root = document.getElementById('agent-task-detail-modal-root');
        if (!root) return;

        const allTasks = [...(scheduledTasksList.length ? scheduledTasksList : defaultScheduledTasks)];
        const task = allTasks.find(t => t.id === taskId) || allTasks[0];
        if (!task) return;

        const isHeart = task.icon === 'heart';
        const iconBg = isHeart ? 'bg-pink-50 text-pink-500 border-pink-200' : 'bg-indigo-50 text-indigo-600 border-indigo-200';
        const iconName = isHeart ? 'heart' : (task.icon || 'calendar');

        root.innerHTML = `
            <div id="scheduled-task-modal-backdrop" onclick="closeScheduledTaskDetailModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 animate-fade-in">
                <div onclick="event.stopPropagation()" class="bg-white rounded-2xl max-w-lg w-full border border-slate-200 shadow-2xl flex flex-col overflow-hidden animate-scale-in">
                    <!-- Modal Header -->
                    <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                        <div class="flex items-center space-x-2.5">
                            <div class="h-8 w-8 rounded-xl ${iconBg} border flex items-center justify-center">
                                <i data-lucide="${iconName}" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-white">${escapeHtml(task.title)}</h3>
                                <p class="text-[10px] text-slate-400">Scheduled Autonomous Automation</p>
                            </div>
                        </div>
                        <button onclick="closeScheduledTaskDetailModal()" class="text-slate-400 hover:text-white p-1 rounded-lg transition cursor-pointer">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-5 space-y-4 text-xs text-slate-700">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Status</span>
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider ${task.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200'}">
                                ${escapeHtml(task.status)}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-3 pb-3 border-b border-slate-100">
                            <div>
                                <span class="text-[11px] text-slate-400 block font-medium">Frequency</span>
                                <span class="text-xs font-semibold text-slate-800">${escapeHtml(task.schedule)}</span>
                            </div>
                            <div>
                                <span class="text-[11px] text-slate-400 block font-medium">Next Execution</span>
                                <span class="text-xs font-semibold text-slate-800">${escapeHtml(task.next_run || 'On schedule')}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-[11px] text-slate-400 block font-medium mb-1">Task Objective & Scope</span>
                            <p class="text-xs text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200/80 leading-relaxed">
                                ${escapeHtml(task.description || 'Executes autonomous operations across connected channels and reports key findings.')}
                            </p>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                        <button onclick="closeScheduledTaskDetailModal()" class="px-3.5 py-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 transition rounded-lg hover:bg-slate-200/60 cursor-pointer">
                            Close
                        </button>
                        <div class="flex items-center space-x-2">
                            <button onclick="toggleTaskStatus('${escapeHtml(task.id)}')" class="px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 rounded-lg transition shadow-2xs cursor-pointer">
                                ${task.status === 'paused' ? 'Resume Task' : 'Pause Task'}
                            </button>
                            <button onclick="runScheduledTaskNow('${escapeHtml(task.id)}', '${escapeHtml(task.title)}')" class="px-4 py-1.5 text-xs font-bold text-white bg-black hover:bg-slate-800 rounded-lg transition shadow-2xs flex items-center space-x-1.5 cursor-pointer">
                                <i data-lucide="play" class="h-3.5 w-3.5"></i>
                                <span>Run Now</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        if (window.lucide) lucide.createIcons();
    };

    window.closeScheduledTaskDetailModal = function () {
        const root = document.getElementById('agent-task-detail-modal-root');
        if (root) root.innerHTML = '';
    };

    window.toggleTaskStatus = function (taskId) {
        const list = scheduledTasksList.length ? scheduledTasksList : defaultScheduledTasks;
        const task = list.find(t => t.id === taskId);
        if (task) {
            task.status = (task.status === 'paused') ? 'active' : 'paused';
            if (typeof showNotification === 'function') {
                showNotification('info', `Task "${task.title}" is now ${task.status}.`);
            }
            renderScheduledTasksList();
            openScheduledTaskDetailModal(taskId);
        }
    };

    window.runScheduledTaskNow = function (taskId, taskTitle) {
        closeScheduledTaskDetailModal();
        closeAllScheduledTasksModal();
        const promptText = `Run scheduled task now: ${taskTitle}. Analyze data and provide the complete briefing.`;
        setAgentComposerPrompt(promptText);
        handleAgentSendMessage();
    };

    /**
     * All Scheduled Tasks Modal
     */
    window.openAllScheduledTasksModal = function () {
        const root = document.getElementById('agent-task-detail-modal-root');
        if (!root) return;

        const tasks = scheduledTasksList.length ? scheduledTasksList : defaultScheduledTasks;

        let itemsHtml = '';
        tasks.forEach(t => {
            const isHeart = t.icon === 'heart';
            const iconBg = isHeart ? 'bg-pink-50 border-pink-100 text-pink-500' : 'bg-slate-100 border-slate-200 text-slate-700';
            const iconName = isHeart ? 'heart' : (t.icon || 'calendar');

            itemsHtml += `
                <div onclick="openScheduledTaskDetailModal('${escapeHtml(t.id)}')" class="p-3 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 rounded-xl flex items-center justify-between transition cursor-pointer">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="h-9 w-9 rounded-xl ${iconBg} border flex items-center justify-center shrink-0">
                            <i data-lucide="${iconName}" class="h-4 w-4"></i>
                        </div>
                        <div class="min-w-0">
                            <h5 class="text-xs font-bold text-slate-900 truncate">${escapeHtml(t.title)}</h5>
                            <p class="text-[11px] text-slate-500">${escapeHtml(t.schedule)} • Next: ${escapeHtml(t.next_run || 'Scheduled')}</p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${t.status === 'completed' ? 'bg-emerald-50 text-emerald-700' : 'bg-indigo-50 text-indigo-700'}">
                        ${escapeHtml(t.status)}
                    </span>
                </div>
            `;
        });

        root.innerHTML = `
            <div id="all-tasks-modal-backdrop" onclick="closeAllScheduledTasksModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 flex items-center justify-center p-4 animate-fade-in">
                <div onclick="event.stopPropagation()" class="bg-white rounded-2xl max-w-xl w-full border border-slate-200 shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                    <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                        <div>
                            <h3 class="font-bold text-sm text-white">All Scheduled Tasks (${tasks.length})</h3>
                            <p class="text-[10px] text-slate-400">Autonomous routines managed by LinkPilot Agent</p>
                        </div>
                        <button onclick="closeAllScheduledTasksModal()" class="text-slate-400 hover:text-white p-1 rounded-lg">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <div class="p-5 overflow-y-auto space-y-2.5 agent-custom-scrollbar">
                        ${itemsHtml}
                    </div>

                    <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-end">
                        <button onclick="closeAllScheduledTasksModal()" class="px-4 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-300 rounded-lg">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
    };

    window.closeAllScheduledTasksModal = function () {
        const root = document.getElementById('agent-task-detail-modal-root');
        if (root) root.innerHTML = '';
    };

    const agentAppsList = [
        {
            id: 'whatsapp',
            name: 'WhatsApp Cloud API',
            category: 'messaging',
            badge: 'Direct Chat',
            description: 'Direct 1-on-1 customer conversations, automated template campaigns & inbound lead routing.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-[#25D366] flex items-center justify-center shrink-0 shadow-xs">
                <svg viewBox="0 0 24 24" class="h-6 w-6 fill-white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91C2.13 13.66 2.59 15.36 3.45 16.86L2.05 22L7.3 20.62C8.75 21.41 10.38 21.83 12.04 21.83C17.5 21.83 21.95 17.38 21.95 11.92C21.95 9.27 20.92 6.78 19.05 4.91C17.18 3.03 14.69 2 12.04 2ZM12.04 20.15C10.56 20.15 9.11 19.76 7.85 19.01L7.55 18.83L4.43 19.65L5.26 16.61L5.06 16.29C4.24 14.99 3.81 13.47 3.81 11.91C3.81 7.37 7.5 3.68 12.04 3.68C14.25 3.68 16.31 4.54 17.87 6.1C19.42 7.66 20.28 9.72 20.28 11.92C20.28 16.46 16.58 20.15 12.04 20.15ZM16.56 14.37C16.31 14.25 15.09 13.65 14.86 13.56C14.64 13.48 14.47 13.44 14.31 13.69C14.14 13.94 13.67 14.51 13.52 14.68C13.38 14.84 13.23 14.86 12.98 14.74C12.73 14.61 11.93 14.35 10.98 13.5C10.24 12.84 9.74 12.03 9.6 11.78C9.45 11.53 9.58 11.4 9.71 11.27C9.82 11.16 9.96 10.99 10.09 10.84C10.21 10.69 10.26 10.58 10.34 10.42C10.42 10.25 10.38 10.11 10.32 9.98C10.26 9.86 9.76 8.63 9.56 8.13C9.36 7.64 9.16 7.71 9.01 7.7C8.87 7.69 8.71 7.69 8.54 7.69C8.38 7.69 8.11 7.75 7.88 8C7.66 8.25 7.02 8.84 7.02 10.05C7.02 11.25 7.9 12.42 8.02 12.58C8.15 12.75 9.75 15.22 12.2 16.28C12.78 16.53 13.24 16.68 13.59 16.79C14.18 16.98 14.71 16.95 15.14 16.89C15.61 16.82 16.59 16.29 16.8 15.72C17 15.14 17 14.65 16.94 14.55C16.88 14.45 16.72 14.39 16.56 14.27V14.37Z"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'Live webhook sync',
            suggestedPrompt: 'Draft a friendly WhatsApp follow-up for our newest prospect inviting them to a brief product demo.'
        },
        {
            id: 'google_calendar',
            name: 'Google Calendar',
            category: 'meetings',
            badge: 'Calendar 31',
            description: 'Automatic client meeting scheduling, event conflict detection, and real-time agenda syncing.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-white border border-slate-200/90 flex items-center justify-center shrink-0 shadow-2xs overflow-hidden">
                <svg viewBox="0 0 48 48" class="h-7 w-7" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="7" y="10" width="34" height="31" rx="4" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="1.5"/>
                    <path d="M7 14C7 11.79 8.79 10 11 10H37C39.21 10 41 11.79 41 14V17H7V14Z" fill="#1A73E8"/>
                    <rect x="13" y="6" width="4" height="7" rx="2" fill="#4285F4"/>
                    <rect x="31" y="6" width="4" height="7" rx="2" fill="#4285F4"/>
                    <text x="24" y="32" font-family="system-ui, -apple-system, sans-serif" font-size="14" font-weight="800" fill="#1A73E8" text-anchor="middle">31</text>
                    <circle cx="34" cy="35" r="3" fill="#34A853"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'OAuth 2.0 synced',
            suggestedPrompt: 'Schedule a 30-minute discovery call with Alex Rivera for tomorrow at 3:00 PM.'
        },
        {
            id: 'google_meet',
            name: 'Google Meet',
            category: 'meetings',
            badge: 'HD Video',
            description: 'One-click meeting room creation with encrypted join links, auto-calendar invites, and dial-in details.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-white border border-slate-200/90 flex items-center justify-center shrink-0 shadow-2xs overflow-hidden">
                <svg viewBox="0 0 48 48" class="h-7 w-7" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M29 20.5V16C29 14.9 28.1 14 27 14H11C9.9 14 9 14.9 9 16V32C9 33.1 9.9 34 11 34H27C28.1 34 29 33.1 29 32V27.5L37 33.5V14.5L29 20.5Z" fill="#00832D"/>
                    <path d="M29 20.5L37 14.5V24L29 20.5Z" fill="#0066DA"/>
                    <path d="M29 27.5L37 33.5V24L29 27.5Z" fill="#00AC47"/>
                    <path d="M9 28V32C9 33.1 9.9 34 11 34H17L9 28Z" fill="#EA4335"/>
                    <path d="M27 14H17L29 22V16C29 14.9 28.1 14 27 14Z" fill="#FFBA00"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'Direct Video API',
            suggestedPrompt: 'Generate a Google Meet room link for our upcoming quarterly sales presentation.'
        },
        {
            id: 'email',
            name: 'Gmail & Business Email',
            category: 'messaging',
            badge: 'SMTP / IMAP',
            description: 'Autonomous inbox monitoring, email draft authoring, automated follow-ups, and thread summarization.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-white border border-slate-200/90 flex items-center justify-center shrink-0 shadow-2xs overflow-hidden">
                <svg viewBox="0 0 48 48" class="h-6 w-6" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6 14V34C6 36.2 7.8 38 10 38H14V22L6 16V14Z" fill="#4285F4"/>
                    <path d="M34 38H38C40.2 38 42 36.2 42 34V14L34 20V38Z" fill="#34A853"/>
                    <path d="M34 20L42 14C41.4 13.1 40.3 12.5 39 12.5H35L24 21L13 12.5H9C7.7 12.5 6.6 13.1 6 14L14 20L24 27L34 20Z" fill="#EA4335"/>
                    <path d="M14 22V38H34V22L24 29.5L14 22Z" fill="#FBBC04"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'IMAP & SMTP connected',
            suggestedPrompt: 'Draft a compelling cold outreach email offering LinkPilot AI CRM to fintech founders.'
        },
        {
            id: 'zoom',
            name: 'Zoom Video Meetings',
            category: 'meetings',
            badge: 'Cloud Video',
            description: 'Instant meeting creation, client calendar invitations, and meeting recording transcript access.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-[#2D8CFF] flex items-center justify-center shrink-0 shadow-xs">
                <svg viewBox="0 0 24 24" class="h-6 w-6 fill-white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 7C4 5.34315 5.34315 4 7 4H13C14.6569 4 16 5.34315 16 7V17C16 18.6569 14.6569 20 13 20H7C5.34315 20 4 18.6569 4 17V7Z"/>
                    <path d="M17 9.5L20.3 7.3C20.7 7 21.2 7.3 21.2 7.8V16.2C21.2 16.7 20.7 17 20.3 16.7L17 14.5V9.5Z"/>
                </svg>
            </div>`,
            connected: false,
            docs: 'Server-to-server OAuth',
            suggestedPrompt: 'Create a Zoom meeting room for customer onboarding and prepare meeting notes template.'
        },
        {
            id: 'meta_ads',
            name: 'Meta Ads (Facebook & IG)',
            category: 'marketing',
            badge: 'Lead Gen',
            description: 'Instant ingestion of Facebook & Instagram Lead Ads directly into LinkPilot CRM with zero latency.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-[#0064E0] to-[#0081FB] flex items-center justify-center shrink-0 shadow-xs">
                <svg viewBox="0 0 24 24" class="h-5 w-5 fill-white" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 10.6C10.7 8.7 9 7.7 7.2 7.7C4.6 7.7 2.6 9.7 2.6 12.3C2.6 14.9 4.6 16.9 7.2 16.9C9 16.9 10.7 15.9 12 14C13.3 15.9 15 16.9 16.8 16.9C19.4 16.9 21.4 14.9 21.4 12.3C21.4 9.7 19.4 7.7 16.8 7.7C15 7.7 13.3 8.7 12 10.6ZM7.2 15C5.7 15 4.5 13.8 4.5 12.3C4.5 10.8 5.7 9.6 7.2 9.6C8.4 9.6 9.6 10.4 10.7 12.3C9.6 14.2 8.4 15 7.2 15ZM16.8 15C15.6 15 14.4 14.2 13.3 12.3C14.4 10.4 15.6 9.6 16.8 9.6C18.3 9.6 19.5 10.8 19.5 12.3C19.5 13.8 18.3 15 16.8 15Z"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'Graph API connected',
            suggestedPrompt: 'Check recent Facebook & Instagram Lead Ad syncs and summarize the newest 5 leads.'
        },
        {
            id: 'crm',
            name: 'LinkPilot CRM Contacts',
            category: 'crm',
            badge: 'Core Database',
            description: 'Lead profiles, custom tags, lifecycle stages, deal size, engagement logs, and company intelligence.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-white border border-slate-200/90 flex items-center justify-center shrink-0 shadow-2xs overflow-hidden p-1.5">
                <img src="assets/img/image.png" alt="LinkPilot CRM" class="h-full w-full object-contain rounded-lg">
            </div>`,
            connected: true,
            docs: 'Full database access',
            suggestedPrompt: 'Find all qualified leads with deal value above $5,000 who haven\'t been contacted in 3 days.'
        },
        {
            id: 'deals',
            name: 'Pipeline & Deals Engine',
            category: 'crm',
            badge: 'Revenue Tracker',
            description: 'Multi-stage revenue pipeline, deal win probability, stalled deal alerts, and closed-won analytics.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center shrink-0 shadow-xs">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 3v18h18"/>
                    <path d="M18 9l-5 5-4-4-3 3"/>
                    <circle cx="18" cy="9" r="2" fill="#FDE047" stroke="#FDE047"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'Real-time pipeline tracker',
            suggestedPrompt: 'Calculate our total pipeline value and list deals currently pending proposal sign-off.'
        },
        {
            id: 'automations',
            name: 'Zapier & Automations',
            category: 'ai',
            badge: 'Webhooks',
            description: 'Trigger multi-app autonomous actions on incoming webhooks, form submissions, and external events.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-[#FF4F00] flex items-center justify-center shrink-0 shadow-xs">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="white" xmlns="http://www.w3.org/2000/svg">
                    <rect x="10.5" y="4" width="3" height="16" rx="1.5"/>
                    <rect x="4" y="10.5" width="16" height="3" rx="1.5"/>
                    <rect x="6.1" y="7.2" width="3" height="13" rx="1.5" transform="rotate(-45 6.1 7.2)"/>
                    <rect x="15.3" y="5.1" width="3" height="13" rx="1.5" transform="rotate(45 15.3 5.1)"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'Active webhook listener',
            suggestedPrompt: 'Trigger an automation event to send a Slack alert whenever a new high-intent lead registers.'
        },
        {
            id: 'google_search',
            name: 'Google Web Search',
            category: 'ai',
            badge: 'Live Intelligence',
            description: 'Autonomous live web search to research companies, check market news, and discover executive contacts.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-white border border-slate-200/90 flex items-center justify-center shrink-0 shadow-2xs">
                <svg viewBox="0 0 24 24" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'Real-time query engine',
            suggestedPrompt: 'Search Google for the latest corporate news, funding round, and leadership at Acme Corp.'
        },
        {
            id: 'local_llm',
            name: 'Local Ollama & Qwen AI',
            category: 'ai',
            badge: 'AWS EC2 0.5B',
            description: '100% private, on-premise lightweight neural engine running directly on our AWS EC2 Free Tier server.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-slate-900 border border-slate-700/80 flex items-center justify-center shrink-0 shadow-xs">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="9" cy="10" r="1.5" fill="#38BDF8"/>
                    <circle cx="15" cy="10" r="1.5" fill="#38BDF8"/>
                    <path d="M8 15c1 1.8 7 1.8 8 0" stroke="#38BDF8" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M12 5v2M7 6l1.5 1.5M17 6l-1.5 1.5" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round"/>
                    <rect x="4" y="4" width="16" height="16" rx="4" stroke="#475569" stroke-width="1.2"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'Self-hosted on AWS EC2',
            suggestedPrompt: 'Summarize customer feedback using our private on-premise lightweight local model.'
        },
        {
            id: 'web_scraper',
            name: 'Live Web Scraper',
            category: 'ai',
            badge: 'Chromium Engine',
            description: 'Autonomous web page content extraction, meta tag inspection, and real-time competitor price parsing.',
            logoHtml: `<div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center shrink-0 shadow-xs">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="9"/>
                    <path d="M3.6 9h16.8M3.6 15h16.8"/>
                    <path d="M11.5 3a17 17 0 0 0 0 18M12.5 3a17 17 0 0 1 0 18"/>
                </svg>
            </div>`,
            connected: true,
            docs: 'HTML parser active',
            suggestedPrompt: 'Scrape pricing tiers and key marketing headlines from https://stripe.com/pricing'
        }
    ];

    let currentAgentAppsCategory = 'all';

    window.openAgentAppsModal = function () {
        const root = document.getElementById('agent-apps-modal-root');
        if (!root) return;

        root.innerHTML = `
            <div id="agent-apps-modal-backdrop" onclick="closeAgentAppsModal()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-3 sm:p-5 animate-fade-in">
                <div onclick="event.stopPropagation()" class="bg-white rounded-3xl max-w-3xl w-full border border-slate-200/90 shadow-2xl flex flex-col max-h-[88vh] overflow-hidden transform transition-all">
                    <!-- Top header with brand gradient -->
                    <div class="px-6 py-4.5 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                        <div class="flex items-center space-x-3">
                            <div class="h-9 w-9 rounded-2xl bg-gradient-to-tr from-indigo-500 to-violet-500 p-0.5 flex items-center justify-center shadow-xs">
                                <div class="h-full w-full bg-slate-900 rounded-[14px] flex items-center justify-center text-indigo-400">
                                    <i data-lucide="layout-grid" class="h-4.5 w-4.5"></i>
                                </div>
                            </div>
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h3 class="font-bold text-sm text-white">AI Tools & Integrations</h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">12 Active Tools</span>
                                </div>
                                <p class="text-[11px] text-slate-400">Official connected tools LinkPilot AI Agent uses to execute real-world business actions</p>
                            </div>
                        </div>
                        <button onclick="closeAgentAppsModal()" class="text-slate-400 hover:text-white p-1.5 rounded-xl hover:bg-slate-800 transition cursor-pointer" title="Close popup">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>

                    <!-- Search & Category Filters Bar -->
                    <div class="p-4 border-b border-slate-100 bg-slate-50/70 space-y-3">
                        <div class="relative">
                            <input type="text" id="agent-apps-search-input" oninput="filterAgentApps(this.value)" placeholder="Search tools by service, feature or keyword (e.g. WhatsApp, Calendar, Qwen)..." class="w-full pl-9 pr-3 py-2 bg-white border border-slate-200/90 rounded-xl text-xs outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 transition text-slate-800 placeholder-slate-400 shadow-2xs">
                            <i data-lucide="search" class="h-4 w-4 text-slate-400 absolute left-3 top-2.5"></i>
                        </div>

                        <!-- Category Filter Pills -->
                        <div class="flex items-center space-x-1.5 overflow-x-auto agent-custom-scrollbar pb-0.5 text-xs">
                            <button type="button" onclick="setAgentAppsCategory('all')" id="agent-cat-all" class="px-3 py-1 rounded-full text-[11px] font-semibold transition cursor-pointer bg-slate-900 text-white shadow-2xs">
                                All Tools
                            </button>
                            <button type="button" onclick="setAgentAppsCategory('messaging')" id="agent-cat-messaging" class="px-3 py-1 rounded-full text-[11px] font-semibold transition cursor-pointer bg-white text-slate-600 border border-slate-200/90 hover:bg-slate-100">
                                Messaging & Email
                            </button>
                            <button type="button" onclick="setAgentAppsCategory('meetings')" id="agent-cat-meetings" class="px-3 py-1 rounded-full text-[11px] font-semibold transition cursor-pointer bg-white text-slate-600 border border-slate-200/90 hover:bg-slate-100">
                                Calendar & Video
                            </button>
                            <button type="button" onclick="setAgentAppsCategory('crm')" id="agent-cat-crm" class="px-3 py-1 rounded-full text-[11px] font-semibold transition cursor-pointer bg-white text-slate-600 border border-slate-200/90 hover:bg-slate-100">
                                CRM & Revenue
                            </button>
                            <button type="button" onclick="setAgentAppsCategory('ai')" id="agent-cat-ai" class="px-3 py-1 rounded-full text-[11px] font-semibold transition cursor-pointer bg-white text-slate-600 border border-slate-200/90 hover:bg-slate-100">
                                AI & Intelligence
                            </button>
                        </div>
                    </div>

                    <!-- Apps list container (2 Columns Grid) -->
                    <div class="p-4 sm:p-5 overflow-y-auto space-y-3 agent-custom-scrollbar" id="agent-apps-list-container">
                        <!-- Populated by renderAgentAppsList() -->
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center space-x-2 text-slate-500 text-[11px]">
                            <i data-lucide="shield-check" class="h-3.5 w-3.5 text-emerald-600"></i>
                            <span>End-to-end encrypted · Actions executed with user permission</span>
                        </div>
                        <button onclick="closeAgentAppsModal()" class="px-4 py-1.5 font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200/90 rounded-xl transition shadow-2xs cursor-pointer">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
        renderAgentAppsList();
        setTimeout(() => {
            const searchInput = document.getElementById('agent-apps-search-input');
            if (searchInput) searchInput.focus();
        }, 50);
    };

    window.closeAgentAppsModal = function () {
        const root = document.getElementById('agent-apps-modal-root');
        if (!root) return;
        root.innerHTML = '';
    };

    window.setAgentAppsCategory = function (category) {
        currentAgentAppsCategory = category;
        const categories = ['all', 'messaging', 'meetings', 'crm', 'ai'];
        categories.forEach(cat => {
            const btn = document.getElementById(`agent-cat-${cat}`);
            if (btn) {
                if (cat === category) {
                    btn.className = 'px-3 py-1 rounded-full text-[11px] font-semibold transition cursor-pointer bg-slate-900 text-white shadow-2xs';
                } else {
                    btn.className = 'px-3 py-1 rounded-full text-[11px] font-semibold transition cursor-pointer bg-white text-slate-600 border border-slate-200/90 hover:bg-slate-100';
                }
            }
        });
        const searchInput = document.getElementById('agent-apps-search-input');
        renderAgentAppsList(searchInput ? searchInput.value : '');
    };

    window.filterAgentApps = function (query) {
        renderAgentAppsList(query);
    };

    window.useToolPrompt = function (promptText) {
        const input = document.getElementById('agent-user-prompt-input');
        if (input) {
            input.value = promptText;
            autoResizeTextarea(input);
            input.focus();
        }
        closeAgentAppsModal();
        if (typeof showNotification === 'function') {
            showNotification('info', 'Tool prompt inserted! Press Enter or Send to execute.');
        }
    };

    function renderAgentAppsList(query = '') {
        const container = document.getElementById('agent-apps-list-container');
        if (!container) return;

        const q = query.toLowerCase().trim();
        const filtered = agentAppsList.filter(app => {
            const matchesCat = currentAgentAppsCategory === 'all' || app.category === currentAgentAppsCategory;
            const matchesQuery = !q ||
                app.name.toLowerCase().includes(q) ||
                app.description.toLowerCase().includes(q) ||
                (app.badge && app.badge.toLowerCase().includes(q));
            return matchesCat && matchesQuery;
        });

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12 text-slate-400 text-xs">
                    <i data-lucide="search-x" class="h-7 w-7 mx-auto mb-2 text-slate-300"></i>
                    <p class="font-medium text-slate-600">No tools found matching "${escapeHtml(query)}"</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Try searching for WhatsApp, Calendar, Qwen or Email</p>
                </div>
            `;
            if (window.lucide) lucide.createIcons();
            return;
        }

        let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
        filtered.forEach(app => {
            const isConn = app.connected;
            const safePrompt = escapeHtml(app.suggestedPrompt || '').replace(/"/g, '&quot;');
            html += `
                <div class="agent-tool-brand-card p-3.5 bg-white border border-slate-200/90 rounded-2xl flex flex-col justify-between shadow-2xs hover:border-indigo-200 transition-all">
                    <div>
                        <!-- Brand Logo + Header -->
                        <div class="flex items-start justify-between gap-2.5">
                            <div class="flex items-center space-x-3 min-w-0">
                                ${app.logoHtml}
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-slate-900 truncate flex items-center gap-1.5">
                                        <span>${escapeHtml(app.name)}</span>
                                    </h4>
                                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">${escapeHtml(app.badge || app.category)}</span>
                                </div>
                            </div>

                            <!-- Connection Status Pill -->
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 flex items-center space-x-1 ${isConn ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200'}">
                                <span class="h-1.5 w-1.5 rounded-full ${isConn ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}"></span>
                                <span>${isConn ? 'Active' : 'Offline'}</span>
                            </span>
                        </div>

                        <!-- Description -->
                        <p class="text-[11px] text-slate-600 mt-2.5 leading-relaxed line-clamp-2">${escapeHtml(app.description)}</p>
                    </div>

                    <!-- Bottom Action Toolbar -->
                    <div class="pt-3 mt-2.5 border-t border-slate-100 flex items-center justify-between gap-2">
                        <button type="button" onclick="useToolPrompt('${safePrompt}')" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100/90 text-indigo-700 rounded-lg text-[11px] font-bold transition flex items-center space-x-1 cursor-pointer" title="Use this tool in chat">
                            <i data-lucide="sparkles" class="h-3 w-3 text-indigo-600"></i>
                            <span>Use in Chat</span>
                        </button>

                        <div class="flex items-center space-x-1.5">
                            <button type="button" onclick="toggleAppConnection('${app.id}')" class="px-2.5 py-1 text-slate-500 hover:text-slate-800 text-[11px] font-semibold rounded-lg hover:bg-slate-100 transition cursor-pointer">
                                ${isConn ? 'Toggle' : 'Connect'}
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
        if (window.lucide) lucide.createIcons();
    }

    window.toggleAppConnection = function (appId) {
        const app = agentAppsList.find(a => a.id === appId);
        if (!app) return;

        app.connected = !app.connected;
        if (typeof showNotification === 'function') {
            if (app.connected) {
                showNotification('success', `${app.name} connected successfully!`);
            } else {
                showNotification('info', `${app.name} set to offline.`);
            }
        }
        const searchInput = document.getElementById('agent-apps-search-input');
        renderAgentAppsList(searchInput ? searchInput.value : '');
    };

    /**
     * Auto-resizes textarea as content expands and activates send button.
     */
    window.autoResizeTextarea = function (textarea) {
        textarea.style.height = 'auto';
        const newHeight = Math.min(Math.max(textarea.scrollHeight, 28), 140);
        textarea.style.height = newHeight + 'px';

        const form = document.getElementById('agent-composer-form');
        if (form) {
            if (newHeight > 42) {
                form.classList.remove('rounded-full');
                form.classList.add('rounded-[26px]');
            } else {
                form.classList.add('rounded-full');
                form.classList.remove('rounded-[26px]');
            }
        }

        const sendBtn = document.getElementById('agent-send-button');
        if (sendBtn) {
            if (textarea.value.trim().length > 0) {
                sendBtn.classList.add('agent-send-active');
            } else {
                sendBtn.classList.remove('agent-send-active');
            }
        }
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
     * Refresh conversation list in sidebar only without destroying the active messages stream.
     */
    async function refreshConversationsSidebarOnly() {
        try {
            const res = await apiCall('crm/agent.php?action=list_conversations');
            conversationsList = (res && res.data && res.data.conversations) ? res.data.conversations : [];
            renderConversationsList();
        } catch (err) {
            console.error("Failed to refresh conversations sidebar:", err);
        }
    }

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
     * User speech card HTML with high-contrast pure black bubble & pure white text.
     */
    function renderUserMessageHTML(msg) {
        return `
            <div class="flex items-start justify-end">
                <div class="agent-user-bubble max-w-[85%] md:max-w-[75%] bg-black text-white px-4 py-3 rounded-2xl rounded-tr-xs shadow-2xs text-xs md:text-sm font-normal leading-relaxed">
                    <p class="whitespace-pre-wrap text-white font-normal">${escapeHtml(msg.content)}</p>
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
                toolsHTML += `
                    <div class="agent-tool-card is-completed flex items-center justify-between text-xs">
                        <div class="flex items-center space-x-2.5 min-w-0">
                            <span class="w-4 h-4 rounded-full ${isSuccess ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'} flex items-center justify-center shrink-0">
                                <i data-lucide="${isSuccess ? 'check' : 'alert-circle'}" class="h-3 w-3"></i>
                            </span>
                            <span class="font-bold text-slate-800 truncate">${escapeHtml(tc.tool_name || tc.tool_id)}</span>
                        </div>
                        <span class="text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase shrink-0 ${isSuccess ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1 shadow-2xs' : 'bg-rose-50 text-rose-700 border border-rose-200'}">
                            ${isSuccess ? '<i data-lucide="check" class="h-3 w-3"></i> Completed' : tc.status}
                        </span>
                    </div>
                `;
            });
            toolsHTML += '</div>';
        }

        // Approval required or executed card
        let approvalHTML = '';
        if (msg.tool_results && (msg.tool_results.tool_id || msg.tool_results.tool_name)) {
            const apprStatus = msg.status || 'completed';
            if (apprStatus === 'waiting_approval' || apprStatus === 'approved' || apprStatus === 'rejected') {
                approvalHTML = renderApprovalCard(msg.tool_results, apprStatus, 'msg_' + msg.id);
            }
        }

        const renderedMarkdown = renderMarkdown(msg.content);

        return `
            <div class="flex items-start space-x-3">
                <div class="h-8 w-8 rounded-full overflow-hidden border border-slate-200/80 shrink-0 shadow-2xs mt-0.5">
                    <img src="${AGENT_AVATAR_STATIC}" alt="${escapeHtml(agentCustomName)}" class="h-full w-full object-cover">
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
     * Maps tool metadata to vivid, human-like action labels.
     */
    function getHumanToolActionLabel(toolName, toolId) {
        const tId = (toolId || '').toLowerCase();
        const tName = (toolName || '').toLowerCase();
        const lower = (tName + ' ' + tId);

        if (tId === 'search_leads' || tName.includes('pipeline leads')) {
            return 'Searching CRM Leads';
        }
        if (tId === 'search_contacts' || tName.includes('contacts')) {
            return 'Searching Contacts';
        }
        if (tId.includes('web_search') || tName.includes('web') || tName.includes('google')) {
            return 'Searching Google & Web';
        }
        if (lower.includes('lead') || lower.includes('finder')) {
            return 'Qualifying Live Leads';
        }
        if (lower.includes('whatsapp')) {
            return 'Composing WhatsApp';
        }
        if (lower.includes('email') || lower.includes('mail')) {
            return 'Drafting Outreach Email';
        }
        if (lower.includes('deal') || lower.includes('pipeline')) {
            return 'Auditing Deal Pipeline';
        }
        if (lower.includes('crm') || lower.includes('contact')) {
            return 'Querying CRM Database';
        }
        if (lower.includes('task') || lower.includes('schedule') || lower.includes('calendar')) {
            return 'Scheduling Operations';
        }
        return 'Executing Action';
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
        const form = document.getElementById('agent-composer-form');
        if (form) {
            form.classList.add('rounded-full');
            form.classList.remove('rounded-[26px]');
        }
        const sendBtn = document.getElementById('agent-send-button');
        if (sendBtn) sendBtn.classList.remove('agent-send-active');

        if (isVoiceListening && speechRecognitionInstance) {
            try { speechRecognitionInstance.stop(); } catch (err) {}
            isVoiceListening = false;
        }
        const micBtn = document.getElementById('agent-mic-button');
        if (micBtn) micBtn.classList.remove('bg-rose-100', 'text-rose-600', 'animate-pulse');

        const plusMenu = document.getElementById('agent-plus-menu');
        if (plusMenu) plusMenu.classList.add('hidden');
        const modelMenu = document.getElementById('agent-model-dropdown');
        if (modelMenu) modelMenu.classList.add('hidden');

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

        // Create Assistant live placeholder with Purple Penguin Laptop Animation GIF
        const assistantPlaceholderId = 'assistant-live-' + Date.now();
        const assistantLiveHTML = `
            <div id="${assistantPlaceholderId}" class="flex items-start space-x-3 animate-fade-in">
                <div class="h-8 w-8 rounded-full overflow-hidden border border-slate-200/80 shrink-0 shadow-2xs mt-0.5">
                    <img src="${AGENT_AVATAR_THINKING}" alt="${escapeHtml(agentCustomName)}" class="agent-live-avatar h-full w-full object-cover">
                </div>
                <div class="flex-1 min-w-0 bg-white border border-slate-200/80 rounded-2xl rounded-tl-xs p-4 md:p-5 shadow-2xs">
                    <div class="agent-live-tools space-y-2 mb-2.5"></div>
                    <div class="agent-live-approval"></div>
                    <div class="agent-live-thinking-stage mb-2">
                        <div class="agent-human-thinking-card flex items-center justify-between text-xs animate-fade-in">
                            <div class="flex items-center space-x-2.5 min-w-0">
                                <div class="flex items-end space-x-0.75 h-4 pl-0.5 shrink-0">
                                    <span class="thought-equalizer-bar bar-1"></span>
                                    <span class="thought-equalizer-bar bar-2"></span>
                                    <span class="thought-equalizer-bar bar-3"></span>
                                    <span class="thought-equalizer-bar bar-4"></span>
                                </div>
                                <span class="font-bold text-indigo-950 text-xs agent-human-thought-text truncate">
                                    Reviewing request & evaluating workspace sources...
                                </span>
                            </div>
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-indigo-700 bg-indigo-100/80 border border-indigo-200/80 px-2.5 py-0.5 rounded-full shrink-0 flex items-center gap-1.5 shadow-2xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span>
                                <span>Co-Pilot Working</span>
                            </span>
                        </div>
                    </div>
                    <div class="agent-live-content agent-markdown-content agent-streaming-cursor"></div>
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

        // Dynamic humanized thoughts rotation
        const humanThoughts = [
            "Reviewing request & evaluating workspace sources...",
            "Searching live Google web results & verifying domains...",
            "Scanning company profiles for decision makers & contact emails...",
            "Filtering out unqualified leads without active websites...",
            "Synthesizing structured results and next action steps..."
        ];
        let thoughtIdx = 0;
        const thoughtTimer = setInterval(() => {
            if (!card) return;
            const thoughtEl = card.querySelector('.agent-human-thought-text');
            if (thoughtEl) {
                thoughtIdx = (thoughtIdx + 1) % humanThoughts.length;
                thoughtEl.innerText = humanThoughts[thoughtIdx];
            }
        }, 2200);

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
                                const actionLabel = getHumanToolActionLabel(data.tool_name, data.tool_id);
                                const stepDomId = `tool-step-${escapeHtml(data.step_id || data.tool_id)}`;

                                const toolHtml = `
                                    <div class="agent-tool-card is-running flex items-center justify-between text-xs animate-fade-in" id="${stepDomId}" data-step-id="${escapeHtml(data.step_id || '')}" data-tool-id="${escapeHtml(data.tool_id)}">
                                        <div class="flex items-center space-x-2.5 min-w-0">
                                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin text-indigo-600 shrink-0"></i>
                                            <span class="font-bold text-slate-800 truncate">${escapeHtml(data.tool_name || data.tool_id)}</span>
                                        </div>
                                        <span class="agent-live-action-badge text-[10px] px-2.5 py-1 rounded-full font-bold uppercase flex items-center gap-1.5 shrink-0">
                                            <span class="relative flex h-2 w-2">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-80"></span>
                                                <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                                            </span>
                                            <span>${escapeHtml(actionLabel)}</span>
                                        </span>
                                    </div>
                                `;
                                toolsContainer.insertAdjacentHTML('beforeend', toolHtml);
                                if (window.lucide) lucide.createIcons();

                                // Update the active thought text to reflect this live step
                                const thoughtEl = card.querySelector('.agent-human-thought-text');
                                if (thoughtEl) {
                                    thoughtEl.innerText = `${actionLabel}...`;
                                }
                                scrollAgentToBottom();
                            } else if (currentEvent === 'tool_complete') {
                                let toolEl = data.step_id ? document.getElementById(`tool-step-${escapeHtml(data.step_id)}`) : null;
                                if (!toolEl && data.step_id) {
                                    toolEl = toolsContainer.querySelector(`[data-step-id="${escapeHtml(data.step_id)}"]`);
                                }
                                if (!toolEl) {
                                    toolEl = toolsContainer.querySelector(`.agent-tool-card.is-running[data-tool-id="${escapeHtml(data.tool_id)}"]`) || toolsContainer.querySelector('.agent-tool-card.is-running');
                                }

                                if (toolEl) {
                                    toolEl.classList.remove('is-running');
                                    toolEl.classList.add('is-completed');
                                    const isError = data.status === 'failed' || (data.result && data.result.error);
                                    toolEl.innerHTML = `
                                        <div class="flex items-center space-x-2.5 min-w-0">
                                            <span class="w-4 h-4 rounded-full ${isError ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'} flex items-center justify-center shrink-0">
                                                <i data-lucide="${isError ? 'alert-circle' : 'check'}" class="h-3 w-3"></i>
                                            </span>
                                            <span class="font-bold text-slate-800 truncate">${escapeHtml(data.tool_name || data.tool_id)}</span>
                                        </div>
                                        <span class="text-[10px] px-2.5 py-0.5 rounded-full font-bold uppercase ${isError ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'} flex items-center gap-1 shadow-2xs shrink-0">
                                            <i data-lucide="${isError ? 'alert-circle' : 'check'}" class="h-3 w-3"></i> ${isError ? 'Failed' : 'Completed'}
                                        </span>
                                    `;
                                    if (window.lucide) lucide.createIcons();
                                }
                            } else if (currentEvent === 'approval_required') {
                                const liveCardId = 'live_' + Date.now();
                                approvalContainer.innerHTML = renderApprovalCard(data, 'waiting_approval', liveCardId);
                                if (window.lucide) lucide.createIcons();
                            } else if (currentEvent === 'token') {
                                clearInterval(thoughtTimer);
                                const thinkingStage = card.querySelector('.agent-live-thinking-stage');
                                if (thinkingStage) {
                                    thinkingStage.remove();
                                }
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

            clearInterval(thoughtTimer);
            const thinkingStage = card.querySelector('.agent-live-thinking-stage');
            if (thinkingStage) {
                thinkingStage.remove();
            }

            contentContainer.classList.remove('agent-streaming-cursor');
            if (accumulatedContent && accumulatedContent.trim()) {
                contentContainer.innerHTML = renderMarkdown(accumulatedContent);
            } else {
                accumulatedContent = "I am connected to your workspace. You can ask me to search CRM contacts, review deals, check WhatsApp messages, or schedule tasks.";
                contentContainer.innerHTML = renderMarkdown(accumulatedContent);
            }

            // Refresh conversation titles in sidebar without destroying active messages stream
            refreshConversationsSidebarOnly();

        } catch (err) {
            console.error("Streaming chat failed, attempting JSON fallback:", err);
            // Fallback non-streaming call
            try {
                const res = await apiCall('crm/agent.php', 'POST', {
                    action: 'chat',
                    conversation_id: activeConversationId,
                    message: message
                });
                if (res && res.data && res.data.content && res.data.content.trim()) {
                    contentContainer.classList.remove('agent-streaming-cursor');
                    contentContainer.innerHTML = renderMarkdown(res.data.content);
                } else {
                    throw new Error("Empty response received");
                }
            } catch (fallbackErr) {
                contentContainer.classList.remove('agent-streaming-cursor');
                contentContainer.innerHTML = `
                    <div class="space-y-2">
                        <p class="text-xs text-slate-700">I am connected to your workspace. I encountered a brief network delay while contacting the AI model.</p>
                        <button onclick="setAgentComposerPrompt('${escapeHtml(message)}'); handleAgentSendMessage(event);" class="px-3 py-1.5 bg-black hover:bg-slate-800 text-white text-xs font-semibold rounded-lg shadow-2xs transition inline-flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
                            <span>Retry Message</span>
                        </button>
                    </div>
                `;
                if (window.lucide) lucide.createIcons();
            }
        } finally {
            clearInterval(thoughtTimer);
            if (card) {
                const thinkingStage = card.querySelector('.agent-live-thinking-stage');
                if (thinkingStage) {
                    thinkingStage.remove();
                }
                const liveAvatar = card.querySelector('.agent-live-avatar');
                if (liveAvatar) liveAvatar.src = AGENT_AVATAR_STATIC;
            }
            setGeneratingState(false);
            scrollAgentToBottom();
        }
    };

    /**
     * Builds safe HTML template envelope for email preview iframe.
     */
    function buildEmailPreviewHtml(body, subject = '', recipient = '') {
        const isHtml = /<[a-z][\s\S]*>/i.test(body);
        let innerContent = isHtml ? body : escapeHtml(body).replace(/\n/g, '<br>');

        if (isHtml && /<html[\s\S]*>/i.test(body)) {
            return body;
        }

        return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      margin: 0;
      padding: 20px;
      background-color: #f8fafc;
      color: #1e293b;
      line-height: 1.6;
      font-size: 14px;
    }
    .email-container {
      max-width: 560px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
      overflow: hidden;
    }
    .email-header-bar {
      background: linear-gradient(135deg, #4f46e5, #6366f1);
      padding: 16px 20px;
      color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .email-brand-title {
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      opacity: 0.95;
    }
    .email-content-box {
      padding: 24px 20px;
    }
    .email-footer-bar {
      border-top: 1px solid #f1f5f9;
      padding: 14px 20px;
      background: #f8fafc;
      font-size: 11px;
      color: #64748b;
      text-align: center;
    }
    a { color: #4f46e5; text-decoration: none; font-weight: 600; }
    h1, h2, h3, h4 { color: #0f172a; margin-top: 0; }
    p { margin: 0 0 14px 0; }
    p:last-child { margin-bottom: 0; }
    .amount-highlight {
      display: inline-block;
      font-weight: 800;
      color: #047857;
      background: #ecfdf5;
      padding: 4px 10px;
      border-radius: 6px;
      border: 1px solid #a7f3d0;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="email-header-bar">
      <div class="email-brand-title">LinkPilot Notification</div>
      <div style="font-size: 11px; opacity: 0.85;">Official Communication</div>
    </div>
    <div class="email-content-box">
      ${innerContent}
    </div>
    <div class="email-footer-bar">
      Sent securely via LinkPilot AI &bull; Autonomous Operations
    </div>
  </div>
</body>
</html>`;
    }

    window.agentPendingApprovals = window.agentPendingApprovals || {};

    /**
     * Renders an interactive approval card with HTML theme preview, inline editor,
     * and auto-closing buttons on approve/reject.
     */
    function renderApprovalCard(appr, status = 'waiting_approval', cardId = null) {
        if (!appr) return '';
        cardId = cardId || ('appr_' + Math.random().toString(36).substr(2, 9));

        const toolId = appr.tool_id || '';
        const toolName = appr.tool_name || toolId || 'Action Confirmation';
        const description = appr.description || '';
        const args = appr.arguments || {};

        window.agentPendingApprovals[cardId] = {
            toolId: toolId,
            toolName: toolName,
            description: description,
            arguments: JSON.parse(JSON.stringify(args)),
            status: status
        };

        const isApproved = (status === 'approved');
        const isRejected = (status === 'rejected');
        const isPending = (status === 'waiting_approval');

        const isEmail = (toolId === 'send_email') || (args && (args.recipient || args.to) && args.subject);

        let statusClass = '';
        if (isApproved) statusClass = 'status-approved';
        else if (isRejected) statusClass = 'status-rejected';

        // --- EMAIL APPROVAL CARD ---
        if (isEmail) {
            const recipient = args.recipient || args.to || '';
            const subject = args.subject || '(No Subject)';
            const body = args.body || args.message || args.content || '';
            const previewHtml = buildEmailPreviewHtml(body, subject, recipient);

            let buttonsHtml = '';
            if (isPending) {
                buttonsHtml = `
                    <div id="approval-actions-${cardId}" class="flex items-center space-x-2 pt-1 flex-wrap gap-y-2">
                        <button onclick="approveAgentAction('${toolId}', null, '${cardId}')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center space-x-1.5 cursor-pointer">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i>
                            <span>Approve & Send Email</span>
                        </button>
                        <button onclick="switchEmailCardTab('${cardId}', 'edit')" class="px-3.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-bold transition shadow-2xs flex items-center space-x-1.5 cursor-pointer">
                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                            <span>Edit Email</span>
                        </button>
                        <button onclick="rejectAgentAction('${toolId}', '${cardId}')" class="px-3.5 py-2 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-xl text-xs font-semibold transition cursor-pointer">
                            Cancel
                        </button>
                    </div>
                `;
            } else if (isApproved) {
                buttonsHtml = `
                    <div id="approval-actions-${cardId}" class="flex items-center space-x-2 pt-1">
                        <div class="flex items-center space-x-2 text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200/90 px-3.5 py-2 rounded-xl w-fit">
                            <i data-lucide="check-circle-2" class="h-4 w-4 text-emerald-600 shrink-0"></i>
                            <span>Email Approved & Dispatched</span>
                        </div>
                    </div>
                `;
            } else if (isRejected) {
                buttonsHtml = `
                    <div id="approval-actions-${cardId}" class="flex items-center space-x-2 pt-1">
                        <div class="flex items-center space-x-2 text-xs font-bold text-rose-800 bg-rose-50 border border-rose-200/90 px-3.5 py-2 rounded-xl w-fit">
                            <i data-lucide="x-circle" class="h-4 w-4 text-rose-600 shrink-0"></i>
                            <span>Email Sending Cancelled / Rejected</span>
                        </div>
                    </div>
                `;
            }

            return `
                <div id="agent-approval-card-${cardId}" class="agent-approval-card space-y-3 ${statusClass}" data-card-id="${cardId}">
                    <!-- Top Header -->
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center space-x-2">
                            <span class="w-6 h-6 rounded-lg ${isApproved ? 'bg-emerald-100 text-emerald-700' : (isRejected ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700')} flex items-center justify-center shrink-0">
                                <i data-lucide="${isApproved ? 'check' : (isRejected ? 'x' : 'mail')}" class="h-3.5 w-3.5"></i>
                            </span>
                            <span class="font-bold text-xs uppercase tracking-wider ${isApproved ? 'text-emerald-800' : (isRejected ? 'text-slate-600' : 'text-amber-900')}">
                                ${isApproved ? 'Email Dispatched' : (isRejected ? 'Email Dispatch Cancelled' : 'Outbound Email Confirmation')}
                            </span>
                        </div>
                        <!-- View / Edit Tabs -->
                        <div class="flex items-center space-x-1 bg-slate-100/90 p-1 rounded-lg border border-slate-200/70 text-[11px] font-semibold">
                            <button id="email-tab-html-${cardId}" onclick="switchEmailCardTab('${cardId}', 'html')" class="px-2.5 py-1 rounded-md bg-indigo-600 text-white shadow-2xs transition flex items-center space-x-1 cursor-pointer">
                                <i data-lucide="eye" class="h-3 w-3"></i>
                                <span>HTML View</span>
                            </button>
                            <button id="email-tab-code-${cardId}" onclick="switchEmailCardTab('${cardId}', 'code')" class="px-2.5 py-1 rounded-md bg-white text-slate-600 border border-transparent hover:text-slate-900 transition flex items-center space-x-1 cursor-pointer">
                                <i data-lucide="code" class="h-3 w-3"></i>
                                <span>Raw View</span>
                            </button>
                            ${isPending ? `
                            <button id="email-tab-edit-${cardId}" onclick="switchEmailCardTab('${cardId}', 'edit')" class="px-2.5 py-1 rounded-md bg-white text-slate-600 border border-transparent hover:text-indigo-600 transition flex items-center space-x-1 cursor-pointer">
                                <i data-lucide="pencil" class="h-3 w-3"></i>
                                <span>Edit</span>
                            </button>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Meta Information Box -->
                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-2xs space-y-1.5 text-xs">
                        <div class="flex items-center space-x-2 text-slate-600">
                            <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px] w-14 shrink-0">To:</span>
                            <span id="email-display-to-${cardId}" class="font-semibold text-slate-800 bg-slate-100 px-2 py-0.5 rounded border border-slate-200 truncate">${escapeHtml(recipient)}</span>
                        </div>
                        <div class="flex items-center space-x-2 text-slate-600">
                            <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px] w-14 shrink-0">Subject:</span>
                            <span id="email-display-sub-${cardId}" class="font-bold text-slate-900 truncate">${escapeHtml(subject)}</span>
                        </div>
                    </div>

                    <!-- Panel 1: HTML View (Default) -->
                    <div id="email-panel-html-${cardId}" class="space-y-2">
                        <div class="rounded-xl border border-slate-200 overflow-hidden shadow-2xs bg-white">
                            <div class="bg-slate-100/90 px-3 py-1.5 border-b border-slate-200 flex items-center justify-between text-[11px] text-slate-500">
                                <div class="flex items-center space-x-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                                    <span class="ml-2 font-mono text-[10px] text-slate-600">email-template-view.html</span>
                                </div>
                                <span class="text-[10px] uppercase font-bold text-indigo-600 flex items-center gap-1">
                                    <i data-lucide="sparkles" class="h-3 w-3"></i> Rendered HTML Theme
                                </span>
                            </div>
                            <iframe id="email-frame-${cardId}" class="agent-email-frame" sandbox="allow-same-origin" srcdoc="${escapeHtml(previewHtml)}"></iframe>
                        </div>
                    </div>

                    <!-- Panel 2: Raw Code View -->
                    <div id="email-panel-code-${cardId}" class="hidden space-y-2">
                        <div class="bg-slate-900 text-slate-100 p-3.5 rounded-xl font-mono text-[11px] leading-relaxed max-h-72 overflow-auto border border-slate-800">
                            <pre class="whitespace-pre-wrap break-all"><code>${escapeHtml(body)}</code></pre>
                        </div>
                    </div>

                    <!-- Panel 3: Edit Mode -->
                    <div id="email-panel-edit-${cardId}" class="hidden space-y-3 bg-white p-3.5 rounded-xl border border-indigo-200 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="font-bold text-xs text-indigo-900 flex items-center gap-1.5">
                                <i data-lucide="edit-3" class="h-3.5 w-3.5 text-indigo-600"></i> Edit Email Draft
                            </span>
                            <span class="text-[11px] text-slate-500">Modify details before dispatch</span>
                        </div>
                        <div class="space-y-2.5">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase tracking-wider">Recipient Email (To):</label>
                                <input type="email" id="edit-recipient-${cardId}" class="w-full text-xs font-semibold px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-indigo-500 transition" value="${escapeHtml(recipient)}">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase tracking-wider">Subject Line:</label>
                                <input type="text" id="edit-subject-${cardId}" class="w-full text-xs font-semibold px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-indigo-500 transition" value="${escapeHtml(subject)}">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase tracking-wider">Email Body (HTML or Plain Text):</label>
                                <textarea id="edit-body-${cardId}" rows="8" class="w-full text-xs font-mono p-3 bg-slate-50 border border-slate-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-indigo-500 transition leading-relaxed">${escapeHtml(body)}</textarea>
                            </div>
                        </div>
                        <!-- Edit Action Controls -->
                        <div class="flex items-center space-x-2 pt-2 border-t border-slate-100 flex-wrap gap-y-2">
                            <button onclick="saveAndSendEmailApproval('${cardId}', '${toolId}')" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center space-x-1.5 cursor-pointer">
                                <i data-lucide="send" class="h-3.5 w-3.5"></i>
                                <span>Save & Send Now</span>
                            </button>
                            <button onclick="saveEmailApprovalChanges('${cardId}')" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-2xs flex items-center space-x-1.5 cursor-pointer">
                                <i data-lucide="check" class="h-3.5 w-3.5"></i>
                                <span>Apply to Preview</span>
                            </button>
                            <button onclick="cancelEmailApprovalEdit('${cardId}')" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition cursor-pointer">
                                Cancel Edit
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Action Buttons (Hidden when in Edit tab) -->
                    ${buttonsHtml}
                </div>
            `;
        }

        // --- GENERAL TOOL APPROVAL CARD ---
        let buttonsHtml = '';
        if (isPending) {
            buttonsHtml = `
                <div id="approval-actions-${cardId}" class="flex items-center space-x-2 pt-1">
                    <button onclick="approveAgentAction('${toolId}', null, '${cardId}')" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-2xs flex items-center space-x-1.5 cursor-pointer">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                        <span>Approve & Execute</span>
                    </button>
                    <button onclick="rejectAgentAction('${toolId}', '${cardId}')" class="agent-btn-secondary px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 rounded-lg text-xs font-bold transition cursor-pointer">
                        Cancel
                    </button>
                </div>
            `;
        } else if (isApproved) {
            buttonsHtml = `
                <div id="approval-actions-${cardId}" class="flex items-center space-x-2 pt-1">
                    <div class="flex items-center space-x-2 text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200/90 px-3 py-1.5 rounded-lg w-fit">
                        <i data-lucide="check-circle-2" class="h-3.5 w-3.5 text-emerald-600 shrink-0"></i>
                        <span>Action Approved & Executed</span>
                    </div>
                </div>
            `;
        } else if (isRejected) {
            buttonsHtml = `
                <div id="approval-actions-${cardId}" class="flex items-center space-x-2 pt-1">
                    <div class="flex items-center space-x-2 text-xs font-bold text-rose-800 bg-rose-50 border border-rose-200/90 px-3 py-1.5 rounded-lg w-fit">
                        <i data-lucide="x-circle" class="h-3.5 w-3.5 text-rose-600 shrink-0"></i>
                        <span>Action Cancelled / Rejected</span>
                    </div>
                </div>
            `;
        }

        return `
            <div id="agent-approval-card-${cardId}" class="agent-approval-card space-y-2.5 ${statusClass}" data-card-id="${cardId}">
                <div class="flex items-center space-x-2 text-amber-800 font-bold text-xs uppercase tracking-wide">
                    <i data-lucide="${isApproved ? 'check-circle-2' : (isRejected ? 'x-circle' : 'shield-alert')}" class="h-4 w-4 ${isApproved ? 'text-emerald-600' : (isRejected ? 'text-rose-600' : 'text-amber-600')}"></i>
                    <span>${isApproved ? 'Action Executed' : (isRejected ? 'Action Cancelled' : 'Confirmation Required Before Execution')}</span>
                </div>
                <div class="text-xs text-slate-700 bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                    <div class="font-bold text-slate-900 mb-1">Action: ${escapeHtml(toolName)}</div>
                    <p class="text-slate-600 text-[11px] mb-2">${escapeHtml(description)}</p>
                    <div class="bg-slate-50 p-2.5 rounded-lg text-[11px] font-mono border border-slate-200 overflow-x-auto">
                        ${escapeHtml(JSON.stringify(args, null, 2))}
                    </div>
                </div>
                ${buttonsHtml}
            </div>
        `;
    }

    /**
     * Tab switcher for email approval card (HTML View, Raw View, Edit).
     */
    window.switchEmailCardTab = function(cardId, tab) {
        const htmlPanel = document.getElementById(`email-panel-html-${cardId}`);
        const codePanel = document.getElementById(`email-panel-code-${cardId}`);
        const editPanel = document.getElementById(`email-panel-edit-${cardId}`);
        const actionsPanel = document.getElementById(`approval-actions-${cardId}`);

        const tabHtml = document.getElementById(`email-tab-html-${cardId}`);
        const tabCode = document.getElementById(`email-tab-code-${cardId}`);
        const tabEdit = document.getElementById(`email-tab-edit-${cardId}`);

        if (htmlPanel) htmlPanel.classList.add('hidden');
        if (codePanel) codePanel.classList.add('hidden');
        if (editPanel) editPanel.classList.add('hidden');

        [tabHtml, tabCode, tabEdit].forEach(t => {
            if (t) {
                t.classList.remove('bg-indigo-600', 'text-white', 'shadow-2xs');
                t.classList.add('bg-white', 'text-slate-600');
            }
        });

        if (tab === 'html') {
            if (htmlPanel) htmlPanel.classList.remove('hidden');
            if (tabHtml) {
                tabHtml.classList.add('bg-indigo-600', 'text-white', 'shadow-2xs');
                tabHtml.classList.remove('bg-white', 'text-slate-600');
            }
            if (actionsPanel) actionsPanel.classList.remove('hidden');
        } else if (tab === 'code') {
            if (codePanel) codePanel.classList.remove('hidden');
            if (tabCode) {
                tabCode.classList.add('bg-indigo-600', 'text-white', 'shadow-2xs');
                tabCode.classList.remove('bg-white', 'text-slate-600');
            }
            if (actionsPanel) actionsPanel.classList.remove('hidden');
        } else if (tab === 'edit') {
            if (editPanel) editPanel.classList.remove('hidden');
            if (tabEdit) {
                tabEdit.classList.add('bg-indigo-600', 'text-white', 'shadow-2xs');
                tabEdit.classList.remove('bg-white', 'text-slate-600');
            }
            if (actionsPanel) actionsPanel.classList.add('hidden');
        }

        if (window.lucide) lucide.createIcons();
    };

    /**
     * Start inline editing for email approval card.
     */
    window.startEmailApprovalEdit = function(cardId) {
        window.switchEmailCardTab(cardId, 'edit');
        const toInput = document.getElementById(`edit-recipient-${cardId}`);
        if (toInput) toInput.focus();
    };

    /**
     * Cancel edit mode and revert input values to stored arguments.
     */
    window.cancelEmailApprovalEdit = function(cardId) {
        if (window.agentPendingApprovals && window.agentPendingApprovals[cardId]) {
            const orig = window.agentPendingApprovals[cardId].arguments || {};
            const toInput = document.getElementById(`edit-recipient-${cardId}`);
            const subInput = document.getElementById(`edit-subject-${cardId}`);
            const bodyInput = document.getElementById(`edit-body-${cardId}`);
            if (toInput) toInput.value = orig.recipient || orig.to || '';
            if (subInput) subInput.value = orig.subject || '';
            if (bodyInput) bodyInput.value = orig.body || orig.message || '';
        }
        window.switchEmailCardTab(cardId, 'html');
    };

    /**
     * Saves user modifications to email fields, refreshes the HTML preview, and switches to HTML view.
     */
    window.saveEmailApprovalChanges = function(cardId) {
        const toInput = document.getElementById(`edit-recipient-${cardId}`);
        const subInput = document.getElementById(`edit-subject-${cardId}`);
        const bodyInput = document.getElementById(`edit-body-${cardId}`);

        if (!toInput || !subInput || !bodyInput) return;

        const toVal = toInput.value.trim();
        const subVal = subInput.value.trim();
        const bodyVal = bodyInput.value.trim();

        if (!toVal) {
            showNotification('warning', 'Recipient email cannot be empty.');
            return;
        }

        // Update stored registry arguments
        if (window.agentPendingApprovals && window.agentPendingApprovals[cardId]) {
            window.agentPendingApprovals[cardId].arguments.recipient = toVal;
            window.agentPendingApprovals[cardId].arguments.subject = subVal;
            window.agentPendingApprovals[cardId].arguments.body = bodyVal;
        }

        // Update HTML preview iframe & display texts
        const frame = document.getElementById(`email-frame-${cardId}`);
        if (frame) {
            frame.srcdoc = buildEmailPreviewHtml(bodyVal, subVal, toVal);
        }
        const toDisplay = document.getElementById(`email-display-to-${cardId}`);
        if (toDisplay) toDisplay.textContent = toVal;
        const subDisplay = document.getElementById(`email-display-sub-${cardId}`);
        if (subDisplay) subDisplay.textContent = subVal;

        // Update raw code display
        const codeEl = document.querySelector(`#email-panel-code-${cardId} code`);
        if (codeEl) codeEl.textContent = bodyVal;

        showNotification('success', 'Email changes applied. Review preview below.');
        window.switchEmailCardTab(cardId, 'html');
    };

    /**
     * Saves edits directly and dispatches email execution in a single step.
     */
    window.saveAndSendEmailApproval = function(cardId, toolId) {
        const toInput = document.getElementById(`edit-recipient-${cardId}`);
        const subInput = document.getElementById(`edit-subject-${cardId}`);
        const bodyInput = document.getElementById(`edit-body-${cardId}`);

        let updatedArgs = {};
        if (window.agentPendingApprovals && window.agentPendingApprovals[cardId]) {
            updatedArgs = Object.assign({}, window.agentPendingApprovals[cardId].arguments);
        }

        if (toInput) updatedArgs.recipient = toInput.value.trim();
        if (subInput) updatedArgs.subject = subInput.value.trim();
        if (bodyInput) updatedArgs.body = bodyInput.value.trim();

        if (!updatedArgs.recipient) {
            showNotification('warning', 'Recipient email cannot be empty.');
            return;
        }

        // Apply changes to preview frame and switch to HTML tab so user sees spinner
        window.saveEmailApprovalChanges(cardId);

        // Execute action with updated arguments
        window.approveAgentAction(toolId || 'send_email', updatedArgs, cardId);
    };

    /**
     * Executes approved action, disables & closes interactive buttons immediately,
     * and shows a permanent confirmation state.
     */
    window.approveAgentAction = async function (toolId, argumentsObj, cardId) {
        let finalArgs = argumentsObj;
        if (!finalArgs && cardId && window.agentPendingApprovals && window.agentPendingApprovals[cardId]) {
            finalArgs = window.agentPendingApprovals[cardId].arguments;
        }

        const isEmailAction = (toolId === 'send_email') || (finalArgs && (finalArgs.recipient || finalArgs.to));

        // Immediately close & remove edit mode if active on this card
        if (cardId) {
            const editTab = document.getElementById(`email-tab-edit-${cardId}`);
            if (editTab) editTab.remove();
            const editPanel = document.getElementById(`email-panel-edit-${cardId}`);
            if (editPanel) editPanel.remove();
            window.switchEmailCardTab(cardId, 'html');
        }

        // Close and lock interactive buttons immediately with animated progress indicator
        const actionsEl = cardId ? document.getElementById(`approval-actions-${cardId}`) : null;
        if (actionsEl) {
            actionsEl.innerHTML = `
                <div class="flex items-center space-x-2 text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200/90 px-3.5 py-2 rounded-xl animate-pulse">
                    <i data-lucide="loader-2" class="h-4 w-4 animate-spin text-emerald-600 shrink-0"></i>
                    <span>${isEmailAction ? 'Sending email...' : 'Executing approved action...'}</span>
                </div>
            `;
            if (window.lucide) lucide.createIcons();
        }

        showNotification('info', isEmailAction ? 'Sending email...' : `Executing approved action: ${toolId}...`);
        try {
            const res = await apiCall('crm/agent.php', 'POST', {
                action: 'approve_action',
                conversation_id: activeConversationId,
                tool_id: toolId,
                arguments: finalArgs
            });

            showNotification('success', isEmailAction ? 'Email dispatched successfully!' : 'Action executed successfully!');

            // Permanently close buttons with confirmed badge
            if (actionsEl) {
                actionsEl.innerHTML = `
                    <div class="flex items-center space-x-2 text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200/90 px-3.5 py-2 rounded-xl w-fit">
                        <i data-lucide="check-circle-2" class="h-4 w-4 text-emerald-600 shrink-0"></i>
                        <span>${isEmailAction ? 'Email Approved & Dispatched' : 'Action Approved & Executed'}</span>
                    </div>
                `;
                if (window.lucide) lucide.createIcons();
            }

            // Reload active conversation stream to display assistant confirmation
            if (activeConversationId) {
                selectAgentConversation(activeConversationId);
            }
        } catch (err) {
            showNotification('error', `Action execution failed: ${err.message}`);
            if (activeConversationId) {
                selectAgentConversation(activeConversationId);
            }
        }
    };

    /**
     * Cancels/rejects action, disables & closes interactive buttons immediately.
     */
    window.rejectAgentAction = async function (toolId, cardId) {
        const isEmailAction = (toolId === 'send_email');

        // Immediately close & remove edit mode if active on this card
        if (cardId) {
            const editTab = document.getElementById(`email-tab-edit-${cardId}`);
            if (editTab) editTab.remove();
            const editPanel = document.getElementById(`email-panel-edit-${cardId}`);
            if (editPanel) editPanel.remove();
            window.switchEmailCardTab(cardId, 'html');
        }

        // Close and lock interactive buttons immediately
        const actionsEl = cardId ? document.getElementById(`approval-actions-${cardId}`) : null;
        if (actionsEl) {
            actionsEl.innerHTML = `
                <div class="flex items-center space-x-2 text-xs font-bold text-rose-800 bg-rose-50 border border-rose-200/90 px-3.5 py-2 rounded-xl w-fit">
                    <i data-lucide="x-circle" class="h-4 w-4 text-rose-600 shrink-0"></i>
                    <span>${isEmailAction ? 'Email Sending Cancelled / Rejected' : 'Action Cancelled / Rejected'}</span>
                </div>
            `;
            if (window.lucide) lucide.createIcons();
        }

        showNotification('warning', 'Action cancelled.');

        if (activeConversationId) {
            try {
                await apiCall('crm/agent.php', 'POST', {
                    action: 'reject_action',
                    conversation_id: activeConversationId,
                    tool_id: toolId || ''
                });
                selectAgentConversation(activeConversationId);
            } catch (err) {
                selectAgentConversation(activeConversationId);
            }
        }
    };

    function setGeneratingState(generating) {
        isGenerating = generating;
        const btn = document.getElementById('agent-send-button');
        if (btn) {
            if (generating) {
                btn.innerHTML = `<i data-lucide="loader-2" class="h-4.5 w-4.5 text-white animate-spin"></i>`;
                btn.disabled = true;
                btn.classList.add('opacity-75');
            } else {
                btn.innerHTML = `<i data-lucide="arrow-up" class="h-4.5 w-4.5 text-white stroke-[2.4]"></i>`;
                btn.disabled = false;
                btn.classList.remove('opacity-75');
            }
        }

        // Switch right drawer large avatar between thinking GIF and static plush avatar
        const profileAvatar = document.getElementById('agent-profile-large-avatar');
        if (profileAvatar) {
            profileAvatar.src = generating ? AGENT_AVATAR_THINKING : AGENT_AVATAR_STATIC;
        }

        // If not generating, ensure all live avatars in messages revert to static
        if (!generating) {
            document.querySelectorAll('.agent-live-avatar').forEach(img => {
                img.src = AGENT_AVATAR_STATIC;
            });
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
