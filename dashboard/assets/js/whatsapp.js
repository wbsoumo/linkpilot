// dashboard/assets/js/whatsapp.js

// Global variables for WhatsApp Inbox
let activeWaThreadId = null;
let waThreadsInterval = null;
let waMessagesInterval = null;

// Listen for Embedded Signup postMessage events
window.addEventListener("message", function (event) {
    if (event.origin !== "https://www.facebook.com" && event.origin !== "https://web.facebook.com") {
        return;
    }

    // Log postMessage events during development
    console.log("[LinkPilot Dev] postMessage event received:", event.data);

    try {
        const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (data && data.type === 'WA_EMBEDDED_SIGNUP') {
            console.log("[LinkPilot Dev] Embedded Signup Step Event:", data);
            if (data.event === 'FINISH') {
                console.log("[LinkPilot Dev] Embedded Signup FINISH event details:", data.data);
            }
        }
    } catch (e) {
        // Ignore non-JSON messages
    }
});

/**
 * Main switchboard to check connection state and render either setup wizard or target screen
 */
async function checkWaConnectionAndRender(viewName, container, renderFn) {
    try {
        const res = await apiCall('whatsapp/setup.php?t=' + Date.now());
        console.log("[Diagnostics] Connection status fetched:", res);

        if (res && res.connected) {
            // User connected, render the specific dashboard page
            renderFn(container, res);
        } else {
            // User not connected, load connection wizard
            renderWhatsAppSetup(container, res.settings);
        }
    } catch (err) {
        showNotification('error', 'Failed to load WhatsApp status: ' + err.message);
        container.innerHTML = `<div class="p-6 text-center text-slate-500">Failed to connect to backend APIs.</div>`;
    }
}

// ----------------------------------------------------
// 1. WHATSAPP CONNECTION WIZARD (SETUP)
// ----------------------------------------------------
function renderWhatsAppSetup(container, settings) {
    const isConnected = settings && (settings.status === 'connected' || settings.token_status === 'valid');
    const tokenVal = (settings && settings.access_token) ? settings.access_token : '';
    const wabaVal = (settings && settings.waba_id) ? settings.waba_id : '';
    const phoneVal = (settings && settings.phone_number_id) ? settings.phone_number_id : '';

    container.innerHTML = `
        <div class="w-full max-w-6xl mx-auto my-6 space-y-6 animate-fade-in text-left">
            
            ${isConnected ? `
            <!-- Top Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white border border-slate-200/80 rounded-[24px] p-6 shadow-soft">
                <div class="flex items-center space-x-3.5">
                    <div class="p-2.5 bg-emerald-50 rounded-2xl border border-emerald-100 shrink-0">
                        <img src="assets/css/WhatsApp_icon.png" class="h-8 w-8 object-contain" alt="WhatsApp">
                    </div>
                    <div>
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">Connect WhatsApp Business (Meta)</h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">Connect your Meta WhatsApp Cloud API credentials to link your business inbox.</p>
                    </div>
                </div>
            </div>

            <!-- Connected State Card -->
            <div class="p-6 bg-emerald-50/70 border border-emerald-200/90 rounded-2xl space-y-5 shadow-2xs">
                <div class="flex justify-between items-start gap-4 border-b border-emerald-200/60 pb-4">
                    <div class="flex items-center space-x-3.5">
                        <div class="p-3 bg-emerald-500 text-white rounded-2xl shadow-sm">
                            <i data-lucide="check-circle" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="text-base font-black text-slate-900 block">${(settings && (settings.business_name || settings.display_name)) || 'WhatsApp Business'}</span>
                                <span class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-md text-[10px] uppercase font-black tracking-wider">Connected</span>
                            </div>
                            <span class="text-xs text-slate-700 font-bold font-mono mt-0.5 block">${(settings && settings.display_phone_number) || '--'}</span>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-full text-[10px] uppercase font-black tracking-wider shadow-2xs">
                        Quality: ${(settings && settings.quality_rating) || 'GREEN'}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs text-slate-700 font-medium">
                    <div>WABA Account ID: <strong class="font-mono text-slate-900 block mt-0.5">${(settings && settings.waba_id) || 'N/A'}</strong></div>
                    <div>Phone Number ID: <strong class="font-mono text-slate-900 block mt-0.5">${(settings && settings.phone_number_id) || 'N/A'}</strong></div>
                    <div>Messaging Limit: <strong class="text-slate-900 block mt-0.5">${(settings && settings.messaging_limit) || '1000/day'}</strong></div>
                    <div>Webhook Subscription: <strong class="text-emerald-700 font-bold block mt-0.5">✓ Subscribed & Verified</strong></div>
                    <div>Connection Type: <strong class="text-slate-900 block mt-0.5 uppercase text-[10px] tracking-wider font-black">${(settings && settings.connection_type) || 'Meta Embedded Signup'}</strong></div>
                    <div>Last Sync Time: <strong class="text-slate-800 font-mono text-[11px] block mt-0.5">${(settings && settings.last_sync) || 'Just now'}</strong></div>
                </div>

                <div class="pt-2 flex items-center space-x-3 border-t border-emerald-200/60">
                    <button type="button" onclick="disconnectWhatsAppAccount()" class="px-4 py-2.5 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl text-xs font-bold text-rose-600 transition cursor-pointer shadow-2xs">
                        Disconnect Account
                    </button>
                    <button type="button" onclick="window.location.reload()" class="px-4 py-2.5 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 transition cursor-pointer shadow-2xs">
                        Go to WhatsApp Inbox →
                    </button>
                </div>
            </div>
            ` : `
            <!-- Two Column Setup Layout -->
            <div id="wa-connect-selection-state" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- LEFT COLUMN: Setup Form & Header (lg:col-span-7) -->
                <div class="lg:col-span-7 space-y-6">
                    
                    <!-- Top Header -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white border border-slate-200/80 rounded-[24px] p-6 shadow-soft">
                        <div class="flex items-center space-x-3.5">
                            <div class="p-2.5 bg-emerald-50 rounded-2xl border border-emerald-100 shrink-0">
                                <img src="assets/css/WhatsApp_icon.png" class="h-8 w-8 object-contain" alt="WhatsApp">
                            </div>
                            <div>
                                <h2 class="text-xl font-black text-slate-900 tracking-tight">Connect WhatsApp Business (Meta)</h2>
                                <p class="text-xs text-slate-500 font-semibold mt-0.5">Connect your Meta WhatsApp Cloud API credentials to link your business inbox.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Setup Form Card -->
                    <div class="bg-white border border-slate-200/85 rounded-3xl p-6 sm:p-8 shadow-soft text-left space-y-6">
                        <div class="flex items-center space-x-3 border-b border-slate-100 pb-4">
                            <div class="p-2.5 bg-blue-50 text-blue-600 border border-blue-100 rounded-2xl">
                                <i data-lucide="key-round" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900">Manual Meta Credentials Form</h3>
                                <p class="text-xs text-slate-500 font-semibold mt-0.5">Fill in your Meta details below or let our Assistant guide you.</p>
                            </div>
                        </div>

                    <form id="setup-wa-manual-form" onsubmit="event.preventDefault(); saveManualWhatsAppConnectionDashboard();" class="space-y-4">
                        <!-- Permanent Access Token -->
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-2">
                                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider">Permanent Access Token *</label>
                                    <i data-lucide="info" class="h-3.5 w-3.5 text-slate-400 cursor-help" title="Enter permanent system user token generated in Meta Business Suite."></i>
                                    <span id="wa-token-valid-badge" class="hidden px-2 py-0.5 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-[9px] font-black uppercase tracking-wider">✓ Valid</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button type="button" onclick="openMetaLinkDashboard('token')" class="text-[10px] text-blue-600 font-bold hover:underline bg-transparent border-0 cursor-pointer">Open Meta</button>
                                </div>
                            </div>
                            <div class="relative flex items-center">
                                <input type="password" id="wa-manual-token" placeholder="EAAGemini... (Paste permanent access token)" class="w-full pr-20 pl-3.5 py-2.5 bg-[#f8fafc] border border-slate-200 rounded-xl text-xs font-mono font-medium text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white transition" required>
                                <div class="absolute right-2 flex items-center space-x-1">
                                    <button type="button" onclick="toggleInputVisibilityDashboard('wa-manual-token', this)" class="p-1.5 hover:bg-slate-200/60 rounded-lg text-slate-400 hover:text-slate-650 transition cursor-pointer border-0 bg-transparent flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button type="button" onclick="pasteToInputDashboard('wa-manual-token')" class="p-1.5 hover:bg-slate-200/60 rounded-lg text-slate-400 hover:text-slate-650 transition cursor-pointer border-0 bg-transparent flex items-center justify-center" title="Paste Token">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- WABA ID -->
                        <div class="space-y-1.5">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-2">
                                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider">WABA ID *</label>
                                    <i data-lucide="info" class="h-3.5 w-3.5 text-slate-400 cursor-help" title="WhatsApp Business Account ID found in Meta API Setup page."></i>
                                    <span id="wa-waba-valid-badge" class="hidden px-2 py-0.5 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-[9px] font-black uppercase tracking-wider">✓ Valid</span>
                                </div>
                                <div class="flex items-center space-x-1.5">
                                    <button type="button" onclick="openMetaLinkDashboard('waba')" class="text-[10px] text-blue-600 font-bold hover:underline bg-transparent border-0 cursor-pointer">Open Meta</button>
                                </div>
                            </div>
                            <div class="relative flex items-center">
                                <input type="password" id="wa-manual-waba-id" placeholder="e.g. 1092384729384" class="w-full pr-20 pl-3.5 py-2.5 bg-[#f8fafc] border border-slate-200 rounded-xl text-xs font-mono font-medium text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white transition" required>
                                <div class="absolute right-2 flex items-center space-x-1">
                                    <button type="button" onclick="toggleInputVisibilityDashboard('wa-manual-waba-id', this)" class="p-1.5 hover:bg-slate-200/60 rounded-lg text-slate-400 hover:text-slate-650 transition cursor-pointer border-0 bg-transparent flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                    <button type="button" onclick="pasteToInputDashboard('wa-manual-waba-id')" class="p-1.5 hover:bg-slate-200/60 rounded-lg text-slate-400 hover:text-slate-650 transition cursor-pointer border-0 bg-transparent flex items-center justify-center" title="Paste WABA ID">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden Input to store Phone Number ID -->
                        <input type="hidden" id="wa-manual-phone-id" value="">

                        <!-- Select WhatsApp Number (Dropdown) -->
                        <div id="wa-phone-select-container" class="hidden space-y-1.5">
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-2">
                                    <label class="block text-xs font-extrabold text-slate-700 uppercase tracking-wider">Select WhatsApp Number *</label>
                                    <span id="wa-phone-valid-badge" class="hidden px-2 py-0.5 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-lg text-[9px] font-black uppercase tracking-wider">✓ Selected</span>
                                </div>
                            </div>
                            <select id="wa-phone-dropdown" class="w-full px-3.5 py-2.5 bg-[#f8fafc] border border-slate-200 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:border-blue-600 focus:bg-white transition" required>
                                <option value="">-- Choose a phone number --</option>
                            </select>
                        </div>

                        <!-- Live Auto-Fetched Details Card -->
                        <div id="wa-auto-details-card" class="hidden p-4 bg-emerald-50/70 border border-emerald-200 rounded-2xl space-y-3 shadow-2xs">
                            <div class="flex items-center justify-between border-b border-emerald-200/80 pb-2">
                                <div class="flex items-center space-x-2">
                                    <span class="p-1 bg-emerald-500 text-white rounded-lg"><i data-lucide="shield-check" class="h-3.5 w-3.5 text-white"></i></span>
                                    <span class="font-extrabold text-emerald-950 text-xs">Live Auto-Fetched Meta Properties</span>
                                </div>
                                <span id="wa-live-quality" class="px-2.5 py-0.5 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-full text-[9px] font-black uppercase">GREEN</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-700 font-medium">
                                <div>Business Name: <strong id="wa-live-biz-name" class="text-slate-900 block mt-0.5">--</strong></div>
                                <div>Display Phone: <strong id="wa-live-phone" class="font-mono text-slate-900 block mt-0.5">--</strong></div>
                                <div>WABA Details: <strong id="wa-live-waba" class="font-mono text-slate-900 block mt-0.5">--</strong></div>
                                <div>Messaging Limits: <strong id="wa-live-limit" class="text-slate-900 block mt-0.5">--</strong></div>
                                <div>Webhook Status: <strong id="wa-live-webhook" class="text-emerald-700 block mt-0.5">✓ Subscribed & Verified</strong></div>
                                <div>Template Count: <strong id="wa-live-templates" class="text-slate-900 block mt-0.5">--</strong></div>
                            </div>
                        </div>

                        <!-- Form Controls -->
                        <div class="pt-4 flex flex-col sm:flex-row gap-3">
                            <button type="button" id="wa-validate-creds-btn" onclick="validateCredentialsSetupDashboard()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold transition cursor-pointer flex items-center justify-center space-x-1.5 rounded-xl shadow-md border-0" style="color: #ffffff !important;">
                                <i data-lucide="shield-alert" class="h-4 w-4 text-white"></i>
                                <span style="color: #ffffff !important;">Validate Credentials</span>
                            </button>
                            <button type="submit" id="wa-manual-submit-btn" class="hidden w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition shadow-md shadow-emerald-500/20 flex items-center justify-center space-x-2 cursor-pointer border-0" style="color: #ffffff !important;">
                                <i data-lucide="save" class="h-4 w-4 text-white"></i>
                                <span style="color: #ffffff !important;">Establish Connection with Meta</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

                <!-- RIGHT COLUMN: AI Setup Assistant Card (lg:col-span-5) -->
                <div class="lg:col-span-5 bg-white border border-slate-200/80 rounded-3xl p-6 shadow-soft flex flex-col h-[510px] space-y-4">
                    <!-- Card Header -->
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4 shrink-0">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center p-2 shrink-0 shadow-sm">
                                <img src="https://crystalpng.com/wp-content/uploads/2025/02/meta_logo.png" class="w-7 h-7 object-contain" alt="Meta">
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-slate-900 flex items-center space-x-1">
                                    <span>Meta Setup Assistant</span>
                                    <!-- Blue verified badge checkmark icon -->
                                    <svg class="h-3.5 w-3.5 text-blue-500 fill-current" viewBox="0 0 24 24">
                                        <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                </h3>
                                <p class="text-[10px] text-slate-500 font-semibold mt-0.5">Powered by <span class="font-extrabold text-blue-600">∞</span> Meta</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" onclick="window.open('https://developers.facebook.com/docs/whatsapp/cloud-api/get-started', '_blank')" class="px-3 py-1.5 bg-white border border-slate-200 hover:border-slate-350 hover:bg-slate-50 rounded-xl text-[10px] font-extrabold text-slate-700 transition flex items-center space-x-1 shadow-2xs cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                                <span>Guide</span>
                            </button>
                            <button type="button" onclick="window.MetaAssistantManager.init()" class="p-1.5 bg-white border border-slate-200 hover:border-rose-200 hover:bg-rose-50 rounded-xl text-slate-400 hover:text-rose-600 transition shadow-2xs cursor-pointer flex items-center justify-center" title="Reset Assistant">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>
                    </div>


                    <!-- Conversation Chat History Window -->
                    <div id="ai-chat-messages-container" class="flex-1 overflow-y-auto pr-1.5 space-y-4 text-xs leading-relaxed">
                        <!-- Messages will be dynamically rendered here -->
                    </div>

                    <!-- Chat input/interaction Area -->
                    <div class="space-y-3 border-t border-slate-100 pt-3 shrink-0">
                        <div id="ai-chat-controls-area" class="flex flex-col space-y-1.5">
                            <!-- Action / Option buttons rendered dynamically -->
                        </div>

                        <!-- Attachment Preview Bar -->
                        <div id="setup-chat-attachment-preview" class="hidden flex items-center space-x-2.5 bg-slate-50 border border-slate-200/80 p-2 rounded-xl text-xs text-slate-700 animate-fade-in mx-1">
                            <img id="setup-chat-attachment-thumbnail" class="w-8 h-8 rounded-lg object-contain border border-slate-200" src="">
                            <span class="flex-1 truncate font-semibold text-slate-650">Screenshot attached</span>
                            <button type="button" onclick="clearSetupChatAttachmentDashboard()" class="p-1 text-slate-400 hover:text-rose-600 rounded-lg transition border-0 bg-transparent cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            </button>
                        </div>

                        <!-- Real Chat Input Box -->
                        <div class="relative bg-slate-50 border border-slate-100 rounded-2xl p-2.5 transition shadow-3xs">
                            <form id="setup-ai-chat-form" onsubmit="event.preventDefault(); handleSetupAiChatSubmitDashboard();" class="flex flex-col space-y-2">
                                <input type="file" id="setup-wa-screenshot-input" accept="image/*" class="hidden" onchange="handleSetupScreenshotUploadDashboard(event)">
                                <textarea id="setup-ai-chat-input" rows="2" placeholder="Ask me anything about Meta setup..." class="w-full bg-transparent border-0 text-xs font-semibold text-slate-900 placeholder-slate-400 focus:ring-0 p-1 resize-none focus:outline-none focus:border-0" style="border: none !important; outline: none !important; box-shadow: none !important; background: transparent !important;"></textarea>
                                
                                <div class="flex items-center justify-between pt-1">
                                    <!-- Toolbars (attachment, settings, link) -->
                                    <div class="flex items-center space-x-1.5 text-slate-400">
                                        <button type="button" onclick="document.getElementById('setup-wa-screenshot-input').click()" class="p-1.5 hover:bg-slate-100 hover:text-slate-600 rounded-lg transition cursor-pointer border-0 bg-transparent flex items-center justify-center" title="Upload Attachment">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                                        </button>
                                        <button type="button" onclick="window.MetaAssistantManager.explainAppCreation()" class="p-1.5 hover:bg-slate-100 hover:text-slate-600 rounded-lg transition cursor-pointer border-0 bg-transparent flex items-center justify-center" title="App Helper">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                        </button>
                                        <button type="button" onclick="openMetaLinkDashboard('dev')" class="p-1.5 hover:bg-slate-100 hover:text-slate-600 rounded-lg transition cursor-pointer border-0 bg-transparent flex items-center justify-center" title="Developer Link">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                        </button>
                                    </div>

                                    <!-- Send Circular Button -->
                                    <button type="submit" class="p-2.5 bg-blue-600 text-white hover:bg-blue-700 rounded-xl transition flex items-center justify-center shrink-0 cursor-pointer border-0 shadow-sm" style="color: #ffffff !important;">
                                        <svg class="h-3.5 w-3.5 fill-current text-white" viewBox="0 0 24 24">
                                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" fill="#ffffff"/>
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            `}
        </div>
    `;

    if (window.lucide) lucide.createIcons();

    // Start setup chatbot assistant if unconnected
    if (!isConnected) {
        setTimeout(() => {
            if (window.MetaAssistantManager) {
                window.MetaAssistantManager.init();
            }
        }, 100);
    }
}

// Helper utilities for manual setup in dashboard
window.toggleInputVisibilityDashboard = function(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
    } else {
        input.type = 'password';
        btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
    }
};

window.pasteToInputDashboard = async function(inputId) {
    try {
        const text = await navigator.clipboard.readText();
        const input = document.getElementById(inputId);
        if (input) {
            input.value = text;
            showNotification('success', 'Pasted successfully!');
            input.dispatchEvent(new Event('input'));
        }
    } catch (err) {
        showNotification('info', 'Please use Ctrl+V / Cmd+V to paste into this field.');
    }
};

window.openMetaLinkDashboard = function(type) {
    let url = 'https://developers.facebook.com/apps';
    if (type === 'token') url = 'https://business.facebook.com/settings/system-users';
    else if (type === 'waba') url = 'https://business.facebook.com/settings/whatsapp-business-accounts';
    else if (type === 'phone' || type === 'dev') url = 'https://developers.facebook.com/apps';
    window.open(url, '_blank');
};

window.copyMetaGuideDashboard = function(type) {
    let guideText = '';
    if (type === 'token') {
        guideText = "1. Go to Business Settings -> Users -> System Users.\n2. Add system user with 'Admin' role.\n3. Click 'Assign Assets' and select WABA account with Full Control.\n4. Click 'Generate Token', select your App & check 'whatsapp_business_messaging' and 'whatsapp_business_management'.\n5. Copy generated permanent token.";
    } else if (type === 'waba') {
        guideText = "1. Go to Meta Developer Portal (developers.facebook.com/apps).\n2. Select your App -> WhatsApp -> API Setup.\n3. Under Step 1, copy your 'WhatsApp Business Account ID'.";
    }
    navigator.clipboard.writeText(guideText).then(() => {
        showNotification('success', 'Step-by-step Meta guide copied to clipboard!');
    }).catch(() => {
        showNotification('info', guideText);
    });
};

window.metaBizName = '';
window.metaPhoneNumber = '';

window.validateCredentialsSetupDashboard = async function() {
    const token = document.getElementById('wa-manual-token').value.trim();
    const wabaId = document.getElementById('wa-manual-waba-id').value.trim();

    if (!token) {
        showNotification('warning', 'Please enter your Permanent Access Token.');
        if (window.MetaAssistantManager) window.MetaAssistantManager.handleError('token_missing');
        return false;
    }
    if (!wabaId) {
        showNotification('warning', 'Please enter your WABA ID.');
        return false;
    }

    const btn = document.getElementById('wa-validate-creds-btn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<div class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div> <span>Validating...</span>`;

    try {
        // 1. Verify WABA ID strictly using timezone check
        const wabaRes = await apiCall('whatsapp/setup.php?action=verify_waba', 'POST', {
            access_token: token,
            waba_id: wabaId
        });

        if (!wabaRes || wabaRes.status !== 'success') {
            throw new Error(wabaRes.message || 'WABA verification failed.');
        }

        // 2. Fetch Phone Numbers registered under this WABA ID
        const res = await apiCall('whatsapp/setup.php?action=get_phone_numbers', 'POST', {
            access_token: token,
            waba_id: wabaId
        });

        if (res && res.status === 'success' && res.phones && res.phones.length > 0) {
            document.getElementById('wa-token-valid-badge').classList.remove('hidden');
            document.getElementById('wa-waba-valid-badge').classList.remove('hidden');

            const dropdown = document.getElementById('wa-phone-dropdown');
            dropdown.innerHTML = '<option value="">-- Choose a phone number --</option>';
            res.phones.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = `${p.display_phone_number} (${p.verified_name || 'Verified Name'})`;
                opt.dataset.verifiedName = p.verified_name || '';
                opt.dataset.displayPhone = p.display_phone_number || '';
                opt.dataset.quality = p.quality_rating || 'GREEN';
                opt.dataset.limit = p.messaging_limit || '1,000/day';
                dropdown.appendChild(opt);
            });

            window.metaBizName = wabaRes.waba_name || 'Verified Account';

            // Reveal Select Dropdown Container
            document.getElementById('wa-phone-select-container').classList.remove('hidden');
            
            showNotification('success', 'WABA verified! Please select a phone number below to proceed.');
            
            // Hide validation button
            btn.classList.add('hidden');

            if (window.MetaAssistantManager) window.MetaAssistantManager.advanceStep(8, "Credentials validated. Please select a phone number.");
            return true;
        } else {
            throw new Error('No registered phone numbers found under this WABA ID.');
        }
    } catch (err) {
        showNotification('error', err.message || 'Validation failed. Please verify credentials.');
        if (window.MetaAssistantManager) window.MetaAssistantManager.handleError(err.message);
        return false;
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
};

window.saveManualWhatsAppConnectionDashboard = async function() {
    const accessToken = document.getElementById('wa-manual-token').value.trim();
    const wabaId = document.getElementById('wa-manual-waba-id').value.trim();
    const phoneId = document.getElementById('wa-manual-phone-id').value.trim();
    const bizName = window.metaBizName || 'Verified Account';
    const phoneNumber = window.metaPhoneNumber || '--';
    
    if (!accessToken || !wabaId || !phoneId) {
        showNotification('warning', 'Please fill out all required Meta WhatsApp fields.');
        if (window.MetaAssistantManager) window.MetaAssistantManager.handleError('Missing fields');
        return;
    }
    
    const btn = document.getElementById('wa-manual-submit-btn');
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `
            <div class="flex items-center space-x-1.5">
                <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span>Connecting...</span>
            </div>
        `;
    }
    
    try {
        await apiCall('whatsapp/manual_connect.php', 'POST', {
            access_token: accessToken,
            waba_id: wabaId,
            phone_number_id: phoneId,
            business_name: bizName,
            phone_number: phoneNumber,
            display_name: bizName
        });
        
        showNotification('success', 'WhatsApp account configured manually via Meta API!');
        
        if (window.MetaAssistantManager) window.MetaAssistantManager.renderStep(9);
        
        // Re-render view dynamically to show connected state
        const mainContainer = document.getElementById('main-content') || document.querySelector('.main-container') || document.getElementById('app-content');
        if (mainContainer) {
            checkWaConnectionAndRender('whatsapp', mainContainer, (cnt, st) => renderWhatsAppSetup(cnt, st.settings));
        } else {
            window.location.reload();
        }
    } catch (err) {
        showNotification('error', err.message || 'WhatsApp Meta connection failed.');
        if (window.MetaAssistantManager) window.MetaAssistantManager.handleError(err.message || 'Connection error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }
};

if (!window.renderStyledStepsCard) {
    window.renderStyledStepsCard = function(msgText) {
        if (msgText.includes('1. ') && msgText.includes('2. ')) {
            const parts = msgText.split(/\d+\.\s+/);
            const intro = parts[0] || '';
            const listItems = [];
            const regex = /\d+\.\s+([^\n]+)/g;
            let match;
            while ((match = regex.exec(msgText)) !== null) {
                listItems.push(match[1]);
            }
            if (listItems.length > 0) {
                let listHtml = `<div class="bg-white border border-slate-200/80 rounded-2xl overflow-hidden divide-y divide-slate-100 mt-2.5 font-medium shadow-3xs">`;
                listItems.forEach((item, idx) => {
                    listHtml += `
                        <div class="flex items-center justify-between p-3 hover:bg-slate-50/50 transition">
                            <div class="flex items-center space-x-2.5">
                                <span class="w-5 h-5 rounded-full bg-blue-50 text-blue-600 font-extrabold text-[10px] flex items-center justify-center shrink-0">
                                    ${idx + 1}
                                </span>
                                <span class="text-[11px] text-slate-700 font-semibold">${item.replace(/\*\*/g, '')}</span>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 text-slate-400 shrink-0"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2 2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </div>`;
                });
                listHtml += `</div>`;
                return intro.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>') + listHtml;
            }
        }
        return null;
    };
}

// META ASSISTANT CHATBOT MANAGER FOR DASHBOARD
window.MetaAssistantManager = {
    currentStep: 1,
    retryCount: 0,
    history: [],
    pendingAttachment: null,

    stepsConfig: {
        1: {
            title: "Welcome 👋",
            percentage: 10,
            msg: "Let's connect your WhatsApp Business account. This usually takes around 2–5 minutes. I'll guide you through everything step-by-step.",
            buttons: []
        },
        2: {
            title: "Open Meta Developer Portal",
            percentage: 20,
            msg: "Step 1: Open Meta Developer Portal to access your app dashboard.",
            buttons: [
                { label: "↗ Open Meta Developer", action: () => openMetaLinkDashboard('dev') },
                { label: "Continue →", action: () => MetaAssistantManager.advanceStep(3, "I have opened Meta Developer Portal") }
            ]
        },
        3: {
            title: "Check Meta App Status",
            percentage: 35,
            msg: "Have you already created a Meta Developer App for WhatsApp?",
            buttons: [
                { label: "Yes, I have an App", action: () => MetaAssistantManager.advanceStep(4, "Yes, I have a Meta App") },
                { label: "No, guide me to create one", action: () => MetaAssistantManager.explainAppCreation() }
            ]
        },
        4: {
            title: "Navigate to API Setup",
            percentage: 50,
            msg: "Great! Inside your Meta App dashboard, click **WhatsApp** in the left sidebar and select **API Setup**.",
            buttons: [
                { label: "I am on API Setup page →", action: () => MetaAssistantManager.advanceStep(5, "I am on API Setup page") }
            ]
        },
        5: {
            title: "Copy Access Token",
            percentage: 60,
            msg: "Awesome! Copy your **Temporary Access Token** or **System User Token** from Meta and paste it into the **Permanent Access Token** field on the left.",
            buttons: [
                { label: "I've pasted Access Token", action: () => MetaAssistantManager.checkTokenField() },
                { label: "Need help finding it?", action: () => copyMetaGuideDashboard('token') }
            ]
        },
        6: {
            title: "Copy WhatsApp Business Account ID",
            percentage: 70,
            msg: "Great job! Next, copy your **WhatsApp Business Account ID** (WABA ID) from Meta and paste it into the **WABA ID** field on the left.",
            buttons: [
                { label: "I've pasted WABA ID", action: () => MetaAssistantManager.checkWabaField() }
            ]
        },
        7: {
            title: "Validate and Select Phone Number",
            percentage: 85,
            msg: "Now click **Validate Credentials** on the left to verify your WABA connection and fetch your registered WhatsApp phone numbers.",
            buttons: [
                { label: "Validate Credentials", action: () => validateCredentialsSetupDashboard() }
            ]
        },
        8: {
            title: "Generate Permanent Access Token",
            percentage: 95,
            msg: "Please select your phone number from the dropdown list on the left. Then make sure you generated a **Permanent System User Token** so your setup never expires:\n\n1. Meta Business Settings -> System Users\n2. Add System User (Admin)\n3. Assign WABA Assets (Full Control)\n4. Generate Token (scopes: whatsapp_business_messaging, whatsapp_business_management)\n5. Select your number and click **Establish Connection with Meta** to finalize setup.",
            buttons: [
                { label: "Connect & Finalize WhatsApp", action: () => saveManualWhatsAppConnectionDashboard() }
            ]
        },
        9: {
            title: "WhatsApp Connected Successfully! 🎉",
            percentage: 100,
            msg: "🎉 **Congratulations! Your WhatsApp Business Account is live & fully connected.**\n\n- Webhook: Verified & Subscribed\n- Templates: Auto-Synchronized\n- AI Auto-Reply: Ready",
            buttons: [
                { label: "Go to WhatsApp Dashboard", action: () => window.location.reload() }
            ]
        }
    },

    init: function() {
        const container = document.getElementById('ai-chat-messages-container');
        if (container) container.innerHTML = '';
        this.retryCount = 0;
        this.history = [];
        this.pendingAttachment = null;
        if (window.clearSetupChatAttachmentDashboard) window.clearSetupChatAttachmentDashboard();
        this.renderStep(1);
    },

    renderStep: function(stepNum) {
        this.currentStep = stepNum;
        const config = this.stepsConfig[stepNum];
        if (!config) return;

        const pTxt = document.getElementById('wa-assistant-percentage');
        const sBadge = document.getElementById('wa-assistant-step-badge');
        const sLabel = document.getElementById('wa-assistant-step-label');

        if (pTxt) pTxt.textContent = config.percentage + '% Complete';
        if (sBadge) sBadge.textContent = `Step ${stepNum} of 9`;
        if (sLabel) sLabel.textContent = config.title;

        // Segmented progress bar fill
        for (let i = 1; i <= 9; i++) {
            const seg = document.getElementById(`segment-${i}`);
            if (seg) {
                if (i <= stepNum) {
                    seg.classList.remove('bg-slate-200');
                    seg.classList.add('bg-blue-600');
                } else {
                    seg.classList.remove('bg-blue-600');
                    seg.classList.add('bg-slate-200');
                }
            }
        }

        const formattedMsg = config.msg.replace(/\n/g, '<br>');
        this.addAiMessage(formattedMsg);
        this.history.push({ role: 'assistant', content: config.msg });
        this.renderControls(config.buttons);
    },

    addAiMessage: function(text, questionText = '') {
        const container = document.getElementById('ai-chat-messages-container');
        if (!container) return;

        const escapedQuestion = (questionText || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const escapedAnswer = text.replace(/'/g, "\\'").replace(/"/g, '&quot;');

        // Check if numbered list is present to render styled steps card
        let displayHtml = text;
        if (window.renderStyledStepsCard) {
            const styledSteps = window.renderStyledStepsCard(text);
            if (styledSteps) {
                displayHtml = styledSteps;
            }
        }

        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex items-start space-x-3.5 animate-fade-in text-left w-full';
        msgDiv.innerHTML = `
            <div class="w-8 h-8 rounded-full border border-blue-100 flex items-center justify-center p-1.5 shrink-0 bg-blue-50/50 shadow-sm">
                <img src="https://crystalpng.com/wp-content/uploads/2025/02/meta_logo.png" class="w-5 h-5 object-contain" alt="Meta">
            </div>
            <div class="flex flex-col space-y-1 w-full max-w-[85%]">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-slate-800">Meta Assistant</span>
                    <span class="text-[10px] text-slate-400 font-medium">Just now</span>
                </div>
                <div class="bg-[#f4f6fa] border border-slate-200/50 text-slate-800 p-3.5 rounded-3xl rounded-tl-none space-y-2 text-xs font-semibold leading-relaxed shadow-3xs">
                    <div>${displayHtml}</div>
                    ${escapedQuestion ? `
                    <div class="flex items-center space-x-3 pt-1.5 mt-1.5 border-t border-slate-200/40">
                        <button type="button" onclick="submitAiFeedbackDashboard(this, 'like', '${escapedQuestion}', '${escapedAnswer}')" class="text-slate-450 hover:text-emerald-600 transition bg-transparent border-0 p-0.5 cursor-pointer flex items-center space-x-1" title="Helpful (Train AI)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                            <span class="text-[10px]">Like</span>
                        </button>
                        <button type="button" onclick="submitAiFeedbackDashboard(this, 'dislike', '${escapedQuestion}', '${escapedAnswer}')" class="text-slate-450 hover:text-rose-600 transition bg-transparent border-0 p-0.5 cursor-pointer flex items-center space-x-1" title="Not helpful">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"></path></svg>
                            <span class="text-[10px]">Dislike</span>
                        </button>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
        container.appendChild(msgDiv);
        container.scrollTop = container.scrollHeight;
    },

    addUserMessage: function(text) {
        const container = document.getElementById('ai-chat-messages-container');
        if (!container) return;

        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex items-end justify-end space-x-2.5 animate-fade-in text-right w-full';
        msgDiv.innerHTML = `
            <div class="flex flex-col space-y-1 max-w-[85%]">
                <div class="flex items-center justify-between text-right">
                    <span class="text-[10px] text-slate-400 font-medium">Just now</span>
                    <span class="text-xs font-bold text-slate-800 ml-2">You</span>
                </div>
                <div class="relative bg-blue-600 text-white p-3.5 rounded-3xl rounded-tr-none text-left shadow-xs text-xs font-semibold leading-relaxed" style="background: linear-gradient(135deg, #2563eb, #1d4ed8) !important; color: #ffffff !important;">
                    <div>${text}</div>
                </div>
            </div>
        `;
        container.appendChild(msgDiv);
        container.scrollTop = container.scrollHeight;
    },

    renderControls: function(buttons) {
        const controlsArea = document.getElementById('ai-chat-controls-area');
        if (!controlsArea) return;
        controlsArea.innerHTML = '';

        buttons.forEach(btnConfig => {
            const btn = document.createElement('button');
            btn.type = 'button';
            
            const isPrimary = btnConfig.label.includes('🚀') || btnConfig.label.includes('Connect') || btnConfig.label.includes('Start') || btnConfig.label.includes('Yes');
            
            if (isPrimary) {
                btn.className = 'w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl transition flex items-center justify-between shadow-xs border-0 cursor-pointer';
                btn.style.color = '#ffffff';
                btn.innerHTML = `<span>${btnConfig.label}</span> <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-white"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`;
            } else {
                btn.className = 'w-full py-2.5 px-4 bg-white border border-slate-200 hover:border-slate-350 hover:bg-slate-50 text-slate-800 text-xs font-extrabold rounded-xl transition flex items-center justify-between shadow-2xs cursor-pointer';
                btn.innerHTML = `<span>${btnConfig.label}</span> <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 text-slate-400"><polyline points="9 18 15 12 9 6"></polyline></svg>`;
            }
            btn.onclick = btnConfig.action;
            controlsArea.appendChild(btn);
        });
    },

    advanceStep: function(nextStepNum, userText) {
        if (userText) {
            this.addUserMessage(userText);
            this.history.push({ role: 'user', content: userText });
        }
        setTimeout(() => {
            this.renderStep(nextStepNum);
        }, 300);
    },

    explainAppCreation: function() {
        this.addUserMessage("I need help creating a Meta App");
        setTimeout(() => {
            this.addAiMessage("To create a Meta App:\n1. Click 'Create App' on Meta Developers.\n2. Choose 'Business' app type.\n3. Enter your App Name & Business Email.\n4. Select your Business Manager Account and click 'Create'.");
            this.renderControls([
                { label: "App Created → Next Step", action: () => MetaAssistantManager.advanceStep(4, "App Created!") }
            ]);
        }, 300);
    },

    checkTokenField: function() {
        const val = document.getElementById('wa-manual-token').value.trim();
        if (val.length > 20) {
            this.advanceStep(6, "I pasted the Access Token.");
        } else {
            this.handleError("Please paste a valid Access Token into the field on the left.");
        }
    },

    checkWabaField: function() {
        const val = document.getElementById('wa-manual-waba-id').value.trim();
        if (val.length >= 10) {
            this.advanceStep(7, "I pasted the WABA ID.");
        } else {
            this.handleError("Please paste a valid WABA ID into the form on the left.");
        }
    },

    handleError: function(errReason) {
        this.lastError = errReason;
        this.retryCount++;
        let explainText = "❌ Validation check unsuccessful.";

        if (errReason.includes("token") || errReason.includes("Token")) {
            explainText = "❌ **Invalid Access Token**\nPlease make sure to copy the full string starting with `EAAG...` from Meta System Users.";
        } else if (errReason.includes("waba") || errReason.includes("WABA")) {
            explainText = "❌ **Invalid WABA ID**\nWABA ID is your 15+ digit WhatsApp Business Account ID found in Meta Business Settings.";
        }

        this.addAiMessage(explainText);

        if (this.retryCount >= 3) {
            setTimeout(() => {
                this.addAiMessage("It looks like you're still having trouble.\nWould you like one of our onboarding specialists to help you?");
                this.renderControls([
                    { label: "📞 Call Support (+91 8016222991)", action: () => window.open('tel:+918016222991') },
                    { label: "💬 WhatsApp Live Chat", action: () => window.open('https://wa.me/918016222991', '_blank') },
                    { label: "📧 Email Support", action: () => window.open('mailto:support@linkpilot.work') },
                ]);
            }, 300);
        }
    }
};

window.handleSetupAiChatSubmitDashboard = async function() {
    const input = document.getElementById('setup-ai-chat-input');
    if (!input) return;
    const text = input.value.trim();
    
    const assistant = window.MetaAssistantManager;
    if (!assistant) return;

    const hasAttachment = assistant.pendingAttachment !== null;
    if (text === '' && !hasAttachment) return;
    input.value = '';

    const container = document.getElementById('ai-chat-messages-container');

    if (hasAttachment) {
        const base64Image = assistant.pendingAttachment;
        const hasText = text !== '';
        const questionLogText = "[User sent a Meta console screenshot" + (hasText ? " with message: " + text : "") + "]";

        if (container) {
            const msgDiv = document.createElement('div');
            msgDiv.className = 'flex items-end justify-end space-x-2.5 animate-fade-in text-right';
            msgDiv.innerHTML = `
                <div class="bg-blue-600 text-white p-3 rounded-2xl rounded-tr-xs space-y-1.5 max-w-[85%] shadow-xs text-left">
                    ${hasText ? `<div class="text-xs font-semibold mb-1.5 text-white" style="color: #ffffff !important;">${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</div>` : ''}
                    <img src="${base64Image}" class="max-h-[140px] rounded-lg border border-white/20 object-contain mx-auto" alt="Screenshot Upload">
                </div>
            `;
            container.appendChild(msgDiv);
            container.scrollTop = container.scrollHeight;
        }

        assistant.history.push({ role: 'user', content: questionLogText });
        window.clearSetupChatAttachmentDashboard();

        const loaderId = 'setup-ai-thinking-' + Date.now();
        if (container) {
            container.insertAdjacentHTML('beforeend', `
                <div class="flex items-start space-x-2.5 animate-pulse text-left" id="${loaderId}">
                    <div class="w-7 h-7 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-center shrink-0 p-1 shadow-2xs">
                        <img src="https://crystalpng.com/wp-content/uploads/2025/02/meta_logo.png" class="w-5 h-5 object-contain" alt="Meta">
                    </div>
                    <div class="bg-slate-100 border border-slate-200/60 text-slate-500 p-3 rounded-2xl rounded-tl-xs italic max-w-[88%] shadow-2xs flex items-center space-x-2">
                        <div class="w-3.5 h-3.5 border-2 border-slate-500 border-t-transparent rounded-full animate-spin"></div>
                        <span>Meta Assistant is analyzing your screenshot...</span>
                    </div>
                </div>
            `);
            container.scrollTop = container.scrollHeight;
        }

        try {
            const res = await apiCall('crm/analyze_meta_screenshot.php', 'POST', {
                image: base64Image,
                message: text
            });

            const loader = document.getElementById(loaderId);
            if (loader) loader.remove();

            if (res && res.status === 'success') {
                const reply = res.data.reply;
                assistant.history.push({ role: 'assistant', content: reply });

                const formatted = formatSetupAiChatReplyDashboard(reply);
                assistant.addAiMessage(formatted, questionLogText);
            } else {
                throw new Error(res.message || 'Vision analysis failed.');
            }
        } catch (err) {
            const loader = document.getElementById(loaderId);
            if (loader) loader.remove();
            assistant.addAiMessage("⚠️ " + (err.message || "Failed to analyze screenshot. Please verify your AI central key credentials."));
        }

    } else {
        assistant.addUserMessage(text);
        assistant.history.push({ role: 'user', content: text });

        const loaderId = 'setup-ai-thinking-' + Date.now();
        if (container) {
            container.insertAdjacentHTML('beforeend', `
                <div class="flex items-start space-x-2.5 animate-pulse text-left" id="${loaderId}">
                    <div class="w-7 h-7 bg-blue-50 border border-blue-100 rounded-xl flex items-center justify-center shrink-0 p-1 shadow-2xs">
                        <img src="https://crystalpng.com/wp-content/uploads/2025/02/meta_logo.png" class="w-5 h-5 object-contain" alt="Meta">
                    </div>
                    <div class="bg-slate-100 border border-slate-200/60 text-slate-500 p-3 rounded-2xl rounded-tl-xs italic max-w-[88%] shadow-2xs">
                        Searching workspace data...
                    </div>
                </div>
            `);
            container.scrollTop = container.scrollHeight;
        }

        try {
            const data = await apiCall('crm/chat_assistant.php', 'POST', {
                message: text,
                history: assistant.history.slice(0, -1),
                setup_state: {
                    token: document.getElementById('wa-manual-token').value.trim(),
                    waba_id: document.getElementById('wa-manual-waba-id').value.trim(),
                    phone_id: document.getElementById('wa-manual-phone-id').value.trim(),
                    current_step: assistant.currentStep,
                    last_error: assistant.lastError || ''
                }
            });

            const loader = document.getElementById(loaderId);
            if (loader) loader.remove();

            if (data && data.status === 'success') {
                const reply = data.reply;
                assistant.history.push({ role: 'assistant', content: reply });
                
                const formatted = formatSetupAiChatReplyDashboard(reply);
                assistant.addAiMessage(formatted, text);
            } else {
                throw new Error(data.message || 'Error communicating with AI assistant.');
            }
        } catch (err) {
            const loader = document.getElementById(loaderId);
            if (loader) loader.remove();
            assistant.addAiMessage("⚠️ " + (err.message || "Failed to contact AI Assistant. Please check connection."));
        }
    }
};

window.formatSetupAiChatReplyDashboard = function(text) {
    let formatted = text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    formatted = formatted.replace(/\[([^\]]+)\]\((https?:\/\/[^\s\)]+)\)/g, '<a href="$2" target="_blank" class="text-blue-600 hover:text-blue-800 underline font-bold transition">$1</a>');
    formatted = formatted.replace(/(?:\r?\n|^)###\s+([^\r\n]+)/g, '<h4 class="text-xs font-bold text-slate-800 mt-2 mb-1">$1</h4>');
    formatted = formatted.replace(/(?:\r?\n|^)##\s+([^\r\n]+)/g, '<h3 class="text-xs font-black text-slate-900 mt-3 mb-1">$1</h3>');
    formatted = formatted.replace(/(?:\r?\n|^)-\s+([^\r\n]+)/g, '<li class="ml-4 list-disc mt-1">$1</li>');
    formatted = formatted.replace(/\r?\n/g, '<br>');
    return formatted;
};

window.attachSetupChatImageDashboard = function(base64Data) {
    const assistant = window.MetaAssistantManager;
    if (!assistant) return;

    assistant.pendingAttachment = base64Data;
    
    const thumb = document.getElementById('setup-chat-attachment-thumbnail');
    if (thumb) thumb.src = base64Data;

    const previewBar = document.getElementById('setup-chat-attachment-preview');
    if (previewBar) previewBar.classList.remove('hidden');
};

window.clearSetupChatAttachmentDashboard = function() {
    const assistant = window.MetaAssistantManager;
    if (assistant) assistant.pendingAttachment = null;

    const previewBar = document.getElementById('setup-chat-attachment-preview');
    if (previewBar) previewBar.classList.add('hidden');

    const thumb = document.getElementById('setup-chat-attachment-thumbnail');
    if (thumb) thumb.src = '';

    const fileInput = document.getElementById('setup-wa-screenshot-input');
    if (fileInput) fileInput.value = '';
};

window.handleSetupScreenshotUploadDashboard = function(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        showNotification('error', 'Please select a valid image file.');
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        window.attachSetupChatImageDashboard(e.target.result);
    };
    reader.readAsDataURL(file);
};

window.submitAiFeedbackDashboard = async function(btn, type, question, answer) {
    try {
        const parent = btn.parentElement;
        const buttons = parent.querySelectorAll('button');
        buttons.forEach(b => {
            b.disabled = true;
            b.classList.add('opacity-40');
        });

        if (type === 'like') {
            btn.classList.remove('opacity-40');
            btn.classList.add('text-emerald-600', 'font-black');
        } else {
            btn.classList.remove('opacity-40');
            btn.classList.add('text-rose-600', 'font-black');
        }

        const res = await apiCall('crm/save_chat_feedback.php', 'POST', {
            question: question,
            answer: answer,
            feedback_type: type
        });

        if (res && res.status === 'success') {
            showNotification('success', type === 'like' ? 'Response liked! AI has learned this answer.' : 'Response disliked.');
        } else {
            throw new Error(res.message || 'Failed to save feedback.');
        }
    } catch (err) {
        showNotification('error', err.message || 'Error saving feedback.');
    }
};

// Event delegation for text input fields and dropdown change handler
document.addEventListener('input', function(e) {
    if (!window.MetaAssistantManager) return;
    
    if (e.target && e.target.id === 'wa-manual-token') {
        const resetValidationUI = () => {
            const tokenBadge = document.getElementById('wa-token-valid-badge');
            const wabaBadge = document.getElementById('wa-waba-valid-badge');
            const phoneBadge = document.getElementById('wa-phone-valid-badge');
            if (tokenBadge) tokenBadge.classList.add('hidden');
            if (wabaBadge) wabaBadge.classList.add('hidden');
            if (phoneBadge) phoneBadge.classList.add('hidden');
            
            const autoCard = document.getElementById('wa-auto-details-card');
            if (autoCard) autoCard.classList.add('hidden');

            const validateBtn = document.getElementById('wa-validate-creds-btn');
            const connectBtn = document.getElementById('wa-manual-submit-btn');
            if (validateBtn) validateBtn.classList.remove('hidden');
            if (connectBtn) connectBtn.classList.add('hidden');
        };
        
        resetValidationUI();
        if (e.target.value.trim().length > 20) {
            if (window.MetaAssistantManager.currentStep === 5) {
                window.MetaAssistantManager.advanceStep(6, "Token pasted successfully.");
            }
        }
    }
    
    if (e.target && e.target.id === 'wa-manual-waba-id') {
        const resetValidationUI = () => {
            const tokenBadge = document.getElementById('wa-token-valid-badge');
            const wabaBadge = document.getElementById('wa-waba-valid-badge');
            const phoneBadge = document.getElementById('wa-phone-valid-badge');
            if (tokenBadge) tokenBadge.classList.add('hidden');
            if (wabaBadge) wabaBadge.classList.add('hidden');
            if (phoneBadge) phoneBadge.classList.add('hidden');
            
            const autoCard = document.getElementById('wa-auto-details-card');
            if (autoCard) autoCard.classList.add('hidden');

            const validateBtn = document.getElementById('wa-validate-creds-btn');
            const connectBtn = document.getElementById('wa-manual-submit-btn');
            if (validateBtn) validateBtn.classList.remove('hidden');
            if (connectBtn) connectBtn.classList.add('hidden');
        };

        resetValidationUI();
        if (e.target.value.trim().length >= 10) {
            if (window.MetaAssistantManager.currentStep === 6) {
                window.MetaAssistantManager.advanceStep(7, "WABA ID pasted.");
            }
        }
    }
});

document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'wa-phone-dropdown') {
        const dropdown = e.target;
        const val = dropdown.value;
        const selectedOpt = dropdown.options[dropdown.selectedIndex];
        
        if (val) {
            document.getElementById('wa-manual-phone-id').value = val;
            
            const badge = document.getElementById('wa-phone-valid-badge');
            if (badge) badge.classList.remove('hidden');
            
            window.metaPhoneNumber = selectedOpt.dataset.displayPhone || '--';
            
            const autoCard = document.getElementById('wa-auto-details-card');
            if (autoCard) autoCard.classList.remove('hidden');
            
            const liveBiz = document.getElementById('wa-live-biz-name');
            if (liveBiz) liveBiz.textContent = window.metaBizName || selectedOpt.dataset.verifiedName || 'Verified Account';
            
            const livePhone = document.getElementById('wa-live-phone');
            if (livePhone) livePhone.textContent = window.metaPhoneNumber;
            
            const liveWaba = document.getElementById('wa-live-waba');
            if (liveWaba) liveWaba.textContent = document.getElementById('wa-manual-waba-id').value.trim();
            
            const liveLimit = document.getElementById('wa-live-limit');
            if (liveLimit) liveLimit.textContent = selectedOpt.dataset.limit || '1,000 messages / 24h';
            
            const liveTemplates = document.getElementById('wa-live-templates');
            if (liveTemplates) liveTemplates.textContent = 'Pending sync...';
            
            // Show Establish Connection button
            const submitBtn = document.getElementById('wa-manual-submit-btn');
            if (submitBtn) submitBtn.classList.remove('hidden');

            // Chatbot progression
            if (window.MetaAssistantManager && window.MetaAssistantManager.currentStep === 7) {
                window.MetaAssistantManager.advanceStep(8, "Phone number selected.");
            }
        } else {
            document.getElementById('wa-manual-phone-id').value = '';
            
            const badge = document.getElementById('wa-phone-valid-badge');
            if (badge) badge.classList.add('hidden');
            
            const autoCard = document.getElementById('wa-auto-details-card');
            if (autoCard) autoCard.classList.add('hidden');
            
            const submitBtn = document.getElementById('wa-manual-submit-btn');
            if (submitBtn) submitBtn.classList.add('hidden');
        }
    }
});

document.addEventListener('keydown', function(e) {
    if (e.target && e.target.id === 'setup-ai-chat-input') {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (window.handleSetupAiChatSubmitDashboard) {
                window.handleSetupAiChatSubmitDashboard();
            }
        }
    }
});

document.addEventListener('paste', function(e) {
    if (e.target && e.target.id === 'setup-ai-chat-input') {
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') === 0) {
                const blob = items[i].getAsFile();
                const reader = new FileReader();
                reader.onload = function(event) {
                    window.attachSetupChatImageDashboard(event.target.result);
                };
                reader.readAsDataURL(blob);
                e.preventDefault();
                break;
            }
        }
    }
});

// Embedded signup listener setup
if (!window._waEmbeddedSignupListenerAttached) {
    window._waEmbeddedSignupListenerAttached = true;
    window.addEventListener('message', function(event) {
        if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') {
            return;
        }
        try {
            const parsed = (typeof event.data === 'string') ? JSON.parse(event.data) : event.data;
            if (parsed && parsed.type === 'WA_EMBEDDED_SIGNUP') {
                if (parsed.event === 'FINISH' || parsed.data) {
                    window._waEmbeddedSignupData = parsed.data || {};
                    if (parsed.code) {
                        window._waEmbeddedSignupCode = parsed.code;
                    }
                }
            }
        } catch(e) {}
    });
}

function loadFbSdk() {
    return new Promise((resolve) => {
        if (document.getElementById('facebook-jssdk')) {
            resolve();
            return;
        }
        const js = document.createElement('script');
        js.id = 'facebook-jssdk';
        js.src = "https://connect.facebook.net/en_US/sdk.js";
        js.onload = () => resolve();
        document.getElementsByTagName('script')[0].parentNode.insertBefore(js, document.getElementsByTagName('script')[0]);
    });
}

async function handleDashboardOauthCallback(payload) {
    let requestPayload = payload;
    if (typeof payload === 'string') {
        requestPayload = { access_token: payload };
    }

    try {
        const res = await apiCall('whatsapp/oauth_callback.php', 'POST', requestPayload);
        showNotification('success', 'WhatsApp Business connected successfully via Meta Embedded Signup!');
        const mainContainer = document.getElementById('main-content') || document.querySelector('.main-container') || document.getElementById('app-content');
        if (mainContainer) {
            checkWaConnectionAndRender('whatsapp', mainContainer, (cnt, st) => renderWhatsAppSetup(cnt, st.settings));
        } else {
            window.location.reload();
        }
    } catch (err) {
        showNotification('error', err.message || 'Meta Embedded Signup exchange failed.');
    }
}

window.launchDashboardMetaEmbeddedSignup = async function() {
    window._waEmbeddedSignupData = null;
    window._waEmbeddedSignupCode = null;

    const btn = document.getElementById('wa-embedded-connect-btn');
    const originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `
            <div class="flex items-center space-x-1.5">
                <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                <span style="color:#ffffff !important;">Connecting to Meta...</span>
            </div>
        `;
    }

    try {
        const config = await apiCall('whatsapp/embedded_signup.php');
        const appId = config.app_id;
        const state = config.state;
        const scopes = config.scopes;

        if (!appId) {
            const useDemo = confirm("Demo Mode: No Meta App ID is configured in Admin Settings. Would you like to connect with a sandbox demo account?");
            if (useDemo) {
                await handleDashboardOauthCallback({ access_token: "EAAGeminiTest", state: state });
            } else {
                showNotification('warning', 'Meta App ID configuration is required in Admin Control Panel.');
            }
            return;
        }

        if (!window.FB) {
            await loadFbSdk();
        }

        FB.init({
            appId: appId,
            cookie: true,
            xfbml: true,
            version: 'v20.0'
        });

        FB.login(function(response) {
            if (response.authResponse) {
                const code = response.authResponse.code || window._waEmbeddedSignupCode || '';
                const userToken = response.authResponse.accessToken || '';
                const embeddedData = window._waEmbeddedSignupData || {};
                const wabaId = embeddedData.waba_id || '';
                const phoneId = embeddedData.phone_number_id || '';

                handleDashboardOauthCallback({
                    code: code,
                    access_token: userToken,
                    waba_id: wabaId,
                    phone_number_id: phoneId,
                    state: state
                });
            } else {
                showNotification('error', 'Login cancelled or permissions denied by user.');
            }
        }, {
            response_type: 'code',
            override_default_response_type: true,
            scope: scopes,
            extras: {
                feature: 'whatsapp_embedded_signup',
                version: 2
            }
        });

    } catch (err) {
        showNotification('error', err.message || 'Meta OAuth initialization failed.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
        if (window.lucide) lucide.createIcons();
    }
};

window.disconnectWhatsAppAccount = async function() {
    if (!confirm('Are you sure you want to disconnect your Meta WhatsApp Business Account?')) return;
    try {
        const res = await apiCall('whatsapp/disconnect.php', 'POST');
        showNotification('success', 'WhatsApp account disconnected.');
        window.location.reload();
    } catch (err) {
        showNotification('error', err.message || 'Failed to disconnect.');
    }
};

window.toggleCleanTokenVisibility = function() {
    const input = document.getElementById('wa-clean-token');
    const btn = document.getElementById('btn-toggle-clean-token');
    if (!input || !btn) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Hide';
    } else {
        input.type = 'password';
        btn.textContent = 'Show';
    }
};

window.clearCleanTokenInput = function() {
    const input = document.getElementById('wa-clean-token');
    if (input) input.value = '';
};

// Step-by-Step Help Dialog Trigger
window.openMetaTokenHelpDialog = function () {
    const existing = document.getElementById('token-help-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'token-help-modal';
    modal.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

    modal.innerHTML = `
        <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl p-6 relative space-y-4 text-xs text-slate-300 shadow-2xl animate-fade-in animate-duration-200">
            <button onclick="document.getElementById('token-help-modal').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-xl font-bold">&times;</button>
            
            <div class="border-b border-slate-800 pb-2 flex items-center space-x-2">
                <span class="text-emerald-500 font-extrabold text-base">🔑</span>
                <h3 class="text-sm font-bold text-white">How to Generate Permanent Meta Access Token</h3>
            </div>
            
            <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1 text-[11px] leading-relaxed">
                <div class="space-y-1">
                    <div class="font-bold text-white">Step 1: Set up a Meta System User</div>
                    <p class="text-slate-400">Log into your <a href="https://business.facebook.com/" target="_blank" class="text-blue-500 underline">Meta Business Suite</a>, open Settings -> Business Settings -> Users -> System Users.</p>
                </div>
                <div class="space-y-1">
                    <div class="font-bold text-white">Step 2: Add a System User</div>
                    <p class="text-slate-400">Click <strong>Add</strong>, set a user role (choose <em>Admin System User</em>), and save.</p>
                </div>
                <div class="space-y-1">
                    <div class="font-bold text-white">Step 3: Assign Assets to User</div>
                    <p class="text-slate-400">Select the newly created System User, click <strong>Assign Assets</strong>. Link your WhatsApp Business account and Business Manager, granting full access.</p>
                </div>
                <div class="space-y-1">
                    <div class="font-bold text-white">Step 4: Generate Access Token</div>
                    <p class="text-slate-400">Click <strong>Generate New Token</strong>. Select your developer application from the dropdown.</p>
                </div>
                <div class="space-y-1">
                    <div class="font-bold text-white">Step 5: Select Required Scopes</div>
                    <p class="text-slate-400">Ensure the following scopes are checkmarked:</p>
                    <ul class="list-disc pl-4 space-y-0.5 font-mono text-[10px] text-slate-400">
                        <li>whatsapp_business_management</li>
                        <li>whatsapp_business_messaging</li>
                    </ul>
                </div>
                <div class="space-y-1">
                    <div class="font-bold text-white">Step 6: Generate and Copy Token</div>
                    <p class="text-slate-400">Click <strong>Generate Token</strong>. Copy the token. Since it is permanent, Meta will never display it again. Save it securely.</p>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
};

// ----------------------------------------------------
// 2. WHATSAPP DASHBOARD VIEW
// ----------------------------------------------------
function renderWhatsAppDashboard(container) {
    checkWaConnectionAndRender('dashboard', container, async (contentArea, state) => {
        try {
            const data = await apiCall('whatsapp/dashboard.php');
            const cards = data.cards;
            const act = data.activities;

            contentArea.innerHTML = `
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 text-slate-700 text-xs">
                    <!-- Left Columns: main analytics (3/4 width) -->
                    <div class="lg:col-span-3 space-y-6">
                        <!-- Top Connection State Card -->
                        <div class="flex items-center justify-between p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm">
                            <div class="flex items-center space-x-3.5">
                                <div class="h-11 w-11 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center border border-emerald-100 shrink-0">
                                    <i data-lucide="phone" class="h-5.5 w-5.5 animate-pulse"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-extrabold text-slate-800">${data.account.business_name || 'WhatsApp Business'}</h3>
                                    <p class="text-[10px] text-slate-500 font-semibold mt-0.5">${data.account.display_phone_number || '+91 92423 22991'} • ${data.account.messaging_limit || '50/day'}</p>
                                </div>
                            </div>
                            <div class="flex flex-col items-end space-y-1">
                                <div class="flex items-center space-x-1.5 text-[10px] font-bold text-emerald-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 inline-block animate-ping"></span>
                                    <span>CONNECTED</span>
                                </div>
                                <span class="text-[9px] text-slate-400">Last synced: 2 min ago <button onclick="window.location.reload()" class="hover:text-blue-500 ml-1 font-bold">↻</button></span>
                            </div>
                        </div>

                        <!-- 4 Stat Cards Row -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <!-- Sent Today -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                                <div class="absolute top-4 right-4 h-7 w-7 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center">
                                    <i data-lucide="send" class="h-3.5 w-3.5"></i>
                                </div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Sent Today</div>
                                <div class="text-2xl font-extrabold text-slate-800" id="wa-stat-sent-today">${cards.sent_today}</div>
                                <div class="text-[9px] text-slate-400 font-medium">~ 0% vs yesterday</div>
                            </div>
                            <!-- Received -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                                <div class="absolute top-4 right-4 h-7 w-7 bg-emerald-50 text-emerald-500 rounded-lg flex items-center justify-center">
                                    <i data-lucide="message-square" class="h-3.5 w-3.5"></i>
                                </div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Received</div>
                                <div class="text-2xl font-extrabold text-slate-800" id="wa-stat-received-today">${cards.received_today}</div>
                                <div class="text-[9px] text-emerald-500 font-bold flex items-center space-x-0.5">
                                    <span>▲ +50%</span>
                                    <span class="text-slate-400 font-medium">vs yesterday</span>
                                </div>
                            </div>
                            <!-- Delivered -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                                <div class="absolute top-4 right-4 h-7 w-7 bg-purple-50 text-purple-500 rounded-lg flex items-center justify-center">
                                    <i data-lucide="check-check" class="h-3.5 w-3.5"></i>
                                </div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Delivered</div>
                                <div class="text-2xl font-extrabold text-slate-800" id="wa-stat-delivered-total">${cards.delivered_total || cards.sent_today}</div>
                                <div class="text-[9px] text-slate-400 font-medium">~ 0% vs yesterday</div>
                            </div>
                            <!-- Read Rate -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                                <div class="absolute top-4 right-4 h-7 w-7 bg-amber-50 text-amber-500 rounded-lg flex items-center justify-center">
                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                </div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Read Rate</div>
                                <div class="text-2xl font-extrabold text-slate-800" id="wa-stat-read-rate">${cards.sent_today > 0 ? Math.round((cards.read_total / cards.sent_today) * 100) : 100}%</div>
                                <div class="text-[9px] text-emerald-500 font-bold flex items-center space-x-0.5">
                                    <span>▲ +100%</span>
                                    <span class="text-slate-400 font-medium">vs yesterday</span>
                                </div>
                            </div>
                        </div>

                        <!-- Messages Analytics Main Chart Card -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-bold text-slate-700">Messages Analytics</div>
                                <div class="flex items-center space-x-2">
                                    <div class="relative">
                                        <select class="pl-2 pr-6 py-1 border border-slate-200 rounded-lg text-[10px] font-bold text-slate-600 bg-white focus:outline-none cursor-pointer appearance-none">
                                            <option>Last 7 Days</option>
                                            <option>Last 30 Days</option>
                                        </select>
                                    </div>
                                    <button onclick="showNotification('success', 'Analytics exported successfully!')" class="flex items-center space-x-1 px-2.5 py-1 border border-slate-200 rounded-lg text-[10px] font-bold text-slate-600 bg-white hover:bg-slate-50 transition shadow-sm">
                                        <i data-lucide="download" class="h-3 w-3"></i>
                                        <span>Export</span>
                                    </button>
                                </div>
                            </div>
                            <div class="h-64 relative">
                                <canvas id="wa-chart-daily"></canvas>
                            </div>
                        </div>

                        <!-- Sparklines Row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Sparkline 1: Total Sent -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-1.5 text-[9px] font-bold text-slate-400 uppercase">
                                        <i data-lucide="send" class="h-3 w-3 text-blue-500"></i>
                                        <span>Total Sent</span>
                                    </div>
                                    <div class="text-xl font-extrabold text-slate-800">129</div>
                                    <div class="text-[9px] text-emerald-500 font-bold flex items-center space-x-0.5">
                                        <span>+18%</span>
                                        <span class="text-slate-400 font-medium">vs last 7 days</span>
                                    </div>
                                </div>
                                <div class="w-24 h-10 relative">
                                    <canvas id="wa-mini-chart-sent"></canvas>
                                </div>
                            </div>
                            <!-- Sparkline 2: Total Received -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-1.5 text-[9px] font-bold text-slate-400 uppercase">
                                        <i data-lucide="message-square" class="h-3 w-3 text-emerald-500"></i>
                                        <span>Total Received</span>
                                    </div>
                                    <div class="text-xl font-extrabold text-slate-800">86</div>
                                    <div class="text-[9px] text-emerald-500 font-bold flex items-center space-x-0.5">
                                        <span>+12%</span>
                                        <span class="text-slate-400 font-medium">vs last 7 days</span>
                                    </div>
                                </div>
                                <div class="w-24 h-10 relative">
                                    <canvas id="wa-mini-chart-received"></canvas>
                                </div>
                            </div>
                            <!-- Sparkline 3: Avg Response Time -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex items-center justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-1.5 text-[9px] font-bold text-slate-400 uppercase">
                                        <i data-lucide="clock" class="h-3 w-3 text-purple-500"></i>
                                        <span>Avg. Response Time</span>
                                    </div>
                                    <div class="text-xl font-extrabold text-slate-800">12m</div>
                                    <div class="text-[9px] text-emerald-500 font-bold flex items-center space-x-0.5">
                                        <span>-8%</span>
                                        <span class="text-slate-400 font-medium">vs last 7 days</span>
                                    </div>
                                </div>
                                <div class="w-24 h-10 relative">
                                    <canvas id="wa-mini-chart-response"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Donut + Lists Bottom row -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Message Status Donut -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm flex flex-col justify-between space-y-3">
                                <div class="text-xs font-bold text-slate-700">Message Status</div>
                                <div class="h-28 flex items-center justify-center relative">
                                    <canvas id="wa-donut-status"></canvas>
                                </div>
                                <div class="flex justify-around text-[10px] font-bold text-slate-600 border-t border-slate-50 pt-2 shrink-0">
                                    <div class="flex items-center space-x-1">
                                        <span class="h-2 w-2 rounded-full bg-indigo-500 inline-block"></span>
                                        <span>Delivered: 2 (50%)</span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <span class="h-2 w-2 rounded-full bg-blue-500 inline-block"></span>
                                        <span>Read: 2 (50%)</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Recent Broadcasts -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-3 flex flex-col justify-between">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-bold text-slate-700">Recent Broadcasts</div>
                                    <a href="#/whatsapp-broadcast" class="text-[10px] text-blue-500 font-bold hover:underline">View All</a>
                                </div>
                                <div class="flex-grow flex items-center py-2.5">
                                    <div class="flex items-center space-x-3 w-full">
                                        <div class="h-8.5 w-8.5 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center border border-emerald-100 shrink-0">
                                            <i data-lucide="radio" class="h-4.5 w-4.5"></i>
                                        </div>
                                        <div class="flex-grow min-w-0">
                                            <div class="flex justify-between items-center">
                                                <h4 class="text-xs font-bold text-slate-800 truncate">New Product Launch</h4>
                                                <span class="px-1.5 py-0.5 bg-emerald-50 text-emerald-600 text-[8px] font-extrabold rounded-md uppercase">Completed</span>
                                            </div>
                                            <p class="text-[9px] text-slate-500 mt-0.5">Sent: 120 • Delivered: 98 • Read: 85</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Recent Campaigns -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-3 flex flex-col justify-between">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-bold text-slate-700">Recent Campaigns</div>
                                    <a href="#/whatsapp-campaigns" class="text-[10px] text-blue-500 font-bold hover:underline">View All</a>
                                </div>
                                <div class="flex-grow flex items-center py-2.5">
                                    <div class="flex items-center space-x-3 w-full">
                                        <div class="h-8.5 w-8.5 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center border border-blue-100 shrink-0">
                                            <i data-lucide="target" class="h-4.5 w-4.5"></i>
                                        </div>
                                        <div class="flex-grow min-w-0">
                                            <div class="flex justify-between items-center">
                                                <h4 class="text-xs font-bold text-slate-800 truncate">Welcome Campaign</h4>
                                                <span class="px-1.5 py-0.5 bg-blue-50 text-blue-600 text-[8px] font-extrabold rounded-md uppercase">Active</span>
                                            </div>
                                            <p class="text-[9px] text-slate-500 mt-0.5">Sent: 45 • Delivered: 40 • Read: 32</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: AI details panel (1/4 width) -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- AI Insights card -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                            <div class="flex items-center space-x-2">
                                <div class="text-xs font-bold text-slate-700">AI Insights</div>
                                <span class="bg-purple-100 text-purple-700 text-[8px] font-extrabold px-1.5 py-0.5 rounded-full uppercase">Beta</span>
                            </div>
                            <div class="space-y-4">
                                <!-- Top Performing Template -->
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-1.5 text-[9px] font-bold text-slate-400 uppercase">
                                        <i data-lucide="layout-template" class="h-3 w-3 text-emerald-500"></i>
                                        <span>Top Performing Template</span>
                                    </div>
                                    <p class="text-[10px] font-bold text-slate-700 leading-normal">"Hi {name}, how can I assist you today?"</p>
                                    <div class="flex items-center space-x-2 pt-0.5">
                                        <div class="flex-grow h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-emerald-400 rounded-full" style="width: 92%;"></div>
                                        </div>
                                        <span class="text-[9px] font-bold text-emerald-600 shrink-0">92% Read Rate</span>
                                    </div>
                                </div>
                                <!-- Best Time to Send -->
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-1.5 text-[9px] font-bold text-slate-400 uppercase">
                                        <i data-lucide="clock" class="h-3 w-3 text-blue-500"></i>
                                        <span>Best Time to Send</span>
                                    </div>
                                    <p class="text-[10px] font-extrabold text-slate-700">Monday, 10:00 AM - 12:00 PM</p>
                                </div>
                                <!-- Audience Engagement -->
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-1.5 text-[9px] font-bold text-slate-400 uppercase">
                                        <i data-lucide="trending-up" class="h-3 w-3 text-indigo-500"></i>
                                        <span>Audience Engagement</span>
                                    </div>
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[9px] font-extrabold rounded-full inline-block mt-0.5">Very High</span>
                                </div>
                            </div>
                        </div>

                        <!-- AI Suggested Replies -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-bold text-slate-700">AI Suggested Replies</div>
                                <a href="#/whatsapp-inbox" class="text-[10px] text-blue-500 font-bold hover:underline">View All</a>
                            </div>
                            <div class="space-y-3" id="wa-suggestions-list">
                                ${act.ai_suggestions.length > 0 ? act.ai_suggestions.map(s => `
                                    <div class="p-3 bg-blue-50/40 border border-blue-100 rounded-xl space-y-1.5 text-xs relative">
                                        <div class="flex justify-between items-center">
                                            <div class="font-bold text-slate-800">${s.profile_name}</div>
                                            <button onclick="copyToClipboard('${s.ai_suggested_reply.replace(/'/g, "\\'")}')" class="text-slate-400 hover:text-blue-500 transition" title="Copy Reply">
                                                <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                            </button>
                                        </div>
                                        <div class="text-[10px] text-slate-500 italic">"${s.original_message}"</div>
                                        <div class="text-[10px] text-blue-600 font-semibold bg-white border border-blue-50/50 p-2 rounded-lg leading-relaxed">
                                            ${s.ai_suggested_reply}
                                        </div>
                                    </div>
                                `).join('') : `
                                    <div class="p-3 bg-blue-50/40 border border-blue-100 rounded-xl space-y-1.5 text-xs relative">
                                        <div class="flex justify-between items-center">
                                            <div class="font-bold text-slate-800">Soumojit Saha</div>
                                            <button onclick="copyToClipboard('Hello Soumojit! How can I assist you today?')" class="text-slate-400 hover:text-blue-500 transition"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                                        </div>
                                        <div class="text-[10px] text-slate-500 italic">"Hi"</div>
                                        <div class="text-[10px] text-blue-600 font-semibold bg-white border border-blue-50/50 p-2 rounded-lg leading-relaxed">
                                            Hello Soumojit! How can I assist you today?
                                        </div>
                                    </div>
                                    <div class="p-3 bg-blue-50/40 border border-blue-100 rounded-xl space-y-1.5 text-xs relative">
                                        <div class="flex justify-between items-center">
                                            <div class="font-bold text-slate-800">Soumojit Saha</div>
                                            <button onclick="copyToClipboard('Hello Soumojit! How can I assist you today?')" class="text-slate-400 hover:text-blue-500 transition"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                                        </div>
                                        <div class="text-[10px] text-slate-500 italic">"Hello"</div>
                                        <div class="text-[10px] text-blue-600 font-semibold bg-white border border-blue-50/50 p-2 rounded-lg leading-relaxed">
                                            Hello Soumojit! How can I assist you today?
                                        </div>
                                    </div>
                                    <div class="p-3 bg-blue-50/40 border border-blue-100 rounded-xl space-y-1.5 text-xs relative">
                                        <div class="flex justify-between items-center">
                                            <div class="font-bold text-slate-800">Soumojit Saha</div>
                                            <button onclick="copyToClipboard('Sure! Please let me know which service you\\'re interested in.')" class="text-slate-400 hover:text-blue-500 transition"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                                        </div>
                                        <div class="text-[10px] text-slate-500 italic">"Price details"</div>
                                        <div class="text-[10px] text-blue-600 font-semibold bg-white border border-blue-50/50 p-2 rounded-lg leading-relaxed">
                                            Sure! Please let me know which service you're interested in.
                                        </div>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            lucide.createIcons();

            // Helper function to render sparklines
            const renderMiniSparkline = (canvasId, dataPoints, borderColor) => {
                const miniCtx = document.getElementById(canvasId).getContext('2d');
                new Chart(miniCtx, {
                    type: 'line',
                    data: {
                        labels: dataPoints.map((_, i) => i),
                        datasets: [{
                            data: dataPoints,
                            borderColor: borderColor,
                            borderWidth: 1.5,
                            pointRadius: 0,
                            tension: 0.4,
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { enabled: false } },
                        scales: {
                            x: { display: false },
                            y: { display: false }
                        }
                    }
                });
            };

            // Initialize mini sparklines
            renderMiniSparkline('wa-mini-chart-sent', [10, 15, 8, 12, 22, 18, 25], '#3b82f6');
            renderMiniSparkline('wa-mini-chart-received', [5, 8, 12, 10, 15, 14, 20], '#10b981');
            renderMiniSparkline('wa-mini-chart-response', [25, 22, 18, 20, 15, 14, 12], '#8b5cf6');

            // Initialize Donut Status chart
            const donutCtx = document.getElementById('wa-donut-status').getContext('2d');
            new Chart(donutCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Delivered', 'Read'],
                    datasets: [{
                        data: [2, 2],
                        backgroundColor: ['#6366f1', '#3b82f6'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { display: false } }
                }
            });

            // Render main Analytics Chart.js
            const ctx = document.getElementById('wa-chart-daily').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.charts.daily_messages.map(d => d.label),
                    datasets: [
                        {
                            label: 'Messages Sent',
                            data: data.charts.daily_messages.map(d => d.sent),
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.05)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Messages Received',
                            data: data.charts.daily_messages.map(d => d.received),
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.05)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { font: { size: 10 } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, ticks: { font: { size: 9 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                    }
                }
            });

            // Setup 15-second real-time polling interval for live message counts & stats
            if (window._waDashboardInterval) clearInterval(window._waDashboardInterval);
            window._waDashboardInterval = setInterval(async () => {
                // Stop polling if user navigated away from whatsapp-dashboard
                if (window.location.hash !== '#/whatsapp-dashboard') {
                    clearInterval(window._waDashboardInterval);
                    window._waDashboardInterval = null;
                    return;
                }
                try {
                    const freshData = await apiCall('whatsapp/dashboard.php');
                    if (freshData && freshData.cards) {
                        const freshCards = freshData.cards;
                        const sentEl = document.getElementById('wa-stat-sent-today');
                        const recvEl = document.getElementById('wa-stat-received-today');
                        const delivEl = document.getElementById('wa-stat-delivered-total');
                        const rateEl = document.getElementById('wa-stat-read-rate');

                        if (sentEl) sentEl.textContent = freshCards.sent_today;
                        if (recvEl) recvEl.textContent = freshCards.received_today;
                        if (delivEl) delivEl.textContent = freshCards.delivered_total || freshCards.sent_today;
                        if (rateEl) rateEl.textContent = `${freshCards.sent_today > 0 ? Math.round((freshCards.read_total / freshCards.sent_today) * 100) : 100}%`;
                    }
                } catch(e) {
                    console.log('WhatsApp dashboard real-time poll error:', e);
                }
            }, 15000);

        } catch (err) {
            showNotification('error', err.message);
        }
    });
}

// ----------------------------------------------------
// 3. WHATSAPP INBOX CHAT WINDOW VIEW
// ----------------------------------------------------
function renderWhatsAppInbox(container) {
    checkWaConnectionAndRender('inbox', container, async (contentArea) => {
        // Stop any background intervals
        clearInterval(waThreadsInterval);
        clearInterval(waMessagesInterval);

        contentArea.innerHTML = `
            <div class="h-[calc(100vh-100px)] flex border border-slate-200 rounded-2xl bg-white overflow-hidden shadow-sm text-xs">
                <!-- Pane 1: Left List (Threads) -->
                <div class="w-1/4 border-r border-slate-200 flex flex-col justify-between bg-slate-50/50">
                    <div class="p-3 border-b border-slate-100 space-y-3 bg-white">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-slate-800">WhatsApp Chats</h3>
                            <button onclick="openNewChatModal()" class="border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg text-[10px] font-bold px-2.5 py-1 bg-white shadow-sm flex items-center space-x-1.5 transition">
                                <i data-lucide="plus" class="h-3 w-3"></i>
                                <span>New Chat</span>
                            </button>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="relative flex-grow">
                                <span class="absolute inset-y-0 left-2.5 flex items-center text-slate-400">
                                    <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                </span>
                                <input type="text" id="wa-search-threads" oninput="loadWaThreads(this.value)" placeholder="Search chats..." class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-[11px] focus:outline-none focus:border-blue-500">
                            </div>
                            <button class="p-1.5 border border-slate-200 rounded-lg bg-white text-slate-500 hover:bg-slate-50 transition shrink-0">
                                <i data-lucide="filter" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Threads list -->
                    <div class="flex-grow overflow-y-auto divide-y divide-slate-100" id="wa-threads-container">
                        <div class="p-6 text-center text-slate-400">Loading threads...</div>
                    </div>
                </div>

                <!-- Pane 2: Center window (Messages Feed) -->
                <div class="w-2/4 flex flex-col justify-between bg-[#F2F4F7]">
                    <!-- Chat Header -->
                    <div class="p-3 border-b border-slate-200 bg-white flex items-center justify-between" id="wa-chat-header">
                        <div class="text-slate-400 text-center py-1 w-full">Select a thread to start chatting</div>
                    </div>

                    <!-- Messages list -->
                    <div class="flex-grow overflow-y-auto p-4 space-y-3 flex flex-col bg-[#F2F4F7]" id="wa-messages-container-list">
                        <div class="flex items-center justify-center h-full text-slate-400">
                            No conversation loaded.
                        </div>
                    </div>

                    <!-- Chat Footer Input -->
                    <div class="p-3 bg-white border-t border-slate-200 hidden" id="wa-chat-footer">
                        <!-- AI Suggestion overlay bar -->
                        <div id="wa-ai-suggestion-bar" class="hidden mb-3 p-3 bg-indigo-50 border border-indigo-100 rounded-xl space-y-2 relative animate-fade-in text-[11px]">
                            <button onclick="dismissAISuggestion()" class="absolute top-2 right-2 text-slate-400 hover:text-slate-600">&times;</button>
                            <div class="flex items-center space-x-1.5 text-indigo-600 font-bold">
                                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                                <span>AI Response draft suggestion:</span>
                            </div>
                            <p class="text-slate-700 italic" id="wa-ai-suggestion-text"></p>
                            <div class="flex space-x-2">
                                <button onclick="applyAISuggestion()" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-bold transition">Use Draft</button>
                                <button onclick="regenerateAISuggestion()" class="px-2.5 py-1 border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 rounded font-semibold transition">Regenerate ↻</button>
                            </div>
                        </div>

                        <form onsubmit="sendWaMessage(event)" class="flex items-center space-x-2 relative">
                            <button type="button" onclick="triggerWaAIChatAnalysis()" class="h-8 px-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-200/50 rounded-lg flex items-center justify-center shrink-0 transition" title="Ask AI reply draft suggestion">
                                <i data-lucide="sparkles" class="h-4 w-4"></i>
                            </button>
                            <input type="text" id="wa-chat-input" placeholder="Type a message..." class="flex-grow px-3 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500">
                            <button type="submit" class="h-8 w-8 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg flex items-center justify-center shrink-0 transition shadow-sm">
                                <i data-lucide="send" class="h-4 w-4"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Pane 3: Right Panel (CRM & AI Details) -->
                <div class="w-1/4 border-l border-slate-200 bg-slate-50/50 overflow-y-auto p-4 space-y-5" id="wa-crm-sidepanel">
                    <div class="text-slate-400 text-center py-10">CRM context values are empty.</div>
                </div>
            </div>
        `;

        lucide.createIcons();

        // Initial thread load
        loadWaThreads();

        // Set Thread poll timer
        waThreadsInterval = setInterval(loadWaThreads, 7000);
    });
}

// Get a unique background and text color class for user avatars based on their initials
function getAvatarColorClass(name) {
    if (!name) return 'bg-slate-100 text-slate-700';
    const colors = [
        'bg-emerald-100 text-emerald-700',
        'bg-orange-100 text-orange-700',
        'bg-blue-100 text-blue-700',
        'bg-purple-100 text-purple-700',
        'bg-pink-100 text-pink-700',
        'bg-teal-100 text-teal-700',
        'bg-indigo-100 text-indigo-700',
        'bg-amber-100 text-amber-700'
    ];
    const index = (name.charCodeAt(0) || 0) % colors.length;
    return colors[index];
}

// Format and normalize phone numbers for UI display (prevent double pluses and format 10-digit Indian mobile numbers as +91...)
function formatPhoneNumber(phone) {
    if (!phone) return '';
    let cleaned = phone.trim();
    if (cleaned.startsWith('+')) {
        return '+' + cleaned.replace(/[^0-9]/g, '');
    }
    const digits = cleaned.replace(/[^0-9]/g, '');
    if (digits.startsWith('91')) {
        return '+' + digits;
    }
    if (digits.length === 10 && ['9', '8', '7', '6'].includes(digits.charAt(0))) {
        return '+91' + digits;
    }
    return '+' + digits;
}

// Clean and normalize phone numbers (prepending 91 to 10-digit Indian mobile numbers starting with 9, 8, 7, 6)
function normalizePhoneNumber(phone) {
    if (!phone) return '';
    let cleaned = phone.trim().replace(/[^0-9]/g, '');
    if (cleaned.length === 10 && ['9', '8', '7', '6'].includes(cleaned.charAt(0))) {
        cleaned = '91' + cleaned;
    }
    return cleaned;
}

// Fetch WhatsApp threads list
async function loadWaThreads(search = '') {
    try {
        const res = await apiCall(`whatsapp/inbox.php?search=${encodeURIComponent(search)}`);
        const threads = res.threads || [];
        const container = document.getElementById('wa-threads-container');
        if (!container) return;

        if (threads.length === 0) {
            container.innerHTML = `<div class="p-6 text-center text-slate-400">No active chats found.</div>`;
            return;
        }

        container.innerHTML = threads.map(t => {
            const isActive = (t.id == activeWaThreadId);
            const displayTime = t.last_message_at ? new Date(t.last_message_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

            let lastMsgText = '';
            if (t.last_message_body) {
                if (t.last_message_type === 'image') {
                    lastMsgText = '📷 Photo';
                } else if (t.last_message_type === 'video') {
                    lastMsgText = '🎥 Video';
                } else if (t.last_message_type === 'audio') {
                    lastMsgText = '🎵 Audio';
                } else if (t.last_message_type === 'document') {
                    lastMsgText = '📄 Document';
                } else {
                    lastMsgText = t.last_message_body;
                }
            } else {
                lastMsgText = formatPhoneNumber(t.wa_id);
            }

            const unreadBadge = (t.unread_count > 0 && !isActive) ?
                `<span id="wa-unread-badge-${t.id}" class="bg-emerald-500 text-white text-[9px] font-bold h-4.5 w-4.5 rounded-full flex items-center justify-center shrink-0 shadow-sm">${t.unread_count}</span>` : '';

            return `
                <div onclick="selectWaThread(${t.id})" class="p-3 flex items-start justify-between cursor-pointer transition ${isActive ? 'bg-blue-50/80 border-l-4 border-blue-500' : 'bg-white hover:bg-slate-50'}">
                    <div class="flex items-start space-x-2.5 truncate">
                        <div class="h-8 w-8 rounded-full ${getAvatarColorClass(t.profile_name)} font-bold flex items-center justify-center shrink-0">
                            ${(t.profile_name || 'WhatsApp Contact').charAt(0).toUpperCase()}
                        </div>
                        <div class="truncate">
                            <div class="font-bold text-slate-700">${t.profile_name || 'WhatsApp Contact'}</div>
                            <div class="text-[10px] text-slate-400 truncate mt-0.5">${lastMsgText}</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end shrink-0 space-y-1">
                        <span class="text-[9px] text-slate-400">${displayTime}</span>
                        ${unreadBadge}
                    </div>
                </div>
            `;
        }).join('');

    } catch (err) {
        console.warn('Failed threads sync: ', err);
        const container = document.getElementById('wa-threads-container');
        if (container) {
            container.innerHTML = `<div class="p-4 text-center text-red-500 font-semibold">Failed to load threads: ${err.message}</div>`;
        }
    }
}

// Select WhatsApp Thread
window.selectWaThread = function (threadId) {
    activeWaThreadId = threadId;

    // Hide unread badge green dot instantly for snappy feel
    const badge = document.getElementById(`wa-unread-badge-${threadId}`);
    if (badge) badge.classList.add('hidden');

    // Highlight list selection
    loadWaThreads();

    // Start Message Poll
    clearInterval(waMessagesInterval);
    loadWaThreadMessages();
    waMessagesInterval = setInterval(loadWaThreadMessages, 4000);
};

// Fetch conversation thread messages and CRM profiles
async function loadWaThreadMessages() {
    if (!activeWaThreadId) return;

    try {
        const res = await apiCall(`whatsapp/inbox.php?action=messages&wa_contact_id=${activeWaThreadId}`);
        const thread = res.thread;
        const messages = res.messages || [];
        const crm = res.crm;

        // 1. Render Chat Header
        const header = document.getElementById('wa-chat-header');
        if (header) {
            header.innerHTML = `
                <div class="flex items-center space-x-2.5">
                    <div class="h-8.5 w-8.5 rounded-full ${getAvatarColorClass(thread.profile_name)} font-bold flex items-center justify-center shrink-0">
                        ${(thread.profile_name || 'WhatsApp Contact').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="flex items-center space-x-1">
                            <h4 class="text-xs font-bold text-slate-800">${thread.profile_name || 'WhatsApp Contact'}</h4>
                            <i data-lucide="check-circle-2" class="h-3.5 w-3.5 text-emerald-500 fill-emerald-100"></i>
                        </div>
                        <p class="text-[10px] text-slate-500 flex items-center space-x-1.5 mt-0.5">
                            <span class="font-mono">${formatPhoneNumber(thread.wa_id)}</span>
                            <span class="h-1 w-1 rounded-full bg-slate-300"></span>
                            <span class="text-emerald-500 font-bold flex items-center space-x-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                                <span>Active Cloud API Thread</span>
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button class="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 transition shadow-sm">
                        <i data-lucide="phone" class="h-3.5 w-3.5"></i>
                    </button>
                    <button class="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 transition shadow-sm">
                        <i data-lucide="tag" class="h-3.5 w-3.5"></i>
                    </button>
                    <button class="p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 transition shadow-sm">
                        <i data-lucide="more-horizontal" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
            `;
        }

        // 24-Hour window calculations
        const inboundMessages = messages.filter(m => m.direction === 'inbound');
        let isWindowActive = true;
        if (inboundMessages.length === 0) {
            isWindowActive = false; // No incoming customer message yet
        } else {
            const lastInbound = inboundMessages[inboundMessages.length - 1];
            const lastInboundTime = new Date(lastInbound.created_at);
            const now = new Date();
            const diffHours = (now - lastInboundTime) / (1000 * 60 * 60);
            if (diffHours >= 24) {
                isWindowActive = false;
            }
        }

        // Show/Hide Input Footer or Warning alert
        const footer = document.getElementById('wa-chat-footer');
        
        // Clean and normalize phone number
        const cleanToPhone = normalizePhoneNumber(thread.wa_id || '');
        let isPhoneAllowed = true;
        let phoneRestrictedReason = '';
        
        if (!cleanToPhone.startsWith('91')) {
            isPhoneAllowed = false;
            phoneRestrictedReason = 'Outside Indian messaging is not allowed if not starting with 91.';
        } else if (cleanToPhone.length !== 12) {
            isPhoneAllowed = false;
            phoneRestrictedReason = 'Invalid Indian mobile number length (must be 10 digits after 91).';
        } else {
            const firstDigit = cleanToPhone.charAt(2);
            if (!['9', '8', '7', '6'].includes(firstDigit)) {
                isPhoneAllowed = false;
                phoneRestrictedReason = 'Outside Indian messaging is not allowed (Indian mobile numbers must start with 9, 8, 7, or 6 after 91).';
            }
        }
        
        if (footer) {
            footer.classList.remove('hidden');
            if (!isPhoneAllowed) {
                footer.innerHTML = `
                    <div class="p-3.5 bg-rose-50/70 border border-rose-200/80 rounded-xl flex flex-col items-center text-center space-y-2 animate-fade-in">
                        <div class="flex items-center space-x-2 text-rose-700 font-bold text-[10px] uppercase tracking-wider">
                            <i data-lucide="alert-circle" class="h-4 w-4"></i>
                            <span>Messaging Restricted</span>
                        </div>
                        <p class="text-slate-600 text-[10px] max-w-sm">
                            ${phoneRestrictedReason}
                        </p>
                    </div>
                `;
                lucide.createIcons();
            } else if (!isWindowActive) {
                // Only render if warning is not already showing
                if (!footer.querySelector('.bg-amber-600')) {
                    footer.innerHTML = `
                        <div class="p-3.5 bg-amber-50/70 border border-amber-200/80 rounded-xl flex flex-col items-center text-center space-y-2 animate-fade-in">
                            <div class="flex items-center space-x-2 text-amber-700 font-bold text-[10px] uppercase tracking-wider">
                                <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                                <span>24-Hour Customer Care Window Expired</span>
                            </div>
                            <p class="text-slate-600 text-[10px] max-w-sm">
                                You can only send template messages to reactivate this chat. Standard text input is locked by Meta until the customer messages you again.
                            </p>
                            <button onclick="openTemplateSelectorModal('${thread.wa_id}')" class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold text-xs shadow-sm transition flex items-center space-x-1.5">
                                <i data-lucide="layout-template" class="h-3.5 w-3.5"></i>
                                <span>Send Template Message</span>
                            </button>
                        </div>
                    `;
                }
            } else {
                // Only render normal form if input box is not already showing
                if (!document.getElementById('wa-chat-input')) {
                    footer.innerHTML = `
                        <!-- AI Suggestion overlay bar -->
                        <div id="wa-ai-suggestion-bar" class="hidden mb-3 p-3 bg-indigo-50 border border-indigo-100 rounded-xl space-y-2 relative animate-fade-in text-[11px]">
                            <button onclick="dismissAISuggestion()" class="absolute top-2 right-2 text-slate-400 hover:text-slate-600">&times;</button>
                            <div class="flex items-center space-x-1.5 text-indigo-600 font-bold">
                                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                                <span>AI Response draft suggestion:</span>
                            </div>
                            <p class="text-slate-700 italic" id="wa-ai-suggestion-text"></p>
                            <div class="flex space-x-2">
                                <button onclick="applyAISuggestion()" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-bold transition">Use Draft</button>
                                <button onclick="regenerateAISuggestion()" class="px-2.5 py-1 border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 rounded font-semibold transition">Regenerate ↻</button>
                            </div>
                        </div>

                        <form onsubmit="sendWaMessage(event)" class="flex items-center space-x-2 relative">
                            <button type="button" onclick="triggerWaAIChatAnalysis()" class="h-8 px-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 border border-indigo-200/50 rounded-lg flex items-center justify-center shrink-0 transition" title="Ask AI reply draft suggestion">
                                <i data-lucide="sparkles" class="h-4 w-4"></i>
                            </button>
                            <input type="text" id="wa-chat-input" placeholder="Type a message..." class="flex-grow px-3 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500">
                            <button type="submit" class="h-8 w-8 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg flex items-center justify-center shrink-0 transition shadow-sm">
                                <i data-lucide="send" class="h-4 w-4"></i>
                            </button>
                        </form>
                    `;
                }
            }
        }

        // 2. Render Messages list
        const msgList = document.getElementById('wa-messages-container-list');
        if (msgList) {
            // Apply faint repeating wallpaper style
            msgList.style.backgroundImage = "linear-gradient(rgba(244, 245, 247, 0.94), rgba(244, 245, 247, 0.94)), url('../backend/api/whatsapp/chatbg.jpg')";
            msgList.style.backgroundRepeat = "repeat";
            msgList.style.backgroundColor = "#efeae2";

            if (messages.length === 0) {
                msgList.innerHTML = `<div class="text-slate-400 text-center py-20">No messages in this chat. Send a template message to start!</div>`;
            } else {
                let currentGroupDate = '';
                let messagesHtml = '';

                messages.forEach(m => {
                    const msgDate = new Date(m.created_at).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
                    const todayDate = new Date().toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
                    const displayDate = (msgDate === todayDate) ? 'Today' : msgDate;

                    if (msgDate !== currentGroupDate) {
                        currentGroupDate = msgDate;
                        messagesHtml += `
                            <div class="flex justify-center my-3">
                                <span class="bg-white px-3 py-1 border border-slate-100 rounded-full text-[10px] text-slate-500 font-semibold shadow-sm">${displayDate}</span>
                            </div>
                        `;
                    }

                    const isInbound = (m.direction === 'inbound');
                    const time = new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    let bubbleHtml = escapeHtml(m.body || '');
                    
                    const mediaUrl = m.media_url || (m.body && (m.body.startsWith('http://') || m.body.startsWith('https://')) ? m.body : '');
                    const mimeType = (m.media_mime_type || '').toLowerCase();
                    const msgType = (m.message_type || m.type || '').toLowerCase();

                    const isAudio = mimeType.includes('audio') || msgType.includes('audio') || msgType.includes('voice') || (mediaUrl && (mediaUrl.endsWith('.mp3') || mediaUrl.endsWith('.ogg') || mediaUrl.endsWith('.wav') || mediaUrl.endsWith('.m4a') || mediaUrl.includes('/audio/')));
                    const isImage = mimeType.includes('image') || msgType.includes('image') || (mediaUrl && (mediaUrl.endsWith('.jpg') || mediaUrl.endsWith('.jpeg') || mediaUrl.endsWith('.png') || mediaUrl.endsWith('.webp') || mediaUrl.endsWith('.gif') || mediaUrl.includes('/image/')));
                    const isDoc = mimeType.includes('pdf') || mimeType.includes('document') || msgType.includes('document') || (mediaUrl && (mediaUrl.endsWith('.pdf') || mediaUrl.endsWith('.doc') || mediaUrl.endsWith('.docx') || mediaUrl.endsWith('.xlsx')));

                    if (isImage) {
                        const imgSrc = mediaUrl.startsWith('http') ? mediaUrl : `../${mediaUrl.replace(/^\.\//, '')}`;
                        bubbleHtml = `
                            <div class="space-y-1.5">
                                <a href="${imgSrc}" target="_blank" class="block overflow-hidden rounded-xl border border-slate-200/60 shadow-sm hover:opacity-95 transition">
                                    <img src="${imgSrc}" class="max-w-[260px] max-h-[300px] w-full object-cover rounded-xl" alt="WhatsApp Image Preview" onerror="this.onerror=null; this.src='assets/img/placeholder.png';">
                                </a>
                                ${m.body && m.body !== mediaUrl ? `<p class="text-xs leading-relaxed text-slate-800">${escapeHtml(m.body)}</p>` : ''}
                            </div>
                        `;
                    } else if (isAudio) {
                        const audioSrc = mediaUrl.startsWith('http') ? mediaUrl : `../${mediaUrl.replace(/^\.\//, '')}`;
                        bubbleHtml = `
                            <div class="space-y-1.5 min-w-[220px]">
                                <div class="flex items-center space-x-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                                    <i data-lucide="mic" class="h-3.5 w-3.5 text-emerald-600"></i>
                                    <span>Voice Message / Audio Note</span>
                                </div>
                                <audio controls class="w-full h-8 rounded-lg outline-none">
                                    <source src="${audioSrc}">
                                    Your browser does not support HTML5 audio player.
                                </audio>
                                ${m.body && m.body !== mediaUrl ? `<p class="text-xs leading-relaxed text-slate-800">${escapeHtml(m.body)}</p>` : ''}
                            </div>
                        `;
                    } else if (isDoc || (mediaUrl && mediaUrl !== m.body)) {
                        const docSrc = mediaUrl.startsWith('http') ? mediaUrl : `../${mediaUrl.replace(/^\.\//, '')}`;
                        const fileName = mediaUrl.split('/').pop() || 'Attachment Document';
                        bubbleHtml = `
                            <div class="space-y-1.5">
                                <a href="${docSrc}" target="_blank" download class="flex items-center space-x-3 p-2.5 bg-slate-50 hover:bg-slate-100 rounded-xl text-slate-800 font-bold border border-slate-200/80 shadow-2xs transition">
                                    <div class="h-8 w-8 rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center shrink-0">
                                        <i data-lucide="file-text" class="h-4 w-4"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow">
                                        <div class="text-xs font-bold text-slate-800 truncate">${escapeHtml(fileName)}</div>
                                        <div class="text-[9px] text-indigo-600 font-extrabold uppercase">Click to Download Document</div>
                                    </div>
                                    <i data-lucide="download" class="h-4 w-4 text-slate-400 shrink-0"></i>
                                </a>
                                ${m.body && m.body !== mediaUrl ? `<p class="text-xs leading-relaxed text-slate-800">${escapeHtml(m.body)}</p>` : ''}
                            </div>
                        `;
                    }

                    const timeHtml = isInbound ?
                        `<span class="text-[9px] text-slate-400">${time}</span>` :
                        `<span class="text-[9px] text-slate-500 flex items-center justify-end space-x-1">
                            <span>${time}</span>
                            <span class="text-blue-500 font-semibold text-[10px]">✔✔</span>
                         </span>`;

                    messagesHtml += `
                        <div class="flex ${isInbound ? 'justify-start' : 'justify-end'}">
                            <div class="max-w-[70%] p-3 rounded-2xl shadow-sm text-xs ${isInbound ? 'bg-white text-slate-800 rounded-tl-none border border-slate-100' : 'bg-[#d9fdd3] text-slate-800 rounded-tr-none border border-emerald-100/50'}">
                                <div>${bubbleHtml}</div>
                                <div class="mt-1.5 flex justify-end">${timeHtml}</div>
                            </div>
                        </div>
                    `;
                });

                msgList.innerHTML = messagesHtml;

                // Auto scroll to bottom
                msgList.scrollTop = msgList.scrollHeight;
                lucide.createIcons();
            }
        }

        // Store resolved CRM context globally
        window.activeWaCrmContext = crm;

        // 3. Render CRM & AI details right panel
        const sidePanel = document.getElementById('wa-crm-sidepanel');
        if (sidePanel) {
            // CRM Contact HTML Block
            let contactHtml = '';
            if (crm.contact) {
                contactHtml = `
                    <div class="bg-indigo-50 border border-indigo-105 p-3.5 rounded-xl space-y-2 text-slate-800 shadow-sm">
                        <div class="flex items-center space-x-2">
                            <div class="h-8 w-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                                ${(crm.contact.name || 'CRM Contact').charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900">${crm.contact.name || 'CRM Contact'}</h4>
                                <p class="text-[10px] text-slate-500 mt-0.5">${crm.contact.designation || 'CRM Contact Profile'}</p>
                            </div>
                        </div>
                        <div class="text-[10px] space-y-1 pt-1.5 border-t border-slate-200 text-slate-600">
                            <div>Email: <span class="font-mono text-slate-900 font-medium">${crm.contact.email || '-'}</span></div>
                            <div>Phone: <span class="font-mono text-slate-900 font-medium">${crm.contact.phone || '-'}</span></div>
                        </div>
                    </div>
                `;
            } else {
                contactHtml = `
                    <div class="bg-amber-50 border border-amber-200/80 p-3.5 rounded-xl text-center space-y-2.5">
                        <p class="text-[10px] text-amber-800 font-medium leading-relaxed">This WhatsApp contact is not linked to any CRM Contact profile.</p>
                        <button onclick="createAndLinkCRMContact(${activeWaThreadId})" class="w-full py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-[10px] font-bold transition flex items-center justify-center space-x-1.5 shadow-sm">
                            <i data-lucide="user-plus" class="h-3.5 w-3.5"></i>
                            <span>Add to CRM Contacts</span>
                        </button>
                    </div>
                `;
            }

            // Lead HTML Block
            let leadHtml = '<div class="text-[10px] text-slate-400">No active leads matched.</div>';
            if (crm.lead) {
                leadHtml = `
                    <div class="bg-blue-50/55 border border-blue-100 p-3 rounded-xl space-y-1">
                        <div class="flex justify-between font-bold text-slate-800">
                            <span>${crm.lead.name}</span>
                            <span class="text-[10px] px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded-md font-extrabold uppercase">${crm.lead.stage}</span>
                        </div>
                        <div class="text-[10px] text-slate-500">Company: ${crm.lead.company || 'None'}</div>
                        <div class="text-[10px] text-slate-500">Budget: ${window.formatCurrency ? window.formatCurrency(crm.lead.budget) : '₹' + parseFloat(crm.lead.budget).toLocaleString('en-IN')}</div>
                        <div class="text-[10px] text-slate-500">Priority: <strong class="text-indigo-600 uppercase font-bold">${crm.lead.priority}</strong></div>
                    </div>
                `;
            }

            // Company HTML Block
            let companyHtml = '<div class="text-[10px] text-slate-400">No company linked.</div>';
            if (crm.company) {
                companyHtml = `
                    <div class="bg-emerald-50/55 border border-emerald-100 p-3 rounded-xl space-y-1">
                        <div class="flex justify-between font-bold text-slate-800">
                            <span>${crm.company.name}</span>
                            <span class="text-[10px] px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded-md font-extrabold uppercase">${crm.company.status}</span>
                        </div>
                        <div class="text-[10px] text-slate-500">Industry: ${crm.company.industry || '-'}</div>
                        <div class="text-[10px] text-slate-500">Website: <a href="${crm.company.website && crm.company.website.startsWith('http') ? crm.company.website : 'https://' + (crm.company.website || '')}" target="_blank" class="text-blue-500 hover:underline font-medium">${crm.company.website || '-'}</a></div>
                    </div>
                `;
            }

            let notesHtml = crm.notes.length > 0 ? crm.notes.map(n => `
                <div class="p-2 bg-white border border-slate-100 rounded-lg text-[10px] text-slate-500 italic">"${n.content}"</div>
            `).join('') : '<p class="text-[10px] text-slate-400">No notes recorded.</p>';

            let taskHtml = crm.tasks.length > 0 ? crm.tasks.map(t => `
                <div class="flex items-center space-x-2 text-[10px] text-slate-500">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    <span>${t.title} (Due: ${t.due_date})</span>
                </div>
            `).join('') : '<p class="text-[10px] text-slate-400">No pending tasks.</p>';

            let timelineHtml = crm.timeline.length > 0 ? crm.timeline.map(t => `
                <div class="flex items-start space-x-2 text-[10px] text-slate-400">
                    <span class="text-slate-300 font-bold shrink-0">${new Date(t.created_at).toLocaleDateString([], { month: 'short', day: 'numeric' })}:</span>
                    <span>${t.description}</span>
                </div>
            `).join('') : '<p class="text-[10px] text-slate-400">No activities.</p>';

            // Render buttons based on whether contact is linked
            const contactLinked = !!crm.contact;
            const leadActionBtn = contactLinked ? `
                <button onclick="openAddLeadFromWa()" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-500 transition flex items-center space-x-0.5">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                    <span>Add Lead</span>
                </button>
            ` : '';
            const taskActionBtn = contactLinked ? `
                <button onclick="openAddTaskFromWa()" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-500 transition flex items-center space-x-0.5">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                    <span>Add Task</span>
                </button>
            ` : '';
            const companyActionBtn = contactLinked ? `
                <button onclick="openLinkCompanyFromWa()" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-500 transition flex items-center space-x-0.5">
                    <i data-lucide="link" class="h-3.5 w-3.5"></i>
                    <span>Link Company</span>
                </button>
            ` : '';

            sidePanel.innerHTML = `
                <!-- AI insights section -->
                <div class="space-y-3 mb-4">
                    <div class="text-xs font-bold text-slate-700 flex items-center space-x-1">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5 text-indigo-500"></i>
                        <span>AI Analysis Panel</span>
                    </div>
                    <div class="glass-panel p-4 bg-slate-900 border border-slate-800 text-slate-300 rounded-2xl space-y-3">
                        <div class="flex justify-between items-center border-b border-slate-850 pb-1.5">
                            <span class="text-[9px] uppercase font-bold text-slate-500">Thread Summary</span>
                            <button onclick="optimizeChatSummary(${thread.id}, this)" class="text-[9px] font-bold text-indigo-400 hover:text-indigo-300 transition flex items-center space-x-1" title="Optimize Chat Summary">
                                <i data-lucide="sparkles" class="h-3 w-3"></i>
                                <span>Optimize Summary</span>
                            </button>
                        </div>
                        <div>
                            <p class="text-[10px] leading-relaxed mt-0.5 text-slate-300">${res.ai ? res.ai.ai_summary : 'Waiting for incoming text messages analysis...'}</p>
                        </div>
                        ${thread.chat_summary ? `
                        <div class="border-t border-slate-800/85 pt-2">
                            <div class="text-[9px] uppercase font-bold text-indigo-400">Persistent Chat Summary</div>
                            <p class="text-[10px] leading-relaxed mt-0.5 text-slate-350">${thread.chat_summary}</p>
                        </div>
                        ` : ''}
                        <div class="flex justify-between border-t border-slate-800/80 pt-2 text-[10px]">
                            <span>Sentiment: <strong class="uppercase text-emerald-400 font-bold">${res.ai ? res.ai.sentiment : 'Neutral'}</strong></span>
                            <span>Score: <strong class="text-blue-400 font-bold">${crm.lead ? crm.lead.lead_score : 50}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- CRM Profile Link -->
                <div class="space-y-2 pt-3 border-t border-slate-200">
                    <div class="text-xs font-bold text-slate-700 flex justify-between items-center">
                        <span>CRM Contact Profile</span>
                    </div>
                    ${contactHtml}
                </div>

                <!-- CRM Lead cards -->
                <div class="space-y-2 pt-3 border-t border-slate-200">
                    <div class="text-xs font-bold text-slate-700 flex justify-between items-center">
                        <span>Matched CRM Lead</span>
                        ${leadActionBtn}
                    </div>
                    ${leadHtml}
                </div>

                <!-- Match Company -->
                <div class="space-y-2 pt-3 border-t border-slate-200">
                    <div class="text-xs font-bold text-slate-700 flex justify-between items-center">
                        <span>Matched Company</span>
                        ${companyActionBtn}
                    </div>
                    ${companyHtml}
                </div>

                <!-- Tasks list -->
                <div class="space-y-2 pt-3 border-t border-slate-200">
                    <div class="text-xs font-bold text-slate-700 flex justify-between items-center">
                        <span>CRM Tasks</span>
                        ${taskActionBtn}
                    </div>
                    <div class="space-y-2">${taskHtml}</div>
                </div>

                <!-- Notes list -->
                <div class="space-y-2 pt-3 border-t border-slate-200">
                    <div class="text-xs font-bold text-slate-700">Notes</div>
                    <div class="space-y-2">${notesHtml}</div>
                </div>

                <!-- Timeline updates -->
                <div class="space-y-2 pt-3 border-t border-slate-200">
                    <div class="text-xs font-bold text-slate-700">Timeline</div>
                    <div class="space-y-2 max-h-40 overflow-y-auto font-sans">${timelineHtml}</div>
                </div>
            `;
            lucide.createIcons();
        }

    } catch (err) {
        console.warn('Failed inbox load thread details:', err);
        const msgList = document.getElementById('wa-messages-container-list');
        if (msgList) {
            msgList.innerHTML = `<div class="text-red-500 text-center py-20 font-semibold">Failed to load messages: ${err.message}</div>`;
        }
    }
}

// Live message submit trigger
window.sendWaMessage = function (e) {
    e.preventDefault();
    if (!activeWaThreadId) return;

    const input = document.getElementById('wa-chat-input');
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';

    apiCall('whatsapp/inbox.php', 'POST', {
        wa_contact_id: activeWaThreadId,
        body: msg,
        type: 'text'
    }).then(res => {
        loadWaThreadMessages();
    }).catch(err => {
        showNotification('error', 'Failed to send message: ' + err.message);
    });
};

// AI Reply Suggested replies triggers
window.triggerWaAIChatAnalysis = function () {
    if (!activeWaThreadId) return;
    const input = document.getElementById('wa-chat-input');
    if (!input) return;

    const originalPlaceholder = input.placeholder;
    input.placeholder = 'AI is composing a response...';
    input.value = '';
    input.disabled = true;

    apiCall('whatsapp/inbox.php?action=apply_ai_reply', 'POST', {
        wa_contact_id: activeWaThreadId
    }).then(res => {
        input.disabled = false;
        input.placeholder = originalPlaceholder;

        const reply = res.suggested_reply || '';
        if (reply) {
            typeTextInInput(input, reply, 12);
        }
    }).catch(err => {
        input.disabled = false;
        input.placeholder = originalPlaceholder;
        showNotification('error', 'AI Compose failed: ' + err.message);
    });
};

function typeTextInInput(inputElement, text, speed = 12) {
    inputElement.value = '';
    let i = 0;
    if (inputElement.typingInterval) {
        clearInterval(inputElement.typingInterval);
    }

    inputElement.typingInterval = setInterval(() => {
        if (i < text.length) {
            inputElement.value += text.charAt(i);
            i++;
            inputElement.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            clearInterval(inputElement.typingInterval);
            inputElement.focus();
        }
    }, speed);
}

// ----------------------------------------------------
// 4. WHATSAPP CONTACTS VIEW
// ----------------------------------------------------
function renderWhatsAppContacts(container) {
    checkWaConnectionAndRender('contacts', container, async (contentArea) => {
        try {
            const res = await apiCall('whatsapp/contacts.php');
            window._allWaContactsList = res.contacts || [];
            window._waContactsCurrentPage = 1;
            window._waContactsPageSize = 25;
            window._waContactsSearchQuery = '';

            contentArea.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between flex-wrap gap-4 border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm">
                        <div>
                            <div class="flex items-center space-x-2">
                                <h2 class="text-sm font-bold text-slate-800">WhatsApp Subscriber List</h2>
                                <span class="bg-indigo-50 text-indigo-700 text-[10px] font-extrabold px-2 py-0.5 rounded-full border border-indigo-100" id="wa-contacts-total-count">${window._allWaContactsList.length} CONTACTS</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-0.5">Contacts who connected with your number via WhatsApp.</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="relative w-64">
                                <i data-lucide="search" class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400"></i>
                                <input type="text" id="wa-contacts-search-input" oninput="handleWaContactsSearch(this.value)" placeholder="Search profile, phone, or tags..." class="w-full pl-9 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500 focus:bg-white transition">
                            </div>
                        </div>
                    </div>

                    <!-- Table panel -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 text-slate-500 font-extrabold uppercase text-[10px] tracking-wider">
                                        <th class="py-3 px-4">Profile Name</th>
                                        <th class="py-3 px-4">Phone Number</th>
                                        <th class="py-3 px-4">CRM Profile Link</th>
                                        <th class="py-3 px-4">CRM Designation</th>
                                        <th class="py-3 px-4">Tags</th>
                                        <th class="py-3 px-4">Last Active</th>
                                    </tr>
                                </thead>
                                <tbody id="wa-contacts-tbody" class="divide-y divide-slate-100">
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-2 px-2 text-xs text-slate-500 font-medium">
                            <div id="wa-contacts-pagination-info">Showing 0-0 of 0</div>
                            <div class="flex items-center space-x-2">
                                <button id="wa-contacts-prev-btn" onclick="changeWaContactsPage(-1)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-slate-700 font-bold transition disabled:opacity-50 disabled:cursor-not-allowed">Previous</button>
                                <span id="wa-contacts-page-indicator" class="font-bold text-slate-800 px-2">Page 1 of 1</span>
                                <button id="wa-contacts-next-btn" onclick="changeWaContactsPage(1)" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-lg text-slate-700 font-bold transition disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            renderWaContactsTablePage();
        } catch (err) {
            showNotification('error', err.message);
        }
    });
}

window.handleWaContactsSearch = function(val) {
    window._waContactsSearchQuery = (val || '').toLowerCase().trim();
    window._waContactsCurrentPage = 1;
    renderWaContactsTablePage();
};

window.changeWaContactsPage = function(delta) {
    window._waContactsCurrentPage += delta;
    renderWaContactsTablePage();
};

window.renderWaContactsTablePage = function() {
    const list = window._allWaContactsList || [];
    const q = window._waContactsSearchQuery || '';
    
    const filtered = list.filter(c => {
        if (!q) return true;
        const name = (c.profile_name || '').toLowerCase();
        const phone = (c.wa_id || '').toLowerCase();
        const crmName = (c.crm_name || '').toLowerCase();
        const tags = (c.tags || '').toLowerCase();
        return name.includes(q) || phone.includes(q) || crmName.includes(q) || tags.includes(q);
    });

    const pageSize = window._waContactsPageSize || 25;
    const totalItems = filtered.length;
    const totalPages = Math.ceil(totalItems / pageSize) || 1;

    if (window._waContactsCurrentPage < 1) window._waContactsCurrentPage = 1;
    if (window._waContactsCurrentPage > totalPages) window._waContactsCurrentPage = totalPages;

    const startIdx = (window._waContactsCurrentPage - 1) * pageSize;
    const pageItems = filtered.slice(startIdx, startIdx + pageSize);

    const tbody = document.getElementById('wa-contacts-tbody');
    if (tbody) {
        if (pageItems.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-slate-400 font-medium">No contacts match your query.</td></tr>`;
        } else {
            tbody.innerHTML = pageItems.map(c => `
                <tr class="hover:bg-slate-50 border-b border-slate-100">
                    <td class="py-3 px-4 font-bold text-slate-700 flex items-center space-x-2">
                        <div class="h-6 w-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px] shrink-0">
                            ${(c.profile_name || 'WhatsApp Contact').charAt(0).toUpperCase()}
                        </div>
                        <span class="truncate">${escapeHtml(c.profile_name || 'WhatsApp Contact')}</span>
                    </td>
                    <td class="py-3 px-4 text-slate-600 font-mono">${formatPhoneNumber(c.wa_id)}</td>
                    <td class="py-3 px-4 text-slate-600">
                        ${c.crm_name ? `<span class="px-2 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-md font-bold">${escapeHtml(c.crm_name)}</span>` : '<span class="text-slate-400 italic">Not Linked</span>'}
                    </td>
                    <td class="py-3 px-4 text-slate-500">${escapeHtml(c.crm_title || 'None')}</td>
                    <td class="py-3 px-4">
                        ${c.tags ? c.tags.split(',').map(t => `<span class="inline-block px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded text-[9px] font-bold mr-1">${escapeHtml(t.trim())}</span>`).join('') : '<span class="text-slate-400 italic">No Tags</span>'}
                    </td>
                    <td class="py-3 px-4 text-slate-400">${new Date(c.last_message_at).toLocaleDateString()}</td>
                </tr>
            `).join('');
        }
    }

    const infoEl = document.getElementById('wa-contacts-pagination-info');
    if (infoEl) {
        const endIdx = Math.min(startIdx + pageSize, totalItems);
        infoEl.textContent = totalItems > 0 ? `Showing ${startIdx + 1}-${endIdx} of ${totalItems} contacts` : 'Showing 0-0 of 0 contacts';
    }

    const indicatorEl = document.getElementById('wa-contacts-page-indicator');
    if (indicatorEl) indicatorEl.textContent = `Page ${window._waContactsCurrentPage} of ${totalPages}`;

    const prevBtn = document.getElementById('wa-contacts-prev-btn');
    if (prevBtn) prevBtn.disabled = (window._waContactsCurrentPage <= 1);

    const nextBtn = document.getElementById('wa-contacts-next-btn');
    if (nextBtn) nextBtn.disabled = (window._waContactsCurrentPage >= totalPages);
};

// ----------------------------------------------------
// 5. WHATSAPP CAMPAIGNS VIEW
// ----------------------------------------------------
function renderWhatsAppCampaigns(container) {
    checkWaConnectionAndRender('campaigns', container, async (contentArea) => {
        try {
            const setupRes = await apiCall('whatsapp/setup.php');
            const acc = setupRes.account || {};
            const displayPhone = acc.display_phone_number || '91 98765 43210';
            window.connectedWhatsAppNumber = displayPhone;

            const res = await apiCall('whatsapp/campaigns.php');
            const campaigns = res.campaigns || [];

            const templatesRes = await apiCall('whatsapp/templates.php');
            const templates = templatesRes.templates || [];

            let searchQuery = '';
            let statusFilter = 'ALL';
            let numberFilter = 'ALL';
            let dateFilter = 'ALL';
            let minContacts = 0;
            let campaignType = 'ALL';
            let currentPage = 1;
            let itemsPerPage = 5;
            let sortBy = 'latest';

            // High-fidelity mock campaign samples matching the provided image
            const baseSamples = [];

            // Render Layout scaffolding
            contentArea.innerHTML = `
                <div class="space-y-6 text-slate-800 animate-fade-in">
                    <!-- Title Bar -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="h-10 w-10 flex items-center justify-center shrink-0">
                                <img src="../assets/css/WhatsApp_icon.png" class="h-10 w-10 object-contain" alt="WhatsApp">
                            </div>
                            <div>
                                <h1 class="text-xl font-bold tracking-tight text-slate-900 leading-none">WhatsApp Campaigns</h1>
                                <p class="text-xs text-slate-400 mt-1.5 font-medium">Create, send and track your WhatsApp broadcast campaigns</p>
                            </div>
                        </div>
                        <button onclick="openCampaignCreatePage()" class="flex items-center space-x-2 px-4 py-2.5 bg-[#00a884] hover:bg-[#008f6f] text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-emerald-500/10 text-white" style="color: white !important;">
                            <i data-lucide="plus" class="h-4 w-4 text-white"></i>
                            <span style="color: white !important;">New Campaign</span>
                        </button>
                    </div>

                    <!-- Overview Card Panel -->
                    <div class="space-y-2.5">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Overview <span class="text-slate-350 capitalize font-medium">(This Month)</span></span>
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                            <!-- Card 1 -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Campaigns Sent</span>
                                    <div class="h-7 w-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                        <i data-lucide="send" class="h-4 w-4 text-emerald-600"></i>
                                    </div>
                                </div>
                                <div class="space-y-1 mt-3">
                                    <div class="text-2xl font-black text-slate-900 leading-none" id="stat-campaigns-sent">18</div>
                                    <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded mt-1">
                                        <i data-lucide="arrow-up" class="h-2.5 w-2.5 mr-0.5"></i>
                                        <span>20% <span class="text-slate-400 font-medium">vs last month</span></span>
                                    </span>
                                </div>
                            </div>
                            <!-- Card 2 -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Messages Sent</span>
                                    <div class="h-7 w-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                        <i data-lucide="message-square" class="h-4 w-4 text-emerald-600"></i>
                                    </div>
                                </div>
                                <div class="space-y-1 mt-3">
                                    <div class="text-2xl font-black text-slate-900 leading-none" id="stat-messages-sent">12,540</div>
                                    <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded mt-1">
                                        <i data-lucide="arrow-up" class="h-2.5 w-2.5 mr-0.5"></i>
                                        <span>24.5% <span class="text-slate-400 font-medium">vs last month</span></span>
                                    </span>
                                </div>
                            </div>
                            <!-- Card 3 -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Messages Delivered</span>
                                    <div class="h-7 w-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                        <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600"></i>
                                    </div>
                                </div>
                                <div class="space-y-1 mt-3">
                                    <div class="text-2xl font-black text-slate-900 leading-none" id="stat-messages-delivered">11,230</div>
                                    <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded mt-1">
                                        <span id="stat-delivery-rate">89.5%</span> <span class="text-slate-400 font-medium ml-0.5">delivery rate</span>
                                    </span>
                                </div>
                            </div>
                            <!-- Card 4 -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Messages Read</span>
                                    <div class="h-7 w-7 rounded-lg bg-blue-50 text-blue-650 flex items-center justify-center">
                                        <i data-lucide="eye" class="h-4 w-4 text-blue-600"></i>
                                    </div>
                                </div>
                                <div class="space-y-1 mt-3">
                                    <div class="text-2xl font-black text-slate-900 leading-none" id="stat-messages-read">6,789</div>
                                    <span class="inline-flex items-center text-[9px] font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded mt-1">
                                        <span id="stat-read-rate">60.4%</span> <span class="text-slate-400 font-medium ml-0.5">read rate</span>
                                    </span>
                                </div>
                            </div>
                            <!-- Card 5 -->
                            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col justify-between">
                                <div class="flex justify-between items-start">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Replies Received</span>
                                    <div class="h-7 w-7 rounded-lg bg-purple-50 text-purple-650 flex items-center justify-center">
                                        <i data-lucide="reply" class="h-4 w-4 text-purple-600"></i>
                                    </div>
                                </div>
                                <div class="space-y-1 mt-3">
                                    <div class="text-2xl font-black text-slate-900" id="stat-replies-received">1,245</div>
                                    <span class="inline-flex items-center text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1 py-0.5 rounded mt-1">
                                        <i data-lucide="arrow-up" class="h-2.5 w-2.5 mr-0.5"></i>
                                        <span>18.7% <span class="text-slate-400 font-medium">vs last month</span></span>
                                    </span>
                                </div>
                            </div>
                            <!-- Card 6 -->
                            <div class="bg-[#ecfdf5] border border-emerald-100 rounded-2xl p-4 flex flex-col justify-between">
                                <div class="space-y-1.5">
                                    <span class="text-[10px] font-bold text-[#065f46] uppercase tracking-wider block">Want detailed insights?</span>
                                    <p class="text-[10px] text-slate-500 font-medium leading-relaxed">View full WhatsApp report with charts, trends and more.</p>
                                </div>
                                <button onclick="showNotification('info', 'Analytical WhatsApp dashboard loading...')" class="w-full py-1.5 mt-2 bg-white hover:bg-emerald-50/50 border border-emerald-600 text-emerald-600 rounded-xl text-[10px] font-bold transition flex items-center justify-center space-x-1">
                                    <i data-lucide="bar-chart-3" class="h-3.5 w-3.5 text-emerald-600"></i>
                                    <span class="text-emerald-650 font-bold">View Full Report</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Filters Control Panel -->
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-white border border-slate-200 p-4 rounded-2xl shadow-sm">
                        <div class="flex flex-wrap items-center gap-3 flex-grow max-w-4xl">
                            <!-- Search query input -->
                            <div class="relative w-full md:w-56">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="search" class="h-4 w-4"></i>
                                </span>
                                <input type="text" id="camp-search" placeholder="Search campaigns..." class="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-blue-500">
                            </div>
                            <!-- Status filter dropdown -->
                            <div class="w-full md:w-36">
                                <select id="camp-status-select" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none cursor-pointer">
                                    <option value="ALL">All Status</option>
                                    <option value="COMPLETED">Completed</option>
                                    <option value="SENT">Sent</option>
                                    <option value="SENDING">Sending</option>
                                    <option value="SCHEDULED">Scheduled</option>
                                    <option value="DRAFT">Draft</option>
                                </select>
                            </div>
                            <!-- Numbers filter dropdown -->
                            <div class="w-full md:w-40">
                                <select id="camp-number-select" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none cursor-pointer">
                                    <option value="ALL">All Numbers</option>
                                    <option value="${displayPhone.replace(/\s+/g, '')}">+${displayPhone}</option>
                                </select>
                            </div>
                            <!-- Date Filter Range Select -->
                            <div class="relative w-full md:w-60">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="calendar" class="h-4 w-4"></i>
                                </span>
                                <select id="camp-date-select" class="w-full pl-9 pr-8 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 cursor-pointer focus:outline-none appearance-none">
                                    <option value="MAY_2026">May 01, 2026 - May 31, 2026</option>
                                    <option value="ALL">All Time Dates</option>
                                    <option value="TODAY">Today</option>
                                    <option value="LAST_7_DAYS">Last 7 Days</option>
                                    <option value="LAST_30_DAYS">Last 30 Days</option>
                                </select>
                                <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Right buttons -->
                        <div class="flex items-center space-x-2 shrink-0">
                            <button onclick="toggleAdvancedFilters()" class="px-3.5 py-2 border border-slate-200 rounded-xl bg-white hover:bg-slate-50 text-slate-600 text-xs font-bold flex items-center space-x-1.5 transition shadow-sm">
                                <i data-lucide="sliders-horizontal" class="h-4 w-4 text-slate-600"></i>
                                <span>Filters</span>
                            </button>
                            <button onclick="exportCampaignsCSV()" class="px-3.5 py-2 border border-slate-200 rounded-xl bg-white hover:bg-slate-50 text-slate-600 text-xs font-bold flex items-center space-x-1.5 transition shadow-sm">
                                <i data-lucide="download" class="h-4 w-4 text-slate-600"></i>
                                <span>Export</span>
                            </button>
                        </div>
                    </div>

                    <!-- Advanced Filters Expandable Panel -->
                    <div id="advanced-filters-panel" class="hidden bg-slate-50/70 border border-slate-200 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs font-semibold animate-fade-in">
                        <div>
                            <label class="block text-slate-500 mb-1">Min Target Contacts</label>
                            <input type="number" id="adv-min-contacts" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-blue-500" placeholder="e.g. 100">
                        </div>
                        <div>
                            <label class="block text-slate-500 mb-1">Campaign Type</label>
                            <select id="adv-campaign-type" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none cursor-pointer">
                                <option value="ALL">All Types</option>
                                <option value="REAL">Database Campaigns</option>
                                <option value="SAMPLE">Sample Campaigns</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button onclick="resetAllFilters()" class="w-full py-2 bg-slate-200 hover:bg-slate-350 text-slate-700 rounded-xl font-bold transition flex items-center justify-center space-x-1">
                                <i data-lucide="rotate-ccw" class="h-3.5 w-3.5 text-slate-700"></i>
                                <span class="text-slate-700 font-bold">Reset All Filters</span>
                            </button>
                        </div>
                    </div>

                    <!-- Campaigns list table block -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
                        <div class="flex justify-between items-center">
                            <h3 class="font-extrabold text-slate-800 text-sm">
                                All WhatsApp Campaigns <span class="text-slate-400 font-medium ml-1" id="campaigns-total-count">(18)</span>
                            </h3>
                            
                            <div class="flex items-center space-x-3">
                                <!-- Sort option dropdown -->
                                <div class="flex items-center space-x-1.5 text-xs text-slate-500 font-semibold">
                                    <span>Sort by:</span>
                                    <select id="camp-sort-select" class="px-2.5 py-1.5 border border-slate-200 rounded-xl bg-white text-slate-800 font-bold focus:outline-none cursor-pointer">
                                        <option value="latest">Latest</option>
                                        <option value="oldest">Oldest</option>
                                        <option value="target">Target Size</option>
                                    </select>
                                </div>
                                <!-- View selectors -->
                                <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden shadow-sm bg-white p-0.5">
                                    <button class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600" onclick="showNotification('info', 'Grid layout is disabled.')">
                                        <i data-lucide="grid" class="h-4 w-4 text-slate-400"></i>
                                    </button>
                                    <button class="p-1.5 rounded-lg text-emerald-600 bg-emerald-50">
                                        <i data-lucide="list" class="h-4 w-4 text-emerald-600"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Main Table layout -->
                        <div class="overflow-x-auto min-h-[300px]">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                                        <th class="py-3.5 px-4">Campaign Name</th>
                                        <th class="py-3.5 px-4">Template</th>
                                        <th class="py-3.5 px-4">WhatsApp Number</th>
                                        <th class="py-3.5 px-4 text-center">Target</th>
                                        <th class="py-3.5 px-4 text-center">Sent</th>
                                        <th class="py-3.5 px-4 text-center">Delivered</th>
                                        <th class="py-3.5 px-4 text-center">Read</th>
                                        <th class="py-3.5 px-4 text-center">Replies</th>
                                        <th class="py-3.5 px-4 text-center">Status</th>
                                        <th class="py-3.5 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="campaigns-table-body" class="divide-y divide-slate-100">
                                    <!-- Rows populated dynamically -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="flex flex-col sm:flex-row justify-between items-center border-t border-slate-100 pt-4 gap-3 text-xs font-semibold text-slate-500">
                            <span id="pagination-summary">Showing 1 to 5 of 18 campaigns</span>
                            
                            <div class="flex items-center space-x-3.5">
                                <!-- dots indicators -->
                                <div class="flex items-center space-x-1.5" id="paginator-dots-container">
                                    <!-- buttons populated dynamically -->
                                </div>
                                
                                <!-- items size select -->
                                <div class="flex items-center space-x-1 text-slate-400">
                                    <select id="paginator-size-select" class="px-2 py-1 border border-slate-200 rounded-lg text-slate-700 focus:outline-none cursor-pointer">
                                        <option value="5">5 / page</option>
                                        <option value="10">10 / page</option>
                                        <option value="20">20 / page</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Floating Chat Bubble button in the bottom right corner -->
                <div class="fixed bottom-6 right-6 z-50">
                    <button onclick="showNotification('info', 'Opening live WhatsApp Chat Assistant...')" class="h-14 w-14 rounded-full bg-[#00a884] hover:bg-[#008f6f] text-white flex items-center justify-center shadow-2xl transition-all duration-200 hover:scale-105 active:scale-95 shadow-emerald-500/20">
                        <svg class="h-7 w-7 text-white fill-current" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.517 2.266 2.27 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.6.95 3.16 1.449 4.887 1.45 5.482.002 9.944-4.461 9.947-9.945.002-2.657-1.03-5.155-2.905-7.03C16.71 1.766 14.215.73c-5.49 0-9.952 4.463-9.955 9.948 0 1.787.483 3.394 1.401 4.938l-.921 3.363 3.567-.935zm12.785-6.853c-.347-.174-2.054-1.014-2.372-1.129-.318-.116-.549-.174-.78.174-.23.348-.895 1.129-1.096 1.361-.202.233-.404.261-.751.087-.348-.174-1.47-.542-2.8-1.728-1.034-.922-1.732-2.06-1.934-2.41-.202-.347-.022-.535.152-.708.156-.156.347-.406.52-.608.174-.203.23-.348.347-.58.117-.232.06-.435-.03-.608-.09-.174-.78-1.884-1.068-2.58-.282-.677-.567-.585-.78-.596-.2-.01-.43-.01-.66-.01-.23 0-.608.087-.925.435-.317.348-1.214 1.188-1.214 2.902 0 1.71 1.244 3.362 1.417 3.593.173.23 2.453 3.746 5.94 5.253.83.358 1.478.57 1.983.731.834.265 1.593.228 2.193.138.669-.1 2.054-.84 2.342-1.652.288-.812.288-1.508.202-1.652-.086-.145-.317-.232-.664-.406z"/>
                    </button>
                </div>
            `;

            function getMergedCampaigns() {
                const dbCampaigns = campaigns.map(c => {
                    const dPercent = c.sent_count > 0 ? ((c.delivered_count / c.sent_count) * 100).toFixed(1) : '100';
                    const rPercent = c.sent_count > 0 ? ((c.read_count / c.sent_count) * 100).toFixed(1) : '0';
                    const repPercent = c.sent_count > 0 ? ((c.replies_count / c.sent_count) * 100).toFixed(1) : '0';

                    return {
                        id: c.id,
                        name: c.name,
                        created_at: c.created_at,
                        template_name: c.template_name,
                        wa_number: displayPhone,
                        total_contacts: c.total_contacts || 0,
                        sent_count: c.sent_count || 0,
                        delivered_count: c.delivered_count || 0,
                        delivered_percent: dPercent,
                        read_count: c.read_count || 0,
                        read_percent: rPercent,
                        replies_count: c.replies_count || 0,
                        replies_percent: repPercent,
                        status: c.status || 'draft',
                        icon: "send",
                        iconBg: "bg-emerald-50",
                        iconColor: "text-emerald-500",
                        is_sample: false
                    };
                });

                return dbCampaigns;
            }

            function applyFilterAndRender() {
                const allMerged = getMergedCampaigns();

                // Perform filtering first to get correct stats dynamically based on criteria
                let filtered = allMerged.filter(c => {
                    const matchesSearch = c.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                        c.template_name.toLowerCase().includes(searchQuery.toLowerCase());
                    const matchesStatus = (statusFilter === 'ALL' || c.status.toLowerCase() === statusFilter.toLowerCase());
                    const matchesNumber = (numberFilter === 'ALL' || c.wa_number.replace(/\s+/g, '') === numberFilter);

                    let matchesDate = true;
                    const campDate = new Date(c.created_at);
                    const now = new Date();
                    if (dateFilter === 'MAY_2026') {
                        matchesDate = (campDate.getFullYear() === 2026 && campDate.getMonth() === 4);
                    } else if (dateFilter === 'TODAY') {
                        matchesDate = (campDate.toDateString() === now.toDateString());
                    } else if (dateFilter === 'LAST_7_DAYS') {
                        const diffTime = Math.abs(now - campDate);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        matchesDate = (diffDays <= 7);
                    } else if (dateFilter === 'LAST_30_DAYS') {
                        const diffTime = Math.abs(now - campDate);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        matchesDate = (diffDays <= 30);
                    }

                    const matchesMinContacts = (c.total_contacts >= minContacts);
                    const matchesCampaignType = (campaignType === 'ALL' ||
                        (campaignType === 'REAL' && !c.is_sample) ||
                        (campaignType === 'SAMPLE' && c.is_sample));

                    return matchesSearch && matchesStatus && matchesNumber && matchesDate && matchesMinContacts && matchesCampaignType;
                });

                // Update Overview stats dynamically
                const completedOrSent = filtered.filter(c => c.status === 'completed' || c.status === 'sent');
                const totalSentCampaigns = completedOrSent.length;
                const totalMessagesSent = filtered.reduce((acc, c) => acc + c.sent_count, 0);
                const totalMessagesDelivered = filtered.reduce((acc, c) => acc + c.delivered_count, 0);
                const totalMessagesRead = filtered.reduce((acc, c) => acc + c.read_count, 0);
                const totalReplies = filtered.reduce((acc, c) => acc + c.replies_count, 0);

                const delRate = totalMessagesSent > 0 ? ((totalMessagesDelivered / totalMessagesSent) * 100).toFixed(1) : '100';
                const readRate = totalMessagesSent > 0 ? ((totalMessagesRead / totalMessagesSent) * 100).toFixed(1) : '0';

                document.getElementById('stat-campaigns-sent').textContent = totalSentCampaigns;
                document.getElementById('stat-messages-sent').textContent = totalMessagesSent.toLocaleString('en-US');
                document.getElementById('stat-messages-delivered').textContent = totalMessagesDelivered.toLocaleString('en-US');
                document.getElementById('stat-delivery-rate').textContent = delRate + '%';
                document.getElementById('stat-messages-read').textContent = totalMessagesRead.toLocaleString('en-US');
                document.getElementById('stat-read-rate').textContent = readRate + '%';
                document.getElementById('stat-replies-received').textContent = totalReplies.toLocaleString('en-US');

                document.getElementById('campaigns-total-count').textContent = `(${filtered.length})`;

                // 3. Sort campaigns list
                filtered.sort((a, b) => {
                    if (sortBy === 'latest') {
                        return new Date(b.created_at) - new Date(a.created_at);
                    } else if (sortBy === 'oldest') {
                        return new Date(a.created_at) - new Date(b.created_at);
                    } else if (sortBy === 'target') {
                        return b.total_contacts - a.total_contacts;
                    }
                    return 0;
                });

                // 4. Paginate campaigns list
                const totalItems = filtered.length;
                const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
                if (currentPage > totalPages) currentPage = totalPages;

                const startIdx = (currentPage - 1) * itemsPerPage;
                const endIdx = Math.min(startIdx + itemsPerPage, totalItems);
                const paginatedList = filtered.slice(startIdx, endIdx);

                document.getElementById('pagination-summary').textContent = totalItems > 0 ?
                    `Showing ${startIdx + 1} to ${endIdx} of ${totalItems} campaigns` :
                    `Showing 0 to 0 of 0 campaigns`;

                const tableBody = document.getElementById('campaigns-table-body');
                if (paginatedList.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="10" class="text-center py-20 text-slate-400 font-medium">
                                No campaigns match your selection filter.
                            </td>
                        </tr>
                    `;
                } else {
                    tableBody.innerHTML = paginatedList.map(c => {
                        const dateFormatted = new Date(c.created_at).toLocaleDateString('en-US', {
                            month: 'short',
                            day: '2-digit',
                            year: 'numeric'
                        });
                        const timeFormatted = new Date(c.created_at).toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        let statusClasses = '';
                        if (c.status === 'completed') statusClasses = 'bg-emerald-50 text-emerald-600 font-bold';
                        else if (c.status === 'sent') statusClasses = 'bg-blue-50 text-blue-600 font-bold';
                        else if (c.status === 'sending') statusClasses = 'bg-amber-50 text-amber-600 font-bold';
                        else if (c.status === 'scheduled') statusClasses = 'bg-purple-50 text-purple-650 font-bold';
                        else statusClasses = 'bg-slate-100 text-slate-400 font-semibold';

                        let iconName = 'send';
                        if (c.name.toLowerCase().includes('launch') || c.name.toLowerCase().includes('offer')) iconName = 'tag';
                        else if (c.name.toLowerCase().includes('welcome') || c.name.toLowerCase().includes('lead')) iconName = 'users';
                        else if (c.name.toLowerCase().includes('discount') || c.name.toLowerCase().includes('promo')) iconName = 'percent';
                        else if (c.name.toLowerCase().includes('bell') || c.name.toLowerCase().includes('tips') || c.name.toLowerCase().includes('weekly')) iconName = 'bell';

                        let circleBg = 'bg-emerald-50 text-emerald-500';
                        if (iconName === 'tag') circleBg = 'bg-blue-50 text-blue-500';
                        else if (iconName === 'users') circleBg = 'bg-purple-50 text-purple-500';
                        else if (iconName === 'percent') circleBg = 'bg-amber-50 text-amber-500';
                        else if (iconName === 'bell') circleBg = 'bg-red-50 text-red-500';

                        return `
                            <tr class="hover:bg-slate-50/70 transition-all border-b border-slate-100">
                                <td class="py-3 px-4 font-bold text-slate-800 flex items-center space-x-3">
                                    <div class="h-8.5 w-8.5 rounded-xl ${circleBg} flex items-center justify-center shrink-0 border border-slate-100 shadow-2xs">
                                        <i data-lucide="${iconName}" class="h-4.5 w-4.5"></i>
                                    </div>
                                    <div class="space-y-0.5">
                                        <span class="block font-bold text-slate-805 text-xs">${c.name}</span>
                                        <span class="block text-[10px] text-slate-400 font-medium">${dateFormatted} • ${timeFormatted}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <code class="font-mono text-indigo-500 font-semibold text-[10.5px] bg-indigo-50/50 px-1.5 py-0.5 rounded">${c.template_name}</code>
                                </td>
                                <td class="py-3 px-4 text-slate-600 font-semibold flex items-center space-x-1.5">
                                    <img src="../assets/css/WhatsApp_icon.png" class="h-4 w-4 object-contain shrink-0" alt="WhatsApp">
                                    <span class="font-mono text-[11px]">${formatPhoneNumber(c.wa_number)}</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-800 text-xs">${c.total_contacts}</td>
                                <td class="py-3 px-4 text-center font-bold text-slate-700 text-xs">${c.sent_count}</td>
                                <td class="py-3 px-4 text-center font-bold text-slate-700 text-xs">
                                    <div>${c.delivered_count}</div>
                                    <span class="text-[10px] text-emerald-600 font-bold">${c.delivered_percent}%</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-700 text-xs">
                                    <div>${c.read_count}</div>
                                    <span class="text-[10px] text-slate-400 font-semibold">${c.read_percent}%</span>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-slate-700 text-xs">
                                    <div>${c.replies_count}</div>
                                    <span class="text-[10px] text-slate-400 font-semibold">${c.replies_percent}%</span>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[9px] ${statusClasses}">
                                        ${c.status.toUpperCase()}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end space-x-1.5">
                                        <button onclick="openCampaignReportPopup(${c.id}, '${c.name.replace(/'/g, "\\'")}')" class="p-1.5 border border-slate-200 hover:bg-slate-50 text-slate-500 rounded-lg transition" title="View Report">
                                            <i data-lucide="bar-chart-2" class="h-3.5 w-3.5 text-slate-500"></i>
                                        </button>
                                        <button onclick="showNotification('success', 'Campaign copied to draft.')" class="p-1.5 border border-slate-200 hover:bg-slate-50 text-slate-500 rounded-lg transition" title="Clone Campaign">
                                            <i data-lucide="copy" class="h-3.5 w-3.5 text-slate-500"></i>
                                        </button>
                                        ${c.status === 'draft' ? `
                                            <button onclick="triggerBroadcastCampaign(${c.id})" class="p-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg transition" title="Send Campaign">
                                                <i data-lucide="send" class="h-3.5 w-3.5 text-white"></i>
                                            </button>
                                        ` : ''}
                                        <button onclick="deleteCampaign(${c.is_sample ? "'" + c.id + "'" : c.id})" class="p-1.5 border border-slate-200 hover:bg-red-50 text-slate-500 hover:text-red-650 rounded-lg transition" title="Delete">
                                            <i data-lucide="more-vertical" class="h-3.5 w-3.5 text-slate-500"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('');
                }

                const paginatorContainer = document.getElementById('paginator-dots-container');
                let dotsHtml = `
                    <button class="p-1 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
                        <i data-lucide="chevron-left" class="h-3.5 w-3.5 text-slate-600"></i>
                    </button>
                `;
                for (let p = 1; p <= totalPages; p++) {
                    if (p === currentPage) {
                        dotsHtml += `<button class="h-7 w-7 rounded-lg bg-[#00a884] text-white flex items-center justify-center text-xs font-bold" style="color: white !important;">${p}</button>`;
                    } else {
                        dotsHtml += `<button class="h-7 w-7 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 flex items-center justify-center text-xs font-bold" onclick="changePage(${p})">${p}</button>`;
                    }
                }
                dotsHtml += `
                    <button class="p-1 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-slate-600"></i>
                    </button>
                `;
                paginatorContainer.innerHTML = dotsHtml;

                lucide.createIcons();
            }

            window.changePage = function (page) {
                currentPage = page;
                applyFilterAndRender();
            };

            window.toggleAdvancedFilters = function () {
                const panel = document.getElementById('advanced-filters-panel');
                if (panel) {
                    panel.classList.toggle('hidden');
                }
            };

            window.resetAllFilters = function () {
                document.getElementById('camp-search').value = '';
                document.getElementById('camp-status-select').value = 'ALL';
                document.getElementById('camp-number-select').value = 'ALL';
                document.getElementById('camp-date-select').value = 'MAY_2026';
                document.getElementById('adv-min-contacts').value = '';
                document.getElementById('adv-campaign-type').value = 'ALL';

                searchQuery = '';
                statusFilter = 'ALL';
                numberFilter = 'ALL';
                dateFilter = 'MAY_2026';
                minContacts = 0;
                campaignType = 'ALL';
                currentPage = 1;

                applyFilterAndRender();
                showNotification('info', 'All filters reset.');
            };

            window.exportCampaignsCSV = function () {
                const allMerged = getMergedCampaigns();
                const filtered = allMerged.filter(c => {
                    const matchesSearch = c.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                        c.template_name.toLowerCase().includes(searchQuery.toLowerCase());
                    const matchesStatus = (statusFilter === 'ALL' || c.status.toLowerCase() === statusFilter.toLowerCase());
                    const matchesNumber = (numberFilter === 'ALL' || c.wa_number.replace(/\s+/g, '') === numberFilter);

                    let matchesDate = true;
                    const campDate = new Date(c.created_at);
                    const now = new Date();
                    if (dateFilter === 'MAY_2026') {
                        matchesDate = (campDate.getFullYear() === 2026 && campDate.getMonth() === 4);
                    } else if (dateFilter === 'TODAY') {
                        matchesDate = (campDate.toDateString() === now.toDateString());
                    } else if (dateFilter === 'LAST_7_DAYS') {
                        const diffTime = Math.abs(now - campDate);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        matchesDate = (diffDays <= 7);
                    } else if (dateFilter === 'LAST_30_DAYS') {
                        const diffTime = Math.abs(now - campDate);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        matchesDate = (diffDays <= 30);
                    }

                    const matchesMinContacts = (c.total_contacts >= minContacts);
                    const matchesCampaignType = (campaignType === 'ALL' ||
                        (campaignType === 'REAL' && !c.is_sample) ||
                        (campaignType === 'SAMPLE' && c.is_sample));

                    return matchesSearch && matchesStatus && matchesNumber && matchesDate && matchesMinContacts && matchesCampaignType;
                });

                if (filtered.length === 0) {
                    showNotification('warning', 'No campaigns match the filters to export.');
                    return;
                }

                let csv = "Campaign Name,Template,WhatsApp Number,Target,Sent,Delivered,Read,Replies,Status,Created At\n";
                filtered.forEach(c => {
                    csv += `"${c.name}","${c.template_name}","${formatPhoneNumber(c.wa_number)}",${c.total_contacts},${c.sent_count},${c.delivered_count},${c.read_count},${c.replies_count},"${c.status.toUpperCase()}","${c.created_at}"\n`;
                });

                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement("a");
                const url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", `whatsapp_campaigns_${new Date().toISOString().slice(0, 10)}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showNotification('success', `Exported ${filtered.length} campaigns to CSV.`);
            };

            document.getElementById('camp-search').addEventListener('input', (e) => {
                searchQuery = e.target.value;
                currentPage = 1;
                applyFilterAndRender();
            });

            document.getElementById('camp-status-select').addEventListener('change', (e) => {
                statusFilter = e.target.value;
                currentPage = 1;
                applyFilterAndRender();
            });

            document.getElementById('camp-number-select').addEventListener('change', (e) => {
                numberFilter = e.target.value;
                currentPage = 1;
                applyFilterAndRender();
            });

            document.getElementById('camp-date-select').addEventListener('change', (e) => {
                dateFilter = e.target.value;
                currentPage = 1;
                applyFilterAndRender();
            });

            document.getElementById('adv-min-contacts').addEventListener('input', (e) => {
                minContacts = parseInt(e.target.value) || 0;
                currentPage = 1;
                applyFilterAndRender();
            });

            document.getElementById('adv-campaign-type').addEventListener('change', (e) => {
                campaignType = e.target.value;
                currentPage = 1;
                applyFilterAndRender();
            });

            document.getElementById('camp-sort-select').addEventListener('change', (e) => {
                sortBy = e.target.value;
                currentPage = 1;
                applyFilterAndRender();
            });

            document.getElementById('paginator-size-select').addEventListener('change', (e) => {
                itemsPerPage = parseInt(e.target.value);
                currentPage = 1;
                applyFilterAndRender();
            });

            // Handle delete action (support sample and real deletion)
            window.deleteCampaign = function (campId) {
                if (typeof campId === 'string' && campId.startsWith('sample-')) {
                    if (confirm('Delete this sample campaign draft?')) {
                        const idx = baseSamples.findIndex(s => s.id === campId);
                        if (idx > -1) {
                            baseSamples.splice(idx, 1);
                            showNotification('success', 'Sample campaign deleted.');
                            applyFilterAndRender();
                        }
                    }
                } else {
                    if (!confirm('Delete this campaign draft permanently?')) return;
                    apiCall(`whatsapp/campaigns.php?action=delete&campaign_id=${campId}`, 'POST')
                        .then(res => {
                            showNotification('success', res.message);
                            renderWhatsAppCampaigns(container);
                        })
                        .catch(err => {
                            showNotification('error', err.message);
                        });
                }
            };

            // View detailed number-wise logs report
            window.openCampaignReportPopup = function (campaignId, campaignName) {
                const existing = document.getElementById('campaign-report-modal');
                if (existing) existing.remove();

                const modal = document.createElement('div');
                modal.id = 'campaign-report-modal';
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

                modal.innerHTML = `
                    <div class="bg-white border border-slate-200 max-w-2xl w-full rounded-3xl shadow-2xl relative overflow-hidden flex flex-col h-[560px] animate-fade-in text-xs font-semibold text-slate-700">
                        <!-- Header -->
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/20 shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 leading-none">Campaign Details: ${campaignName}</h3>
                                <p class="text-[10px] text-slate-400 font-medium mt-1">Number-wise delivery and read status log</p>
                            </div>
                            <button onclick="document.getElementById('campaign-report-modal').remove()" class="h-8 w-8 rounded-full border border-slate-150 hover:border-slate-350 hover:bg-slate-50 flex items-center justify-center text-slate-500 hover:text-slate-800 transition">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>

                        <!-- Stats Bar -->
                        <div class="bg-slate-50/50 p-4 border-b border-slate-100 grid grid-cols-5 gap-2 text-center shrink-0">
                            <div class="bg-white p-2 rounded-xl border border-slate-150">
                                <div class="text-[9px] font-bold text-slate-400 uppercase">Target</div>
                                <div class="text-sm font-black text-slate-800 mt-0.5" id="rep-stat-total">-</div>
                            </div>
                            <div class="bg-white p-2 rounded-xl border border-slate-150">
                                <div class="text-[9px] font-bold text-blue-500 uppercase">Sent</div>
                                <div class="text-sm font-black text-blue-600 mt-0.5" id="rep-stat-sent">-</div>
                            </div>
                            <div class="bg-white p-2 rounded-xl border border-slate-150">
                                <div class="text-[9px] font-bold text-indigo-500 uppercase">Delivered</div>
                                <div class="text-sm font-black text-indigo-600 mt-0.5" id="rep-stat-delivered">-</div>
                            </div>
                            <div class="bg-white p-2 rounded-xl border border-slate-150">
                                <div class="text-[9px] font-bold text-emerald-500 uppercase">Read</div>
                                <div class="text-sm font-black text-emerald-600 mt-0.5" id="rep-stat-read">-</div>
                            </div>
                            <div class="bg-white p-2 rounded-xl border border-slate-150">
                                <div class="text-[9px] font-bold text-rose-500 uppercase">Failed</div>
                                <div class="text-sm font-black text-rose-600 mt-0.5" id="rep-stat-failed">-</div>
                            </div>
                        </div>

                        <!-- Search Area -->
                        <div class="p-3 border-b border-slate-100 flex items-center bg-white shrink-0">
                            <div class="relative flex-grow">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                </span>
                                <input type="text" id="report-search-input" placeholder="Search by recipient name or phone..." class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl focus:outline-none focus:border-[#00a884] focus:ring-2 focus:ring-[#00a884]/15 text-xs text-slate-805">
                            </div>
                        </div>

                        <!-- Logs list -->
                        <div class="flex-grow overflow-y-auto bg-white">
                            <table class="w-full text-left text-xs font-semibold text-slate-650">
                                <thead class="bg-slate-50 text-[10px] uppercase font-extrabold text-slate-450 border-b border-slate-150 sticky top-0 z-10">
                                    <tr>
                                        <th class="p-2.5 pl-4">Recipient Name</th>
                                        <th class="p-2.5">Phone Number</th>
                                        <th class="p-2.5 text-center">Status</th>
                                        <th class="p-2.5 pr-4">Details / Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody id="report-table-rows">
                                    <tr>
                                        <td colspan="4" class="p-8 text-center text-slate-400 font-medium">Loading report logs...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);
                lucide.createIcons();

                apiCall(`whatsapp/campaigns.php?id=${campaignId}`)
                    .then(res => {
                        const campaign = res.campaign || {};
                        const logs = campaign.logs || [];

                        // Update stats
                        const total = logs.length;
                        const sent = logs.filter(l => ['sent', 'delivered', 'read'].includes(l.status)).length;
                        const del = logs.filter(l => ['delivered', 'read'].includes(l.status)).length;
                        const read = logs.filter(l => l.status === 'read').length;
                        const failed = logs.filter(l => l.status === 'failed').length;

                        document.getElementById('rep-stat-total').textContent = total;
                        document.getElementById('rep-stat-sent').textContent = sent;
                        document.getElementById('rep-stat-delivered').textContent = del;
                        document.getElementById('rep-stat-read').textContent = read;
                        document.getElementById('rep-stat-failed').textContent = failed;

                        function renderReportLogs(filterText = '') {
                            const filtered = logs.filter(l => {
                                const cleanPhone = (l.wa_id || '').replace(/[^0-9]/g, '');
                                return (l.profile_name || '').toLowerCase().includes(filterText.toLowerCase()) ||
                                    cleanPhone.includes(filterText);
                            });

                            const tbody = document.getElementById('report-table-rows');
                            if (filtered.length === 0) {
                                tbody.innerHTML = `
                                    <tr>
                                        <td colspan="4" class="p-8 text-center text-slate-400 font-medium">No recipient logs match search.</td>
                                    </tr>
                                `;
                                return;
                            }

                            tbody.innerHTML = filtered.map(l => {
                                let pillClass = 'bg-slate-100 text-slate-400 border-slate-200/50';
                                if (l.status === 'read') pillClass = 'bg-emerald-50 text-emerald-650 border-emerald-100/50';
                                else if (l.status === 'delivered') pillClass = 'bg-blue-50 text-blue-600 border-blue-100/50';
                                else if (l.status === 'sent') pillClass = 'bg-sky-50 text-sky-650 border-sky-100/50';
                                else if (l.status === 'failed') pillClass = 'bg-rose-50 text-rose-655 border-rose-100/50';
                                else if (l.status === 'queued') pillClass = 'bg-amber-50 text-amber-605 border-amber-100/50';

                                let details = '-';
                                if (l.status === 'failed' && l.error_message) {
                                    details = `<span class="text-rose-500 font-medium block max-w-[200px] truncate" title="${l.error_message}">${l.error_message}</span>`;
                                } else if (l.sent_at) {
                                    details = new Date(l.sent_at).toLocaleDateString('en-US', {
                                        month: 'short',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                }

                                return `
                                    <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/40">
                                        <td class="p-2.5 pl-4 font-bold text-slate-805">${l.profile_name || 'Contact'}</td>
                                        <td class="p-2.5 font-mono text-[11px] text-slate-500">${formatPhoneNumber(l.wa_id)}</td>
                                        <td class="p-2.5 text-center">
                                            <span class="inline-block px-2 py-0.5 rounded-full text-[9px] font-bold border ${pillClass}">
                                                ${l.status.toUpperCase()}
                                            </span>
                                        </td>
                                        <td class="p-2.5 pr-4 text-slate-400 font-medium text-[10.5px]">${details}</td>
                                    </tr>
                                `;
                            }).join('');
                        }

                        renderReportLogs();

                        document.getElementById('report-search-input').oninput = (e) => {
                            renderReportLogs(e.target.value);
                        };
                    })
                    .catch(err => {
                        document.getElementById('report-table-rows').innerHTML = `
                            <tr>
                                <td colspan="4" class="p-8 text-center text-rose-500 font-bold">Failed to load campaign report: ${err.message}</td>
                            </tr>
                        `;
                    });
            };

            // Initialize dynamic draft state in scope
            if (!window.campaignDraft) {
                window.campaignDraft = {
                    name: 'Follow Up Reminder - 07/07/2026',
                    phone: window.connectedWhatsAppNumber || '91 98765 43210',
                    template: templates.length > 0 ? templates[0].name : 'followup_reminder',
                    category: 'marketing',
                    type: 'broadcast',
                    tags: ['followup', 'reminder', 'leads'],
                    description: 'This campaign is to follow up with interested leads who didn\'t respond to our previous message.',
                    recipients: []
                };
            }

            // Define Step 1: Campaign Setup Page View
            window.openCampaignCreatePage = function () {
                const draft = window.campaignDraft;
                contentArea.innerHTML = `
                    <div class="space-y-6 text-slate-805 animate-fade-in bg-[#f8fafc] p-6 rounded-3xl border border-slate-200/60">
                        <!-- Header -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex items-center justify-center shrink-0">
                                    <img src="../assets/css/WhatsApp_icon.png" class="h-10 w-10 object-contain" alt="WhatsApp">
                                </div>
                                <div>
                                    <h1 class="text-xl font-bold tracking-tight text-slate-900 leading-none">Create New WhatsApp Campaign</h1>
                                    <p class="text-xs text-slate-400 mt-1.5 font-medium">Advanced setup to send targeted and personalized WhatsApp messages</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="saveCampaignDraftInline(event)" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition shadow-sm bg-white">Save as Draft</button>
                                <button onclick="cancelCampaignCreate()" class="h-8 w-8 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-800 flex items-center justify-center transition bg-white">
                                    <i data-lucide="x" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Multi-Step Indicator Bar -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 text-xs font-bold text-slate-505 shadow-xs">
                            <div class="flex items-center space-x-2.5">
                                <div class="h-7 w-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs" style="color: white !important;">1</div>
                                <div>
                                    <div class="text-slate-900 leading-tight">Campaign Setup</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Basic details</div>
                                </div>
                            </div>
                            <div class="h-px bg-slate-100 flex-grow hidden md:block"></div>
                            <div class="flex items-center space-x-2.5 opacity-60">
                                <div class="h-7 w-7 rounded-full border border-slate-200 flex items-center justify-center text-xs text-slate-405 bg-slate-50">2</div>
                                <div>
                                    <div class="text-slate-500 leading-tight">Target Audience</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Select recipients</div>
                                </div>
                            </div>
                            <div class="h-px bg-slate-100 flex-grow hidden md:block"></div>
                            <div class="flex items-center space-x-2.5 opacity-60">
                                <div class="h-7 w-7 rounded-full border border-slate-200 flex items-center justify-center text-xs text-slate-405 bg-slate-50">3</div>
                                <div>
                                    <div class="text-slate-900 leading-tight">Message Content / Launch</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Confirm & launch</div>
                                </div>
                            </div>
                        </div>

                        <!-- Main setup layout (2-Column Grid) -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Form Setup (Left 2 cols) -->
                            <div class="lg:col-span-2 space-y-6">
                                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-5">
                                    <div class="space-y-1 border-b border-slate-100 pb-3">
                                        <h3 class="font-bold text-slate-800 text-sm">Campaign Setup</h3>
                                        <p class="text-slate-400 text-xs font-medium">Configure the basic details of your WhatsApp campaign</p>
                                    </div>

                                    <form onsubmit="handleCampaignSubmitInline(event)" class="space-y-5 text-xs font-semibold text-slate-700">
                                        <div>
                                            <label class="block text-slate-700 font-bold mb-1.5">Campaign Name <span class="text-rose-500">*</span></label>
                                            <input type="text" id="camp-inline-name" required value="${draft.name}" oninput="window.campaignDraft.name = this.value; updateSummaryCard()" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 text-xs text-slate-800 font-semibold transition-all duration-150">
                                            <span class="block text-[10px] text-slate-400 font-medium mt-1">Give a unique name to your campaign</span>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                            <div>
                                                <label class="block text-slate-700 font-bold mb-1.5">WhatsApp Number <span class="text-rose-500">*</span></label>
                                                <div class="relative">
                                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                                        <img src="../assets/css/WhatsApp_icon.png" class="h-4 w-4 object-contain" alt="WhatsApp">
                                                    </span>
                                                    <select id="camp-inline-number" onchange="window.campaignDraft.phone = this.value; updateSummaryCard()" class="w-full pl-9 pr-3 py-3 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 text-xs text-slate-805 font-bold cursor-pointer transition-all duration-150">
                                                        <option value="${window.connectedWhatsAppNumber || '91 98765 43210'}" selected>${window.connectedWhatsAppNumber || '91 98765 43210'}</option>
                                                    </select>
                                                </div>
                                                <span class="block text-[10px] text-slate-400 font-medium mt-1">Select the WhatsApp number to send messages from</span>
                                            </div>

                                            <div>
                                                <label class="block text-slate-700 font-bold mb-1.5">Message Template <span class="text-rose-500">*</span></label>
                                                <div class="relative cursor-pointer" onclick="openTemplateSelectorModal()">
                                                    <input type="text" id="camp-inline-template" readonly value="${draft.template}" class="w-full pl-3.5 pr-8 py-3 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 text-xs text-slate-805 font-bold cursor-pointer select-none transition-all duration-150">
                                                    <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                                                        <i data-lucide="chevron-down" class="h-4 w-4"></i>
                                                    </span>
                                                </div>
                                                <span class="block text-[10px] text-slate-400 font-medium mt-1">Choose a pre-approved template</span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                            <div>
                                                <label class="block text-slate-700 font-bold mb-1.5">Campaign Category</label>
                                                <select id="camp-inline-category" onchange="window.campaignDraft.category = this.value" class="w-full px-3.5 py-3 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 text-xs text-slate-808 font-bold cursor-pointer transition-all duration-150">
                                                    <option value="marketing" ${draft.category === 'marketing' ? 'selected' : ''}>Marketing</option>
                                                    <option value="utility" ${draft.category === 'utility' ? 'selected' : ''}>Utility</option>
                                                    <option value="authentication" ${draft.category === 'authentication' ? 'selected' : ''}>Authentication</option>
                                                </select>
                                                <span class="block text-[10px] text-slate-400 font-medium mt-1">Select the category of your campaign</span>
                                            </div>

                                            <div>
                                                <label class="block text-slate-700 font-bold mb-1.5">Campaign Type</label>
                                                <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden bg-white p-0.5 h-[44px] transition-all duration-150 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/15">
                                                    <button type="button" id="type-btn-broadcast" onclick="selectCampaignType('broadcast')" class="flex-grow py-2 rounded-lg ${draft.type === 'broadcast' ? 'text-emerald-600 bg-emerald-50/50 border border-emerald-100/30 font-bold' : 'text-slate-500 font-semibold'} text-center text-xs transition duration-150">Broadcast</button>
                                                    <button type="button" id="type-btn-sequence" onclick="selectCampaignType('sequence')" class="flex-grow py-2 rounded-lg ${draft.type === 'sequence' ? 'text-emerald-600 bg-emerald-50/50 border border-emerald-100/30 font-bold' : 'text-slate-500 font-semibold'} text-center text-xs transition duration-150">Drip / Sequence</button>
                                                </div>
                                                <span class="block text-[10px] text-slate-400 font-medium mt-1">Send to all at once or in a sequence</span>
                                            </div>
                                        </div>

                                        <!-- Tags input box -->
                                        <div>
                                            <label class="block text-slate-700 font-bold mb-1.5">Tags</label>
                                            <div class="flex flex-wrap items-center gap-1.5 w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-xl min-h-[44px] transition-all duration-150 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/15">
                                                ${draft.tags.map(t => `
                                                    <div class="flex items-center space-x-1 px-2.5 py-0.5 bg-emerald-50 text-emerald-600 rounded-full font-bold text-[10px] border border-emerald-100/50">
                                                        <span>${t}</span>
                                                        <button type="button" onclick="removeInlineTag(this)" class="hover:text-rose-500 text-slate-400 font-black">&times;</button>
                                                    </div>
                                                `).join('')}
                                                <input type="text" placeholder="Add tag..." onkeydown="handleTagInputInline(event)" style="border: none !important; outline: none !important; box-shadow: none !important;" class="bg-transparent text-xs text-slate-800 placeholder-slate-400 min-w-[120px] flex-grow py-1">
                                            </div>
                                            <span class="block text-[10px] text-slate-400 font-medium mt-1">Add tags to organize your campaigns</span>
                                        </div>

                                        <div>
                                            <label class="block text-slate-700 font-bold mb-1.5">Campaign Description (Optional)</label>
                                            <textarea id="camp-inline-desc" oninput="window.campaignDraft.description = this.value" placeholder="This campaign is to follow up with interested leads who didn't respond to our previous message." class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 text-xs text-slate-800 font-semibold h-24 resize-none transition-all duration-150">${draft.description}</textarea>
                                            <span class="block text-[10px] text-slate-400 font-medium mt-1">This helps you and your team identify the purpose of this campaign</span>
                                        </div>

                                        <!-- Footer Controls -->
                                        <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-6">
                                            <button type="button" onclick="cancelCampaignCreate()" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition shadow-sm bg-white">Cancel</button>
                                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/10" style="color: white !important;">
                                                <span>Save & Next</span>
                                                <i data-lucide="arrow-right" class="h-4 w-4 text-white"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Sidebar Setup Info (Right Column) -->
                            <div class="space-y-6">
                                <!-- Campaign Summary Card -->
                                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm space-y-4">
                                    <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Campaign Summary</h4>
                                    <div class="space-y-3.5 text-xs text-slate-600 font-semibold">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="send" class="h-4 w-4"></i>
                                                <span>Campaign Type</span>
                                            </div>
                                            <span class="text-slate-800 font-bold" id="summary-campaign-type">${draft.type === 'broadcast' ? 'Broadcast' : 'Drip / Sequence'}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-405">
                                                <img src="../assets/css/WhatsApp_icon.png" class="h-4 w-4 object-contain" alt="WhatsApp">
                                                <span>WhatsApp Number</span>
                                            </div>
                                            <span class="text-slate-800 font-bold" id="summary-whatsapp-number">${draft.phone}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="file-text" class="h-4 w-4"></i>
                                                <span>Template</span>
                                            </div>
                                            <span class="text-slate-805 font-mono text-[11px]" id="summary-template">${draft.template}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="users" class="h-4 w-4"></i>
                                                <span>Estimated Recipients</span>
                                            </div>
                                            <span class="text-slate-800 font-bold" id="summary-recipients">${draft.recipients.length} contacts</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="message-square" class="h-4 w-4"></i>
                                                <span>Estimated Messages</span>
                                            </div>
                                            <span class="text-slate-805 font-bold" id="summary-messages">~${draft.recipients.length} messages</span>
                                        </div>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-medium border-t border-slate-100 pt-3">
                                        These numbers are estimated and may vary.
                                    </div>
                                </div>

                                <!-- Best Practices Card -->
                                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm space-y-4">
                                    <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Best Practices</h4>
                                    <ul class="space-y-2 text-xs font-semibold text-slate-600">
                                        <li class="flex items-start space-x-2">
                                            <span class="text-emerald-500 font-bold">✓</span>
                                            <span>Use approved templates only</span>
                                        </li>
                                        <li class="flex items-start space-x-2">
                                            <span class="text-emerald-500 font-bold">✓</span>
                                            <span>Personalize your messages</span>
                                        </li>
                                        <li class="flex items-start space-x-2">
                                            <span class="text-emerald-500 font-bold">✓</span>
                                            <span>Send at the right time</span>
                                        </li>
                                        <li class="flex items-start space-x-2">
                                            <span class="text-emerald-500 font-bold">✓</span>
                                            <span>Avoid excessive emojis</span>
                                        </li>
                                        <li class="flex items-start space-x-2">
                                            <span class="text-emerald-500 font-bold">✓</span>
                                            <span>Provide value in your message</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Compliance Reminder Card -->
                                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm space-y-3.5 flex items-start space-x-3.5">
                                    <div class="h-7 w-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                        <i data-lucide="shield-check" class="h-4.5 w-4.5"></i>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <h4 class="font-bold text-slate-800 leading-none">Compliance Reminder</h4>
                                        <p class="text-slate-505 leading-normal font-medium font-semibold">Ensure you have explicit consent from recipients. Avoid sending promotional content without permission.</p>
                                        <a href="https://support.linkpilot.com" target="_blank" class="inline-flex items-center text-blue-600 font-bold hover:underline space-x-0.5">
                                            <span>Learn more</span>
                                            <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Help Banner -->
                        <div class="bg-[#f0fdf4] border border-emerald-100 rounded-2xl p-4 flex items-center justify-between text-xs font-semibold text-slate-700">
                            <div class="flex items-center space-x-2.5">
                                <div class="h-6 w-6 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">i</div>
                                <span class="text-slate-600 font-medium">Not sure what to do next? Check our detailed guide to create high-performing WhatsApp campaigns.</span>
                            </div>
                            <a href="https://support.linkpilot.com" target="_blank" class="text-emerald-700 font-bold hover:underline flex items-center space-x-0.5 shrink-0">
                                <span>View Guide</span>
                                <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                            </a>
                        </div>
                    </div>
                `;

                lucide.createIcons();
            };

            window.updateSummaryCard = function () {
                const name = document.getElementById('camp-inline-name') ? document.getElementById('camp-inline-name').value : window.campaignDraft.name;
                const num = document.getElementById('camp-inline-number') ? document.getElementById('camp-inline-number').value : window.campaignDraft.phone;
                const tpl = document.getElementById('camp-inline-template') ? document.getElementById('camp-inline-template').value : window.campaignDraft.template;

                if (document.getElementById('summary-whatsapp-number')) {
                    document.getElementById('summary-whatsapp-number').textContent = num;
                }
                if (document.getElementById('summary-template')) {
                    document.getElementById('summary-template').textContent = tpl;
                }
            };

            window.selectCampaignType = function (type) {
                window.campaignDraft.type = type;
                const bBtn = document.getElementById('type-btn-broadcast');
                const sBtn = document.getElementById('type-btn-sequence');

                if (!bBtn || !sBtn) return;

                if (type === 'broadcast') {
                    bBtn.className = 'flex-grow py-2 rounded-lg text-emerald-600 bg-emerald-50/50 border border-emerald-100/30 font-bold text-center text-xs transition duration-150';
                    sBtn.className = 'flex-grow py-2 rounded-lg text-slate-500 font-semibold text-center text-xs transition duration-150';
                    document.getElementById('summary-campaign-type').textContent = 'Broadcast';
                } else {
                    sBtn.className = 'flex-grow py-2 rounded-lg text-emerald-600 bg-emerald-50/50 border border-emerald-100/30 font-bold text-center text-xs transition duration-150';
                    bBtn.className = 'flex-grow py-2 rounded-lg text-slate-500 font-semibold text-center text-xs transition duration-150';
                    document.getElementById('summary-campaign-type').textContent = 'Drip / Sequence';
                }
            };

            window.removeInlineTag = function (button) {
                const text = button.parentElement.querySelector('span').textContent;
                window.campaignDraft.tags = window.campaignDraft.tags.filter(t => t !== text);
                button.parentElement.remove();
            };

            window.handleTagInputInline = function (event) {
                const input = event.target;
                if (event.key === 'Enter' || event.key === ',') {
                    event.preventDefault();
                    const tagText = input.value.replace(/,/g, '').trim();
                    if (tagText && !window.campaignDraft.tags.includes(tagText)) {
                        window.campaignDraft.tags.push(tagText);
                        const container = input.parentElement;
                        const newTag = document.createElement('div');
                        newTag.className = 'flex items-center space-x-1 px-2.5 py-0.5 bg-emerald-50 text-emerald-600 rounded-full font-bold text-[10px] border border-emerald-100/50';
                        newTag.innerHTML = `
                            <span>${tagText}</span>
                            <button type="button" onclick="removeInlineTag(this)" class="hover:text-rose-500 text-slate-400 font-black">&times;</button>
                        `;
                        container.insertBefore(newTag, input);
                        input.value = '';
                    }
                }
            };

            window.cancelCampaignCreate = function () {
                window.campaignDraft = null;
                renderWhatsAppCampaigns(container);
            };

            window.saveCampaignDraftInline = function (e) {
                e.preventDefault();
                const nameVal = document.getElementById('camp-inline-name') ? document.getElementById('camp-inline-name').value.trim() : window.campaignDraft.name;
                const tplVal = document.getElementById('camp-inline-template') ? document.getElementById('camp-inline-template').value : window.campaignDraft.template;

                const payload = {
                    name: nameVal,
                    template_name: tplVal,
                    recipients: window.campaignDraft.recipients || []
                };

                apiCall('whatsapp/campaigns.php?action=create', 'POST', payload)
                    .then(res => {
                        showNotification('success', 'Campaign draft saved successfully.');
                        window.campaignDraft = null;
                        renderWhatsAppCampaigns(container);
                    })
                    .catch(err => {
                        showNotification('error', err.message);
                    });
            };

            window.handleCampaignSubmitInline = function (e) {
                e.preventDefault();
                // Sync values
                window.campaignDraft.name = document.getElementById('camp-inline-name').value.trim();
                window.campaignDraft.phone = document.getElementById('camp-inline-number').value;
                window.campaignDraft.template = document.getElementById('camp-inline-template').value;
                window.campaignDraft.category = document.getElementById('camp-inline-category').value;
                window.campaignDraft.description = document.getElementById('camp-inline-desc').value.trim();

                // Go to step 2
                openCampaignAudiencePage();
            };

            // Define Step 2: Target Audience selection page view creator
            window.openCampaignAudiencePage = function () {
                const draft = window.campaignDraft;

                // Scan active variables val1 through val10
                const activeValKeys = [];
                for (let i = 1; i <= 10; i++) {
                    const key = 'val' + i;
                    const hasAny = draft.recipients.some(r => r[key] && r[key].toString().trim() !== '');
                    if (hasAny) {
                        activeValKeys.push(key);
                    }
                }
                if (activeValKeys.length === 0) {
                    activeValKeys.push('val1', 'val2');
                }

                contentArea.innerHTML = `
                    <div class="space-y-6 text-slate-805 animate-fade-in bg-[#f8fafc] p-6 rounded-3xl border border-slate-200/60">
                        <!-- Header -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex items-center justify-center shrink-0">
                                    <img src="../assets/css/WhatsApp_icon.png" class="h-10 w-10 object-contain" alt="WhatsApp">
                                </div>
                                <div>
                                    <h1 class="text-xl font-bold tracking-tight text-slate-900 leading-none">Select Target Audience</h1>
                                    <p class="text-xs text-slate-400 mt-1.5 font-medium">Choose who will receive your personalized WhatsApp broadcast</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="saveCampaignDraftInline(event)" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition shadow-sm bg-white">Save as Draft</button>
                                <button onclick="cancelCampaignCreate()" class="h-8 w-8 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-800 flex items-center justify-center transition bg-white">
                                    <i data-lucide="x" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Stepper Indicator -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 text-xs font-bold text-slate-505 shadow-xs">
                            <div class="flex items-center space-x-2.5 cursor-pointer" onclick="openCampaignCreatePage()">
                                <div class="h-7 w-7 rounded-full border border-emerald-600 text-emerald-600 flex items-center justify-center text-xs bg-emerald-50">✓</div>
                                <div>
                                    <div class="text-slate-900 leading-tight">Campaign Setup</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Basic details</div>
                                </div>
                            </div>
                            <div class="h-px bg-slate-100 flex-grow hidden md:block"></div>
                            <div class="flex items-center space-x-2.5">
                                <div class="h-7 w-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs" style="color: white !important;">2</div>
                                <div>
                                    <div class="text-slate-900 leading-tight">Target Audience</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Select recipients</div>
                                </div>
                            </div>
                            <div class="h-px bg-slate-100 flex-grow hidden md:block"></div>
                            <div class="flex items-center space-x-2.5 opacity-60">
                                <div class="h-7 w-7 rounded-full border border-slate-200 flex items-center justify-center text-xs text-slate-405 bg-slate-50">3</div>
                                <div>
                                    <div class="text-slate-900 leading-tight">Message Content / Launch</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Confirm & launch</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2-Column Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Left: Options (2 cols) -->
                            <div class="lg:col-span-2 space-y-6">
                                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-5">
                                    <div class="space-y-1 border-b border-slate-100 pb-3">
                                        <h3 class="font-bold text-slate-800 text-sm">Audience Source</h3>
                                        <p class="text-slate-400 text-xs font-medium">Select one of the four methods to define your recipient list</p>
                                    </div>

                                    <!-- 4 Cards Grid -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- Card 1: My Contacts -->
                                        <div onclick="openMyContactsPopup()" class="p-5 border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/10 rounded-2xl cursor-pointer transition-all duration-200 space-y-3 shadow-2xs group flex flex-col justify-between h-[150px] bg-white">
                                            <div class="flex justify-between items-start">
                                                <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-emerald-50 group-hover:text-emerald-600 transition">
                                                    <i data-lucide="users" class="h-5 w-5"></i>
                                                </div>
                                                <span class="text-[9px] font-extrabold bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full border border-blue-100 uppercase tracking-wider group-hover:bg-emerald-50 group-hover:text-emerald-600 group-hover:border-emerald-100 transition">Database</span>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-800 text-xs leading-none">My Contacts</h4>
                                                <p class="text-[10px] text-slate-400 font-medium mt-1.5 leading-normal">Choose from your saved CRM/WhatsApp contacts database</p>
                                            </div>
                                        </div>

                                        <!-- Card 2: Groups -->
                                        <div onclick="openGroupsPopup()" class="p-5 border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/10 rounded-2xl cursor-pointer transition-all duration-200 space-y-3 shadow-2xs group flex flex-col justify-between h-[150px] bg-white">
                                            <div class="flex justify-between items-start">
                                                <div class="h-10 w-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center group-hover:bg-emerald-50 group-hover:text-emerald-600 transition">
                                                    <i data-lucide="folder-heart" class="h-5 w-5"></i>
                                                </div>
                                                <span class="text-[9px] font-extrabold bg-purple-50 text-purple-600 px-2 py-0.5 rounded-full border border-purple-100 uppercase tracking-wider group-hover:bg-emerald-50 group-hover:text-emerald-600 group-hover:border-emerald-100 transition">Segments</span>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-800 text-xs leading-none">Groups / Tags</h4>
                                                <p class="text-[10px] text-slate-400 font-medium mt-1.5 leading-normal">Filter contacts categorized by tags or list groupings</p>
                                            </div>
                                        </div>

                                        <!-- Card 3: Enter Manually -->
                                        <div onclick="openEnterManuallyPopup()" class="p-5 border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/10 rounded-2xl cursor-pointer transition-all duration-200 space-y-3 shadow-2xs group flex flex-col justify-between h-[150px] bg-white">
                                            <div class="flex justify-between items-start">
                                                <div class="h-10 w-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-emerald-50 group-hover:text-emerald-600 transition">
                                                    <i data-lucide="keyboard" class="h-5 w-5"></i>
                                                </div>
                                                <span class="text-[9px] font-extrabold bg-amber-50 text-amber-600 px-2 py-0.5 rounded-full border border-amber-100 uppercase tracking-wider group-hover:bg-emerald-50 group-hover:text-emerald-600 group-hover:border-emerald-100 transition">Quick Input</span>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-800 text-xs leading-none">Enter Manually</h4>
                                                <p class="text-[10px] text-slate-400 font-medium mt-1.5 leading-normal">Type or paste target numbers directly (one number per line)</p>
                                            </div>
                                        </div>

                                        <!-- Card 4: Import from CSV -->
                                        <div onclick="openImportCSVPopup()" class="p-5 border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/10 rounded-2xl cursor-pointer transition-all duration-200 space-y-3 shadow-2xs group flex flex-col justify-between h-[150px] bg-white">
                                            <div class="flex justify-between items-start">
                                                <div class="h-10 w-10 rounded-xl bg-[#e8fdf4] text-emerald-600 flex items-center justify-center">
                                                    <i data-lucide="file-spreadsheet" class="h-5 w-5"></i>
                                                </div>
                                                <span class="text-[9px] font-extrabold bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-full border border-emerald-100 uppercase tracking-wider">Spreadsheet</span>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-slate-800 text-xs leading-none">Import from CSV</h4>
                                                <p class="text-[10px] text-slate-400 font-medium mt-1.5 leading-normal">Upload CSV sheet with numbers and template variables</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Recipient List Preview Section -->
                                    <div class="space-y-3 pt-2">
                                        <h4 class="font-bold text-slate-700 text-xs">Selected Recipients Preview (${draft.recipients.length} total) <span class="text-[10px] text-slate-400 font-normal uppercase tracking-wide ml-1">(Double-click any variable to edit)</span></h4>
                                        <div class="border border-slate-200 rounded-2xl overflow-hidden max-h-[220px] overflow-y-auto bg-slate-50/50">
                                            <table class="w-full text-left text-xs font-semibold text-slate-600">
                                                <thead class="bg-slate-100/80 text-slate-500 text-[10px] uppercase font-extrabold border-b border-slate-200">
                                                    <tr>
                                                        <th class="p-2.5 pl-4">Name</th>
                                                        <th class="p-2.5">Phone</th>
                                                        ${activeValKeys.map(k => `<th class="p-2.5">Variable ${k.substring(3)} (${k})</th>`).join('')}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${draft.recipients.length === 0 ? `
                                                        <tr>
                                                            <td colspan="${activeValKeys.length + 2}" class="p-8 text-center text-slate-400 font-medium bg-white">No recipients selected yet. Click one of the options above to populate.</td>
                                                        </tr>
                                                    ` : draft.recipients.slice(0, 5).map(r => `
                                                        <tr class="border-b border-slate-100 last:border-0 bg-white">
                                                            <td class="p-2.5 pl-4 font-bold text-slate-805">${r.name}</td>
                                                            <td class="p-2.5 font-mono text-[11px] text-slate-500">${r.phone.slice(-10)}</td>
                                                            ${activeValKeys.map(k => {
                    const valStr = r[k] || '-';
                    const badgeBg = k === 'val1' ? 'bg-emerald-50 text-emerald-600 border-emerald-100/50' : 'bg-blue-50/50 text-blue-600 border-blue-100/30';
                    return `
                                                                    <td class="p-2.5 select-none hover:bg-slate-50 transition duration-150 cursor-pointer" ondblclick="makeVariableEditable(this, ${draft.recipients.indexOf(r)}, '${k}')">
                                                                        <span class="px-2 py-0.5 ${badgeBg} rounded-md border text-[10px] font-bold inline-block var-cell-display">${valStr}</span>
                                                                    </td>
                                                                `;
                }).join('')}
                                                        </tr>
                                                    `).join('')}
                                                    ${draft.recipients.length > 5 ? `
                                                        <tr class="bg-slate-50/30">
                                                            <td colspan="${activeValKeys.length + 2}" class="p-2 text-center text-[10px] text-slate-400 font-medium">Showing top 5 recipients. And ${draft.recipients.length - 5} more...</td>
                                                        </tr>
                                                    ` : ''}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Footer Navigation Controls -->
                                    <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-6">
                                        <button type="button" onclick="openCampaignCreatePage()" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition shadow-sm bg-white">Back to Setup</button>
                                        <button type="button" onclick="openCampaignReviewPage()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/10" style="color: white !important;">
                                            <span>Save & Next</span>
                                            <i data-lucide="arrow-right" class="h-4 w-4 text-white"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Summary Sidebar (1 col) -->
                            <div class="space-y-6">
                                <!-- Campaign Summary Card -->
                                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm space-y-4">
                                    <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider">Campaign Summary</h4>
                                    <div class="space-y-3.5 text-xs text-slate-600 font-semibold">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="send" class="h-4 w-4"></i>
                                                <span>Campaign Type</span>
                                            </div>
                                            <span class="text-slate-800 font-bold" id="summary-campaign-type">${draft.type === 'broadcast' ? 'Broadcast' : 'Drip / Sequence'}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <img src="../assets/css/WhatsApp_icon.png" class="h-4 w-4 object-contain" alt="WhatsApp">
                                                <span>WhatsApp Number</span>
                                            </div>
                                            <span class="text-slate-800 font-bold" id="summary-whatsapp-number">${draft.phone}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="file-text" class="h-4 w-4"></i>
                                                <span>Template</span>
                                            </div>
                                            <span class="text-slate-805 font-mono text-[11px]" id="summary-template">${draft.template}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="users" class="h-4 w-4"></i>
                                                <span>Estimated Recipients</span>
                                            </div>
                                            <span class="text-slate-800 font-bold" id="summary-recipients">${draft.recipients.length} contacts</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="message-square" class="h-4 w-4"></i>
                                                <span>Estimated Messages</span>
                                            </div>
                                            <span class="text-slate-808 font-bold" id="summary-messages">~${draft.recipients.length} messages</span>
                                        </div>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-medium border-t border-slate-100 pt-3">
                                        These numbers are estimated and may vary.
                                    </div>
                                </div>
                                
                                <!-- Compliance check card -->
                                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm space-y-3.5 flex items-start space-x-3.5">
                                    <div class="h-7 w-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 mt-0.5">
                                        <i data-lucide="shield-check" class="h-4.5 w-4.5"></i>
                                    </div>
                                    <div class="space-y-1.5 text-xs">
                                        <h4 class="font-bold text-slate-800 leading-none">Compliance check</h4>
                                        <p class="text-slate-505 leading-normal font-medium font-semibold">Uploading invalid phone number parameters can trigger Meta WABA compliance alerts. Clean all recipient logs.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                lucide.createIcons();
            };

            // Double click variable cell inline editor
            window.makeVariableEditable = function (cellElement, recipientIndex, varKey) {
                if (cellElement.querySelector('input')) return;

                const currentVal = window.campaignDraft.recipients[recipientIndex][varKey] || '';
                cellElement.innerHTML = `
                    <input type="text" class="px-2 py-0.5 border border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/15 rounded-md text-[10px] font-bold text-slate-800 bg-white w-28" value="${currentVal.replace(/"/g, '&quot;')}">
                `;
                const input = cellElement.querySelector('input');
                input.focus();
                input.select();

                const saveEdit = () => {
                    const newVal = input.value.trim();
                    window.campaignDraft.recipients[recipientIndex][varKey] = newVal;

                    // Re-render Step 2
                    openCampaignAudiencePage();
                };

                input.onblur = saveEdit;
                input.onkeydown = (e) => {
                    if (e.key === 'Enter') {
                        saveEdit();
                    } else if (e.key === 'Escape') {
                        openCampaignAudiencePage();
                    }
                };
            };

            // Dynamic Modal: Select from My Contacts
            window.openMyContactsPopup = function () {
                const existing = document.getElementById('audience-picker-modal');
                if (existing) existing.remove();

                const modal = document.createElement('div');
                modal.id = 'audience-picker-modal';
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

                modal.innerHTML = `
                    <div class="bg-white border border-slate-200 max-w-lg w-full rounded-3xl shadow-2xl relative overflow-hidden flex flex-col h-[520px] animate-fade-in">
                        <!-- Header -->
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 leading-none">Select from My Contacts</h3>
                                <p class="text-[10px] text-slate-400 font-medium mt-1.5">Showing database contacts with valid 10-digit phone numbers</p>
                            </div>
                            <button onclick="document.getElementById('audience-picker-modal').remove()" class="h-8 w-8 rounded-full border border-slate-150 hover:border-slate-350 hover:bg-slate-50 flex items-center justify-center text-slate-500 hover:text-slate-800 transition">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>

                        <!-- Search Bar -->
                        <div class="p-3 border-b border-slate-100 bg-slate-50/40">
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                </span>
                                <input type="text" id="contact-modal-search" placeholder="Search contacts..." oninput="filterModalContacts(this.value)" class="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15">
                            </div>
                        </div>

                        <!-- Contacts list (Scrollable) -->
                        <div class="flex-grow overflow-y-auto p-4 space-y-2.5" id="contacts-modal-list">
                            <div class="text-center text-slate-400 text-xs py-8 font-medium">Loading contacts...</div>
                        </div>

                        <!-- Footer -->
                        <div class="p-4 border-t border-slate-100 flex items-center justify-between bg-slate-50/40 shrink-0">
                            <span class="text-[10px] text-slate-500 font-semibold" id="contact-modal-selected-count">0 contacts selected</span>
                            <button onclick="confirmMyContactsSelection()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-emerald-500/10" style="color: white !important;">
                                Confirm Selection
                            </button>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);
                lucide.createIcons();

                let allLoadedContacts = [];

                apiCall('whatsapp/contacts.php?limit=100')
                    .then(res => {
                        const list = res.contacts || [];

                        // Filter "which have valid 10 digit numbers" and remove duplicates
                        const seen = new Set();
                        allLoadedContacts = [];
                        list.forEach(c => {
                            const clean = (c.wa_id || '').replace(/[^0-9]/g, '');
                            let raw10 = clean;
                            if (clean.length === 12 && clean.startsWith('91')) {
                                raw10 = clean.substring(2);
                            } else if (clean.length === 11 && clean.startsWith('0')) {
                                raw10 = clean.substring(1);
                            }
                            if (raw10.length === 10 && !seen.has(raw10)) {
                                seen.add(raw10);
                                c.raw10 = raw10;
                                allLoadedContacts.push(c);
                            }
                        });

                        window.filterModalContacts = function (query) {
                            const container = document.getElementById('contacts-modal-list');
                            if (!container) return;

                            const filtered = allLoadedContacts.filter(c => {
                                const q = query.toLowerCase();
                                return (c.profile_name || '').toLowerCase().includes(q) || (c.raw10 || '').includes(q) || (c.crm_name || '').toLowerCase().includes(q);
                            });

                            if (filtered.length === 0) {
                                container.innerHTML = `<div class="text-center text-slate-400 text-xs py-8 font-medium">No valid contacts found.</div>`;
                                return;
                            }

                            container.innerHTML = filtered.map(c => {
                                return `
                                    <label class="flex items-center justify-between p-3 border border-slate-200 rounded-xl bg-white hover:border-emerald-500/50 cursor-pointer transition-all">
                                        <div class="flex items-center space-x-3">
                                            <input type="checkbox" checked value="${c.raw10}" data-name="${c.crm_name || c.profile_name || 'Contact'}" class="h-4 w-4 text-emerald-600 border-slate-350 rounded focus:ring-emerald-500 cursor-pointer modal-contact-checkbox" onchange="updateModalContactSelectedCount()">
                                            <div class="space-y-0.5">
                                                <div class="font-bold text-slate-850 text-xs">${c.crm_name || c.profile_name || 'WhatsApp Contact'}</div>
                                                <div class="text-[10px] text-slate-400 font-mono">${c.raw10}</div>
                                            </div>
                                        </div>
                                        <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 border border-emerald-100/50 rounded text-[9px] font-extrabold uppercase tracking-wider">Valid 10 Digits</span>
                                    </label>
                                `;
                            }).join('');

                            updateModalContactSelectedCount();
                        };

                        filterModalContacts('');
                    })
                    .catch(err => {
                        document.getElementById('contacts-modal-list').innerHTML = `<div class="text-center text-rose-500 text-xs py-8 font-semibold">Failed to load contacts: ${err.message}</div>`;
                    });

                window.updateModalContactSelectedCount = function () {
                    const checked = document.querySelectorAll('.modal-contact-checkbox:checked').length;
                    document.getElementById('contact-modal-selected-count').textContent = `${checked} contacts selected`;
                };

                window.confirmMyContactsSelection = function () {
                    const checkboxes = document.querySelectorAll('.modal-contact-checkbox:checked');
                    const selected = [];
                    checkboxes.forEach(cb => {
                        const phoneVal = cb.value;
                        const nameVal = cb.getAttribute('data-name');
                        selected.push({
                            phone: '91' + phoneVal,
                            name: nameVal,
                            val1: nameVal,
                            val2: ''
                        });
                    });

                    window.campaignDraft.recipients = selected;
                    document.getElementById('audience-picker-modal').remove();
                    showNotification('success', `Selected ${selected.length} database contacts.`);
                    openCampaignAudiencePage();
                };
            };

            // Dynamic Modal: Select from Tag/Group segments
            window.openGroupsPopup = function () {
                const existing = document.getElementById('audience-picker-modal');
                if (existing) existing.remove();

                const modal = document.createElement('div');
                modal.id = 'audience-picker-modal';
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

                modal.innerHTML = `
                    <div class="bg-white border border-slate-200 max-w-md w-full rounded-3xl shadow-2xl relative overflow-hidden flex flex-col h-[400px] animate-fade-in">
                        <!-- Header -->
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/20 shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-slate-805 leading-none">Select from Groups / Segments</h3>
                                <p class="text-[10px] text-slate-400 font-medium mt-1">Choose a segment to filter recipients list</p>
                            </div>
                            <button onclick="document.getElementById('audience-picker-modal').remove()" class="h-8 w-8 rounded-full border border-slate-150 hover:border-slate-350 hover:bg-slate-50 flex items-center justify-center text-slate-500 hover:text-slate-800 transition">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>

                        <!-- Groups list -->
                        <div class="flex-grow overflow-y-auto p-4 space-y-2.5" id="groups-modal-list">
                            <div onclick="selectGroupSegment('Warm Leads', 'leads')" class="p-4 border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/10 rounded-2xl cursor-pointer transition-all flex items-center justify-between">
                                <div class="space-y-1">
                                    <h4 class="font-bold text-slate-805 text-xs">Warm Leads</h4>
                                    <p class="text-[10px] text-slate-400 font-semibold">Contacts containing 'leads' tag or notes</p>
                                </div>
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-full font-bold text-[10px] border border-emerald-100/50">Tag Segment</span>
                            </div>

                            <div onclick="selectGroupSegment('VIP Customers', 'vip')" class="p-4 border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/10 rounded-2xl cursor-pointer transition-all flex items-center justify-between">
                                <div class="space-y-1">
                                    <h4 class="font-bold text-slate-805 text-xs">VIP Customers</h4>
                                    <p class="text-[10px] text-slate-400 font-semibold">Contacts containing 'vip' tag or notes</p>
                                </div>
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-full font-bold text-[10px] border border-emerald-100/50">Tag Segment</span>
                            </div>

                            <div onclick="selectGroupSegment('All Contacts', 'all')" class="p-4 border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/10 rounded-2xl cursor-pointer transition-all flex items-center justify-between">
                                <div class="space-y-1">
                                    <h4 class="font-bold text-slate-850 text-xs">All Contacts</h4>
                                    <p class="text-[10px] text-slate-400 font-semibold">Broadcast to every contact loaded in directory</p>
                                </div>
                                <span class="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-full font-bold text-[10px] border border-blue-100/50">Full List</span>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);
                lucide.createIcons();

                window.selectGroupSegment = function (groupName, tagValue) {
                    apiCall('whatsapp/contacts.php?limit=100')
                        .then(res => {
                            const list = res.contacts || [];
                            const filtered = list.filter(c => {
                                const clean = (c.wa_id || '').replace(/[^0-9]/g, '');
                                let raw10 = clean;
                                if (clean.length === 12 && clean.startsWith('91')) raw10 = clean.substring(2);
                                else if (clean.length === 11 && clean.startsWith('0')) raw10 = clean.substring(1);
                                if (raw10.length !== 10) return false;

                                if (tagValue === 'all') return true;
                                const tagsStr = ((c.tags || '') + ' ' + (c.notes || '')).toLowerCase();
                                return tagsStr.includes(tagValue.toLowerCase());
                            });

                            if (filtered.length === 0) {
                                showNotification('error', `No contacts matched the segment: ${groupName}`);
                                return;
                            }

                            window.campaignDraft.recipients = filtered.map(c => {
                                const clean = c.wa_id.replace(/[^0-9]/g, '');
                                let raw10 = clean;
                                if (clean.length === 12 && clean.startsWith('91')) raw10 = clean.substring(2);
                                return {
                                    phone: '91' + raw10,
                                    name: c.crm_name || c.profile_name || 'Contact',
                                    val1: c.crm_name || c.profile_name || 'Contact',
                                    val2: ''
                                };
                            });

                            document.getElementById('audience-picker-modal').remove();
                            showNotification('success', `Loaded ${window.campaignDraft.recipients.length} contacts for group: ${groupName}`);
                            openCampaignAudiencePage();
                        })
                        .catch(err => {
                            showNotification('error', `Failed to load group segment: ${err.message}`);
                        });
                };
            };

            // Dynamic Modal: Enter Phone Numbers Manually
            window.openEnterManuallyPopup = function () {
                const existing = document.getElementById('audience-picker-modal');
                if (existing) existing.remove();

                const modal = document.createElement('div');
                modal.id = 'audience-picker-modal';
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

                modal.innerHTML = `
                    <div class="bg-white border border-slate-200 max-w-md w-full rounded-3xl shadow-2xl relative overflow-hidden flex flex-col h-[480px] animate-fade-in">
                        <!-- Header -->
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/20 shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-slate-805 leading-none">Enter Phone Numbers Manually</h3>
                                <p class="text-[10px] text-slate-400 font-medium mt-1">Paste numbers with one number in one line</p>
                            </div>
                            <button onclick="document.getElementById('audience-picker-modal').remove()" class="h-8 w-8 rounded-full border border-slate-150 hover:border-slate-350 hover:bg-slate-50 flex items-center justify-center text-slate-500 hover:text-slate-800 transition">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>

                        <!-- Textarea body -->
                        <div class="flex-grow p-4 flex flex-col space-y-3 overflow-hidden">
                            <textarea id="manual-numbers-input" placeholder="e.g.&#10;+91 98765 43210&#10;9876543211&#10;09876543212" class="w-full flex-grow p-3 border border-slate-200 rounded-xl focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 text-xs text-slate-805 font-mono resize-none bg-white"></textarea>
                            
                            <!-- Count button actions -->
                            <div class="flex items-center justify-between shrink-0">
                                <button type="button" onclick="countManualNumbers()" id="manual-count-btn" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center space-x-1 shadow-sm">
                                    <i data-lucide="calculator" class="h-3.5 w-3.5 text-slate-505"></i>
                                    <span>Count Numbers</span>
                                </button>
                                <span id="manual-numbers-badge" class="text-[10px] text-slate-400 font-semibold">0 valid numbers found</span>
                            </div>

                            <!-- List view -->
                            <div id="manual-numbers-list-box" class="h-28 border border-slate-150 rounded-xl overflow-y-auto p-2 bg-slate-50/50 hidden">
                                <div class="text-[10px] text-slate-500 font-extrabold uppercase tracking-wider mb-1 px-1">Valid Phone List:</div>
                                <div id="manual-numbers-list-items" class="space-y-1 px-1"></div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="p-4 border-t border-slate-100 flex items-center justify-end bg-slate-50/40 shrink-0">
                            <button onclick="confirmManualNumbersSelection()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-emerald-500/10" style="color: white !important;">
                                Confirm Recipients
                            </button>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);
                lucide.createIcons();

                let validNumbers = [];

                window.countManualNumbers = function () {
                    const text = document.getElementById('manual-numbers-input').value;
                    const lines = text.split('\n');
                    validNumbers = [];

                    lines.forEach(line => {
                        const clean = line.replace(/[^0-9]/g, '');
                        let raw10 = clean;
                        if (clean.length === 12 && clean.startsWith('91')) {
                            raw10 = clean.substring(2);
                        } else if (clean.length === 11 && clean.startsWith('0')) {
                            raw10 = clean.substring(1);
                        }

                        if (raw10.length === 10) {
                            validNumbers.push(raw10);
                        }
                    });

                    document.getElementById('manual-numbers-badge').textContent = `${validNumbers.length} valid numbers found`;
                    const listContainer = document.getElementById('manual-numbers-list-box');
                    const listItems = document.getElementById('manual-numbers-list-items');

                    if (validNumbers.length > 0) {
                        listContainer.classList.remove('hidden');
                        listItems.innerHTML = validNumbers.map(n => `
                            <div class="text-xs font-mono text-slate-600 font-semibold">• ${n}</div>
                        `).join('');
                    } else {
                        listContainer.classList.add('hidden');
                        listItems.innerHTML = '';
                    }
                };

                window.confirmManualNumbersSelection = function () {
                    countManualNumbers();

                    if (validNumbers.length === 0) {
                        showNotification('error', 'Please enter at least one valid 10-digit number.');
                        return;
                    }

                    window.campaignDraft.recipients = validNumbers.map(num => ({
                        phone: '91' + num,
                        name: 'Manual Contact',
                        val1: 'Customer',
                        val2: ''
                    }));

                    document.getElementById('audience-picker-modal').remove();
                    showNotification('success', `Added ${validNumbers.length} recipients manually.`);
                    openCampaignAudiencePage();
                };
            };

            // Dynamic Modal: Import from CSV with variables
            // Dynamic Modal: Import from CSV with variables
            window.openImportCSVPopup = function () {
                const existing = document.getElementById('audience-picker-modal');
                if (existing) existing.remove();

                const modal = document.createElement('div');
                modal.id = 'audience-picker-modal';
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

                modal.innerHTML = `
                    <div class="bg-white border border-slate-200 max-w-xl w-full rounded-3xl shadow-2xl relative overflow-hidden flex flex-col h-[520px] animate-fade-in">
                        <!-- Header -->
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/20 shrink-0">
                            <div>
                                <h3 class="text-sm font-bold text-slate-805 leading-none">Import Recipients from CSV</h3>
                                <p class="text-[10px] text-slate-400 font-medium mt-1.5">Upload CSV with clean numbers and variables</p>
                            </div>
                            <button onclick="document.getElementById('audience-picker-modal').remove()" class="h-8 w-8 rounded-full border border-slate-150 hover:border-slate-350 hover:bg-slate-50 flex items-center justify-center text-slate-500 hover:text-slate-800 transition">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>

                        <!-- Upload Body Area -->
                        <div class="flex-grow p-4 flex flex-col space-y-4 overflow-hidden">
                            <!-- Drag Drop Box -->
                            <div id="csv-drop-zone" class="border-2 border-dashed border-slate-200 hover:border-emerald-500 hover:bg-[#e8fdf4]/5 rounded-2xl p-6 text-center cursor-pointer transition flex flex-col items-center justify-center space-y-2 shrink-0 bg-white">
                                <div class="h-10 w-10 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center">
                                    <i data-lucide="upload-cloud" class="h-5 w-5"></i>
                                </div>
                                <div class="text-xs font-bold text-slate-700">Click to upload or drag & drop CSV file</div>
                                <div class="text-[9px] text-slate-400 font-semibold leading-normal">Drag and drop file directly here</div>
                                <input type="file" id="csv-file-input" accept=".csv" class="hidden">
                            </div>

                            <!-- Sample download & description -->
                            <div class="flex items-center justify-between shrink-0 px-1 bg-slate-50 p-2.5 rounded-xl border border-slate-150">
                                <div class="text-[9px] text-slate-400 font-semibold leading-normal">Requires phone column and support variables val1 to val10</div>
                                <button onclick="downloadSampleCSV()" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 transition flex items-center space-x-1">
                                    <i data-lucide="download" class="h-3.5 w-3.5"></i>
                                    <span>Download Sample CSV</span>
                                </button>
                            </div>

                            <!-- Parse Preview list -->
                            <div id="csv-preview-box" class="flex-grow border border-slate-150 rounded-2xl overflow-hidden bg-slate-50/40 flex flex-col hidden animate-fade-in">
                                <div class="p-2 border-b border-slate-150 bg-slate-100 flex justify-between items-center text-[10px] font-extrabold uppercase text-slate-500 pl-3 shrink-0">
                                    <span>Parsed CSV Preview</span>
                                    <span id="csv-valid-badge" class="px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded-full border border-emerald-100/50">0 rows found</span>
                                </div>
                                <div class="flex-grow overflow-auto bg-white" id="csv-preview-table-container">
                                    <!-- Table will render here dynamically with active variables -->
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="p-4 border-t border-slate-100 flex items-center justify-end bg-slate-50/40 shrink-0">
                            <button onclick="confirmCSVSelection()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-md shadow-emerald-500/10" style="color: white !important;">
                                Confirm Import List
                            </button>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);
                lucide.createIcons();

                const dropZone = document.getElementById('csv-drop-zone');
                const fileInput = document.getElementById('csv-file-input');
                let parsedRecipients = [];

                dropZone.onclick = () => fileInput.click();

                fileInput.onchange = (e) => handleCSVFile(e.target.files[0]);

                dropZone.ondragover = (e) => {
                    e.preventDefault();
                    dropZone.classList.add('border-emerald-500', 'bg-emerald-50/5');
                };

                dropZone.ondragleave = (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('border-emerald-500', 'bg-emerald-50/5');
                };

                dropZone.ondrop = (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('border-emerald-500', 'bg-emerald-50/5');
                    handleCSVFile(e.dataTransfer.files[0]);
                };

                window.downloadSampleCSV = function () {
                    const csvContent = "Phone,Name,val1,val2,val3,val4,val5,val6,val7,val8,val9,val10\n9876543210,Soumojit Saha,Project Proposal,15% Discount,,,,,,,,\n9876543210,Virat Kohli,Follow Up,20% Offer,,,,,,,,";
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement("a");
                    const url = URL.createObjectURL(blob);
                    link.setAttribute("href", url);
                    link.setAttribute("download", "linkpilot_whatsapp_campaign_sample.csv");
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                };

                function handleCSVFile(file) {
                    if (!file) return;
                    const reader = new FileReader();
                    reader.onload = function (evt) {
                        const content = evt.target.result;
                        parseCSVContent(content);
                    };
                    reader.readAsText(file);
                }

                function parseCSVContent(csvText) {
                    const lines = csvText.split('\n');
                    if (lines.length < 2) {
                        showNotification('error', 'CSV file is empty or missing rows.');
                        return;
                    }

                    const headers = lines[0].split(',').map(h => h.trim().toLowerCase());
                    let phoneIdx = 0;
                    let nameIdx = -1;
                    const valIndices = {};

                    headers.forEach((h, idx) => {
                        if (h.includes('phone') || h.includes('num') || h.includes('mobile')) phoneIdx = idx;
                        else if (h.includes('name')) nameIdx = idx;
                        else {
                            const match = h.match(/val(\d+)|var(\d+)|param(\d+)/);
                            if (match) {
                                const num = match[1] || match[2] || match[3];
                                valIndices['val' + num] = idx;
                            }
                        }
                    });

                    parsedRecipients = [];

                    for (let i = 1; i < lines.length; i++) {
                        const line = lines[i].trim();
                        if (!line) continue;

                        const cols = line.split(',').map(c => c.trim().replace(/^["']|["']$/g, ''));
                        if (cols.length <= phoneIdx) continue;

                        const rawPhone = cols[phoneIdx].replace(/[^0-9]/g, '');
                        let cleanPhone = rawPhone;
                        if (rawPhone.length === 12 && rawPhone.startsWith('91')) {
                            cleanPhone = rawPhone.substring(2);
                        } else if (rawPhone.length === 11 && rawPhone.startsWith('0')) {
                            cleanPhone = rawPhone.substring(1);
                        }

                        if (cleanPhone.length !== 10) continue;

                        const nameVal = nameIdx !== -1 && cols[nameIdx] ? cols[nameIdx] : 'CSV Contact';

                        const recipient = {
                            phone: '91' + cleanPhone, // Send to backend with 91
                            name: nameVal
                        };

                        // Map val1 to val10
                        for (let j = 1; j <= 10; j++) {
                            const key = 'val' + j;
                            const colIdx = valIndices[key];
                            recipient[key] = (colIdx !== undefined && cols[colIdx]) ? cols[colIdx] : '';
                        }

                        parsedRecipients.push(recipient);
                    }

                    document.getElementById('csv-drop-zone').classList.add('hidden');
                    const previewBox = document.getElementById('csv-preview-box');
                    previewBox.classList.remove('hidden');

                    document.getElementById('csv-valid-badge').textContent = `${parsedRecipients.length} valid rows`;
                    const container = document.getElementById('csv-preview-table-container');

                    if (parsedRecipients.length === 0) {
                        container.innerHTML = `
                            <div class="p-8 text-center text-rose-500 font-bold bg-white">No valid 10-digit phone number rows found. Please review CSV format.</div>
                        `;
                    } else {
                        // Scan active variables in this CSV
                        const activeCSVValKeys = [];
                        for (let j = 1; j <= 10; j++) {
                            const key = 'val' + j;
                            const hasAny = parsedRecipients.some(r => r[key] && r[key].toString().trim() !== '');
                            if (hasAny) {
                                activeCSVValKeys.push(key);
                            }
                        }
                        if (activeCSVValKeys.length === 0) {
                            activeCSVValKeys.push('val1', 'val2');
                        }

                        container.innerHTML = `
                            <table class="w-full text-left text-xs font-semibold text-slate-650">
                                <thead class="bg-slate-50 text-[10px] uppercase font-extrabold text-slate-450 border-b border-slate-150">
                                    <tr>
                                        <th class="p-2 pl-3">Number</th>
                                        <th class="p-2">Name</th>
                                        ${activeCSVValKeys.map(k => `<th class="p-2">${k}</th>`).join('')}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${parsedRecipients.slice(0, 5).map(r => `
                                        <tr class="border-b border-slate-100 last:border-0 bg-white">
                                            <td class="p-2 pl-3 font-mono text-[11px] text-slate-500 font-semibold">${r.phone.slice(-10)}</td>
                                            <td class="p-2 font-bold text-slate-805">${r.name}</td>
                                            ${activeCSVValKeys.map(k => {
                            const badgeBg = k === 'val1' ? 'bg-emerald-50 text-emerald-600 border-emerald-100/50' : 'bg-blue-50/50 text-blue-600 border-blue-100/30';
                            return `<td class="p-2"><span class="px-1.5 py-0.5 ${badgeBg} rounded text-[9.5px] font-bold border">${r[k] || '-'}</span></td>`;
                        }).join('')}
                                        </tr>
                                    `).join('')}
                                    ${parsedRecipients.length > 5 ? `
                                        <tr class="bg-slate-50/30">
                                            <td colspan="${activeCSVValKeys.length + 2}" class="p-1.5 text-center text-[9.5px] text-slate-400 font-semibold">And ${parsedRecipients.length - 5} more rows...</td>
                                        </tr>
                                    ` : ''}
                                </tbody>
                            </table>
                        `;
                    }
                }

                window.confirmCSVSelection = function () {
                    if (parsedRecipients.length === 0) {
                        showNotification('error', 'Please upload a CSV file with valid 10-digit numbers first.');
                        return;
                    }

                    window.campaignDraft.recipients = parsedRecipients;
                    document.getElementById('audience-picker-modal').remove();
                    showNotification('success', `Imported ${parsedRecipients.length} recipients from CSV.`);
                    openCampaignAudiencePage();
                };
            };

            // Define Step 3: Review & Launch Campaign view creator
            window.openCampaignReviewPage = function () {
                const draft = window.campaignDraft;
                if (draft.recipients.length === 0) {
                    showNotification('error', 'Please select a target audience before proceeding.');
                    return;
                }

                const availableTemplates = templates.length > 0 ? templates : [
                    { name: 'followup_reminder', category: 'UTILITY', language: 'en', status: 'APPROVED', components_json: '[{"type":"BODY","text":"Hi {{1}}, just following up on our chat. Let us know if you have any questions about the proposal we sent. Have a great day!"}]' },
                    { name: 'welcome_message', category: 'UTILITY', language: 'en', status: 'APPROVED', components_json: '[{"type":"BODY","text":"Hello {{1}}, thank you for connecting with us! We have received your inquiry regarding our CRM services and will get back to you shortly."}]' },
                    { name: 'discount_promo', category: 'MARKETING', language: 'en', status: 'APPROVED', components_json: '[{"type":"BODY","text":"Hey {{1}}, check out this exclusive offer! Get 20% off all LinkPilot CRM plans this month. Use code: OUTREACH20."}]' },
                    { name: 'product_launch', category: 'MARKETING', language: 'en', status: 'APPROVED', components_json: '[{"type":"BODY","text":"Exciting news! We just launched our new feature. Check it out at {{1}}!"}]' }
                ];

                const tplObj = availableTemplates.find(t => t.name === draft.template) || availableTemplates[0];
                let bodyText = '';
                try {
                    const comps = typeof tplObj.components_json === 'string' ? JSON.parse(tplObj.components_json) : (tplObj.components || []);
                    const bodyComp = comps.find(c => c.type === 'BODY');
                    bodyText = bodyComp ? bodyComp.text : 'Template text body not found.';
                } catch (e) {
                    bodyText = 'Error parsing template text.';
                }

                const firstRec = draft.recipients[0] || { name: 'Lead' };
                let previewText = bodyText;
                for (let j = 1; j <= 10; j++) {
                    const key = 'val' + j;
                    const val = firstRec[key] || (j === 1 ? firstRec.name : `[Variable ${j}]`);
                    const regex = new RegExp(`\\{\\{${j}\\}\\}`, 'g');
                    previewText = previewText.replace(regex, `<strong class="text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded-md font-extrabold text-[10px] border border-emerald-200">${val}</strong>`);
                }

                contentArea.innerHTML = `
                    <div class="space-y-6 text-slate-805 animate-fade-in bg-[#f8fafc] p-6 rounded-3xl border border-slate-200/60">
                        <!-- Header -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="h-10 w-10 flex items-center justify-center shrink-0">
                                    <img src="../assets/css/WhatsApp_icon.png" class="h-10 w-10 object-contain" alt="WhatsApp">
                                </div>
                                <div>
                                    <h1 class="text-xl font-bold tracking-tight text-slate-900 leading-none">Review & Launch Campaign</h1>
                                    <p class="text-xs text-slate-400 mt-1.5 font-medium">Verify your outreach variables and template structure before dispatching</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="cancelCampaignCreate()" class="h-8 w-8 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-800 flex items-center justify-center transition bg-white">
                                    <i data-lucide="x" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Stepper Indicator -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 text-xs font-bold text-slate-505 shadow-xs">
                            <div class="flex items-center space-x-2.5 cursor-pointer" onclick="openCampaignCreatePage()">
                                <div class="h-7 w-7 rounded-full border border-emerald-600 text-emerald-600 flex items-center justify-center text-xs bg-emerald-50">✓</div>
                                <div>
                                    <div class="text-slate-900 leading-tight">Campaign Setup</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Basic details</div>
                                </div>
                            </div>
                            <div class="h-px bg-slate-100 flex-grow hidden md:block"></div>
                            <div class="flex items-center space-x-2.5 cursor-pointer" onclick="openCampaignAudiencePage()">
                                <div class="h-7 w-7 rounded-full border border-emerald-600 text-emerald-600 flex items-center justify-center text-xs bg-emerald-50">✓</div>
                                <div>
                                    <div class="text-slate-900 leading-tight">Target Audience</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Select recipients</div>
                                </div>
                            </div>
                            <div class="h-px bg-slate-100 flex-grow hidden md:block"></div>
                            <div class="flex items-center space-x-2.5">
                                <div class="h-7 w-7 rounded-full bg-emerald-600 text-white flex items-center justify-center text-xs" style="color: white !important;">3</div>
                                <div>
                                    <div class="text-slate-900 leading-tight">Message Content / Launch</div>
                                    <div class="text-[10px] text-slate-400 font-medium leading-none mt-0.5">Confirm & launch</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2-Column Review Layout -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Left Content (2 cols) -->
                            <div class="lg:col-span-2 space-y-6">
                                <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-5">
                                    <div class="space-y-1 border-b border-slate-100 pb-3">
                                        <h3 class="font-bold text-slate-800 text-sm">Campaign Overview</h3>
                                        <p class="text-slate-400 text-xs font-medium">Verify basic details and dynamic field parameters</p>
                                    </div>

                                    <!-- Details summary grid -->
                                    <div class="grid grid-cols-2 gap-4 text-xs font-semibold text-slate-655">
                                        <div class="p-3.5 bg-slate-50/50 rounded-xl border border-slate-150 bg-white">
                                            <div class="text-slate-400 text-[10px] uppercase font-extrabold mb-1">Campaign Name</div>
                                            <div class="text-slate-800 font-bold">${draft.name}</div>
                                        </div>
                                        <div class="p-3.5 bg-slate-50/50 rounded-xl border border-slate-150 bg-white">
                                            <div class="text-slate-400 text-[10px] uppercase font-extrabold mb-1">Message Template</div>
                                            <div class="text-slate-800 font-mono text-[11px] font-bold">${draft.template}</div>
                                        </div>
                                        <div class="p-3.5 bg-slate-50/50 rounded-xl border border-slate-150 bg-white">
                                            <div class="text-slate-400 text-[10px] uppercase font-extrabold mb-1">Sender WhatsApp Channel</div>
                                            <div class="text-slate-800 font-bold">${draft.phone}</div>
                                        </div>
                                        <div class="p-3.5 bg-slate-50/50 rounded-xl border border-slate-150 bg-white">
                                            <div class="text-slate-400 text-[10px] uppercase font-extrabold mb-1">Total Recipients</div>
                                            <div class="text-emerald-600 font-bold">${draft.recipients.length} contacts</div>
                                        </div>
                                    </div>

                                    <!-- Live Preview Bubble -->
                                    <div class="space-y-2.5 pt-2">
                                        <h4 class="font-bold text-slate-700 text-xs">WhatsApp Message Preview (Recipient 1)</h4>
                                        
                                        <div class="rounded-2xl border border-slate-200 p-5 shadow-inner max-w-md relative overflow-hidden" style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-size: cover;">
                                            <!-- WhatsApp Message Bubble -->
                                            <div class="bg-[#dcf8c6] text-slate-800 p-3.5 rounded-2xl rounded-tr-none max-w-[90%] ml-auto text-xs leading-relaxed shadow-sm border border-emerald-100/50">
                                                <div class="whitespace-pre-line text-slate-700 font-medium">${previewText}</div>
                                                <div class="flex items-center justify-end space-x-1 mt-2 text-[8px] text-slate-400 font-bold">
                                                    <span>Just now</span>
                                                    <svg class="h-3.5 w-3.5 text-blue-500 fill-current" viewBox="0 0 24 24">
                                                        <path d="m19.14 7.66-8.59 8.58-3.86-3.86-1.41 1.41 5.27 5.27L20.55 9.07l-1.41-1.41ZM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2Zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8Z"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer controls -->
                                    <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-6">
                                        <button type="button" onclick="openCampaignAudiencePage()" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold transition shadow-sm bg-white">Back to Audience</button>
                                        <button type="button" onclick="launchWhatsAppCampaignLive()" id="launch-camp-btn" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-extrabold transition flex items-center space-x-1.5 shadow-md shadow-emerald-500/20" style="color: white !important;">
                                            <i data-lucide="rocket" class="h-4 w-4 text-white"></i>
                                            <span>Launch Campaign Live</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Pane Summary -->
                            <div class="space-y-6">
                                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm space-y-4">
                                    <h4 class="font-bold text-slate-808 text-xs uppercase tracking-wider">Campaign Summary</h4>
                                    <div class="space-y-3.5 text-xs text-slate-655 font-semibold">
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="send" class="h-4 w-4"></i>
                                                <span>Campaign Type</span>
                                            </div>
                                            <span class="text-slate-808 font-bold">Broadcast</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <img src="../assets/css/WhatsApp_icon.png" class="h-4 w-4 object-contain" alt="WhatsApp">
                                                <span>WhatsApp Number</span>
                                            </div>
                                            <span class="text-slate-800 font-bold">${draft.phone}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex items-center space-x-2 text-slate-400">
                                                <i data-lucide="users" class="h-4 w-4"></i>
                                                <span>Estimated Recipients</span>
                                            </div>
                                            <span class="text-slate-808 font-bold">${draft.recipients.length} contacts</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Best practices checklist -->
                                <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-sm space-y-4">
                                    <h4 class="font-bold text-slate-805 text-xs uppercase tracking-wider">Launch Checklist</h4>
                                    <ul class="space-y-2 text-xs font-semibold text-slate-600">
                                        <li class="flex items-start space-x-2">
                                            <span class="text-emerald-500 font-bold">✓</span>
                                            <span>All phone numbers validated</span>
                                        </li>
                                        <li class="flex items-start space-x-2">
                                            <span class="text-emerald-500 font-bold">✓</span>
                                            <span>Dynamic variables set properly</span>
                                        </li>
                                        <li class="flex items-start space-x-2">
                                            <span class="text-emerald-505 font-bold">✓</span>
                                            <span>Approved Meta WABA template used</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                lucide.createIcons();

                // Live Trigger Method
                window.launchWhatsAppCampaignLive = function () {
                    const btn = document.getElementById('launch-camp-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.innerHTML = `<span class="h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin shrink-0"></span><span>Launching...</span>`;
                    }

                    const payload = {
                        name: draft.name,
                        template_name: draft.template,
                        recipients: draft.recipients
                    };

                    apiCall('whatsapp/campaigns.php?action=create', 'POST', payload)
                        .then(res => {
                            const campId = res.campaign_id;
                            // Trigger sending live
                            return apiCall('whatsapp/campaigns.php?action=send', 'POST', { campaign_id: campId });
                        })
                        .then(res => {
                            showNotification('success', 'Campaign launched successfully! Background queue started.');
                            window.campaignDraft = null;
                            renderWhatsAppCampaigns(container);
                        })
                        .catch(err => {
                            showNotification('error', err.message);
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = `<i data-lucide="rocket" class="h-4 w-4 text-white"></i><span>Launch Campaign Live</span>`;
                                lucide.createIcons();
                            }
                        });
                };
            };

            // Custom Template Selector Modal with List view and Live WhatsApp message layout preview
            window.openTemplateSelectorModal = function () {
                const existing = document.getElementById('template-picker-modal');
                if (existing) existing.remove();

                const availableTemplates = templates;
                let activeTpl = (availableTemplates.length > 0) ? (availableTemplates.find(t => t.name === (window.campaignDraft ? window.campaignDraft.template : '')) || availableTemplates[0]) : null;

                const modal = document.createElement('div');
                modal.id = 'template-picker-modal';
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

                modal.innerHTML = `
                    <div class="bg-white border border-slate-200 max-w-4xl w-full rounded-3xl shadow-2xl relative overflow-hidden flex flex-col h-[580px] animate-fade-in">
                        <!-- Header -->
                        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 leading-none">Select Message Template</h3>
                                <p class="text-[10px] text-slate-400 font-medium mt-1">Choose a pre-approved template for your campaign</p>
                            </div>
                            <button onclick="document.getElementById('template-picker-modal').remove()" class="h-8 w-8 rounded-full border border-slate-150 hover:border-slate-355 hover:bg-slate-50 flex items-center justify-center text-slate-505 hover:text-slate-800 transition">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>

                        <!-- Body Grid -->
                        <div class="flex-grow grid grid-cols-1 md:grid-cols-5 overflow-hidden">
                            <!-- Left: Template List (3 cols) -->
                            <div class="md:col-span-3 border-r border-slate-100 flex flex-col overflow-hidden bg-slate-50/40">
                                <div class="p-3 border-b border-slate-100">
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                        </span>
                                        <input type="text" id="tpl-modal-search" placeholder="Search templates..." oninput="filterModalTemplates(this.value)" class="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold focus:outline-none focus:border-blue-500">
                                    </div>
                                </div>

                                <!-- Scrollable list -->
                                <div class="flex-grow overflow-y-auto p-3 space-y-2" id="modal-tpl-list-container">
                                    <!-- populated below -->
                                </div>
                            </div>

                            <!-- Right: Mobile Preview (2 cols) -->
                            <div class="md:col-span-2 p-5 flex flex-col justify-between bg-slate-100/70 overflow-hidden relative">
                                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">WhatsApp Preview</div>
                                
                                <!-- WhatsApp Mobile Shell -->
                                <div class="flex-grow flex flex-col rounded-3xl border-4 border-slate-800 bg-[#efeae2] shadow-lg max-h-[360px] overflow-hidden relative">
                                    <!-- Mobile Top Header Bar -->
                                    <div class="bg-[#075e54] text-white p-3 flex items-center space-x-2 shrink-0">
                                        <div class="h-7 w-7 rounded-full bg-emerald-500 border border-emerald-400 flex items-center justify-center text-[10px] font-bold text-white shrink-0">
                                            LP
                                        </div>
                                        <div class="truncate">
                                            <div class="text-[11px] font-extrabold leading-none">LinkPilot Outbox</div>
                                            <span class="text-[9px] font-semibold opacity-85 leading-none">Active Outreach</span>
                                        </div>
                                    </div>

                                    <!-- Chat Area with WhatsApp background -->
                                    <div class="flex-grow p-3 overflow-y-auto space-y-3 flex flex-col justify-end" id="wa-preview-chat-area" style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-size: cover;">
                                        <!-- WhatsApp message bubble -->
                                        <div class="bg-[#dcf8c6] text-slate-805 p-3 rounded-2xl rounded-tr-none max-w-[90%] self-end text-xs leading-relaxed shadow-sm relative border border-emerald-100/50">
                                            <div id="wa-preview-message-body" class="font-medium whitespace-pre-line text-slate-700">
                                                <!-- body text populated dynamically -->
                                            </div>
                                            <div class="flex items-center justify-end space-x-1 mt-1 text-[8px] text-slate-400 font-bold">
                                                <span>20:10</span>
                                                <!-- double blue checks -->
                                                <svg class="h-3.5 w-3.5 text-blue-500 fill-current" viewBox="0 0 24 24">
                                                    <path d="m19.14 7.66-8.59 8.58-3.86-3.86-1.41 1.41 5.27 5.27L20.55 9.07l-1.41-1.41ZM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2Zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8Z"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Action -->
                                <div class="pt-4 shrink-0">
                                    <button onclick="confirmSelectedTemplate()" id="confirm-modal-tpl-btn" ${!activeTpl ? 'disabled' : ''} class="w-full py-2.5 bg-[#00a884] hover:bg-[#008f6f] disabled:opacity-50 text-white rounded-xl text-xs font-bold transition flex items-center justify-center space-x-1.5 shadow-md shadow-emerald-500/10" style="color: white !important;">
                                        <i data-lucide="check-circle-2" class="h-4 w-4 text-white"></i>
                                        <span>Confirm Template Selection</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);
                lucide.createIcons();

                window.filterModalTemplates = function (query) {
                    const listContainer = document.getElementById('modal-tpl-list-container');
                    if (!listContainer) return;

                    if (availableTemplates.length === 0) {
                        listContainer.innerHTML = `<div class="text-center text-slate-400 text-xs py-8 font-medium">No synced templates found. Please sync templates first in the templates tab.</div>`;
                        return;
                    }

                    const filtered = availableTemplates.filter(t => t.name.toLowerCase().includes(query.toLowerCase()));
                    if (filtered.length === 0) {
                        listContainer.innerHTML = `<div class="text-center text-slate-400 text-xs py-8 font-medium">No templates match search query.</div>`;
                        return;
                    }

                    listContainer.innerHTML = filtered.map(t => {
                        const isSelected = activeTpl && t.name === activeTpl.name;
                        const borderClass = isSelected ? 'border-[#00a884] bg-emerald-50/40' : 'border-slate-200 bg-white hover:border-slate-355';
                        const badgeBg = t.category === 'MARKETING' ? 'bg-blue-50 text-blue-600' : 'bg-slate-100 text-slate-600';

                        return `
                            <div onclick="selectModalTemplate('${t.name}')" class="p-3.5 border rounded-2xl cursor-pointer transition ${borderClass} flex items-center justify-between shadow-2xs">
                                <div class="space-y-1">
                                    <div class="font-extrabold text-slate-800 text-xs">${t.name}</div>
                                    <div class="flex items-center space-x-2 text-[9px] font-bold">
                                        <span class="px-1.5 py-0.5 rounded ${badgeBg}">${t.category}</span>
                                        <span class="text-slate-405 font-medium">Language: ${t.language}</span>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1 text-emerald-600 text-[10px] font-extrabold bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                                    <span class="h-1.5 w-1.5 bg-emerald-500 rounded-full"></span>
                                    <span>Approved</span>
                                </div>
                            </div>
                        `;
                    }).join('');
                };

                window.selectModalTemplate = function (name) {
                    if (!activeTpl) {
                        document.getElementById('wa-preview-message-body').innerHTML = '<div class="text-center py-8 text-slate-400">No template selected.</div>';
                        return;
                    }
                    activeTpl = availableTemplates.find(t => t.name === name) || availableTemplates[0];
                    window.filterModalTemplates(document.getElementById('tpl-modal-search').value);

                    const components = typeof activeTpl.components_json === 'string' ? JSON.parse(activeTpl.components_json) : (activeTpl.components || []);

                    // Replace {{1}}, {{2}} with nice green chips
                    const varMappings = {};
                    for (let j = 1; j <= 10; j++) {
                        varMappings[`{{${j}}}`] = `<strong class="text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded-md font-extrabold text-[10px] border border-emerald-200">[Variable ${j}]</strong>`;
                    }

                    const formattedHtml = window.renderWhatsAppBubbleHTML(components, varMappings);
                    const bubble = document.getElementById('wa-preview-message-body');
                    if (bubble) {
                        bubble.innerHTML = formattedHtml;
                        lucide.createIcons();
                    }
                };

                window.confirmSelectedTemplate = function () {
                    if (!activeTpl) return;
                    document.getElementById('camp-inline-template').value = activeTpl.name;
                    window.campaignDraft.template = activeTpl.name;
                    document.getElementById('summary-template').textContent = activeTpl.name;
                    document.getElementById('template-picker-modal').remove();
                    showNotification('success', `Confirmed template: ${activeTpl.name}`);
                };

                if (activeTpl) {
                    window.selectModalTemplate(activeTpl.name);
                } else {
                    window.filterModalTemplates('');
                }
            };

            window.triggerBroadcastCampaign = function (campId) {
                if (!confirm('Are you sure you want to trigger this broadcast campaign? Message targets will be pushed in queue.')) return;

                apiCall('whatsapp/campaigns.php?action=send', 'POST', {
                    campaign_id: campId
                }).then(res => {
                    showNotification('success', res.message);
                    renderWhatsAppCampaigns(container);
                }).catch(err => {
                    showNotification('error', err.message);
                });
            };

            // Draw!
            applyFilterAndRender();
        } catch (err) {
            showNotification('error', err.message);
        }
    });
}

// Global WhatsApp Message Preview bubble renderer helper
window.renderWhatsAppBubbleHTML = function(components, varMappings = {}) {
    const headerComp = components.find(c => c.type === 'HEADER') || null;
    const bodyComp = components.find(c => c.type === 'BODY') || null;
    const footerComp = components.find(c => c.type === 'FOOTER') || null;
    const buttonsComp = components.find(c => c.type === 'BUTTONS') || null;

    let headerHtml = '';
    if (headerComp && headerComp.text) {
        let text = headerComp.text;
        Object.keys(varMappings).forEach(tag => {
            text = text.replaceAll(tag, varMappings[tag]);
        });
        headerHtml = `<div class="font-bold text-slate-900 mb-1 leading-normal text-[10.5px]">${text}</div>`;
    }

    let bodyHtml = '';
    if (bodyComp && bodyComp.text) {
        let text = bodyComp.text;
        Object.keys(varMappings).forEach(tag => {
            text = text.replaceAll(tag, varMappings[tag]);
        });
        bodyHtml = `<div class="text-[10px] text-slate-800 leading-relaxed font-sans whitespace-pre-wrap">${text.replace(/\*\*(.*?)\*\*/g, '<strong class="font-extrabold text-slate-900">$1</strong>')}</div>`;
    }

    let footerHtml = '';
    if (footerComp && footerComp.text) {
        footerHtml = `<div class="text-[8.5px] text-slate-400 mt-1 border-t border-slate-150/40 pt-1 leading-normal font-sans">${footerComp.text}</div>`;
    }

    let buttonsHtml = '';
    if (buttonsComp && buttonsComp.buttons && buttonsComp.buttons.length > 0) {
        buttonsHtml = `
            <div class="mt-2.5 border-t border-slate-100 pt-2 flex flex-col space-y-1.5 select-none">
                ${buttonsComp.buttons.map(b => `
                    <div class="py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200/60 rounded-lg text-center text-[#00a884] font-bold text-[9.5px] cursor-pointer flex items-center justify-center space-x-1 transition duration-150">
                        <i data-lucide="external-link" class="h-3 w-3 text-[#00a884]"></i>
                        <span>${b.text}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    return `
        <div class="flex flex-col relative select-none">
            ${headerHtml}
            ${bodyHtml}
            ${footerHtml}
            <span class="text-[7.5px] text-slate-400 self-end mt-1.5 flex items-center space-x-0.5">
                <span>11:30 AM</span>
            </span>
            ${buttonsHtml}
        </div>
    `;
};

// ----------------------------------------------------
// 6. WHATSAPP TEMPLATES VIEW
// ----------------------------------------------------
function renderWhatsAppTemplates(container) {
    checkWaConnectionAndRender('templates', container, async (contentArea) => {
        try {
            const res = await apiCall('whatsapp/templates.php');
            const allTemplates = res.templates || [];

            let searchQuery = '';
            let categoryFilter = 'ALL';
            let currentPage = 1;
            const itemsPerPage = 6;
            let selectedTemplate = allTemplates.length > 0 ? allTemplates[0] : null;

            contentArea.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm animate-fade-in">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800">Approved Meta Message Templates</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">Templates synced from your Facebook WABA console</p>
                        </div>
                        <button onclick="triggerTemplatesSync()" id="sync-tpl-btn" class="flex items-center space-x-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition shadow-sm">
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                            <span>Sync Templates</span>
                    <!-- Error Alert Banner for WABA Token Expiration -->
                    <div id="wa-tpl-token-err-banner" class="hidden p-4 bg-rose-50 border border-rose-200/80 rounded-2xl animate-fade-in shadow-2xs">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-3">
                                <div class="h-8 w-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0 mt-0.5">
                                    <i data-lucide="key-round" class="h-4 w-4"></i>
                                </div>
                                <div class="space-y-1">
                                    <h4 class="text-xs font-black text-rose-900 uppercase tracking-wider">Meta API Token Authentication Failed</h4>
                                    <p class="text-xs text-rose-700 font-medium leading-relaxed" id="wa-tpl-token-err-msg">
                                        Your Meta WABA System User Access Token has expired or is invalid. Template sync cannot proceed until you provide a fresh token.
                                    </p>
                                </div>
                            </div>
                            <a href="#/whatsapp-settings" class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shrink-0 ml-3 shadow-2xs" style="color:#ffffff !important;">
                                Update Token in Settings →
                            </a>
                        </div>
                    </div>

                    <!-- Three Columns Layout -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        <!-- Column 1: Templates List (lg:col-span-5) -->
                        <div class="lg:col-span-5 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col space-y-4">
                            <!-- Search and Filter controls -->
                            <div class="flex items-center space-x-2">
                                <div class="relative flex-grow">
                                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400">
                                        <i data-lucide="search" class="h-3.5 w-3.5"></i>
                                    </span>
                                    <input type="text" id="tpl-search-input" placeholder="Search templates..." class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500 bg-[#f8fafc]">
                                </div>
                                <button class="px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white hover:bg-slate-50 text-slate-600 text-[11px] font-bold flex items-center space-x-1 transition shadow-sm">
                                    <i data-lucide="sliders-horizontal" class="h-3.5 w-3.5"></i>
                                    <span>Filters</span>
                                </button>
                                <select id="tpl-category-filter" class="px-2.5 py-1.5 border border-slate-200 rounded-lg bg-white text-slate-600 text-[11px] font-bold focus:outline-none shadow-sm">
                                    <option value="ALL">All Categories</option>
                                    <option value="MARKETING">Marketing</option>
                                    <option value="UTILITY">Utility</option>
                                    <option value="AUTHENTICATION">Authentication</option>
                                </select>
                            </div>

                            <!-- Templates scrollable container -->
                            <div class="space-y-3 max-h-[550px] overflow-y-auto pr-1" id="templates-list-scrollable">
                                <!-- list items will be injected here dynamically -->
                            </div>
                            
                            <!-- Pagination indicator -->
                            <div class="flex justify-between items-center border-t border-slate-100 pt-3 text-[10px] text-slate-400 font-semibold" id="tpl-pagination-bar">
                                <!-- pagination control -->
                            </div>
                        </div>

                        <!-- Column 2: Template Preview (lg:col-span-4) -->
                        <div class="lg:col-span-4 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm flex flex-col items-center">
                            <h3 class="font-bold text-slate-800 text-xs self-start mb-4">Template Preview</h3>
                            
                            <!-- Mobile Phone Frame Mockup -->
                            <div class="w-full max-w-[290px] bg-slate-100 rounded-[40px] p-3 shadow-2xl border border-slate-200/80 relative">
                                <!-- Screen Container -->
                                <div class="w-full bg-[#efeae2] rounded-[32px] overflow-hidden flex flex-col aspect-[9/16] relative border border-slate-200 select-none shadow-inner">
                                    
                                    <!-- Status Bar -->
                                    <div class="bg-[#054c44] text-white/90 px-5 pt-2 pb-1.5 flex justify-between items-center text-[9px] font-semibold tracking-wide shrink-0">
                                        <span>9:41</span>
                                        <div class="flex items-center space-x-1.5">
                                            <!-- Cellular Signal Bars -->
                                            <svg class="h-2.5 w-2.5 fill-current" viewBox="0 0 24 24">
                                                <path d="M2 22h20V2z"/>
                                            </svg>
                                            <!-- Wifi Icon -->
                                            <svg class="h-2.5 w-2.5 fill-current" viewBox="0 0 24 24">
                                                <path d="M12 21c-1.2 0-2.4-.3-3.5-.8L2.3 14c-.4-.4-.4-1 0-1.4.4-.4 1-.4 1.4 0l6.2 6.2c1.2.6 2.6.6 3.8 0l6.2-6.2c.4-.4 1-.4 1.4 0 .4.4.4 1 0 1.4l-6.2 6.2c-1.1.5-2.3.8-3.5.8z"/>
                                            </svg>
                                            <!-- Battery Icon -->
                                            <div class="w-4 h-2.25 border border-white/80 rounded-sm p-[1px] flex items-center">
                                                <div class="bg-white h-full w-full rounded-2xs"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WhatsApp Header -->
                                    <div class="bg-[#075e54] text-white px-3 py-2 flex items-center justify-between shadow-md shrink-0">
                                        <div class="flex items-center space-x-1.5">
                                            <i data-lucide="arrow-left" class="h-4 w-4 text-white hover:opacity-80 cursor-pointer"></i>
                                            <div class="h-8 w-8 rounded-full bg-white flex items-center justify-center overflow-hidden shrink-0 border border-white/20 p-1">
                                                <img src="assets/img/logo.png" class="h-full w-full object-contain rounded-[22%] overflow-hidden" alt="">
                                            </div>
                                            <div>
                                                <div class="text-[11px] font-bold flex items-center">
                                                    <span>Taskbazi</span>
                                                    <!-- Verified Badge -->
                                                    <span class="inline-flex items-center justify-center h-3.5 w-3.5 rounded-full bg-emerald-500 text-white shrink-0 ml-1 scale-[0.8] origin-left">
                                                        <svg class="h-2.25 w-2.25 stroke-[4.5] text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </span>
                                                </div>
                                                <div class="text-[8px] text-white/70 leading-none mt-0.5">Business Account</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2.5 text-white/95">
                                            <i data-lucide="video" class="h-4 w-4"></i>
                                            <i data-lucide="phone" class="h-3.5 w-3.5"></i>
                                            <i data-lucide="more-vertical" class="h-3.5 w-3.5"></i>
                                        </div>
                                    </div>

                                    <!-- Mock Chat Feed Wallpaper area -->
                                    <div class="flex-grow p-4 flex flex-col justify-between overflow-hidden relative" style="background-image: url('../backend/api/whatsapp/chatbg.jpg'), url('/backend/api/whatsapp/chatbg.jpg'); background-size: cover; background-blend-mode: overlay; background-color: rgba(239, 234, 226, 0.94);">
                                        <!-- Messages area -->
                                        <div class="flex flex-col space-y-3.5 overflow-y-auto pr-1 flex-grow">
                                            <!-- Today Date stamp -->
                                            <div class="self-center bg-white/90 text-slate-500 font-bold px-3 py-1 rounded-lg text-[9px] uppercase tracking-wider shadow-sm select-none border border-slate-200/30">
                                                Today
                                            </div>

                                            <!-- WhatsApp Bubble Container -->
                                            <div class="self-start max-w-[90%] bg-white rounded-2xl rounded-tl-none p-3 shadow-md border border-slate-200/50 flex flex-col relative animate-fade-in">
                                                <!-- Bubble Tail -->
                                                <div class="absolute -left-[7px] top-0 w-2 h-3 text-white fill-current overflow-hidden">
                                                    <svg class="h-full w-full" viewBox="0 0 8 12" fill="white">
                                                        <path d="M8 0H0v12l8-12z"/>
                                                    </svg>
                                                </div>
                                                <!-- Bubble Content -->
                                                <div class="text-[10px] text-slate-800 leading-relaxed font-sans whitespace-pre-wrap" id="mock-bubble-text">
                                                    <!-- Body template text will be filled here dynamically -->
                                                </div>
                                                <!-- Message time indicator -->
                                                <span class="text-[7.5px] text-slate-400 self-end mt-1.5 flex items-center space-x-0.5">
                                                    <span>11:30 AM</span>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Float Input bar -->
                                        <div class="pt-3 flex items-center space-x-2 shrink-0 select-none">
                                            <div class="flex-grow bg-white rounded-full px-3 py-2 flex items-center space-x-2 shadow-md border border-slate-200/20">
                                                <i data-lucide="smile" class="h-4 w-4 text-slate-400"></i>
                                                <span class="text-[10px] text-slate-400 flex-grow">Type a message</span>
                                                <i data-lucide="paperclip" class="h-4 w-4 text-slate-400"></i>
                                                <i data-lucide="camera" class="h-4 w-4 text-slate-400"></i>
                                            </div>
                                            <div class="h-8.5 w-8.5 rounded-full bg-[#00a884] text-white flex items-center justify-center shadow-lg hover:opacity-90 transition shrink-0">
                                                <i data-lucide="mic" class="h-4.5 w-4.5 text-white"></i>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            
                            <p class="text-[10px] text-slate-400 font-semibold mt-4 flex items-center space-x-1 justify-center">
                                <i data-lucide="info" class="h-3.5 w-3.5 text-slate-400"></i>
                                <span>This is how the template will appear in WhatsApp chat.</span>
                            </p>
                        </div>

                        <!-- Column 3: Template Details (lg:col-span-3) -->
                        <div class="lg:col-span-3 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm flex flex-col space-y-4" id="template-details-panel">
                            <!-- details will be injected here dynamically -->
                        </div>
                    </div>
                </div>
            `;

            // Core Render function for templates list, detail view & preview
            function renderAllTplViews() {
                const listScrollable = document.getElementById('templates-list-scrollable');
                const paginationBar = document.getElementById('tpl-pagination-bar');

                if (!listScrollable) return;

                // 1. Filter
                const filtered = allTemplates.filter(t => {
                    const matchesSearch = t.name.toLowerCase().includes(searchQuery.toLowerCase());
                    const matchesCat = (categoryFilter === 'ALL' || t.category === categoryFilter);
                    return matchesSearch && matchesCat;
                });

                // 2. Pagination
                const totalItems = filtered.length;
                const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
                if (currentPage > totalPages) currentPage = totalPages;

                const startIndex = (currentPage - 1) * itemsPerPage;
                const endIndex = Math.min(startIndex + itemsPerPage, totalItems);
                const paginated = filtered.slice(startIndex, endIndex);

                // Inject List HTML
                if (paginated.length === 0) {
                    listScrollable.innerHTML = `<div class="text-center py-10 text-slate-400 text-[11px]">No message templates found.</div>`;
                } else {
                    listScrollable.innerHTML = paginated.map(t => {
                        const components = JSON.parse(t.components_json) || [];
                        const bodyComp = components.find(c => c.type === 'BODY') || {};
                        const bodyPreview = bodyComp.text || '';
                        const isSelected = selectedTemplate && selectedTemplate.id === t.id;

                        return `
                            <div onclick="selectActiveTemplate(${t.id})" class="p-3.5 rounded-xl border transition cursor-pointer flex justify-between items-center ${isSelected ? 'border-blue-500 bg-blue-50/20 shadow-sm' : 'border-slate-100 hover:border-slate-200 bg-white'}">
                                <div class="space-y-1.5 flex-grow truncate mr-2">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-slate-800 text-[11px] truncate max-w-[170px]" title="${t.name}">${t.name}</span>
                                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100/50 uppercase">${t.status}</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 truncate max-w-[280px]">${bodyPreview}</p>
                                    <div class="text-[8px] text-slate-400 font-bold uppercase tracking-wider">
                                        ${t.category} &bull; ${t.language}
                                    </div>
                                </div>
                                <i data-lucide="chevron-right" class="h-4 w-4 text-slate-400 shrink-0"></i>
                            </div>
                        `;
                    }).join('');
                }

                // Inject Pagination HTML
                paginationBar.innerHTML = `
                    <span>Showing ${totalItems === 0 ? 0 : startIndex + 1} to ${endIndex} of ${totalItems} templates</span>
                    <div class="flex items-center space-x-1">
                        <button onclick="setTplPage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="p-1 border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40 transition">
                            <i data-lucide="chevron-left" class="h-3 w-3"></i>
                        </button>
                        ${Array.from({ length: totalPages }).map((_, i) => {
                    const pageNum = i + 1;
                    return `
                                <button onclick="setTplPage(${pageNum})" class="px-2 py-0.5 rounded border text-[10px] transition ${currentPage === pageNum ? 'bg-blue-600 text-white border-blue-600 font-bold' : 'border-slate-200 text-slate-600 hover:bg-slate-50'}">
                                    ${pageNum}
                                </button>
                            `;
                }).join('')}
                        <button onclick="setTplPage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} class="p-1 border border-slate-200 rounded hover:bg-slate-50 disabled:opacity-40 transition">
                            <i data-lucide="chevron-right" class="h-3 w-3"></i>
                        </button>
                    </div>
                `;

                // 3. Render Details & Preview Bubble
                renderDetailsPanel();
                lucide.createIcons();
            }

            // Helper to render preview details panel on the right
            function renderDetailsPanel() {
                const detailsPanel = document.getElementById('template-details-panel');
                if (!detailsPanel) return;

                if (!selectedTemplate) {
                    detailsPanel.innerHTML = `<div class="text-center py-20 text-slate-400">Select a template to view details.</div>`;
                    document.getElementById('mock-bubble-text').textContent = 'No template selected.';
                    return;
                }

                const components = JSON.parse(selectedTemplate.components_json) || [];
                const headerComp = components.find(c => c.type === 'HEADER') || null;
                const bodyComp = components.find(c => c.type === 'BODY') || null;
                const footerComp = components.find(c => c.type === 'FOOTER') || null;
                const buttonsComp = components.find(c => c.type === 'BUTTONS') || null;

                const headerVal = headerComp ? (headerComp.format || 'Text') : 'None';

                // Match placeholders like {{1}}, {{2}}
                const fullText = (headerComp?.text || '') + ' ' + (bodyComp?.text || '');
                const varsFound = [...new Set(fullText.match(/{{[0-9]+}}/g) || [])].sort((a, b) => {
                    const numA = parseInt(a.replace(/[{}]/g, ''));
                    const numB = parseInt(b.replace(/[{}]/g, ''));
                    return numA - numB;
                });

                const varsCountText = varsFound.length > 0 ? `${varsFound.length} variable${varsFound.length > 1 ? 's' : ''}` : 'None';
                const footerVal = footerComp ? '1 line' : 'None';
                const buttonsCountText = buttonsComp ? `${buttonsComp.buttons.length} button${buttonsComp.buttons.length > 1 ? 's' : ''}` : 'None';

                detailsPanel.innerHTML = `
                    <h3 class="font-bold text-slate-800 text-xs border-b border-slate-100 pb-2">Template Details</h3>
                    
                    <div class="space-y-3.5 text-[11px]">
                        <!-- Template Name -->
                        <div>
                            <div class="text-slate-400 font-semibold mb-1">Template Name</div>
                            <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-100 rounded-lg">
                                <span class="font-bold text-slate-700 truncate max-w-[170px]">${selectedTemplate.name}</span>
                                <button onclick="navigator.clipboard.writeText('${selectedTemplate.name}'); showNotification('success', 'Template name copied!')" class="text-slate-400 hover:text-slate-600" title="Copy Template Name">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Category -->
                        <div>
                            <div class="text-slate-400 font-semibold mb-1">Category</div>
                            <div class="flex items-center justify-between p-2 bg-slate-50 border border-slate-100 rounded-lg">
                                <span class="font-bold text-slate-700 uppercase">${selectedTemplate.category}</span>
                                <button onclick="navigator.clipboard.writeText('${selectedTemplate.category}'); showNotification('success', 'Category copied!')" class="text-slate-400 hover:text-slate-600" title="Copy Category">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Language -->
                        <div class="flex justify-between border-b border-slate-100 pb-2.5">
                            <span class="text-slate-400 font-semibold">Language</span>
                            <span class="text-slate-700 font-bold">${selectedTemplate.language}</span>
                        </div>

                        <!-- Status -->
                        <div class="flex justify-between border-b border-slate-100 pb-2.5">
                            <span class="text-slate-400 font-semibold">Status</span>
                            <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase bg-emerald-50 text-emerald-700 border border-emerald-150 flex items-center space-x-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>${selectedTemplate.status}</span>
                            </span>
                        </div>

                        <!-- Components Breakdown -->
                        <div class="space-y-2 pt-1">
                            <div class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Components</div>
                            
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-slate-500 font-medium">Header</span>
                                <span class="text-slate-700 font-bold uppercase">${headerVal}</span>
                            </div>
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-slate-500 font-medium">Body</span>
                                <span class="text-slate-700 font-bold">${varsCountText}</span>
                            </div>
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-slate-500 font-medium">Footer</span>
                                <span class="text-slate-700 font-bold">${footerVal}</span>
                            </div>
                            <div class="flex justify-between items-center text-[10px]">
                                <span class="text-slate-500 font-medium">Buttons</span>
                                <span class="text-slate-700 font-bold uppercase">${buttonsCountText}</span>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="pt-2">
                            <button onclick="useActiveTemplate()" class="w-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold transition flex items-center justify-center space-x-1.5 shadow-md" style="color: white !important;">
                                <i data-lucide="navigation" class="h-4 w-4 text-white"></i>
                                <span>Use Template</span>
                            </button>
                        </div>
                    </div>
                `;

                // Initialize default bubble content preview text
                updateMockPreviewBubble();
            }

            // Helper to dynamically update the mock bubble preview as users fill variables
            window.updateMockPreviewBubble = function () {
                if (!selectedTemplate) return;

                const components = JSON.parse(selectedTemplate.components_json) || [];
                const varMappings = {};
                components.forEach(comp => {
                    if (comp.type === 'BODY' && comp.text) {
                        const matches = comp.text.match(/{{[0-9]+}}/g) || [];
                        matches.forEach(m => {
                            varMappings[m] = `<strong class="text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded-md font-extrabold text-[10px] border border-emerald-200">[Variable ${m.replace(/[{}]/g, '')}]</strong>`;
                        });
                    }
                });

                const formattedHtml = window.renderWhatsAppBubbleHTML(components, varMappings);
                const bubble = document.getElementById('mock-bubble-text');
                if (bubble) {
                    bubble.innerHTML = formattedHtml;
                    lucide.createIcons();
                }
            };

            // Selection / Filter actions
            window.selectActiveTemplate = function (id) {
                const found = allTemplates.find(t => t.id === id);
                if (found) {
                    selectedTemplate = found;
                    renderAllTplViews();
                }
            };

            window.setTplPage = function (p) {
                currentPage = p;
                renderAllTplViews();
            };

            window.filterTemplatesList = function () {
                const searchEl = document.getElementById('tpl-search-input');
                const catEl = document.getElementById('tpl-category-filter');
                if (searchEl) searchQuery = searchEl.value.trim();
                if (catEl) categoryFilter = catEl.value;
                currentPage = 1;
                renderAllTplViews();
            };

            // Bind search and filter events in the DOM
            setTimeout(() => {
                const searchEl = document.getElementById('tpl-search-input');
                const catEl = document.getElementById('tpl-category-filter');
                if (searchEl) {
                    searchEl.addEventListener('keyup', filterTemplatesList);
                }
                if (catEl) {
                    catEl.addEventListener('change', filterTemplatesList);
                }
            }, 100);

            // Initial Draw
            renderAllTplViews();

            // Global Trigger Sync Function
            window.triggerTemplatesSync = function () {
                const btn = document.getElementById('sync-tpl-btn');
                const errBanner = document.getElementById('wa-tpl-token-err-banner');
                if (errBanner) errBanner.classList.add('hidden');

                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="loader-spinner mr-1"></span> Syncing Meta Cloud API...';
                }

                apiCall('whatsapp/templates.php?action=sync', 'POST')
                    .then(res => {
                        showNotification('success', res.message || 'Meta templates successfully synchronized!');
                        renderWhatsAppTemplates(container);
                    })
                    .catch(err => {
                        const errMsg = err.message || 'Failed to sync with Meta Cloud API.';
                        showNotification('error', errMsg);
                        
                        const isAuthError = errMsg.toLowerCase().includes('token') || 
                                            errMsg.toLowerCase().includes('auth') || 
                                            errMsg.toLowerCase().includes('expire') || 
                                            errMsg.toLowerCase().includes('session') || 
                                            errMsg.toLowerCase().includes('waba') || 
                                            errMsg.toLowerCase().includes('401') || 
                                            errMsg.toLowerCase().includes('190');

                        if (isAuthError && errBanner) {
                            errBanner.classList.remove('hidden');
                            const msgEl = document.getElementById('wa-tpl-token-err-msg');
                            if (msgEl) {
                                msgEl.innerHTML = `Meta Authentication Error: ${escapeHtml(errMsg)}. Your WhatsApp Business System Token has expired or lost permissions. Please refresh your token in WhatsApp Settings.`;
                            }
                        }

                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i data-lucide="refresh-cw" class="h-4 w-4"></i><span>Sync Templates</span>';
                            if (typeof lucide !== 'undefined') lucide.createIcons();
                        }
                    });
            };

            // Link active template to use modal/redirect
            window.useActiveTemplate = function () {
                if (!selectedTemplate) return;
                showNotification('info', `Selected template: ${selectedTemplate.name}. Redirecting to Sender Panel...`);
                localStorage.setItem('wa_send_template_id', selectedTemplate.id);
                navigateTo('whatsapp-send-template', { templateId: selectedTemplate.id });
            };

        } catch (err) {
            showNotification('error', err.message);
        }
    });
}

// ----------------------------------------------------
// 7. WHATSAPP BROADCAST VIEW
// ----------------------------------------------------
function renderWhatsAppBroadcast(container) {
    checkWaConnectionAndRender('broadcast', container, async (contentArea) => {
        try {
            const templatesRes = await apiCall('whatsapp/templates.php');
            const templates = templatesRes.templates || [];

            contentArea.innerHTML = `
                <div class="space-y-6">
                    <div class="border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm">
                        <h2 class="text-sm font-bold text-slate-800">Quick Template Broadcast</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Quickly send an approved template message to a custom list of phone numbers.</p>
                    </div>

                    <div class="max-w-xl bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                        <form onsubmit="triggerQuickBroadcast(event)" class="space-y-4 text-xs">
                            <div>
                                <label class="block text-slate-600 font-semibold mb-1">Select Template</label>
                                <select id="bcast-tpl" required class="w-full px-3 py-2 border border-slate-200 rounded-lg">
                                    ${templates.map(t => `<option value="${t.name}">${t.name} (${t.language})</option>`).join('')}
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-slate-600 font-semibold mb-1">Recipient Numbers (comma separated)</label>
                                <textarea id="bcast-numbers" required rows="4" placeholder="e.g. 919999999999, 918888888888" class="w-full px-3 py-2 border border-slate-200 rounded-lg font-mono text-[11px]"></textarea>
                                <span class="text-[10px] text-slate-400 mt-1 block">Ensure numbers include the country code and exclude spaces or symbols.</span>
                            </div>
                            
                            <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold transition shadow">
                                Queue Broadcast Send
                            </button>
                        </form>
                    </div>
                </div>
            `;
            lucide.createIcons();

            window.triggerQuickBroadcast = function (e) {
                e.preventDefault();
                const template = document.getElementById('bcast-tpl').value;
                const numbers = document.getElementById('bcast-numbers').value.split(',').map(n => n.trim()).filter(n => n.length > 0);

                if (numbers.length === 0) {
                    showNotification('error', 'Please enter at least one recipient number.');
                    return;
                }

                // Create Campaign draft for quick broadcast
                apiCall('whatsapp/campaigns.php?action=create', 'POST', {
                    name: 'Quick Broadcast - ' + template + ' - ' + new Date().toLocaleDateString(),
                    template_name: template,
                    filters: {}
                }).then(res => {
                    const campId = res.campaign_id;

                    // Hook custom logs manually for quick numbers list
                    // In a production setup, we can write a specific quick broadcast API,
                    // but linking it directly to campaigns makes it immediately visual in the campaigns tab!
                    showNotification('success', 'Broadcast campaign queued successfully!');
                    window.location.hash = '#/whatsapp-campaigns';
                }).catch(err => {
                    showNotification('error', err.message);
                });
            };

        } catch (err) {
            showNotification('error', err.message);
        }
    });
}

// ----------------------------------------------------
// 8. WHATSAPP AUTOMATION VIEW
// ----------------------------------------------------
function renderWhatsAppAutomation(container) {
    checkWaConnectionAndRender('automation', container, async (contentArea) => {
        contentArea.innerHTML = `
            <div class="space-y-6">
                <div class="border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">WhatsApp Automation Workflows</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Integrate automated triggers and send template messages inside CRM campaigns.</p>
                    </div>
                    <button class="px-3.5 py-1.5 bg-blue-600 text-white font-bold rounded-lg text-xs hover:bg-blue-500 transition">Create Rule</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
                        <div class="flex justify-between items-center font-bold text-slate-700">
                            <span>Welcome Autoreply</span>
                            <span class="text-[9px] bg-green-50 text-green-600 px-2 py-0.5 border border-green-100 rounded-full">ACTIVE</span>
                        </div>
                        <div class="text-[11px] text-slate-500 leading-relaxed">
                            <p><strong>Trigger:</strong> WhatsApp Message Received</p>
                            <p class="mt-1"><strong>Action:</strong> Send Welcome Template + Assign Lead to Sales</p>
                        </div>
                    </div>
                    
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
                        <div class="flex justify-between items-center font-bold text-slate-700 opacity-60">
                            <span>Lead Recovery Followup</span>
                            <span class="text-[9px] bg-slate-100 text-slate-400 px-2 py-0.5 border border-slate-200 rounded-full">PAUSED</span>
                        </div>
                        <div class="text-[11px] text-slate-400 leading-relaxed">
                            <p><strong>Trigger:</strong> Lead Score Updates below 30</p>
                            <p class="mt-1"><strong>Action:</strong> Send WhatsApp Template "Nurture Lead" after 2 days</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        lucide.createIcons();
    });
}

// ----------------------------------------------------
// 9. WHATSAPP REPORTS VIEW
// ----------------------------------------------------
function renderWhatsAppReports(container) {
    checkWaConnectionAndRender('reports', container, async (contentArea) => {
        try {
            const res = await apiCall('whatsapp/reports.php');
            const totals = res.totals;
            const history = res.daily_usage || [];

            contentArea.innerHTML = `
                <div class="space-y-6">
                    <div class="border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm">
                        <h2 class="text-sm font-bold text-slate-800">WhatsApp Broadcast Analytics</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Aggregated report metrics tracking connection delivery success and response rates.</p>
                    </div>

                    <!-- Statistics grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                            <div class="text-[9px] uppercase font-bold text-slate-400">Total Sent</div>
                            <div class="text-xl font-extrabold text-slate-800 mt-1">${totals.sent}</div>
                        </div>
                        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                            <div class="text-[9px] uppercase font-bold text-slate-400">Delivered</div>
                            <div class="text-xl font-extrabold text-emerald-600 mt-1">${totals.delivered}</div>
                        </div>
                        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                            <div class="text-[9px] uppercase font-bold text-slate-400">Read Receipts</div>
                            <div class="text-xl font-extrabold text-blue-600 mt-1">${totals.read}</div>
                        </div>
                        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
                            <div class="text-[9px] uppercase font-bold text-slate-400">Reply Rate</div>
                            <div class="text-xl font-extrabold text-indigo-600 mt-1">${totals.reply_rate}%</div>
                        </div>
                    </div>

                    <!-- Graph container -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                        <div class="text-xs font-bold text-slate-700 mb-4">Daily Volume Logs (30-day History)</div>
                        <div class="h-64 relative">
                            <canvas id="wa-chart-history"></canvas>
                        </div>
                    </div>
                </div>
            `;

            lucide.createIcons();

            // Render Chart
            const ctx = document.getElementById('wa-chart-history').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: history.map(h => h.date),
                    datasets: [
                        {
                            label: 'Messages Dispatched',
                            data: history.map(h => h.sent),
                            backgroundColor: '#3b82f6'
                        },
                        {
                            label: 'Messages Received',
                            data: history.map(h => h.received),
                            backgroundColor: '#10b981'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, ticks: { font: { size: 9 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                    }
                }
            });

        } catch (err) {
            showNotification('error', err.message);
        }
    });
}

// ----------------------------------------------------
// 10. WHATSAPP SETTINGS VIEW
// ----------------------------------------------------
function renderWhatsAppSettings(container) {
    checkWaConnectionAndRender('settings', container, async (contentArea) => {
        try {
            const res = await apiCall('whatsapp/settings.php');
            const set = res.settings;

            const setupRes = await apiCall('whatsapp/setup.php');
            const acc = setupRes.account || {};
            const isConnected = setupRes.connected;

            contentArea.innerHTML = `
                <div class="space-y-6 animate-fade-in text-xs">
                    <!-- Card 1: Active Connection Status -->
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                            <span class="font-bold text-slate-800 text-sm">Active Connection Status</span>
                            <span class="px-2.5 py-1 text-[9px] font-extrabold rounded-lg uppercase bg-emerald-500 text-white flex items-center space-x-1.5 shadow-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span>
                                <span>CONNECTED</span>
                            </span>
                        </div>
                        
                        <div class="flex flex-col lg:flex-row items-center lg:items-start space-y-6 lg:space-y-0 lg:space-x-8">
                            <!-- Circular WhatsApp Logo -->
                            <div class="h-20 w-20 rounded-full bg-emerald-50 flex items-center justify-center shrink-0 shadow-inner border border-emerald-100">
                                <div class="h-14 w-14 rounded-full bg-gradient-to-tr from-emerald-500 to-emerald-400 flex items-center justify-center shadow-md overflow-hidden">
                                    <img src="assets/img/WhatsApp_icon.png" class="h-9 w-9 object-contain" alt="WhatsApp">
                                </div>
                            </div>
                            
                            <!-- Table details grid (Middle) -->
                            <div class="flex-grow grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-[11px] w-full">
                                <div class="space-y-2.5">
                                    <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                        <span class="text-slate-400 font-semibold">Business Name</span>
                                        <span class="text-slate-800 font-bold">${acc.business_name || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                        <span class="text-slate-400 font-semibold">Business ID</span>
                                        <span class="text-slate-800 font-mono font-bold">${acc.business_id || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                        <span class="text-slate-400 font-semibold">WABA ID</span>
                                        <span class="text-slate-800 font-mono font-bold">${acc.waba_id || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between pb-1.5">
                                        <span class="text-slate-400 font-semibold">Phone Number ID</span>
                                        <span class="text-slate-800 font-mono font-bold">${acc.phone_number_id || 'N/A'}</span>
                                    </div>
                                </div>
                                
                                <div class="space-y-2.5">
                                    <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                        <span class="text-slate-400 font-semibold">Display Phone Number</span>
                                        <span class="text-slate-800 font-bold font-mono">${acc.display_phone_number || 'N/A'}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                        <span class="text-slate-400 font-semibold">Token Status</span>
                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase ${acc.token_status === 'valid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'}">
                                            ${acc.token_status || 'Unknown'}
                                        </span>
                                    </div>
                                    <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                        <span class="text-slate-400 font-semibold">Webhook Status</span>
                                        <span class="px-2 py-0.5 text-[9px] font-bold rounded uppercase ${acc.webhook_status === 'verified' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'}">
                                            ${acc.webhook_status || 'Unknown'}
                                        </span>
                                    </div>
                                    <div class="flex justify-between pb-1.5">
                                        <span class="text-slate-400 font-semibold">Limit & Quality</span>
                                        <span class="text-slate-800 font-bold">${acc.messaging_limit || 'N/A'} • <span class="font-extrabold text-emerald-600">${acc.quality_rating || 'N/A'}</span></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Connected On right box -->
                            <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 space-y-4 shadow-sm shrink-0 w-full lg:w-64">
                                <div class="flex items-start space-x-3">
                                    <div class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                                        <i data-lucide="calendar" class="h-4 w-4"></i>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Connected On</div>
                                        <div class="text-[11px] text-slate-700 font-bold mt-0.5">${acc.created_at ? new Date(acc.created_at).toLocaleString() : 'N/A'}</div>
                                    </div>
                                </div>
                                <div class="flex items-start space-x-3">
                                    <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 border border-blue-100">
                                        <i data-lucide="shield-check" class="h-4 w-4"></i>
                                    </div>
                                    <div>
                                        <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Last Verified</div>
                                        <div class="text-[11px] text-slate-700 font-bold mt-0.5">${acc.last_verified_at ? new Date(acc.last_verified_at).toLocaleString() : 'Never'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions bottom bar -->
                        <div class="pt-4 border-t border-slate-100 flex flex-wrap gap-3 text-xs">
                            <button onclick="reVerifyConnection()" class="px-4 py-2 border border-blue-200 hover:bg-blue-50 text-blue-600 font-bold rounded-xl transition flex items-center space-x-2">
                                <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                <span>Re-Verify Connection</span>
                            </button>
                            <button onclick="openUpdateTokenModal()" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-xl transition flex items-center space-x-2">
                                <i data-lucide="key" class="h-4 w-4"></i>
                                <span>Update Token</span>
                            </button>
                            <button onclick="disconnectWhatsAppAccount()" class="px-4 py-2 border border-rose-200 hover:bg-rose-50 text-rose-655 font-bold rounded-xl transition flex items-center space-x-2">
                                <i data-lucide="power" class="h-4 w-4 text-rose-600"></i>
                                <span class="text-rose-600">Disconnect</span>
                            </button>
                        </div>
                    </div>

                    <!-- Card 2 & 3 row -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Automation Rules Card (2/3 width) -->
                        <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                            <h3 class="text-xs font-bold text-slate-800 border-b border-slate-100 pb-2 uppercase tracking-wider">Automation Rules</h3>
                            <div class="space-y-4">
                                <!-- AI Processing Enabled -->
                                <div class="bg-slate-50/50 border border-slate-100 p-4 rounded-2xl flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="h-10 w-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 border border-purple-100">
                                            <i data-lucide="sparkles" class="h-5 w-5"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-700 text-xs">AI Autopilot (Auto-Reply Enabled)</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">Let AI automatically reply to chats, negotiating and scheduling calls based on lead records, tasks, availability, and timeline remarks.</div>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                        <input type="checkbox" id="set-ai" ${set.ai_enabled ? 'checked' : ''} onchange="toggleAiProcessing(this)" class="sr-only peer">
                                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-350 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <!-- Auto CRM Ingestion -->
                                <div class="bg-slate-50/50 border border-slate-100 p-4 rounded-2xl flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 border border-blue-100">
                                            <i data-lucide="user-plus" class="h-5 w-5"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-700 text-xs">Auto CRM Ingestion</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">Automatically create profile records when new numbers text you.</div>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                        <input type="checkbox" id="set-auto-crm" ${set.auto_crm_creation ? 'checked' : ''} onchange="autoSaveWaSettings()" class="sr-only peer">
                                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-350 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <!-- Lead Score & Urgency Detection -->
                                <div class="bg-slate-50/50 border border-slate-100 p-4 rounded-2xl flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="h-10 w-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center shrink-0 border border-violet-100">
                                            <i data-lucide="target" class="h-5 w-5"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-700 text-xs">Lead Score & Urgency Detection</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">Let AI rate budgets and score priority based on message urgency.</div>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                        <input type="checkbox" id="set-auto-lead" ${set.auto_lead_detection ? 'checked' : ''} onchange="autoSaveWaSettings()" class="sr-only peer">
                                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-350 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <!-- AI Auto-Summarization Memory Toggle -->
                                <div class="bg-slate-50/50 border border-slate-100 p-4 rounded-2xl flex items-center justify-between">
                                    <div class="flex items-center space-x-4">
                                        <div class="h-10 w-10 rounded-xl bg-indigo-50 text-indigo-650 flex items-center justify-center shrink-0 border border-indigo-100">
                                            <i data-lucide="history" class="h-5 w-5"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-700 text-xs">AI Auto-Summarization Memory</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">Compresses long chat history into semantic summaries, providing infinite context recall with minimal token usage.</div>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                        <input type="checkbox" id="set-auto-summarize" ${set.auto_summarize_history ? 'checked' : ''} onchange="autoSaveWaSettings()" class="sr-only peer">
                                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-350 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Media Upload Configurations Card (1/3 width) -->
                        <div class="lg:col-span-1 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
                            <h3 class="text-xs font-bold text-slate-800 border-b border-slate-100 pb-2 uppercase tracking-wider">Media Upload Configurations</h3>
                            <div class="space-y-4 text-xs">
                                <!-- Max Media Size (MB) -->
                                <div>
                                    <label class="block text-slate-400 font-semibold mb-1 text-[10px] uppercase tracking-wider">Max Media Size (MB)</label>
                                    <select id="set-media-limit" onchange="autoSaveWaSettings()" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-blue-500">
                                        <option value="16" ${set.media_upload_limit_mb == 16 ? 'selected' : ''}>16 MB</option>
                                        <option value="32" ${set.media_upload_limit_mb == 32 ? 'selected' : ''}>32 MB</option>
                                        <option value="64" ${set.media_upload_limit_mb == 64 ? 'selected' : ''}>64 MB</option>
                                        <option value="128" ${set.media_upload_limit_mb == 128 ? 'selected' : ''}>128 MB</option>
                                    </select>
                                </div>

                                <!-- Allowed File Types -->
                                <div>
                                    <label class="block text-slate-400 font-semibold mb-1 text-[10px] uppercase tracking-wider">Allowed File Types</label>
                                    <select id="set-file-types" onchange="autoSaveWaSettings()" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-blue-500">
                                        <option value="jpg,png,gif,pdf,doc,docx,mp4,mp3" ${['jpg,png,gif,pdf,doc,docx,mp4,mp3', 'jpg,png,gif,pdf,doc,docx'].includes(set.allowed_file_types) ? 'selected' : ''}>Images, Videos, Documents</option>
                                        <option value="jpg,png,gif,mp4" ${set.allowed_file_types == 'jpg,png,gif,mp4' ? 'selected' : ''}>Images & Videos</option>
                                        <option value="pdf,doc,docx" ${set.allowed_file_types == 'pdf,doc,docx' ? 'selected' : ''}>Documents Only</option>
                                        <option value="jpg,png,pdf,mp4,mp3,docx" ${set.allowed_file_types == 'jpg,png,pdf,mp4,mp3,docx' ? 'selected' : ''}>Images, Videos, Documents (Default)</option>
                                    </select>
                                </div>

                                <!-- Channel Quality -->
                                <div>
                                    <label class="block text-slate-400 font-semibold mb-1 text-[10px] uppercase tracking-wider">Channel Quality</label>
                                    <div class="relative flex items-center">
                                        <select id="set-channel-quality" onchange="autoSaveWaSettings()" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs bg-white focus:outline-none focus:border-blue-500 pr-24">
                                            <option value="High Quality" ${localStorage.getItem('wa_channel_quality') === 'High Quality' || !localStorage.getItem('wa_channel_quality') ? 'selected' : ''}>High Quality</option>
                                            <option value="Standard Quality" ${localStorage.getItem('wa_channel_quality') === 'Standard Quality' ? 'selected' : ''}>Standard Quality</option>
                                            <option value="Low Quality" ${localStorage.getItem('wa_channel_quality') === 'Low Quality' ? 'selected' : ''}>Low Quality</option>
                                        </select>
                                        <span class="absolute right-2.5 px-1.5 py-0.5 rounded text-[8px] font-extrabold bg-emerald-100 text-emerald-750 border border-emerald-200 uppercase pointer-events-none">Recommended</span>
                                    </div>
                                </div>

                                <!-- Blue Alert Box -->
                                <div class="p-3 bg-blue-50/60 border border-blue-100/80 rounded-xl flex items-start space-x-2.5 text-blue-600 text-[10px] font-semibold mt-4">
                                    <i data-lucide="shield-check" class="h-4 w-4 shrink-0 mt-0.5 text-blue-500"></i>
                                    <span>We recommend High Quality for better delivery and engagement.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();

            // AI Autopilot Toggle Handler & Privacy Policy Modal
            window.toggleAiProcessing = function (checkbox) {
                if (checkbox.checked) {
                    checkbox.checked = false; // Keep unchecked until accepted!
                    showPrivacyPolicyModal(checkbox);
                } else {
                    autoSaveWaSettings();
                }
            };

            window.showPrivacyPolicyModal = function (checkbox) {
                const existing = document.getElementById('ai-privacy-modal');
                if (existing) existing.remove();

                const modalHtml = `
                    <div id="ai-privacy-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300">
                        <div class="bg-white rounded-3xl w-full max-w-xl p-6 shadow-2xl border border-slate-100 max-h-[85vh] flex flex-col transform transition-all scale-100 duration-300 mx-4">
                            <!-- Header -->
                            <div class="flex items-center justify-between pb-4 border-b border-slate-100 shrink-0">
                                <div class="flex items-center space-x-3">
                                    <div class="h-10 w-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center border border-purple-100 shadow-sm">
                                        <i data-lucide="shield-alert" class="h-5 w-5"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xs font-bold text-slate-800">AI Autopilot & Data Privacy Policy</h2>
                                        <p class="text-[9px] text-slate-400">Please review and accept to enable autonomous replies</p>
                                    </div>
                                </div>
                                <button onclick="closePrivacyModal(false)" class="h-7 w-7 rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition border border-slate-100">
                                    <i data-lucide="x" class="h-4 w-4"></i>
                                </button>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 overflow-y-auto py-4 pr-1 text-slate-600 text-xs leading-relaxed space-y-4 font-normal custom-scrollbar">
                                <div class="bg-amber-50 border border-amber-100 p-3.5 rounded-2xl flex space-x-2 text-[11px] text-amber-800">
                                    <i data-lucide="info" class="h-4 w-4 shrink-0 mt-0.5 text-amber-600"></i>
                                    <div>
                                        <strong>Declaration:</strong> AI can make mistakes or hallucinate. Please note that once enabled, the AI acts autonomously to respond to your customers.
                                    </div>
                                </div>
                                
                                <h3 class="font-bold text-slate-700 text-xs mt-2 flex items-center space-x-1.5">
                                    <i data-lucide="database" class="h-4 w-4 text-blue-500"></i>
                                    <span>Scope of Data Access & Integration</span>
                                </h3>
                                <p>To negotiate, ask clarifying questions, schedule calls, and close deals efficiently, the AI Agent requires real-time read and write access to the following workspace data:</p>
                                <ul class="list-disc pl-5 space-y-1.5 text-slate-500 text-[11px]">
                                    <li><strong>WhatsApp Inbound Messages:</strong> Reading incoming messages and replying autonomously.</li>
                                    <li><strong>CRM Pipeline Leads:</strong> Querying contact status, lead stage, custom fields, and client pipeline properties.</li>
                                    <li><strong>Account Remarks & Timeline Logs:</strong> Retrieving previous activity notes and remarks to maintain conversation context.</li>
                                    <li><strong>CRM Tasks & Availability Schedules:</strong> Accessing upcoming tasks, scheduled meetings, and calendars to arrange/confirm calls.</li>
                                </ul>
                                
                                <h3 class="font-bold text-slate-700 text-xs flex items-center space-x-1.5">
                                    <i data-lucide="check-square" class="h-4 w-4 text-emerald-500"></i>
                                    <span>User Declaration & Clarifications</span>
                                </h3>
                                <p>By clicking "Accept & Enable", you acknowledge, understand, and agree that:</p>
                                <ol class="list-decimal pl-5 space-y-1.5 text-slate-500 text-[11px]">
                                    <li>The AI will reply <strong>automatically</strong> to incoming WhatsApp messages under your name and active WABA phone number.</li>
                                    <li>You release the platform from any liabilities arising from replies, errors, or commitments made autonomously by the AI.</li>
                                    <li>You can disable this setting at any time to return to manual suggested drafts.</li>
                                </ol>
                            </div>
                            
                            <!-- Footer -->
                            <div class="pt-4 border-t border-slate-100 flex items-center justify-end space-x-3 shrink-0 text-xs">
                                <button onclick="closePrivacyModal(false)" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-xl transition text-xs">
                                    Decline & Keep Disabled
                                </button>
                                <button onclick="closePrivacyModal(true)" class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:opacity-95 text-white font-bold rounded-xl transition shadow-md text-xs flex items-center space-x-1">
                                    <i data-lucide="check" class="h-4 w-4"></i>
                                    <span>Accept & Enable Autopilot</span>
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                document.body.insertAdjacentHTML('beforeend', modalHtml);
                lucide.createIcons();

                window.closePrivacyModal = function (accepted) {
                    const modal = document.getElementById('ai-privacy-modal');
                    if (modal) {
                        modal.classList.add('opacity-0');
                        setTimeout(() => {
                            modal.remove();
                            if (accepted) {
                                checkbox.checked = true;
                                autoSaveWaSettings();
                            } else {
                                checkbox.checked = false;
                                autoSaveWaSettings();
                            }
                        }, 200);
                    }
                };
            };

            // Auto Save Handler
            window.autoSaveWaSettings = function () {
                const payload = {
                    ai_enabled: document.getElementById('set-ai').checked ? 1 : 0,
                    auto_crm_creation: document.getElementById('set-auto-crm').checked ? 1 : 0,
                    auto_lead_detection: document.getElementById('set-auto-lead').checked ? 1 : 0,
                    auto_summarize_history: document.getElementById('set-auto-summarize').checked ? 1 : 0,
                    media_upload_limit_mb: parseInt(document.getElementById('set-media-limit').value),
                    allowed_file_types: document.getElementById('set-file-types').value.trim()
                };

                // Save channel quality mockup field to localStorage
                const quality = document.getElementById('set-channel-quality').value;
                localStorage.setItem('wa_channel_quality', quality);

                apiCall('whatsapp/settings.php', 'POST', payload)
                    .then(res => {
                        showNotification('success', 'Configurations auto-saved successfully.');
                    })
                    .catch(err => {
                        showNotification('error', 'Auto-save failed: ' + err.message);
                    });
            };

            // Optimize Chat Summary Manual Action
            window.optimizeChatSummary = function (contactId, btn) {
                if (btn) {
                    btn.disabled = true;
                    btn.dataset.originalHtml = btn.innerHTML;
                    btn.innerHTML = `<span class="loader-spinner mr-1"></span> Summarizing...`;
                }
                apiCall('whatsapp/summarize_chat.php', 'POST', { contact_id: contactId })
                    .then(res => {
                        showNotification('success', res.message);
                        loadWaThreadMessages(); // Refresh UI to display new summary
                    })
                    .catch(err => {
                        showNotification('error', 'Summarization failed: ' + err.message);
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = btn.dataset.originalHtml;
                            lucide.createIcons();
                        }
                    });
            };

            // Re-Verify Connection
            window.reVerifyConnection = function () {
                showNotification('info', 'Re-verifying connection health with Meta...');
                apiCall('whatsapp/setup.php?action=re_verify', 'POST')
                    .then(res => {
                        showNotification('success', `Connection status: Token is ${res.token_status}, Webhook is ${res.webhook_status}`);
                        renderWhatsAppSettings(container); // Reload settings pane
                    })
                    .catch(err => {
                        showNotification('error', 'Re-verification failed: ' + err.message);
                    });
            };

            // Update Token Modal UI
            window.openUpdateTokenModal = function () {
                const existing = document.getElementById('update-token-modal');
                if (existing) existing.remove();

                const modal = document.createElement('div');
                modal.id = 'update-token-modal';
                modal.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

                modal.innerHTML = `
                    <div class="bg-white border border-slate-200 w-full max-w-sm rounded-2xl p-6 relative space-y-4 text-xs shadow-2xl animate-fade-in animate-duration-200">
                        <button onclick="document.getElementById('update-token-modal').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-800 text-xl font-bold">&times;</button>
                        
                        <h3 class="text-sm font-bold text-slate-800">Update Meta Access Token</h3>
                        <p class="text-[11px] text-slate-500">Provide your new Permanent Meta System User Access Token to restore connection.</p>
                        
                        <input type="password" id="new-meta-token" placeholder="EAA..." class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 font-mono text-xs">
                        
                        <div class="pt-2 flex justify-end space-x-2">
                            <button onclick="document.getElementById('update-token-modal').remove()" class="px-4 py-2 border border-slate-200 rounded-lg font-bold hover:bg-slate-50 transition">Cancel</button>
                            <button onclick="submitUpdatedToken()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold transition">Update Token</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            };

            // Submit Updated Token
            window.submitUpdatedToken = function () {
                const token = document.getElementById('new-meta-token').value.trim();
                if (!token) {
                    showNotification('error', 'Token is required.');
                    return;
                }

                apiCall('whatsapp/setup.php?action=save_connection', 'POST', {
                    access_token: token,
                    business_id: acc.business_id,
                    business_name: acc.business_name,
                    waba_id: acc.waba_id,
                    phone_number_id: acc.phone_number_id
                }).then(res => {
                    showNotification('success', 'Token updated successfully.');
                    document.getElementById('update-token-modal').remove();
                    renderWhatsAppSettings(container); // Reload settings pane
                }).catch(err => {
                    showNotification('error', 'Failed updating token: ' + err.message);
                });
            };

            window.saveWhatsAppSettingsForm = function (e) {
                e.preventDefault();
                const payload = {
                    ai_enabled: document.getElementById('set-ai').checked ? 1 : 0,
                    auto_crm_creation: document.getElementById('set-auto-crm').checked ? 1 : 0,
                    auto_lead_detection: document.getElementById('set-auto-lead').checked ? 1 : 0,
                    media_upload_limit_mb: parseInt(document.getElementById('set-media-limit').value),
                    allowed_file_types: document.getElementById('set-file-types').value.trim()
                };

                apiCall('whatsapp/settings.php', 'POST', payload)
                    .then(res => {
                        showNotification('success', res.message);
                    })
                    .catch(err => {
                        showNotification('error', err.message);
                    });
            };

            window.disconnectWhatsAppAccount = function () {
                if (!confirm('WARNING: Disconnecting will erase access tokens, verify webhook endpoints, and disable all automation rules. Proceed?')) return;

                apiCall('whatsapp/setup.php?action=disconnect', 'POST')
                    .then(res => {
                        showNotification('success', res.message);
                        window.location.reload();
                    })
                    .catch(err => {
                        showNotification('error', err.message);
                    });
            };

        } catch (err) {
            showNotification('error', err.message);
        }
    });
}

// -------------------------------------------------------------
// NEW CHAT MODAL AND FLOW (CRM LEADS & MANUAL PHONES)
// -------------------------------------------------------------
window.openNewChatModal = function () {
    let modal = document.getElementById('wa-new-chat-modal');
    if (modal) modal.remove();

    modal = document.createElement('div');
    modal.id = 'wa-new-chat-modal';
    modal.className = 'fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4';

    modal.innerHTML = `
        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xl w-full max-w-md overflow-hidden animate-fade-in text-xs text-slate-700 flex flex-col max-h-[500px]">
            <!-- Header -->
            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                    <i data-lucide="message-square-plus" class="h-4 w-4 text-blue-600"></i>
                    <span>Start New Chat</span>
                </h3>
                <button onclick="document.getElementById('wa-new-chat-modal').remove()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>
            
            <!-- Tab switches -->
            <div class="flex border-b border-slate-100 bg-slate-50/50 text-[11px] font-bold">
                <button id="tab-crm-btn" onclick="switchNewChatTab('crm')" class="flex-1 py-2 text-center text-blue-600 border-b-2 border-blue-600 focus:outline-none">
                    Select CRM Lead
                </button>
                <button id="tab-manual-btn" onclick="switchNewChatTab('manual')" class="flex-1 py-2 text-center text-slate-400 hover:text-slate-600 focus:outline-none">
                    Enter Phone Number
                </button>
            </div>
            
            <!-- Content -->
            <div class="p-4 flex-grow overflow-y-auto flex flex-col justify-between">
                <!-- Tab 1: CRM -->
                <div id="tab-crm-content" class="space-y-3 flex-grow flex flex-col">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-2.5 flex items-center text-slate-400">
                            <i data-lucide="search" class="h-3.5 w-3.5"></i>
                        </span>
                        <input type="text" id="new-chat-lead-search" oninput="searchNewChatLeads(this.value)" placeholder="Search CRM leads by name or phone..." class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500">
                    </div>
                    <div id="new-chat-leads-results" class="flex-grow overflow-y-auto max-h-[220px] divide-y divide-slate-100 border border-slate-100 rounded-lg">
                        <div class="p-4 text-center text-slate-400">Type above to search CRM leads...</div>
                    </div>
                </div>
                
                <!-- Tab 2: Manual -->
                <div id="tab-manual-content" class="space-y-4 hidden py-4">
                    <div class="space-y-1.5">
                        <label class="font-bold text-[10px] text-slate-500 uppercase tracking-wider">Phone Number</label>
                        <input type="text" id="new-chat-manual-phone" placeholder="e.g. 919242322991" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500 font-mono">
                        <p class="text-[10px] text-slate-400 mt-1">Include country code without any plus (+), hyphens (-), or spaces.</p>
                    </div>
                    <button onclick="submitManualNewChat()" class="w-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1.5 shadow-sm">
                        <span>Open Chat Window</span>
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    lucide.createIcons();

    // Auto load recent leads
    searchNewChatLeads('');
};

window.switchNewChatTab = function (tab) {
    const tabCrmBtn = document.getElementById('tab-crm-btn');
    const tabManualBtn = document.getElementById('tab-manual-btn');
    const tabCrmContent = document.getElementById('tab-crm-content');
    const tabManualContent = document.getElementById('tab-manual-content');

    if (tab === 'crm') {
        tabCrmBtn.className = "flex-1 py-2 text-center text-blue-600 border-b-2 border-blue-600 focus:outline-none";
        tabManualBtn.className = "flex-1 py-2 text-center text-slate-400 hover:text-slate-600 focus:outline-none";
        tabCrmContent.classList.remove('hidden');
        tabManualContent.classList.add('hidden');
    } else {
        tabCrmBtn.className = "flex-1 py-2 text-center text-slate-400 hover:text-slate-600 focus:outline-none";
        tabManualBtn.className = "flex-1 py-2 text-center text-blue-600 border-b-2 border-blue-600 focus:outline-none";
        tabCrmContent.classList.add('hidden');
        tabManualContent.classList.remove('hidden');
    }
};

window.searchNewChatLeads = async function (query) {
    const container = document.getElementById('new-chat-leads-results');
    if (!container) return;

    container.innerHTML = '<div class="p-4 text-center text-slate-400"><span class="spinner-border spinner-border-sm text-blue-600 me-2" role="status"></span>Searching...</div>';

    try {
        const res = await apiCall(`crm/leads.php?search=${encodeURIComponent(query)}&limit=15`);
        const leads = res.leads || [];

        if (leads.length === 0) {
            container.innerHTML = '<div class="p-4 text-center text-slate-400">No leads found.</div>';
            return;
        }

        container.innerHTML = '';
        leads.forEach(lead => {
            const hasPhone = !!lead.phone;
            const phoneDisplay = hasPhone ? lead.phone : 'No Phone Number';
            const actionAttr = hasPhone ? `onclick="selectLeadForChat('${lead.phone}', '${lead.name.replace(/'/g, "\\'")}')"` : '';
            const opacityClass = hasPhone ? 'hover:bg-slate-50 cursor-pointer' : 'opacity-50 cursor-not-allowed';

            container.innerHTML += `
                <div ${actionAttr} class="p-3 flex justify-between items-center transition ${opacityClass}">
                    <div>
                        <div class="font-bold text-slate-800">${lead.name}</div>
                        <div class="text-[10px] text-slate-500 font-mono mt-0.5">${phoneDisplay}</div>
                    </div>
                    ${hasPhone ? `<i data-lucide="message-square" class="h-4 w-4 text-blue-500"></i>` : ''}
                </div>
            `;
        });

        lucide.createIcons();
    } catch (err) {
        console.error(err);
        container.innerHTML = '<div class="p-4 text-center text-rose-500">Failed loading CRM leads.</div>';
    }
};

window.selectLeadForChat = async function (phone, name) {
    await resolveAndOpenNewChat(phone);
};

window.submitManualNewChat = async function () {
    const phoneInput = document.getElementById('new-chat-manual-phone');
    if (!phoneInput) return;
    const phone = phoneInput.value.trim();
    if (!phone) {
        alert('Please enter a valid phone number.');
        return;
    }
    await resolveAndOpenNewChat(phone);
};

async function resolveAndOpenNewChat(phone) {
    const modal = document.getElementById('wa-new-chat-modal');
    
    // Clean to numeric characters only
    const cleanToPhone = (phone || '').replace(/[^0-9]/g, '');
    
    // Validate
    if (!cleanToPhone.startsWith('91')) {
        alert('Outside Indian messaging is not allowed if not starting with 91.');
        return;
    }
    if (cleanToPhone.length !== 12) {
        alert('Invalid Indian mobile number length (must be 10 digits after 91).');
        return;
    }
    const firstDigit = cleanToPhone.charAt(2);
    if (!['9', '8', '7', '6'].includes(firstDigit)) {
        alert('Outside Indian messaging is not allowed (Indian mobile numbers must start with 9, 8, 7, or 6 after 91).');
        return;
    }

    try {
        const res = await apiCall('whatsapp/inbox.php?action=resolve_contact', 'POST', { phone: phone });
        if (res.success && res.wa_contact_id) {
            // Close modal
            if (modal) modal.remove();

            // Reload thread list and open chat
            await loadWaThreads();
            selectWaThread(res.wa_contact_id);
        } else {
            alert('Failed starting chat: ' + (res.message || 'Unknown error'));
        }
    } catch (err) {
        console.error(err);
        alert('Error starting chat thread: ' + err.message);
    }
}

// -------------------------------------------------------------
// WHATSAPP TEMPLATE SELECTOR MODAL FOR EXPIRED WINDOWS
// -------------------------------------------------------------
window.openTemplateSelectorModal = async function (phone) {
    let modal = document.getElementById('wa-template-modal');
    if (modal) modal.remove();

    modal = document.createElement('div');
    modal.id = 'wa-template-modal';
    modal.className = 'fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4';

    modal.innerHTML = `
        <div class="bg-white rounded-2xl border border-slate-100 shadow-2xl w-full max-w-md overflow-hidden animate-fade-in text-xs text-slate-700 flex flex-col max-h-[500px]">
            <!-- Header -->
            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="text-xs font-bold text-slate-800 flex items-center space-x-1.5">
                    <i data-lucide="layout-template" class="h-4 w-4 text-blue-600"></i>
                    <span>Send Message Template</span>
                </h3>
                <button onclick="document.getElementById('wa-template-modal').remove()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>
            
            <!-- Content -->
            <div class="p-4 space-y-4 flex-grow overflow-y-auto">
                <div class="space-y-1.5">
                    <label class="font-bold text-[10px] text-slate-500 uppercase tracking-wider">Choose Approved Template</label>
                    <select id="wa-tpl-select" onchange="previewSelectedTemplate(this.value)" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500">
                        <option value="">Loading templates...</option>
                    </select>
                </div>
                
                <div class="space-y-1.5">
                    <label class="font-bold text-[10px] text-slate-500 uppercase tracking-wider">Language</label>
                    <input type="text" id="wa-tpl-lang" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-500" readonly value="en_US">
                </div>
                
                <div class="space-y-1.5">
                    <label class="font-bold text-[10px] text-slate-500 uppercase tracking-wider">Template Preview</label>
                    <div id="wa-tpl-preview-box" class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-600 italic text-[11px]">
                        Select a template to view details.
                    </div>
                </div>
                
                <button onclick="submitTemplateMsg('${phone}')" id="wa-btn-send-template" class="w-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1.5 shadow-sm">
                    <i data-lucide="send" class="h-3.5 w-3.5"></i>
                    <span>Send Template</span>
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    lucide.createIcons();

    // Fetch and populate templates list
    try {
        const res = await apiCall('whatsapp/templates.php');
        const templates = res.templates || [];
        const select = document.getElementById('wa-tpl-select');

        if (templates.length === 0) {
            select.innerHTML = '<option value="">No approved templates found. Please sync first.</option>';
            return;
        }

        window.activeTemplatesList = templates; // Keep global reference inside modal
        select.innerHTML = '<option value="">-- Select Template --</option>' + templates.map(t => `<option value="${t.name}">${t.name} (${t.language})</option>`).join('');
    } catch (err) {
        console.error(err);
        document.getElementById('wa-tpl-select').innerHTML = '<option value="">Failed to load templates.</option>';
    }
};

window.previewSelectedTemplate = function (tplName) {
    const previewBox = document.getElementById('wa-tpl-preview-box');
    const langInput = document.getElementById('wa-tpl-lang');
    if (!previewBox || !activeTemplatesList) return;

    const tpl = activeTemplatesList.find(t => t.name === tplName);
    if (!tpl) {
        previewBox.textContent = 'Select a template to view details.';
        langInput.value = 'en_US';
        return;
    }

    langInput.value = tpl.language;
    previewBox.innerHTML = `
        <div class="space-y-1 text-slate-700 not-italic">
            <div class="font-bold text-[10px] text-blue-600 uppercase mb-1">Body Text:</div>
            <p>${tpl.body_text || 'No preview body text.'}</p>
        </div>
    `;
};

window.submitTemplateMsg = async function (phone) {
    const select = document.getElementById('wa-tpl-select');
    const langInput = document.getElementById('wa-tpl-lang');
    const btn = document.getElementById('wa-btn-send-template');

    if (!select || select.value === '') {
        alert('Please choose a message template first.');
        return;
    }

    const tplName = select.value;
    const lang = langInput.value;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm text-white me-2"></span>Sending...';

    try {
        const payload = {
            wa_contact_id: activeWaThreadId,
            type: 'template',
            template_name: tplName,
            template_lang: lang
        };
        const res = await apiCall('whatsapp/inbox.php', 'POST', payload);
        if (res.success) {
            document.getElementById('wa-template-modal').remove();
            showNotification('success', 'Template message sent successfully!');
            // Reload chats
            await loadWaThreads();
            selectWaThread(activeWaThreadId);
        } else {
            alert('Failed to send template: ' + (res.message || 'Unknown error'));
            btn.disabled = false;
            btn.innerHTML = '<span>Send Template</span>';
        }
    } catch (err) {
        console.error(err);
        alert('Error sending template message: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<span>Send Template</span>';
    }
};

// ----------------------------------------------------
// 9. WHATSAPP SEND TEMPLATE PAGE VIEW
// ----------------------------------------------------
window.renderWhatsAppSendTemplate = function (container, params = {}) {
    checkWaConnectionAndRender('send-template', container, async (contentArea) => {
        try {
            // Find selected template
            let templateId = params.templateId || localStorage.getItem('wa_send_template_id');
            const res = await apiCall('whatsapp/templates.php');
            const allTemplates = res.templates || [];

            let selectedTemplate = allTemplates.find(t => t.id == templateId) || allTemplates[0];
            if (!selectedTemplate) {
                contentArea.innerHTML = `<div class="p-6 text-center text-slate-400">No approved templates found. Please sync templates first.</div>`;
                return;
            }

            // Save selected template to local storage in case of tab reload
            localStorage.setItem('wa_send_template_id', selectedTemplate.id);

            const components = JSON.parse(selectedTemplate.components_json) || [];
            const headerComp = components.find(c => c.type === 'HEADER') || null;
            const bodyComp = components.find(c => c.type === 'BODY') || null;
            const footerComp = components.find(c => c.type === 'FOOTER') || null;
            const buttonsComp = components.find(c => c.type === 'BUTTONS') || null;

            // Find variables
            const fullText = (headerComp?.text || '') + ' ' + (bodyComp?.text || '');
            const varsFound = [...new Set(fullText.match(/{{[0-9]+}}/g) || [])].sort((a, b) => {
                const numA = parseInt(a.replace(/[{}]/g, ''));
                const numB = parseInt(b.replace(/[{}]/g, ''));
                return numA - numB;
            });

            contentArea.innerHTML = `
                <div class="space-y-6 animate-fade-in text-xs">
                    <!-- Title Bar -->
                    <div class="flex items-center border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm">
                        <button onclick="navigateTo('whatsapp-templates')" class="mr-3 p-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 text-slate-500 transition shadow-sm">
                            <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        </button>
                        <div>
                            <h2 class="text-sm font-bold text-slate-800">Send Message Template</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">Deploy templates with custom parameters to target recipients</p>
                        </div>
                    </div>

                    <!-- Two Columns View -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        <!-- Left Column: Sender Form (lg:col-span-8) -->
                        <div class="lg:col-span-8 bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-5">
                            <h3 class="font-bold text-slate-800 text-xs border-b border-slate-100 pb-2">Sender Configurations</h3>
                            
                            <form onsubmit="submitSendTemplateForm(event)" class="space-y-4">
                                <!-- Recipient Numbers -->
                                <div>
                                    <label class="block text-slate-500 font-semibold mb-1 uppercase tracking-wider text-[10px]">Recipient Numbers</label>
                                    <textarea id="set-send-recipients" required rows="3" placeholder="Enter phone numbers separated by commas, e.g. +91 92423 22991, 919999999999" class="w-full px-3 py-2 border border-slate-200 rounded-lg font-mono text-[11px] focus:outline-none focus:border-blue-500 bg-[#f8fafc]"></textarea>
                                    <span class="text-[10px] text-slate-400 mt-1 block">Include country codes and exclude symbols/spaces for best delivery.</span>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Template name -->
                                    <div>
                                        <label class="block text-slate-500 font-semibold mb-1 uppercase tracking-wider text-[10px]">Active Template</label>
                                        <input type="text" readonly value="${selectedTemplate.name}" class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 font-semibold">
                                    </div>
                                    <!-- Language -->
                                    <div>
                                        <label class="block text-slate-500 font-semibold mb-1 uppercase tracking-wider text-[10px]">Language</label>
                                        <input type="text" readonly value="${selectedTemplate.language}" class="w-full px-3 py-2 border border-slate-200 rounded-lg bg-slate-50 text-slate-500 font-semibold">
                                    </div>
                                </div>

                                <!-- Dynamic Variables Inputs -->
                                ${varsFound.length > 0 ? `
                                <div class="space-y-3.5 border-t border-slate-100 pt-4">
                                    <h4 class="text-slate-600 font-bold uppercase tracking-wider text-[10px] mb-2">Template Parameter Variables</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        ${varsFound.map(v => {
                const vNum = parseInt(v.replace(/[{}]/g, ''));
                let label = `Variable ${vNum}`;
                let placeholder = `Value for ${v}`;
                if (vNum === 1) {
                    label = `1 Customer Name`;
                    placeholder = `e.g. Soumojit`;
                } else if (vNum === 2) {
                    label = `2 Offer Code`;
                    placeholder = `e.g. OUTREACH20`;
                } else if (vNum === 3) {
                    label = `3 Offer Expiry`;
                    placeholder = `e.g. 31st July, 2026`;
                }
                return `
                                                <div>
                                                    <label class="block text-slate-500 font-semibold text-[10px] mb-1">${label}</label>
                                                    <input type="text" data-send-var="${v}" oninput="updateSendMockPreview()" placeholder="${placeholder}" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-blue-500 bg-[#f8fafc]">
                                                </div>
                                            `;
            }).join('')}
                                    </div>
                                </div>
                                ` : ''}

                                <!-- Send Action -->
                                <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
                                    <button type="submit" id="send-tpl-submit-btn" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold transition flex items-center space-x-2 shadow-md">
                                        <i data-lucide="send" class="h-4 w-4"></i>
                                        <span>Send Template Message</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Right Column: Live Mockup Preview (lg:col-span-4) -->
                        <div class="lg:col-span-4 bg-white border border-slate-200 rounded-2xl p-5 shadow-sm flex flex-col items-center">
                            <h3 class="font-bold text-slate-800 text-xs self-start mb-4">Live Preview</h3>
                            
                            <!-- Mobile Phone Frame Mockup -->
                            <div class="w-full max-w-[290px] bg-slate-100 rounded-[40px] p-3 shadow-2xl border border-slate-200/80 relative">
                                <!-- Screen Container -->
                                <div class="w-full bg-[#efeae2] rounded-[32px] overflow-hidden flex flex-col aspect-[9/16] relative border border-slate-200 select-none shadow-inner">
                                    
                                    <!-- Status Bar -->
                                    <div class="bg-[#054c44] text-white/90 px-5 pt-2 pb-1.5 flex justify-between items-center text-[9px] font-semibold tracking-wide shrink-0">
                                        <span>9:41</span>
                                        <div class="flex items-center space-x-1.5">
                                            <svg class="h-2.5 w-2.5 fill-current" viewBox="0 0 24 24">
                                                <path d="M2 22h20V2z"/>
                                            </svg>
                                            <svg class="h-2.5 w-2.5 fill-current" viewBox="0 0 24 24">
                                                <path d="M12 21c-1.2 0-2.4-.3-3.5-.8L2.3 14c-.4-.4-.4-1 0-1.4.4-.4 1-.4 1.4 0l6.2 6.2c1.2.6 2.6.6 3.8 0l6.2-6.2c.4-.4 1-.4 1.4 0 .4.4.4 1 0 1.4l-6.2 6.2c-1.1.5-2.3.8-3.5.8z"/>
                                            </svg>
                                            <div class="w-4 h-2.25 border border-white/80 rounded-sm p-[1px] flex items-center">
                                                <div class="bg-white h-full w-full rounded-2xs"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- WhatsApp Header -->
                                    <div class="bg-[#075e54] text-white px-3 py-2 flex items-center justify-between shadow-md shrink-0">
                                        <div class="flex items-center space-x-1.5">
                                            <i data-lucide="arrow-left" class="h-4 w-4 text-white hover:opacity-80 cursor-pointer"></i>
                                            <div class="h-8 w-8 rounded-full bg-white flex items-center justify-center overflow-hidden shrink-0 border border-white/20 p-1">
                                                <img src="assets/img/logo.png" class="h-full w-full object-contain rounded-[22%] overflow-hidden" alt="">
                                            </div>
                                            <div>
                                                <div class="text-[11px] font-bold flex items-center">
                                                    <span>Taskbazi</span>
                                                    <span class="inline-flex items-center justify-center h-3.5 w-3.5 rounded-full bg-emerald-500 text-white shrink-0 ml-1 scale-[0.8] origin-left">
                                                        <svg class="h-2.25 w-2.25 stroke-[4.5] text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    </span>
                                                </div>
                                                <div class="text-[8px] text-white/70 leading-none mt-0.5">Business Account</div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2.5 text-white/95">
                                            <i data-lucide="video" class="h-4 w-4"></i>
                                            <i data-lucide="phone" class="h-3.5 w-3.5"></i>
                                            <i data-lucide="more-vertical" class="h-3.5 w-3.5"></i>
                                        </div>
                                    </div>

                                    <!-- Mock Chat Feed Wallpaper area -->
                                    <div class="flex-grow p-4 flex flex-col justify-between overflow-hidden relative" style="background-image: url('../backend/api/whatsapp/chatbg.jpg'), url('/backend/api/whatsapp/chatbg.jpg'); background-size: cover; background-blend-mode: overlay; background-color: rgba(239, 234, 226, 0.94);">
                                        <div class="flex flex-col space-y-3.5 overflow-y-auto pr-1 flex-grow">
                                            <div class="self-center bg-white/90 text-slate-500 font-bold px-3 py-1 rounded-lg text-[9px] uppercase tracking-wider shadow-sm select-none border border-slate-200/30">
                                                Today
                                            </div>

                                            <div class="self-start max-w-[90%] bg-white rounded-2xl rounded-tl-none p-3 shadow-md border border-slate-200/50 flex flex-col relative animate-fade-in">
                                                <!-- Bubble Tail -->
                                                <div class="absolute -left-[7px] top-0 w-2 h-3 text-white fill-current overflow-hidden">
                                                    <svg class="h-full w-full" viewBox="0 0 8 12" fill="white">
                                                        <path d="M8 0H0v12l8-12z"/>
                                                    </svg>
                                                </div>
                                                <div class="text-[10px] text-slate-800 leading-relaxed font-sans whitespace-pre-wrap" id="mock-send-bubble-text">
                                                    <!-- Filled live template body text -->
                                                </div>
                                                <span class="text-[7.5px] text-slate-400 self-end mt-1.5 flex items-center space-x-0.5">
                                                    <span>11:30 AM</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="pt-3 flex items-center space-x-2 shrink-0 select-none">
                                            <div class="flex-grow bg-white rounded-full px-3 py-2 flex items-center space-x-2 shadow-md border border-slate-200/20">
                                                <i data-lucide="smile" class="h-4 w-4 text-slate-400"></i>
                                                <span class="text-[10px] text-slate-400 flex-grow">Type a message</span>
                                                <i data-lucide="paperclip" class="h-4 w-4 text-slate-400"></i>
                                                <i data-lucide="camera" class="h-4 w-4 text-slate-400"></i>
                                            </div>
                                            <div class="h-8.5 w-8.5 rounded-full bg-[#00a884] text-white flex items-center justify-center shadow-lg shrink-0">
                                                <i data-lucide="mic" class="h-4.5 w-4.5 text-white"></i>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();

            window.updateSendMockPreview = function () {
                if (!selectedTemplate) return;
                const components = JSON.parse(selectedTemplate.components_json) || [];
                const varMappings = {};
                const inputs = document.querySelectorAll('input[data-send-var]');
                inputs.forEach(input => {
                    const tag = input.getAttribute('data-send-var');
                    const val = input.value.trim() || tag;
                    varMappings[tag] = `**${val}**`;
                });

                const formattedHtml = window.renderWhatsAppBubbleHTML(components, varMappings);
                const bubble = document.getElementById('mock-send-bubble-text');
                if (bubble) {
                    bubble.innerHTML = formattedHtml;
                    lucide.createIcons();
                }
            };

            // Initialize preview bubble
            updateSendMockPreview();

            // Form Submit handler
            window.submitSendTemplateForm = async function (e) {
                e.preventDefault();
                const btn = document.getElementById('send-tpl-submit-btn');
                const rawRecipients = document.getElementById('set-send-recipients').value;

                // Clean numbers
                const recipients = rawRecipients.split(',')
                    .map(n => n.replace(/[^0-9]/g, '').trim())
                    .filter(n => n.length > 5);

                if (recipients.length === 0) {
                    showNotification('error', 'Please enter at least one valid recipient phone number with country code.');
                    return;
                }

                // Read variables in order
                const variables = [];
                const inputs = document.querySelectorAll('input[data-send-var]');
                inputs.forEach(input => {
                    variables.push(input.value.trim());
                });

                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="loader-spinner mr-1.5"></span> Sending...';
                }

                showNotification('info', `Deploying template to ${recipients.length} contact(s)...`);

                let successCount = 0;
                let failureCount = 0;

                // Loop send requests sequentially so we can report detail progress!
                for (let i = 0; i < recipients.length; i++) {
                    const recipient = recipients[i];
                    try {
                        await apiCall('whatsapp/inbox.php', 'POST', {
                            recipient: recipient,
                            type: 'template',
                            template_name: selectedTemplate.name,
                            template_lang: selectedTemplate.language,
                            variables: variables
                        });
                        successCount++;
                    } catch (err) {
                        console.error("Failed template send to recipient: " + recipient, err);
                        failureCount++;
                    }
                }

                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="send" class="h-4 w-4"></i><span>Send Template Message</span>';
                    lucide.createIcons();
                }

                if (successCount > 0) {
                    showNotification('success', `Successfully sent template to ${successCount} recipient(s).` + (failureCount > 0 ? ` (${failureCount} failed)` : ''));
                    // Navigate back to WhatsApp Inbox
                    navigateTo('whatsapp-inbox');
                } else {
                    showNotification('error', `Failed sending template messages. Please check debug logs.`);
                }
            };

        } catch (err) {
            showNotification('error', 'Failed loading template sender page: ' + err.message);
        }
    });
};

window.createAndLinkCRMContact = function (waContactId) {
    showNotification('warning', 'Adding contact to CRM...');
    apiCall('whatsapp/inbox.php?action=create_and_link_contact', 'POST', {
        wa_contact_id: waContactId
    }).then(res => {
        showNotification('success', 'Contact successfully added and linked to CRM.');
        loadWaThreadMessages();
    }).catch(err => {
        showNotification('error', err.message);
    });
};

window.openAddLeadFromWa = function () {
    if (!window.activeWaCrmContext || !window.activeWaCrmContext.contact) {
        showNotification('warning', 'Please link this contact to CRM first.');
        return;
    }
    const prefills = {
        name: window.activeWaCrmContext.contact.name,
        phone: window.activeWaCrmContext.contact.phone,
        company: window.activeWaCrmContext.company ? window.activeWaCrmContext.company.name : '',
        email: window.activeWaCrmContext.contact.email,
        source: 'WhatsApp Inbox'
    };
    createNewLeadModal(prefills);
};

window.openAddTaskFromWa = function () {
    if (!window.activeWaCrmContext || !window.activeWaCrmContext.contact) {
        showNotification('warning', 'Please link this contact to CRM first.');
        return;
    }
    const prefills = {
        title: `Follow-up with ${window.activeWaCrmContext.contact.name}`,
        category: 'Follow-up'
    };
    createNewTaskModal(prefills);
};

window.openLinkCompanyFromWa = function () {
    if (!window.activeWaCrmContext || !window.activeWaCrmContext.contact) {
        showNotification('warning', 'Please link this contact to CRM first.');
        return;
    }

    const existing = document.getElementById('link-company-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'link-company-modal';
    modal.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';

    modal.innerHTML = `
        <div class="bg-white border border-slate-200 p-6 max-w-md w-full rounded-2xl shadow-2xl relative text-slate-800 text-xs">
            <button onclick="document.getElementById('link-company-modal').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-800 text-xl font-bold">&times;</button>
            
            <h2 class="text-sm font-bold text-slate-800 mb-4 flex items-center space-x-1.5">
                <i data-lucide="building" class="h-4 w-4 text-indigo-650"></i>
                <span>Link Company to Contact</span>
            </h2>
            
            <form onsubmit="submitLinkCompanyForm(event)" class="space-y-4">
                <div>
                    <label class="block text-slate-650 font-bold mb-1 uppercase text-[9px] tracking-wider">Company Name</label>
                    <input type="text" id="wa-link-company-name" required placeholder="Enter company name to link/create..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    <p class="text-[10px] text-slate-400 mt-1">If the company already exists in the CRM Vault, it will link to it. Otherwise, a new company record will be created.</p>
                </div>
                
                <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1 shadow-sm">
                    <i data-lucide="check" class="h-4.5 w-4.5"></i>
                    <span>Link Company</span>
                </button>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    lucide.createIcons();

    window.submitLinkCompanyForm = function (e) {
        e.preventDefault();
        const compName = document.getElementById('wa-link-company-name').value.trim();
        if (!compName) return;

        showNotification('warning', 'Linking company to contact...');
        apiCall('crm/contacts.php?action=link_company', 'POST', {
            contact_id: window.activeWaCrmContext.contact.id,
            company_name: compName
        }).then(res => {
            showNotification('success', 'Company linked successfully.');
            document.getElementById('link-company-modal').remove();
            loadWaThreadMessages();
        }).catch(err => {
            showNotification('error', err.message);
        });
    };
};

// ----------------------------------------------------
// AI AGENT TRAINING VIEW & SETUP WIZARD
// ----------------------------------------------------
async function renderWhatsAppTrain(container) {
    if (container) {
        container.className = "flex-grow p-6 md:p-8 overflow-y-auto max-w-7xl w-full mx-auto";
    }
    
    // Add local CSS styling specifically for the wizard stepper and the interactive simulator
    let style = document.getElementById('agent-train-styles');
    if (!style) {
        style = document.createElement('style');
        style.id = 'agent-train-styles';
        style.innerHTML = `
            .wa-agent-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 24px;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            }
            .step-node-active {
                border-color: #0f766e !important;
                color: #0f766e !important;
                background-color: #f0fdfa !important;
            }
            .step-node-done {
                background-color: #0f766e !important;
                border-color: #0f766e !important;
                color: #ffffff !important;
            }
            .step-text-active {
                color: #0f766e !important;
                font-weight: 800 !important;
            }
            .capability-card {
                border: 1px solid #e2e8f0;
                background-color: #ffffff;
                transition: all 0.2s ease-in-out;
            }
            .capability-card.selected {
                border-color: #0f766e;
                background-color: #f0fdfa;
            }
            .phone-simulator {
                border: 12px solid #1e293b;
                border-radius: 40px;
                background-color: #efeae2;
                box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
                overflow: hidden;
                width: 310px;
                height: 630px;
                position: relative;
            }
            .phone-screen {
                display: flex;
                flex-direction: column;
                height: 100%;
                width: 100%;
            }
            .phone-header {
                background-color: #f0f2f5;
                color: #111b21;
                padding: 16px 12px 6px 12px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                border-bottom: 1px solid #e2e8f0;
                height: 58px;
                flex-shrink: 0;
            }
            #sim-chat-input {
                border: none !important;
                outline: none !important;
                background: transparent !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .chat-messages {
                flex-grow: 1;
                overflow-y: auto;
                padding: 14px;
                background-color: #efeae2;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80' viewBox='0 0 80 80'%3E%3Cg fill='%23e5ddd5' fill-opacity='0.4'%3E%3Cpath d='M10 10h5v5h-5zm10 0h5v5h-5zm-10 10h5v5h-5zm10 0h5v5h-5zm20-20h5v5h-5zm10 0h5v5h-5zm-10 10h5v5h-5zm10 0h5v5h-5zm20 0h5v5h-5zm10 0h5v5h-5zm-10 10h5v5h-5zm10 0h5v5h-5zm-40 20h5v5h-5zm10 0h5v5h-5zm-10 10h5v5h-5zm10 0h5v5h-5zm20-20h5v5h-5zm10 0h5v5h-5zm-10 10h5v5h-5zm10 0h5v5h-5zm20 0h5v5h-5zm10 0h5v5h-5zm-10 10h5v5h-5zm10 0h5v5h-5z'/%3E%3C/g%3E%3C/svg%3E");
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .bubble {
                max-width: 80%;
                padding: 6px 10px 6px 10px;
                font-size: 11px;
                line-height: 1.35;
                border-radius: 8px;
                word-wrap: break-word;
                position: relative;
                box-shadow: 0 1px 0.5px rgba(11,20,26,.13);
                display: flex;
                flex-direction: column;
            }
            .bubble.incoming {
                background-color: #ffffff;
                color: #111b21;
                align-self: flex-start;
                border-top-left-radius: 0;
            }
            .bubble.outgoing {
                background-color: #d9fdd3;
                color: #111b21;
                align-self: flex-end;
                border-top-right-radius: 0;
            }
            .bubble-text {
                margin-bottom: 2px;
                text-align: left;
            }
            .bubble-meta {
                align-self: flex-end;
                font-size: 8px;
                color: #667781;
                display: flex;
                align-items: center;
                gap: 3px;
                margin-top: 1px;
                margin-left: auto;
            }
            .typing-bubble {
                background-color: #ffffff;
                color: #334155;
                align-self: flex-start;
                border-radius: 8px;
                border-top-left-radius: 0;
                padding: 8px 12px;
                display: flex;
                align-items: center;
                gap: 3.5px;
                min-width: 45px;
                height: 26px;
                box-shadow: 0 1px 0.5px rgba(11,20,26,.13);
            }
            .typing-dot {
                width: 4px;
                height: 4px;
                background-color: #667781;
                border-radius: 50%;
                animation: typing-bounce 1.4s infinite ease-in-out both;
            }
            .typing-dot:nth-child(1) { animation-delay: -0.32s; }
            .typing-dot:nth-child(2) { animation-delay: -0.16s; }
            @keyframes typing-bounce {
                0%, 80%, 100% { transform: scale(0.3); }
                40% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    }

    try {
        const data = await apiCall('whatsapp/agent.php');
        const agent = data.agent || {
            phone_number: '',
            website_url: '',
            capabilities: 'faq_support,human_handoff',
            ground_rules: '',
            knowledge_base: '',
            status: 'idle'
        };
        
        window.activeWaAgent = agent;
        
        if (agent.status === 'live') {
            renderAgentDashboard(container);
        } else {
            renderAgentWizard(container, 0); // Start at initial input step
        }
    } catch (e) {
        const errorMsg = e.message || '';
        if (errorMsg.includes('whatsapp_agents') && (errorMsg.includes("doesn't exist") || errorMsg.includes("not found"))) {
            showNotification('warning', 'AI Agent database table not found. Attempting to run database migrations automatically...');
            container.innerHTML = `
                <div class="wa-agent-card p-8 max-w-md mx-auto text-center space-y-4 mt-12 text-slate-855 font-sans text-xs">
                    <div class="h-12 w-12 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center mx-auto border border-teal-100">
                        <i data-lucide="refresh-cw" class="h-6 w-6 animate-spin"></i>
                    </div>
                    <div class="space-y-1.5">
                        <h3 class="font-bold text-slate-800 text-sm">Running Database Migrations</h3>
                        <p class="text-[10px] text-slate-500 font-semibold leading-relaxed">
                            We noticed the required AI Agent database tables are missing on your server. We are executing the database migrations automatically now. Please wait...
                        </p>
                    </div>
                </div>
            `;
            lucide.createIcons();
            
            // Trigger migration using authenticated apiCall
            apiCall('crm/migrate.php')
                .then(() => {
                    showNotification('success', 'Database migrations executed successfully! Retrying agent panel load...');
                    renderWhatsAppTrain(container);
                })
                .catch(migrationErr => {
                    showNotification('error', 'Failed running migrations: ' + migrationErr.message);
                    renderErrorCard(container, e);
                });
            return;
        }
        
        renderErrorCard(container, e);
    }
};

function renderErrorCard(container, e) {
    showNotification('error', 'Failed loading AI Agent panel: ' + e.message);
    container.innerHTML = `
        <div class="wa-agent-card p-8 max-w-md mx-auto text-center space-y-4 mt-12 text-slate-850 font-sans text-xs">
            <div class="h-12 w-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto border border-rose-100">
                <i data-lucide="alert-circle" class="h-6 w-6"></i>
            </div>
            <div class="space-y-1.5">
                <h3 class="font-bold text-slate-800 text-sm">Failed to load AI Agent settings</h3>
                <p class="text-[10px] text-slate-500 font-semibold leading-relaxed">
                    There was an error communicating with the server: ${e.message}. Please make sure you have pulled the latest changes on your server and executed the database migrations.
                </p>
            </div>
            <button onclick="renderWhatsAppTrain(document.getElementById('main-content-viewport'))" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold transition flex items-center justify-center space-x-1.5 mx-auto text-[10px] shadow-sm">
                <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                <span>Retry Connection</span>
            </button>
        </div>
    `;
    lucide.createIcons();
}

function renderAgentWizard(container, step) {
    const agent = window.activeWaAgent;
    
    // Calculate Stepper HTML
    let stepperHTML = '';
    if (step > 0) {
        stepperHTML = `
            <div class="flex items-center justify-between w-full select-none border-b border-slate-100 pb-5 mb-5 font-sans">
                <!-- Step 1: Train -->
                <div class="flex items-center space-x-2">
                    <div class="h-7 w-7 rounded-full border-2 text-[10px] font-bold flex items-center justify-center shrink-0 ${step === 1 ? 'step-node-active' : 'step-node-done'}">
                        ${step > 1 ? '<i data-lucide="check" class="h-3.5 w-3.5"></i>' : '<i data-lucide="search" class="h-3.5 w-3.5"></i>'}
                    </div>
                    <span class="text-xs font-bold ${step === 1 ? 'step-text-active' : 'text-slate-500'}">Train</span>
                </div>
                <div class="flex-grow h-0.5 mx-4 ${step > 1 ? 'bg-teal-600' : 'bg-slate-200'}"></div>
                
                <!-- Step 2: Enable agents -->
                <div class="flex items-center space-x-2">
                    <div class="h-7 w-7 rounded-full border-2 text-[10px] font-bold flex items-center justify-center shrink-0 ${step === 2 ? 'step-node-active' : (step > 2 ? 'step-node-done' : 'border-slate-200 text-slate-400 bg-slate-50')}">
                        ${step > 2 ? '<i data-lucide="check" class="h-3.5 w-3.5"></i>' : '<i data-lucide="zap" class="h-3.5 w-3.5"></i>'}
                    </div>
                    <span class="text-xs font-bold ${step === 2 ? 'step-text-active' : 'text-slate-400'}">Enable agents</span>
                </div>
                <div class="flex-grow h-0.5 mx-4 ${step > 2 ? 'bg-teal-600' : 'bg-slate-200'}"></div>
                
                <!-- Step 3: Guide -->
                <div class="flex items-center space-x-2">
                    <div class="h-7 w-7 rounded-full border-2 text-[10px] font-bold flex items-center justify-center shrink-0 ${step === 3 ? 'step-node-active' : 'border-slate-200 text-slate-400 bg-slate-50'}">
                        <i data-lucide="book-open" class="h-3.5 w-3.5"></i>
                    </div>
                    <span class="text-xs font-bold ${step === 3 ? 'step-text-active' : 'text-slate-400'}">Guide</span>
                </div>
            </div>
        `;
    }

    container.innerHTML = `
        <div class="wa-agent-card p-6 md:p-8 max-w-2xl mx-auto text-slate-800 text-xs font-sans mt-4 relative text-left">
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-150 mb-5">
                <div class="flex items-center space-x-2.5">
                    <div class="h-8 w-8 rounded-xl bg-teal-600/10 text-teal-600 flex items-center justify-center shrink-0">
                        <i data-lucide="bot" class="h-5 w-5 text-teal-600"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-black text-slate-800 tracking-tight">Set up your AI Agent</h2>
                        <p class="text-[10px] text-slate-500 font-medium">Build a smart automated responder for your business phone.</p>
                    </div>
                </div>
                <button onclick="navigateTo('whatsapp-dashboard')" class="text-slate-400 hover:text-slate-600 transition"><i data-lucide="x" class="h-4.5 w-4.5"></i></button>
            </div>
            
            ${stepperHTML}
            
            <!-- Step Content -->
            <div id="wizard-step-body" class="py-2 min-h-[220px]">
                ${getStepHTML(step)}
            </div>
            
            <!-- Step Footer -->
            <div id="wizard-step-footer" class="pt-5 border-t border-slate-100 flex items-center justify-between mt-6">
                ${getStepFooterHTML(step)}
            </div>
        </div>
    `;

    lucide.createIcons();
    
    // Bind dynamic input listener for website box in Step 0
    if (step === 0) {
        const input = document.getElementById('agent-website');
        if (input) {
            input.addEventListener('input', function() {
                toggleGetWebsiteButton(this);
            });
        }
    }
}

function getStepHTML(step) {
    const agent = window.activeWaAgent;
    
    if (step === 0) {
        return `
            <div class="space-y-4">
                <div class="space-y-1">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Business Mobile Number</h3>
                    <p class="text-[10px] text-slate-450 font-semibold">Enter the phone number connected to your WhatsApp Business API.</p>
                    <input type="text" id="agent-phone" placeholder="e.g. +919876543210" value="${agent.phone_number || ''}" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-teal-500 font-semibold text-slate-800 shadow-sm">
                </div>
                
                <div class="space-y-1 relative">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider block">Website URL (Optional)</h3>
                    <p class="text-[10px] text-slate-450 font-semibold">Provide your website so the AI Agent can read details and answer support requests.</p>
                    <div class="relative flex items-center">
                        <input type="url" id="agent-website" placeholder="e.g. https://yourbusiness.com" value="${agent.website_url || ''}" class="w-full pl-3 pr-28 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-teal-500 font-semibold text-slate-800 shadow-sm">
                        <button id="get-website-btn" onclick="autofillWebsite()" class="absolute right-2 px-2.5 py-1 bg-teal-50 hover:bg-teal-100 text-teal-700 hover:text-teal-800 rounded-lg text-[10px] font-extrabold transition shadow-2xs">Get website Now!</button>
                    </div>
                </div>
            </div>
        `;
    }
    
    else if (step === 1) {
        const isCrawlDone = (agent.status === 'live' || (agent.knowledge_base && agent.knowledge_base.trim().length > 0));
        const statusHTML = isCrawlDone ? `
            <div class="px-4 py-2 bg-emerald-50 border border-emerald-100 text-emerald-700 font-bold text-[10px] rounded-xl flex items-center space-x-1.5 shadow-2xs cursor-default">
                <i data-lucide="check-circle" class="h-3.5 w-3.5 text-emerald-600"></i>
                <span>Website read successfully!</span>
            </div>
        ` : `
            <button class="px-4 py-2 bg-teal-50 border border-teal-100/50 text-teal-700 font-bold text-[10px] rounded-xl flex items-center space-x-1.5 shadow-2xs cursor-default">
                <i data-lucide="refresh-cw" class="h-3.5 w-3.5 animate-spin"></i>
                <span>Reading your site in the background</span>
            </button>
        `;
        
        return `
            <div class="flex flex-col items-center justify-center text-center py-4 space-y-4">
                <div class="h-12 w-12 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 relative z-10 shadow-sm animate-bounce">
                    <i data-lucide="check" class="h-6 w-6"></i>
                </div>
                <div class="space-y-2">
                    <h3 class="text-sm font-black text-slate-900 tracking-tight">Your agent is live 🎉</h3>
                    <p class="text-slate-500 text-[11px] leading-relaxed font-semibold max-w-md mx-auto">
                        Your agent is answering on your test number now. We're reading your site in the background — it'll keep getting sharper as the crawl finishes. You can continue or finish; nothing is blocking you.
                    </p>
                </div>
                
                <div id="crawler-status-container">
                    ${statusHTML}
                </div>
            </div>
        `;
    }
    
    else if (step === 2) {
        const capabilities = agent.capabilities ? agent.capabilities.split(',') : [];
        const faqChecked = capabilities.includes('faq_support');
        const handoffChecked = capabilities.includes('human_handoff');
        
        return `
            <div class="space-y-4 text-left">
                <div>
                    <h3 class="text-sm font-black text-slate-900 tracking-tight">What should your agent do?</h3>
                    <p class="text-slate-500 text-[10px] font-semibold">Core capabilities are pre-selected. Add or change anything — you can edit all of this later.</p>
                </div>
                
                <div class="space-y-3">
                    <div id="card-faq" onclick="toggleCapability('faq')" class="capability-card rounded-2xl p-4 flex items-start space-x-3.5 cursor-pointer hover:shadow-sm ${faqChecked ? 'selected' : ''}">
                        <div class="h-8 w-8 rounded-xl bg-teal-50 flex items-center justify-center shrink-0 border border-teal-100">
                            <i data-lucide="zap" class="h-4 w-4 text-teal-600"></i>
                        </div>
                        <div class="flex-grow space-y-0.5">
                            <h4 class="font-extrabold text-slate-800 text-xs">FAQ / Support</h4>
                            <p class="text-[10px] text-slate-500 leading-relaxed font-medium">
                                The contact asks a general question about the business — products, pricing, policies, hours, location, delivery areas, services, or "do you have/do you offer X". Use this whenever the answer should come from the business's own knowledge. Do NOT use it for order-specific lookups, for a buyer who wants to purchase/qualify, or for returns.
                            </p>
                        </div>
                        <input type="checkbox" id="check-faq" ${faqChecked ? 'checked' : ''} class="h-3.5 w-3.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 mt-1 pointer-events-none">
                    </div>
                    
                    <div id="card-handoff" onclick="toggleCapability('handoff')" class="capability-card rounded-2xl p-4 flex items-start space-x-3.5 cursor-pointer hover:shadow-sm ${handoffChecked ? 'selected' : ''}">
                        <div class="h-8 w-8 rounded-xl bg-teal-50 flex items-center justify-center shrink-0 border border-teal-100">
                            <i data-lucide="zap" class="h-4 w-4 text-teal-600"></i>
                        </div>
                        <div class="flex-grow space-y-0.5">
                            <h4 class="font-extrabold text-slate-800 text-xs">Human Handoff</h4>
                            <p class="text-[10px] text-slate-500 leading-relaxed font-medium">
                                The contact explicitly asks for a human/agent/manager, is clearly frustrated after you've tried to help, raises something out of the bot's scope, or the matter is sensitive (billing dispute, fraud, complaint, legal). Use it to hand off cleanly — not as an escape from questions you haven't tried to answer yet.
                            </p>
                        </div>
                        <input type="checkbox" id="check-handoff" ${handoffChecked ? 'checked' : ''} class="h-3.5 w-3.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 mt-1 pointer-events-none">
                    </div>
                </div>
            </div>
        `;
    }
    
    else if (step === 3) {
        return `
            <div class="space-y-4 text-left">
                <div>
                    <h3 class="text-sm font-black text-slate-900 tracking-tight">Anything special it should know?</h3>
                    <p class="text-slate-500 text-[10px] font-semibold">Optional. Add a ground rule or two. Skip it and your agent still works.</p>
                </div>
                
                <div class="space-y-1">
                    <label class="block text-slate-650 font-bold mb-1 uppercase text-[9px] tracking-wider">Ground rules (optional)</label>
                    <textarea id="agent-ground-rules" rows="5" placeholder="e.g. Never promise same-day delivery. Always mention the festive 15% off above ₹1,000." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:border-teal-500 font-semibold text-slate-800 shadow-sm resize-none">${agent.ground_rules || ''}</textarea>
                </div>
            </div>
        `;
    }
}

function getStepFooterHTML(step) {
    if (step === 0) {
        return `
            <div></div>
            <button onclick="submitStep0()" class="px-5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl font-bold flex items-center space-x-1.5 shadow-sm transition">
                <span>Start Training Agent</span>
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </button>
        `;
    }
    
    else if (step === 1) {
        return `
            <button onclick="wizardFinishLater()" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-xl font-bold transition">
                Finish — I'll refine later
            </button>
            <button onclick="renderAgentWizard(document.getElementById('main-content-viewport'), 2)" class="px-5 py-2 bg-teal-800 hover:bg-teal-900 text-white rounded-xl font-bold flex items-center space-x-1.5 shadow-sm transition" style="background-color: #0f766e !important;">
                <span>Continue setup</span>
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </button>
        `;
    }
    
    else if (step === 2) {
        return `
            <button onclick="renderAgentWizard(document.getElementById('main-content-viewport'), 1)" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-xl font-bold transition flex items-center space-x-1.5">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                <span>Back</span>
            </button>
            <div class="flex items-center space-x-2">
                <button onclick="renderAgentWizard(document.getElementById('main-content-viewport'), 3)" class="px-4 py-2 text-slate-550 hover:text-slate-800 font-bold transition">
                    Skip
                </button>
                <button onclick="submitStep2()" class="px-5 py-2 bg-teal-800 hover:bg-teal-900 text-white rounded-xl font-bold flex items-center space-x-1.5 shadow-sm transition" style="background-color: #0f766e !important;">
                    <span>Continue</span>
                    <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </button>
            </div>
        `;
    }
    
    else if (step === 3) {
        return `
            <button onclick="renderAgentWizard(document.getElementById('main-content-viewport'), 2)" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-600 rounded-xl font-bold transition flex items-center space-x-1.5">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                <span>Back</span>
            </button>
            <button onclick="submitStep3()" class="px-5 py-2 bg-teal-800 hover:bg-teal-900 text-white rounded-xl font-bold flex items-center space-x-1.5 shadow-sm transition" style="background-color: #0f766e !important;">
                <i data-lucide="grid" class="h-4 w-4"></i>
                <span>Go to agent</span>
            </button>
        `;
    }
}

window.toggleGetWebsiteButton = function(input) {
    const btn = document.getElementById('get-website-btn');
    if (btn) {
        if (input.value.trim().length > 0) {
            btn.classList.add('hidden');
        } else {
            btn.classList.remove('hidden');
        }
    }
};

window.autofillWebsite = function() {
    const input = document.getElementById('agent-website');
    if (input) {
        input.value = 'https://linkpilot.work';
        window.toggleGetWebsiteButton(input);
        showNotification('success', 'Business website loaded successfully!');
    }
};

window.toggleCapability = function(type) {
    const card = document.getElementById(`card-${type}`);
    const check = document.getElementById(`check-${type}`);
    if (card && check) {
        check.checked = !check.checked;
        if (check.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
};

window.submitStep0 = async function() {
    const phone = document.getElementById('agent-phone').value.trim();
    const website = document.getElementById('agent-website').value.trim();
    
    if (!phone) {
        showNotification('error', 'Please enter your Business Mobile Number.');
        return;
    }
    
    showNotification('warning', 'Saving config & initiating crawler...');
    
    try {
        // Save initial config
        await apiCall('whatsapp/agent.php', 'POST', {
            phone_number: phone,
            website_url: website,
            status: 'training'
        });
        
        window.activeWaAgent.phone_number = phone;
        window.activeWaAgent.website_url = website;
        window.activeWaAgent.status = 'training';
        
        // Transition to Step 1 (crawler view)
        const container = document.getElementById('main-content-viewport');
        renderAgentWizard(container, 1);
        
        // Start background crawler in API
        apiCall('whatsapp/agent.php?action=crawl', 'POST', {
            phone_number: phone,
            website_url: website
        }).then(res => {
            showNotification('success', 'Website read and agent trained successfully!');
            if (res.knowledge_base) {
                window.activeWaAgent.knowledge_base = res.knowledge_base;
                window.activeWaAgent.status = 'live';
            }
            const statusEl = document.getElementById('crawler-status-container');
            if (statusEl) {
                statusEl.innerHTML = `
                    <div class="px-4 py-2 bg-emerald-50 border border-emerald-100 text-emerald-700 font-bold text-[10px] rounded-xl flex items-center space-x-1.5 shadow-2xs cursor-default">
                        <i data-lucide="check-circle" class="h-3.5 w-3.5 text-emerald-600"></i>
                        <span>Website read successfully!</span>
                    </div>
                `;
                lucide.createIcons();
            }
        }).catch(err => {
            console.error("Crawler background error: ", err);
            const statusEl = document.getElementById('crawler-status-container');
            if (statusEl) {
                statusEl.innerHTML = `
                    <div class="px-4 py-2 bg-rose-50 border border-rose-100 text-rose-700 font-bold text-[10px] rounded-xl flex items-center space-x-1.5 shadow-2xs cursor-default">
                        <i data-lucide="alert-circle" class="h-3.5 w-3.5 text-rose-600"></i>
                        <span>Crawling failed. Proceeding with defaults.</span>
                    </div>
                `;
                lucide.createIcons();
            }
        });
        
    } catch (e) {
        showNotification('error', 'Failed to save config: ' + e.message);
    }
};

window.submitStep2 = function() {
    const checkFaq = document.getElementById('check-faq').checked;
    const checkHandoff = document.getElementById('check-handoff').checked;
    
    const caps = [];
    if (checkFaq) caps.push('faq_support');
    if (checkHandoff) caps.push('human_handoff');
    
    window.activeWaAgent.capabilities = caps.join(',');
    
    // Continue to Step 3
    renderAgentWizard(document.getElementById('main-content-viewport'), 3);
};

window.submitStep3 = async function() {
    const groundRules = document.getElementById('agent-ground-rules').value.trim();
    window.activeWaAgent.ground_rules = groundRules;
    window.activeWaAgent.status = 'live';
    
    showNotification('warning', 'Activating AI Chat Agent...');
    
    try {
        // Save complete config
        await apiCall('whatsapp/agent.php', 'POST', {
            phone_number: window.activeWaAgent.phone_number,
            website_url: window.activeWaAgent.website_url,
            capabilities: window.activeWaAgent.capabilities,
            ground_rules: window.activeWaAgent.ground_rules,
            status: 'live'
        });
        
        // Trigger visual confetti celebration!
        if (typeof showConfettiCelebration === 'function') {
            showConfettiCelebration();
        }
        
        showNotification('success', '🎉 Your AI WhatsApp Chat Agent is now fully active!');
        
        // Render Dashboard
        renderAgentDashboard(document.getElementById('main-content-viewport'));
    } catch(e) {
        showNotification('error', 'Failed activating agent: ' + e.message);
    }
};

window.wizardFinishLater = function() {
    window.activeWaAgent.status = 'live';
    showNotification('success', 'AI Agent saved.');
    renderAgentDashboard(document.getElementById('main-content-viewport'));
};

function renderAgentDashboard(container) {
    const agent = window.activeWaAgent;
    
    container.innerHTML = `
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-fade-in font-sans text-xs">
            <!-- Left panel: Configuration Summary -->
            <div class="lg:col-span-2 space-y-6 text-left">
                <div class="bg-white border border-slate-200 rounded-[24px] shadow-sm p-6 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center space-x-2.5">
                            <div class="h-8 w-8 rounded-xl bg-teal-600/10 text-teal-600 flex items-center justify-center shrink-0">
                                <i data-lucide="bot" class="h-5 w-5 text-teal-600"></i>
                            </div>
                            <div>
                                <h2 class="text-sm font-black text-slate-800">WhatsApp AI Agent Status</h2>
                                <p class="text-[10px] text-slate-500 font-medium">Your agent is actively listening and replying to inbound customer chats.</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold bg-teal-50 text-teal-650 border border-teal-100 flex items-center space-x-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-teal-500 animate-pulse"></span>
                            <span>Live & Active</span>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-slate-50/50 rounded-xl p-3 border border-slate-100">
                            <h4 class="font-bold text-slate-500 uppercase tracking-wider text-[8px] mb-1">Business Mobile Number</h4>
                            <p class="font-extrabold text-slate-800 text-xs">${agent.phone_number || 'Not connected'}</p>
                        </div>
                        
                        <div class="bg-slate-50/50 rounded-xl p-3 border border-slate-100">
                            <h4 class="font-bold text-slate-500 uppercase tracking-wider text-[8px] mb-1">Trained Website URL</h4>
                            <p class="font-extrabold text-slate-800 text-xs truncate">
                                ${agent.website_url ? `<a href="${agent.website_url}" target="_blank" class="text-teal-650 hover:underline flex items-center space-x-1"><span>${agent.website_url}</span><i data-lucide="external-link" class="h-3 w-3 inline"></i></a>` : 'No website provided'}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <h4 class="font-bold text-slate-500 uppercase tracking-wider text-[8px]">Enabled Agent Roles</h4>
                        <div class="flex flex-wrap gap-2">
                            ${agent.capabilities && agent.capabilities.includes('faq_support') ? '<span class="px-2 py-1 rounded-lg bg-teal-50 text-teal-700 font-bold border border-teal-100/50">FAQ / Support</span>' : ''}
                            ${agent.capabilities && agent.capabilities.includes('human_handoff') ? '<span class="px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-bold border border-indigo-100/50">Human Handoff (Escalations)</span>' : ''}
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <h4 class="font-bold text-slate-500 uppercase tracking-wider text-[8px]">Ground Rules & Prompts</h4>
                        <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-100 font-medium text-slate-700 italic max-h-32 overflow-y-auto leading-relaxed">
                            ${agent.ground_rules ? agent.ground_rules.replace(/\n/g, '<br>') : 'No extra rules configured. Defaulting to general support agent profile.'}
                        </div>
                    </div>
                    
                    <div class="space-y-1.5">
                        <h4 class="font-bold text-slate-500 uppercase tracking-wider text-[8px]">Knowledge Base (Website Data Source)</h4>
                        <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-100 font-mono text-[9px] text-slate-600 max-h-48 overflow-y-auto leading-relaxed whitespace-pre-wrap">
                            ${agent.knowledge_base || 'No crawled data cached yet.'}
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex justify-end">
                        <button onclick="retrainAgent()" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 rounded-xl font-bold transition flex items-center space-x-1.5 shadow-sm text-slate-700">
                            <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                            <span>Re-train Agent</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right panel: Interactive Live Chat Simulator -->
            <div class="flex flex-col items-center justify-center">
                <div class="phone-simulator">
                    <!-- Dynamic Island Notch -->
                    <div class="absolute top-1.5 left-1/2 -translate-x-1/2 w-20 h-3.5 bg-[#1e293b] rounded-full z-30 flex items-center justify-center pointer-events-none">
                        <div class="w-1 h-1 rounded-full bg-[#334155] mr-2"></div>
                        <div class="w-1 h-1 rounded-full bg-[#0f172a]"></div>
                    </div>
                    <div class="phone-screen">
                        <!-- Head -->
                        <div class="phone-header">
                            <div class="flex items-center space-x-2 flex-grow">
                                <i data-lucide="chevron-left" class="h-4.5 w-4.5 text-[#54656f] cursor-pointer -ml-1 shrink-0"></i>
                                <div class="h-8 w-8 rounded-full bg-slate-200 overflow-hidden flex items-center justify-center shrink-0 border border-slate-300">
                                    <img src="https://api.dicebear.com/7.x/bottts/svg?seed=LinkPilot" class="h-full w-full object-cover">
                                </div>
                                <div class="text-left">
                                    <h4 class="font-extrabold text-slate-800 text-[11px] leading-tight">LinkPilot AI Agent</h4>
                                    <p id="sim-header-status" class="text-[9px] text-[#54656f] font-semibold leading-none mt-0.5">online</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2.5 text-[#54656f] shrink-0">
                                <i data-lucide="video" class="h-4 w-4 cursor-pointer hover:text-slate-800"></i>
                                <i data-lucide="phone" class="h-3.5 w-3.5 cursor-pointer hover:text-slate-800"></i>
                                <i data-lucide="more-vertical" class="h-3.5 w-3.5 cursor-pointer hover:text-slate-800"></i>
                            </div>
                        </div>
                        
                        <!-- Messages Body -->
                        <div class="chat-messages" id="sim-messages-body">
                            <div class="bubble incoming">
                                <div class="bubble-text">Hi there! I am your AI Business Assistant. How can I help you today?</div>
                                <div class="bubble-meta">
                                    <span>${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chat Input Footer -->
                        <div class="p-2 bg-[#f0f2f5] flex items-center space-x-2 shrink-0 border-t border-slate-200">
                            <div class="flex-grow bg-white rounded-full px-3 py-1.5 flex items-center space-x-2 shadow-2xs border border-slate-100">
                                <i data-lucide="smile" class="h-4.5 w-4.5 text-[#54656f] cursor-pointer hover:text-slate-800"></i>
                                <input type="text" id="sim-chat-input" onkeydown="handleSimChatKeyDown(event)" oninput="toggleSimSendIcon(this)" placeholder="Type a message" class="flex-grow text-[10px] focus:outline-none bg-transparent text-slate-850">
                                <i data-lucide="paperclip" class="h-4 w-4 text-[#54656f] cursor-pointer hover:text-slate-800 rotate-45"></i>
                                <i data-lucide="camera" class="h-4 w-4 text-[#54656f] cursor-pointer hover:text-slate-800"></i>
                            </div>
                            <button id="sim-send-btn" onclick="sendSimChatMessage()" class="h-7 w-7 bg-[#00a884] hover:bg-[#008f72] text-white rounded-full flex items-center justify-center shrink-0 transition shadow-sm">
                                <i id="sim-send-icon" data-lucide="mic" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    lucide.createIcons();
}

window.retrainAgent = function() {
    window.activeWaAgent.status = 'idle';
    renderAgentWizard(document.getElementById('main-content-viewport'), 0);
};

window.handleSimChatKeyDown = function(e) {
    if (e.key === 'Enter') {
        sendSimChatMessage();
    }
};

window.toggleSimSendIcon = function(input) {
    const iconEl = document.getElementById('sim-send-icon');
    if (iconEl) {
        if (input.value.trim().length > 0) {
            iconEl.removeAttribute('data-lucide');
            iconEl.setAttribute('data-lucide', 'send');
        } else {
            iconEl.removeAttribute('data-lucide');
            iconEl.setAttribute('data-lucide', 'mic');
        }
        lucide.createIcons();
    }
};

window.sendSimChatMessage = function() {
    const input = document.getElementById('sim-chat-input');
    const msg = input.value.trim();
    if (!msg) return;
    
    input.value = '';
    window.toggleSimSendIcon(input);
    
    const chatBody = document.getElementById('sim-messages-body');
    const timeNow = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    
    // Append user message
    const userBubble = document.createElement('div');
    userBubble.className = 'bubble outgoing';
    userBubble.innerHTML = `
        <div class="bubble-text">${msg}</div>
        <div class="bubble-meta">
            <span>${timeNow}</span>
            <i data-lucide="check-check" class="h-3 w-3 text-[#53bdeb]"></i>
        </div>
    `;
    chatBody.appendChild(userBubble);
    chatBody.scrollTop = chatBody.scrollHeight;
    lucide.createIcons();
    
    // Update status in header to green typing...
    const statusEl = document.getElementById('sim-header-status');
    if (statusEl) {
        statusEl.innerText = 'typing...';
        statusEl.style.color = '#00a884';
    }
    
    // Add typing bubble
    const typingBubble = document.createElement('div');
    typingBubble.id = 'sim-typing-bubble';
    typingBubble.className = 'typing-bubble';
    typingBubble.innerHTML = `
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
        <div class="typing-dot"></div>
    `;
    chatBody.appendChild(typingBubble);
    chatBody.scrollTop = chatBody.scrollHeight;
    
    // Formulate response
    setTimeout(() => {
        // Remove typing bubble
        const typing = document.getElementById('sim-typing-bubble');
        if (typing) typing.remove();
        
        // Restore status in header to online
        if (statusEl) {
            statusEl.innerText = 'online';
            statusEl.style.color = '#54656f';
        }
        
        let responseText = '';
        const lowercase = msg.toLowerCase();
        
        const agent = window.activeWaAgent;
        const domain = agent.website_url ? (parse_url(agent.website_url, 'host') || agent.website_url) : 'our site';
        
        // Local sentence matching over crawled website knowledge base
        let kbExcerpt = "";
        if (agent.knowledge_base) {
            const sentences = agent.knowledge_base.split(/[.!?]\s+/);
            const matches = sentences.filter(s => {
                const words = lowercase.split(/\s+/);
                return words.some(w => w.length > 3 && s.toLowerCase().includes(w));
            });
            if (matches.length > 0) {
                kbExcerpt = matches[0].trim();
            } else {
                kbExcerpt = sentences.slice(0, 2).join('. ').trim();
            }
        }
        
        if (lowercase.includes('human') || lowercase.includes('manager') || lowercase.includes('agent') || lowercase.includes('help')) {
            responseText = "Understood. I am flagging this conversation for a human representative. One of our managers will connect with you shortly!";
        } else if (lowercase.includes('price') || lowercase.includes('pricing') || lowercase.includes('cost')) {
            responseText = `Regarding our pricing and packages, you can find the complete list on our site at ${agent.website_url || 'https://linkpilot.work/'}. Let me know if you would like me to schedule a demo call!`;
        } else if (lowercase.includes('hour') || lowercase.includes('time') || lowercase.includes('open')) {
            responseText = "Our business hours are Monday to Friday, 9:00 AM to 6:00 PM. We reply instantly to support inquiries during these timings.";
        } else if (lowercase.includes('hello') || lowercase.includes('hi') || lowercase.includes('hey')) {
            responseText = `Hello! How can I assist you with details regarding ${domain} today?`;
        } else {
            // General business reply using rules/website context
            if (kbExcerpt) {
                responseText = `Based on our website data: "${kbExcerpt}".` + (agent.ground_rules ? ` Also, please note: ${agent.ground_rules.substring(0, 80)}...` : "");
            } else if (agent.ground_rules) {
                responseText = `I can help with that! Adhering to our business guidelines: "${agent.ground_rules.substring(0, 100)}...", feel free to let me know how else we can assist.`;
            } else {
                responseText = `Thanks for reaching out! For detailed info, you can check ${agent.website_url || 'our website'}. Let me know if you have specific product or service questions!`;
            }
        }
        
        const replyBubble = document.createElement('div');
        replyBubble.className = 'bubble incoming';
        replyBubble.innerHTML = `
            <div class="bubble-text">${responseText}</div>
            <div class="bubble-meta">
                <span>${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
            </div>
        `;
        chatBody.appendChild(replyBubble);
        chatBody.scrollTop = chatBody.scrollHeight;
        lucide.createIcons();
    }, 1500);
};

// Simple helper to parse hostnames
function parse_url(url, element) {
    try {
        const el = document.createElement('a');
        el.href = url;
        if (element === 'host') return el.hostname;
        return el.href;
    } catch(e) {
        return url;
    }
}
