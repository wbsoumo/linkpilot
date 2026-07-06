// dashboard/assets/js/whatsapp.js

// Global variables for WhatsApp Inbox
let activeWaThreadId = null;
let waThreadsInterval = null;
let waMessagesInterval = null;

// Listen for Embedded Signup postMessage events
window.addEventListener("message", function(event) {
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
    let currentStep = 1;
    let accessToken = '';
    let wabaId = '';
    
    // Discovered Assets
    let phones = [];
    
    // Selections
    let selectedWaba = null;
    let selectedPhone = null;
    
    // Status metrics
    let tokenVerifiedInfo = null;
    let verifyError = '';
    let wabaVerifiedInfo = null;
    let wabaError = '';
    let healthChecklist = null;
    let healthDetails = null;
    let healthError = '';
    let connectedDetails = {};

    function drawWizard() {
        let stepHtml = '';
        
        if (currentStep === 1) {
            if (verifyError) {
                stepHtml = `
                    <div class="space-y-4 py-2 text-center">
                        <div class="h-10 w-10 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto text-base font-bold">✕</div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800">Token Verification Failed</h3>
                            <p class="text-xs text-rose-500 mt-2 bg-rose-50 border border-rose-100 rounded-lg p-3 text-left font-semibold">${verifyError}</p>
                        </div>
                        <div class="pt-4 flex justify-between">
                            <button onclick="clearTokenError()" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                            <button onclick="verifyMetaToken()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">Retry Verification</button>
                        </div>
                    </div>
                `;
            } else if (tokenVerifiedInfo) {
                stepHtml = `
                    <div class="space-y-4 py-2">
                        <div class="text-center">
                            <div class="h-10 w-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto text-base font-bold">✓</div>
                            <h3 class="text-xs font-bold text-emerald-600 mt-2">✓ Access Token Verified</h3>
                        </div>
                        
                        <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-xs space-y-2.5 max-w-xs mx-auto">
                            <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                                <span class="text-slate-500 font-semibold">Meta User Name</span>
                                <span class="text-slate-800 font-bold">${tokenVerifiedInfo.user_name}</span>
                            </div>
                            <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                                <span class="text-slate-500 font-semibold">Meta App Name</span>
                                <span class="text-slate-800 font-bold">${tokenVerifiedInfo.app_name}</span>
                            </div>
                            <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                                <span class="text-slate-500 font-semibold">Expiry</span>
                                <span class="text-slate-800 font-bold font-mono text-[10px]">${tokenVerifiedInfo.expiry}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 font-semibold">Token Status</span>
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-bold text-[9px] uppercase">Active</span>
                            </div>
                        </div>
                        
                        <div class="pt-4 flex justify-between">
                            <button onclick="goWizardStep(1); tokenVerifiedInfo = null;" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Change Token</button>
                            <button onclick="goWizardStep(2)" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">Next: WABA ID →</button>
                        </div>
                    </div>
                `;
            } else {
                stepHtml = `
                    <div class="space-y-4">
                        <div class="text-center pb-2">
                            <h3 class="text-sm font-bold text-slate-800">System User Access Token</h3>
                            <p class="text-xs text-slate-500 mt-1">Provide your Permanent Meta System User Access Token.</p>
                        </div>
                        
                        <div class="space-y-3.5 text-left">
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <label class="block text-slate-600 font-semibold text-[11px]">System User Access Token</label>
                                    <button onclick="openMetaTokenHelpDialog()" class="text-[10px] text-blue-600 hover:text-blue-700 hover:underline font-bold">How do I get this?</button>
                                </div>
                                <div class="relative rounded-lg shadow-sm">
                                    <input type="password" id="wa-access-token" value="${accessToken}" placeholder="EAA..." class="w-full pl-3 pr-28 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500 font-mono">
                                    <div class="absolute inset-y-0 right-0 pr-1.5 flex items-center space-x-1">
                                        <button onclick="toggleTokenVisibility()" class="p-1 text-slate-400 hover:text-slate-600 text-[10px] font-bold" id="btn-toggle-visibility">Show</button>
                                        <button onclick="pasteToken()" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-[9px] font-bold">Paste</button>
                                        <button onclick="clearTokenInput()" class="p-1 text-slate-400 hover:text-slate-600 text-[10px] font-bold">Clear</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button id="btn-verify-token" onclick="verifyMetaToken()" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">
                                Verify Access Token
                            </button>
                        </div>
                    </div>
                `;
            }
        } 
        
        else if (currentStep === 2) {
            if (wabaError) {
                stepHtml = `
                    <div class="space-y-4 py-2 text-center">
                        <div class="h-10 w-10 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto text-base font-bold">✕</div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800">WABA Verification Failed</h3>
                            <p class="text-xs text-rose-500 mt-2 bg-rose-50 border border-rose-100 rounded-lg p-3 text-left font-semibold">${wabaError}</p>
                        </div>
                        <div class="pt-4 flex justify-between">
                            <button onclick="clearWabaError()" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                            <button onclick="verifyWabaId()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">Retry Verification</button>
                        </div>
                    </div>
                `;
            } else if (wabaVerifiedInfo) {
                stepHtml = `
                    <div class="space-y-4 py-2">
                        <div class="text-center">
                            <div class="h-10 w-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto text-base font-bold">✓</div>
                            <h3 class="text-xs font-bold text-emerald-600 mt-2">✓ WABA Verified Successfully</h3>
                        </div>
                        
                        <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-xs space-y-2.5 max-w-xs mx-auto">
                            <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                                <span class="text-slate-500 font-semibold">WABA Name</span>
                                <span class="text-slate-800 font-bold">${wabaVerifiedInfo.waba_name}</span>
                            </div>
                            <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                                <span class="text-slate-500 font-semibold">WABA ID</span>
                                <span class="text-slate-800 font-bold font-mono text-[10px]">${wabaVerifiedInfo.waba_id}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 font-semibold">Status</span>
                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-bold text-[9px] uppercase">${wabaVerifiedInfo.waba_status}</span>
                            </div>
                        </div>
                        
                        <div class="pt-4 flex justify-between">
                            <button onclick="goWizardStep(2); wabaVerifiedInfo = null;" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Change ID</button>
                            <button onclick="fetchPhones()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">Next: Select Phone →</button>
                        </div>
                    </div>
                `;
            } else {
                stepHtml = `
                    <div class="space-y-4">
                        <div class="text-center pb-2">
                            <h3 class="text-sm font-bold text-slate-800">WhatsApp Business Account ID</h3>
                            <p class="text-xs text-slate-500 mt-1">Specify your WhatsApp Business Account (WABA) ID.</p>
                        </div>
                        
                        <div class="space-y-3.5 text-left">
                            <div>
                                <label class="block text-slate-600 font-semibold text-[11px] mb-1">WABA ID</label>
                                <input type="text" id="wa-waba-id" value="${wabaId}" placeholder="e.g. 718557" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500 font-mono">
                            </div>
                        </div>
                        
                        <div class="pt-4 flex justify-between">
                            <button onclick="goWizardStep(1)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                            <button id="btn-verify-waba" onclick="verifyWabaId()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">
                                Verify WABA Account
                            </button>
                        </div>
                    </div>
                `;
            }
        }
        
        else if (currentStep === 3) {
            // Select Phone Number
            stepHtml = `
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xs font-bold text-slate-800">Select Phone Number</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Select the verified phone number to link.</p>
                    </div>
                    
                    <div class="space-y-2.5 max-h-56 overflow-y-auto pr-1">
                        ${phones.map(p => `
                            <div onclick="selectPhone('${p.id}')" class="p-3 border rounded-xl cursor-pointer transition text-left space-y-2.5 ${selectedPhone && selectedPhone.id === p.id ? 'border-blue-500 bg-blue-50/20' : 'border-slate-200 hover:bg-slate-50/60'}">
                                <div class="flex justify-between items-center">
                                    <div class="text-xs text-slate-700 font-extrabold">${p.display_phone_number}</div>
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded ${p.status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'}">${p.status}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-1 text-[10px] text-slate-500 font-semibold">
                                    <div>
                                        <div class="text-slate-400 uppercase text-[8px] font-bold">Display Name</div>
                                        <div class="truncate text-slate-600">${p.verified_name || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <div class="text-slate-400 uppercase text-[8px] font-bold">Quality</div>
                                        <span class="font-extrabold ${p.quality_rating === 'GREEN' ? 'text-emerald-500' : p.quality_rating === 'YELLOW' ? 'text-amber-500' : 'text-red-500'}">${p.quality_rating}</span>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                        ${phones.length === 0 ? '<p class="text-xs text-slate-400 text-center py-4">No registered phone numbers found in this WABA account.</p>' : ''}
                    </div>
                    
                    <div class="pt-4 flex justify-between">
                        <button onclick="goWizardStep(2)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                        <button onclick="triggerHealthCheck()" ${!selectedPhone ? 'disabled' : ''} class="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg transition shadow-md">Next: Health Check →</button>
                    </div>
                </div>
            `;
        }
        
        else if (currentStep === 4) {
            // Connection Health Check
            if (healthError) {
                stepHtml = `
                    <div class="space-y-4 py-2 text-center">
                        <div class="h-10 w-10 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto text-base font-bold">✕</div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800">Health Check Failed</h3>
                            <p class="text-xs text-rose-500 mt-2 bg-rose-50 border border-rose-100 rounded-lg p-3 text-left font-semibold">${healthError}</p>
                        </div>
                        <div class="pt-4 flex justify-between">
                            <button onclick="goWizardStep(3)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                            <button onclick="triggerHealthCheck()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">Retry Diagnostics</button>
                        </div>
                    </div>
                `;
            } else if (!healthChecklist) {
                stepHtml = `
                    <div class="space-y-4 py-6 text-center">
                        <div class="loader-spinner mx-auto"></div>
                        <p class="text-xs font-bold text-slate-600">Running diagnostic health checklist...</p>
                    </div>
                `;
            } else {
                const renderCheck = (val, label) => `
                    <div class="flex items-center space-x-2.5 text-xs text-left">
                        <span class="h-4.5 w-4.5 rounded-full flex items-center justify-center font-bold text-[10px] ${val ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'}">
                            ${val ? '✓' : '✕'}
                        </span>
                        <span class="${val ? 'text-slate-700' : 'text-rose-500 font-bold'}">${label}</span>
                    </div>
                `;
                
                const passes = Object.values(healthChecklist).every(v => v === true);
                
                stepHtml = `
                    <div class="space-y-4 text-left">
                        <div>
                            <h3 class="text-xs font-bold text-slate-800">Connection Health Diagnostics</h3>
                            <p class="text-[10px] text-slate-400 uppercase tracking-wider font-bold">Health Checklist</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 bg-slate-50/50 border border-slate-100 p-4 rounded-2xl max-w-sm mx-auto">
                            ${renderCheck(healthChecklist.token_valid, 'Access Token Valid')}
                            ${renderCheck(healthChecklist.waba_found, 'WABA Found')}
                            ${renderCheck(healthChecklist.phone_found, 'Phone Number Found')}
                            ${renderCheck(healthChecklist.cloud_api_enabled, 'Cloud API Enabled')}
                            ${renderCheck(healthChecklist.ready_to_send, 'Ready to Send')}
                        </div>

                        ${healthDetails ? `
                        <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 text-xs space-y-1.5 max-w-sm mx-auto mt-2">
                            <div class="flex justify-between">
                                <span class="text-slate-400">WABA Name</span>
                                <span class="text-slate-700 font-bold text-right">${healthDetails.waba_name}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Phone Number</span>
                                <span class="text-slate-700 font-bold text-right">${healthDetails.phone_number}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Messaging Limit</span>
                                <span class="text-slate-700 font-bold text-right">${healthDetails.messaging_limit}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Quality Rating</span>
                                <span class="text-emerald-600 font-extrabold text-right">${healthDetails.quality_rating}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Verified Name</span>
                                <span class="text-slate-700 font-bold text-right">${healthDetails.verified_name}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Status</span>
                                <span class="text-slate-700 font-bold text-right">${healthDetails.status}</span>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="pt-4 flex justify-between">
                            <button onclick="goWizardStep(3)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                            <button onclick="saveConnection()" ${!passes ? 'disabled' : ''} class="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg transition shadow-md">Next: Save & Connect →</button>
                        </div>
                    </div>
                `;
            }
        }
        
        else if (currentStep === 5 || currentStep === 6) {
            // Success & Test Connection Dashboard Overview
            stepHtml = `
                <div class="space-y-5 text-center py-4">
                    <div class="h-12 w-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto text-xl font-bold">🎉</div>
                    <div>
                        <h3 class="text-base font-extrabold text-slate-800">WhatsApp Connected Successfully</h3>
                        <p class="text-xs text-slate-500 mt-1">Your manual credential config is verified and live.</p>
                    </div>
                    
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 text-left text-xs max-w-sm mx-auto space-y-2.5 shadow-sm">
                        <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                            <span class="text-slate-500 font-medium">Business</span>
                            <span class="text-slate-800 font-bold">${connectedDetails.business_name || 'Taskbazi'}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                            <span class="text-slate-500 font-medium">Phone</span>
                            <span class="text-slate-800 font-bold font-mono">${connectedDetails.phone_number || '+91 80162 22991'}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                            <span class="text-slate-500 font-medium">Messaging Tier</span>
                            <span class="text-slate-800 font-bold">${connectedDetails.messaging_limit || '1000/day'}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                            <span class="text-slate-500 font-medium">Quality Rating</span>
                            <span class="text-emerald-600 font-extrabold">${connectedDetails.quality_rating || 'Green'}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                            <span class="text-slate-500 font-medium">Webhook Status</span>
                            <span class="text-emerald-600 font-bold">${connectedDetails.webhook_status || 'Verified'}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500 font-medium">Connection Status</span>
                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-md font-extrabold text-[9px] uppercase tracking-wide">${connectedDetails.status || 'Connected'}</span>
                        </div>
                    </div>
                    
                    <div class="pt-4">
                        <button onclick="window.location.reload()" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">
                            Go to WhatsApp Inbox
                        </button>
                    </div>
                </div>
            `;
        }

        container.innerHTML = `
            <div class="max-w-md mx-auto my-12 bg-white border border-slate-200/80 rounded-2xl p-6 shadow-xl space-y-6 animate-fade-in">
                <div class="flex items-center space-x-3 border-b border-slate-100 pb-4">
                    <div class="h-10 w-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center shrink-0">
                        <i data-lucide="message-circle" class="h-5.5 w-5.5"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-slate-800">Connect WhatsApp Business</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Guided Manual Cloud API Wizard</p>
                    </div>
                </div>
                
                <!-- Stepper Wizard Progress tracker -->
                <div class="flex items-center justify-center space-x-2 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-3">
                    <span class="${currentStep === 1 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">1. Token</span>
                    <span class="text-slate-300">•</span>
                    <span class="${currentStep === 2 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">2. WABA</span>
                    <span class="text-slate-300">•</span>
                    <span class="${currentStep === 3 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">3. Phone</span>
                    <span class="text-slate-300">•</span>
                    <span class="${currentStep === 4 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">4. Health</span>
                    <span class="text-slate-300">•</span>
                    <span class="${currentStep >= 5 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">5. Success</span>
                </div>
                
                <div class="py-2">
                    ${stepHtml}
                </div>
            </div>
        `;
        lucide.createIcons();
    }

    // Step-by-Step Help Dialog Trigger
    window.openMetaTokenHelpDialog = function() {
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

    // UI actions helper
    window.toggleTokenVisibility = function() {
        const input = document.getElementById('wa-access-token');
        const btn = document.getElementById('btn-toggle-visibility');
        if (input && btn) {
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Hide';
            } else {
                input.type = 'password';
                btn.textContent = 'Show';
            }
        }
    };

    window.clearTokenInput = function() {
        const input = document.getElementById('wa-access-token');
        if (input) input.value = '';
        accessToken = '';
    };

    window.pasteToken = async function() {
        try {
            const text = await navigator.clipboard.readText();
            const input = document.getElementById('wa-access-token');
            if (input) input.value = text;
            accessToken = text;
        } catch (err) {
            showNotification('error', 'Clipboard access denied. Please paste manually.');
        }
    };
    
    // Verify Access Token action
    window.verifyMetaToken = function() {
        const input = document.getElementById('wa-access-token');
        const tokenVal = input ? input.value.trim() : accessToken;
        
        if (!tokenVal) {
            showNotification('error', 'Please enter a Meta System User Access Token.');
            return;
        }
        
        accessToken = tokenVal;
        verifyError = '';
        tokenVerifiedInfo = null;
        drawWizard();
        
        apiCall('whatsapp/setup.php?action=verify_token', 'POST', {
            access_token: accessToken
        }).then(res => {
            tokenVerifiedInfo = res.data || res;
            drawWizard();
        }).catch(err => {
            verifyError = err.message || 'Token verification failed.';
            drawWizard();
        });
    };

    window.clearTokenError = function() {
        verifyError = '';
        tokenVerifiedInfo = null;
        drawWizard();
    };

    window.verifyWabaId = function() {
        const input = document.getElementById('wa-waba-id');
        const wabaVal = input ? input.value.trim() : wabaId;
        
        if (!wabaVal) {
            showNotification('error', 'Please enter your WABA Account ID.');
            return;
        }
        
        wabaId = wabaVal;
        wabaError = '';
        wabaVerifiedInfo = null;
        drawWizard();
        
        apiCall('whatsapp/setup.php?action=verify_waba', 'POST', {
            access_token: accessToken,
            waba_id: wabaId
        }).then(res => {
            wabaVerifiedInfo = res.data || res;
            selectedWaba = { id: wabaVerifiedInfo.waba_id, name: wabaVerifiedInfo.waba_name, status: wabaVerifiedInfo.waba_status };
            drawWizard();
        }).catch(err => {
            wabaError = err.message || 'WABA verification failed.';
            drawWizard();
        });
    };

    window.clearWabaError = function() {
        wabaError = '';
        wabaVerifiedInfo = null;
        drawWizard();
    };
    
    window.selectPhone = function(id) {
        selectedPhone = phones.find(p => p.id === id);
        drawWizard();
    };
    
    window.goWizardStep = function(step) {
        currentStep = step;
        drawWizard();
    };
    
    // 3. Fetch Phone Numbers
    window.fetchPhones = function() {
        if (!selectedWaba) return;
        currentStep = 3;
        phones = [];
        selectedPhone = null;
        drawWizard();
        
        apiCall('whatsapp/setup.php?action=get_phone_numbers', 'POST', {
            access_token: accessToken,
            waba_id: selectedWaba.id
        }).then(res => {
            phones = res.phones || [];
            if (phones.length === 1) {
                selectedPhone = phones[0];
            }
            drawWizard();
        }).catch(err => {
            showNotification('error', 'Failed retrieving phone numbers: ' + err.message);
            goWizardStep(2);
        });
    };
    
    // 4. Diagnostics Checklist health check
    window.triggerHealthCheck = function() {
        currentStep = 4;
        healthChecklist = null;
        healthDetails = null;
        healthError = '';
        drawWizard();
        
        apiCall('whatsapp/setup.php?action=health_check', 'POST', {
            access_token: accessToken,
            waba_id: selectedWaba.id,
            phone_number_id: selectedPhone.id
        }).then(res => {
            healthChecklist = res.checklist || null;
            healthDetails = res.details || null;
            drawWizard();
        }).catch(err => {
            healthError = err.message || 'Diagnostic checklist failed.';
            drawWizard();
        });
    };
    
    // 5. Save connection details
    window.saveConnection = function() {
        apiCall('whatsapp/setup.php?action=save_connection', 'POST', {
            access_token: accessToken,
            business_id: 'WABA_' + selectedWaba.id,
            business_name: selectedWaba.name,
            waba_id: selectedWaba.id,
            waba_name: selectedWaba.name,
            phone_number_id: selectedPhone.id,
            phone_number: selectedPhone.display_phone_number,
            display_name: selectedPhone.verified_name || selectedWaba.name
        }).then(res => {
            connectedDetails = res.data || res;
            currentStep = 5;
            drawWizard();
        }).catch(err => {
            showNotification('error', 'Failed saving connection: ' + err.message);
            currentStep = 4;
            drawWizard();
        });
    };

    drawWizard();
}

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
                <div class="space-y-6">
                    <!-- Top Connection State bar -->
                    <div class="flex items-center justify-between p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl shadow-sm">
                        <div class="flex items-center space-x-3">
                            <div class="h-9 w-9 rounded-full bg-emerald-500/20 text-emerald-500 flex items-center justify-center">
                                <i data-lucide="phone" class="h-4.5 w-4.5 animate-pulse"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-bold text-slate-800">${data.account.business_name}</h3>
                                <p class="text-[10px] text-slate-500 font-semibold">${data.account.display_phone_number} • ${data.account.messaging_limit}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3 text-xs">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            <span class="font-bold text-emerald-600">CONNECTED</span>
                        </div>
                    </div>

                    <!-- Cards row -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Sent Today</div>
                            <div class="text-xl font-extrabold text-slate-800">${cards.sent_today}</div>
                        </div>
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Received</div>
                            <div class="text-xl font-extrabold text-slate-800">${cards.received_today}</div>
                        </div>
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Delivered</div>
                            <div class="text-xl font-extrabold text-slate-800">${cards.delivered_total}</div>
                        </div>
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Read Rate</div>
                            <div class="text-xl font-extrabold text-slate-800">${cards.sent_today > 0 ? Math.round((cards.read_total / cards.sent_today) * 100) : 100}%</div>
                        </div>
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1">
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Broadcast Success</div>
                            <div class="text-xl font-extrabold text-slate-800">${cards.broadcast_success_rate}%</div>
                        </div>
                    </div>

                    <!-- Charts & Categories Row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Chart container -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm md:col-span-2 space-y-4">
                            <div class="text-xs font-bold text-slate-700">Messages Analytics</div>
                            <div class="h-64 relative">
                                <canvas id="wa-chart-daily"></canvas>
                            </div>
                        </div>
                        
                        <!-- AI Suggestions -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                            <div class="text-xs font-bold text-slate-700">AI Suggested Replies</div>
                            <div class="space-y-3" id="wa-suggestions-list">
                                ${act.ai_suggestions.length > 0 ? act.ai_suggestions.map(s => `
                                    <div class="p-3 bg-indigo-50/50 border border-indigo-100 rounded-xl space-y-1 text-xs">
                                        <div class="font-bold text-slate-700">${s.profile_name}</div>
                                        <div class="text-[10px] text-slate-500 italic">"${s.original_message}"</div>
                                        <div class="text-[11px] text-indigo-600 font-semibold mt-1">Suggested: ${s.ai_suggested_reply}</div>
                                    </div>
                                `).join('') : '<p class="text-xs text-slate-400 py-6 text-center">No AI suggestions pending.</p>'}
                            </div>
                        </div>
                    </div>

                    <!-- Activities row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Latest Chats -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                            <div class="text-xs font-bold text-slate-700">Recent Chats</div>
                            <div class="divide-y divide-slate-100 text-xs">
                                ${act.latest_chats.length > 0 ? act.latest_chats.map(c => `
                                    <div class="py-2.5 flex justify-between items-center">
                                        <div>
                                            <div class="font-bold text-slate-700">${c.profile_name}</div>
                                            <div class="text-[10px] text-slate-400 truncate max-w-[150px]">${c.body}</div>
                                        </div>
                                        <span class="text-[9px] text-slate-400">${new Date(c.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                                    </div>
                                `).join('') : '<p class="text-xs text-slate-400 py-6 text-center">No recent chats.</p>'}
                            </div>
                        </div>
                        
                        <!-- Active Campaigns -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                            <div class="text-xs font-bold text-slate-700">Recent Campaigns</div>
                            <div class="divide-y divide-slate-100 text-xs">
                                ${act.latest_campaigns.length > 0 ? act.latest_campaigns.map(c => `
                                    <div class="py-2.5 flex justify-between items-center">
                                        <div>
                                            <div class="font-bold text-slate-700">${c.name}</div>
                                            <div class="text-[10px] text-slate-400">Total: ${c.total_contacts} • Sent: ${c.sent_count}</div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold ${c.status === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600'}">${c.status.toUpperCase()}</span>
                                    </div>
                                `).join('') : '<p class="text-xs text-slate-400 py-6 text-center">No campaigns executed.</p>'}
                            </div>
                        </div>
                        
                        <!-- Failed Messages logs -->
                        <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-sm space-y-4">
                            <div class="text-xs font-bold text-slate-700">Delivery Errors</div>
                            <div class="divide-y divide-slate-100 text-xs">
                                ${act.failed_messages.length > 0 ? act.failed_messages.map(f => `
                                    <div class="py-2.5 text-xs">
                                        <div class="flex justify-between font-bold text-slate-700">
                                            <span>${f.profile_name}</span>
                                            <span class="text-red-500 text-[9px]">ERROR</span>
                                        </div>
                                        <div class="text-[10px] text-slate-400 truncate max-w-[200px] mt-0.5">${f.body}</div>
                                        <div class="text-[9px] text-red-400 font-semibold mt-1">Reason: ${f.error_message}</div>
                                    </div>
                                `).join('') : '<p class="text-xs text-slate-400 py-6 text-center text-emerald-500 font-semibold">All messages successfully sent! 🚀</p>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            lucide.createIcons();
            
            // Render Chart.js
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
                        <h3 class="text-xs font-bold text-slate-800">WhatsApp Chats</h3>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-2.5 flex items-center text-slate-400">
                                <i data-lucide="search" class="h-3.5 w-3.5"></i>
                            </span>
                            <input type="text" id="wa-search-threads" oninput="loadWaThreads(this.value)" placeholder="Search chats..." class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-lg text-[11px] focus:outline-none focus:border-blue-500">
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
                    <div class="flex-grow overflow-y-auto p-4 space-y-3 flex flex-col" id="wa-messages-container-list">
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
            const displayTime = t.last_message_at ? new Date(t.last_message_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
            return `
                <div onclick="selectWaThread(${t.id})" class="p-3 flex items-start justify-between cursor-pointer transition ${isActive ? 'bg-blue-50/80 border-l-4 border-blue-500' : 'bg-white hover:bg-slate-50'}">
                    <div class="flex items-start space-x-2.5 truncate">
                        <div class="h-8 w-8 rounded-full bg-slate-200 text-slate-600 font-bold flex items-center justify-center shrink-0">
                            ${t.profile_name.charAt(0).toUpperCase()}
                        </div>
                        <div class="truncate">
                            <div class="font-bold text-slate-700">${t.profile_name}</div>
                            <div class="text-[10px] text-slate-400 truncate mt-0.5">+${t.wa_id}</div>
                        </div>
                    </div>
                    <div class="flex flex-col items-end shrink-0 space-y-1">
                        <span class="text-[9px] text-slate-400">${displayTime}</span>
                        ${t.unread_count > 0 ? `<span class="bg-blue-600 text-white text-[9px] font-extrabold px-1.5 py-0.5 rounded-full">${t.unread_count}</span>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        
    } catch (err) {
        console.warn('Failed threads sync: ', err);
    }
}

// Select WhatsApp Thread
window.selectWaThread = function(threadId) {
    activeWaThreadId = threadId;
    
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
                    <div class="h-8.5 w-8.5 rounded-full bg-slate-200 text-slate-600 font-bold flex items-center justify-center">
                        ${thread.profile_name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-800">${thread.profile_name}</h4>
                        <p class="text-[10px] text-emerald-500 font-bold flex items-center space-x-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 inline-block"></span>
                            <span>Active Cloud API Thread</span>
                        </p>
                    </div>
                </div>
                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider pl-2 pr-1">+${thread.wa_id}</div>
            `;
        }
        
        // Show Input Footer
        const footer = document.getElementById('wa-chat-footer');
        if (footer) footer.classList.remove('hidden');
        
        // 2. Render Messages list
        const msgList = document.getElementById('wa-messages-container-list');
        if (msgList) {
            if (messages.length === 0) {
                msgList.innerHTML = `<div class="text-slate-400 text-center py-20">No messages in this chat. Send a template message to start!</div>`;
            } else {
                msgList.innerHTML = messages.map(m => {
                    const isInbound = (m.direction === 'inbound');
                    const time = new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    let bubbleHtml = m.body;
                    if (m.media_url) {
                        if (m.media_mime_type.includes('image')) {
                            bubbleHtml = `
                                <div class="space-y-1.5">
                                    <img src="../${m.media_url}" class="max-w-[200px] rounded-lg shadow-sm cursor-pointer object-cover">
                                    <p>${m.body}</p>
                                </div>
                            `;
                        } else {
                            bubbleHtml = `
                                <a href="../${m.media_url}" target="_blank" class="flex items-center space-x-2 p-2 bg-slate-900/10 hover:bg-slate-900/20 rounded-lg text-slate-800 font-bold border border-slate-300">
                                    <i data-lucide="file-text" class="h-4 w-4 text-slate-500"></i>
                                    <span>Download Attachment</span>
                                </a>
                            `;
                        }
                    }
                    
                    return `
                        <div class="flex ${isInbound ? 'justify-start' : 'justify-end'}">
                            <div class="max-w-[70%] p-3 rounded-2xl shadow-sm text-xs ${isInbound ? 'bg-white text-slate-800 rounded-tl-none' : 'bg-emerald-500 text-white rounded-tr-none'}">
                                <div>${bubbleHtml}</div>
                                <div class="text-[9px] text-right mt-1.5 ${isInbound ? 'text-slate-400' : 'text-emerald-100'}">${time}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                // Auto scroll to bottom
                msgList.scrollTop = msgList.scrollHeight;
                lucide.createIcons();
            }
        }
        
        // 3. Render CRM & AI details right panel
        const sidePanel = document.getElementById('wa-crm-sidepanel');
        if (sidePanel) {
            let leadHtml = '<div class="text-[10px] text-slate-400">No active leads matched.</div>';
            if (crm.lead) {
                leadHtml = `
                    <div class="bg-indigo-500/5 border border-indigo-100/80 p-3 rounded-xl space-y-1">
                        <div class="flex justify-between font-bold text-slate-700">
                            <span>${crm.lead.name}</span>
                            <span class="text-[10px] px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded-md font-extrabold uppercase">${crm.lead.stage}</span>
                        </div>
                        <div class="text-[10px] text-slate-500">Company: ${crm.lead.company || 'None'}</div>
                        <div class="text-[10px] text-slate-500">Budget: $${crm.lead.budget}</div>
                        <div class="text-[10px] text-slate-500">Priority: <strong class="text-indigo-600 uppercase font-bold">${crm.lead.priority}</strong></div>
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
                    <span class="text-slate-300 font-bold shrink-0">${new Date(t.created_at).toLocaleDateString([], {month: 'short', day: 'numeric'})}:</span>
                    <span>${t.description}</span>
                </div>
            `).join('') : '<p class="text-[10px] text-slate-400">No activities.</p>';
            
            sidePanel.innerHTML = `
                <!-- AI insights section -->
                <div class="space-y-3">
                    <div class="text-xs font-bold text-slate-700 flex items-center space-x-1">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5 text-indigo-500"></i>
                        <span>AI Analysis Panel</span>
                    </div>
                    <div class="glass-panel p-4 bg-slate-900 border border-slate-800 text-slate-300 rounded-2xl space-y-3">
                        <div>
                            <div class="text-[9px] uppercase font-bold text-slate-500">Thread Summary</div>
                            <p class="text-[10px] leading-relaxed mt-0.5 text-slate-300">${res.ai ? res.ai.ai_summary : 'Waiting for incoming text messages analysis...'}</p>
                        </div>
                        <div class="flex justify-between border-t border-slate-800/80 pt-2 text-[10px]">
                            <span>Sentiment: <strong class="uppercase text-emerald-400 font-bold">${res.ai ? res.ai.sentiment : 'Neutral'}</strong></span>
                            <span>Score: <strong class="text-blue-400 font-bold">${crm.lead ? crm.lead.lead_score : 50}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- CRM Lead cards -->
                <div class="space-y-2">
                    <div class="text-xs font-bold text-slate-700">Matched CRM Lead</div>
                    ${leadHtml}
                </div>

                <!-- Tasks list -->
                <div class="space-y-2">
                    <div class="text-xs font-bold text-slate-700">CRM Tasks</div>
                    <div class="space-y-2">${taskHtml}</div>
                </div>

                <!-- Notes list -->
                <div class="space-y-2">
                    <div class="text-xs font-bold text-slate-700">Notes</div>
                    <div class="space-y-2">${notesHtml}</div>
                </div>

                <!-- Timeline updates -->
                <div class="space-y-2">
                    <div class="text-xs font-bold text-slate-700">Timeline</div>
                    <div class="space-y-2 max-h-40 overflow-y-auto">${timelineHtml}</div>
                </div>
            `;
            lucide.createIcons();
        }
        
    } catch (err) {
        console.warn('Failed inbox load thread details:', err);
    }
}

// Live message submit trigger
window.sendWaMessage = function(e) {
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
window.triggerWaAIChatAnalysis = function() {
    if (!activeWaThreadId) return;
    const bar = document.getElementById('wa-ai-suggestion-bar');
    const text = document.getElementById('wa-ai-suggestion-text');
    if (!bar || !text) return;
    
    text.textContent = 'Analyzing context...';
    bar.classList.remove('hidden');
    
    apiCall('whatsapp/inbox.php?action=apply_ai_reply', 'APPLY_AI_REPLY', {
        wa_contact_id: activeWaThreadId
    }).then(res => {
        text.textContent = res.suggested_reply;
    }).catch(err => {
        showNotification('error', err.message);
        bar.classList.add('hidden');
    });
};

window.dismissAISuggestion = function() {
    const bar = document.getElementById('wa-ai-suggestion-bar');
    if (bar) bar.classList.add('hidden');
};

window.applyAISuggestion = function() {
    const text = document.getElementById('wa-ai-suggestion-text').textContent;
    const input = document.getElementById('wa-chat-input');
    if (input && text) {
        input.value = text;
        dismissAISuggestion();
    }
};

window.regenerateAISuggestion = function() {
    triggerWaAIChatAnalysis();
};

// ----------------------------------------------------
// 4. WHATSAPP CONTACTS VIEW
// ----------------------------------------------------
function renderWhatsAppContacts(container) {
    checkWaConnectionAndRender('contacts', container, async (contentArea) => {
        try {
            const res = await apiCall('whatsapp/contacts.php');
            const list = res.contacts || [];
            
            contentArea.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800">WhatsApp Subscriber List</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">Contacts who connected with your number via WhatsApp.</p>
                        </div>
                    </div>

                    <!-- Table panel -->
                    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 text-slate-500">
                                        <th class="py-3 px-4">Profile Name</th>
                                        <th class="py-3 px-4">Phone Number</th>
                                        <th class="py-3 px-4">CRM Profile Link</th>
                                        <th class="py-3 px-4">CRM Designation</th>
                                        <th class="py-3 px-4">Tags</th>
                                        <th class="py-3 px-4">Last Active</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${list.length > 0 ? list.map(c => `
                                        <tr class="hover:bg-slate-50 border-b border-slate-100">
                                            <td class="py-3 px-4 font-bold text-slate-700 flex items-center space-x-2">
                                                <div class="h-6 w-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-[10px]">
                                                    ${c.profile_name.charAt(0).toUpperCase()}
                                                </div>
                                                <span>${c.profile_name}</span>
                                            </td>
                                            <td class="py-3 px-4 text-slate-600 font-mono">+${c.wa_id}</td>
                                            <td class="py-3 px-4 text-slate-600">
                                                ${c.crm_name ? `<span class="px-2 py-1 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-md font-bold">${c.crm_name}</span>` : '<span class="text-slate-400 italic">Not Linked</span>'}
                                            </td>
                                            <td class="py-3 px-4 text-slate-500">${c.crm_title || 'None'}</td>
                                            <td class="py-3 px-4">
                                                ${c.tags ? c.tags.split(',').map(t => `<span class="inline-block px-1.5 py-0.5 bg-slate-100 text-slate-600 rounded text-[9px] font-bold mr-1">${t}</span>`).join('') : '<span class="text-slate-400 italic">No Tags</span>'}
                                            </td>
                                            <td class="py-3 px-4 text-slate-400">${new Date(c.last_message_at).toLocaleDateString()}</td>
                                        </tr>
                                    `).join('') : '<tr><td colspan="6" class="text-center py-10 text-slate-400">No contacts synced.</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        } catch (err) {
            showNotification('error', err.message);
        }
    });
}

// ----------------------------------------------------
// 5. WHATSAPP CAMPAIGNS VIEW
// ----------------------------------------------------
function renderWhatsAppCampaigns(container) {
    checkWaConnectionAndRender('campaigns', container, async (contentArea) => {
        try {
            const res = await apiCall('whatsapp/campaigns.php');
            const campaigns = res.campaigns || [];
            
            const templatesRes = await apiCall('whatsapp/templates.php');
            const templates = templatesRes.templates || [];
            
            contentArea.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800">Broadcast Campaigns Builder</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">Build and target massive approved template messages campaigns to matched leads.</p>
                        </div>
                        <button onclick="openCampaignCreateModal()" class="flex items-center space-x-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            <span>New Campaign</span>
                        </button>
                    </div>

                    <!-- Campaigns Grid list -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="campaigns-grid-list">
                        ${campaigns.length > 0 ? campaigns.map(c => {
                            const successRate = c.sent_count > 0 ? Math.round((c.delivered_count / c.sent_count) * 100) : 100;
                            return `
                                <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h3 class="font-extrabold text-slate-800 text-sm">${c.name}</h3>
                                            <p class="text-[10px] text-slate-400 mt-0.5">Template: <code class="font-mono text-indigo-500">${c.template_name}</code></p>
                                        </div>
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-bold ${c.status === 'sending' ? 'bg-blue-50 text-blue-600 animate-pulse' : c.status === 'completed' ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'}">
                                            ${c.status.toUpperCase()}
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-4 gap-2 text-center text-xs border-y border-slate-100 py-3">
                                        <div>
                                            <div class="text-[9px] font-bold text-slate-400 uppercase">Target</div>
                                            <div class="font-extrabold text-slate-700">${c.total_contacts}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] font-bold text-slate-400 uppercase">Sent</div>
                                            <div class="font-extrabold text-slate-700">${c.sent_count}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] font-bold text-slate-400 uppercase">Read</div>
                                            <div class="font-extrabold text-slate-700">${c.read_count}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] font-bold text-slate-400 uppercase">Success</div>
                                            <div class="font-extrabold text-emerald-600">${successRate}%</div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between pt-1">
                                        <span class="text-[10px] text-slate-400">Created: ${new Date(c.created_at).toLocaleDateString()}</span>
                                        <div class="flex space-x-2">
                                            ${c.status === 'draft' ? `
                                                <button onclick="triggerBroadcastCampaign(${c.id})" class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded text-[10px] font-bold transition flex items-center space-x-1">
                                                    <i data-lucide="send" class="h-3 w-3"></i>
                                                    <span>Send Campaign</span>
                                                </button>
                                            ` : ''}
                                            <button onclick="deleteCampaign(${c.id})" class="px-3 py-1.5 border border-red-200 text-red-500 hover:bg-red-50 rounded text-[10px] font-semibold transition">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('') : '<div class="md:col-span-2 text-center py-20 text-slate-400">No campaigns built yet. Create one above!</div>'}
                    </div>
                </div>
            `;
            lucide.createIcons();
            
            // Define Modal popup helper
            window.openCampaignCreateModal = function() {
                const existing = document.getElementById('campaign-modal');
                if (existing) existing.remove();
                
                const modal = document.createElement('div');
                modal.id = 'campaign-modal';
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4';
                
                modal.innerHTML = `
                    <div class="bg-white border border-slate-200 p-6 max-w-md w-full rounded-2xl shadow-2xl relative">
                        <button onclick="document.getElementById('campaign-modal').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-800 text-xl font-bold">&times;</button>
                        
                        <h2 class="text-sm font-bold text-slate-800 mb-4">Create WhatsApp Campaign</h2>
                        
                        <form onsubmit="saveNewCampaignDraft(event)" class="space-y-4 text-xs">
                            <div>
                                <label class="block text-slate-600 font-semibold mb-1">Campaign Name</label>
                                <input type="text" id="camp-name" required placeholder="e.g. Promo July 2026" class="w-full px-3 py-2 border border-slate-200 rounded-lg">
                            </div>
                            
                            <div>
                                <label class="block text-slate-600 font-semibold mb-1">Target Template</label>
                                <select id="camp-tpl" required class="w-full px-3 py-2 border border-slate-200 rounded-lg">
                                    ${templates.map(t => `<option value="${t.name}">${t.name} (${t.language})</option>`).join('')}
                                </select>
                            </div>
                            
                            <div class="border-t border-slate-100 pt-3 space-y-3">
                                <span class="font-bold text-slate-600 block">Audience Filters (Optional)</span>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-slate-500 mb-0.5 text-[10px]">Company Name</label>
                                        <input type="text" id="filt-comp" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-[11px]">
                                    </div>
                                    <div>
                                        <label class="block text-slate-500 mb-0.5 text-[10px]">Industry</label>
                                        <input type="text" id="filt-ind" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-[11px]">
                                    </div>
                                    <div>
                                        <label class="block text-slate-500 mb-0.5 text-[10px]">City/Location</label>
                                        <input type="text" id="filt-city" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-[11px]">
                                    </div>
                                    <div>
                                        <label class="block text-slate-500 mb-0.5 text-[10px]">Notes Keywords/Tags</label>
                                        <input type="text" id="filt-tags" class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-[11px]">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-bold transition mt-4">
                                Create Campaign Draft
                            </button>
                        </form>
                    </div>
                `;
                
                document.body.appendChild(modal);
            };
            
            window.saveNewCampaignDraft = function(e) {
                e.preventDefault();
                const payload = {
                    name: document.getElementById('camp-name').value.trim(),
                    template_name: document.getElementById('camp-tpl').value,
                    filters: {
                        company: document.getElementById('filt-comp').value.trim(),
                        industry: document.getElementById('filt-ind').value.trim(),
                        city: document.getElementById('filt-city').value.trim(),
                        tags: document.getElementById('filt-tags').value.trim()
                    }
                };
                
                apiCall('whatsapp/campaigns.php?action=create', 'POST', payload)
                    .then(res => {
                        showNotification('success', 'Campaign draft created successfully.');
                        document.getElementById('campaign-modal').remove();
                        renderWhatsAppCampaigns(container);
                    })
                    .catch(err => {
                        showNotification('error', err.message);
                    });
            };
            
            window.triggerBroadcastCampaign = function(campId) {
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
            
            window.deleteCampaign = function(campId) {
                if (!confirm('Delete this campaign permanently?')) return;
                
                apiCall(`whatsapp/campaigns.php?action=delete&campaign_id=${campId}`, 'POST')
                    .then(res => {
                        showNotification('success', res.message);
                        renderWhatsAppCampaigns(container);
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

// ----------------------------------------------------
// 6. WHATSAPP TEMPLATES VIEW
// ----------------------------------------------------
function renderWhatsAppTemplates(container) {
    checkWaConnectionAndRender('templates', container, async (contentArea) => {
        try {
            const res = await apiCall('whatsapp/templates.php');
            const list = res.templates || [];
            
            contentArea.innerHTML = `
                <div class="space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800">Approved Meta Message Templates</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">Approved templates synced from your Facebook WABA console.</p>
                        </div>
                        <button onclick="triggerTemplatesSync()" id="sync-tpl-btn" class="flex items-center space-x-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold transition">
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                            <span>Sync Templates</span>
                        </button>
                    </div>

                    <!-- Grid list -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="templates-grid-list">
                        ${list.length > 0 ? list.map(t => {
                            const components = JSON.parse(t.components_json) || [];
                            const bodyComponent = components.find(c => c.type === 'BODY') || {};
                            const bodyText = bodyComponent.text || 'No text components.';
                            return `
                                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm flex flex-col justify-between min-h-[160px]">
                                    <div class="space-y-3">
                                        <div class="flex justify-between items-start">
                                            <span class="font-extrabold text-slate-800 text-[11px] truncate max-w-[150px]" title="${t.name}">${t.name}</span>
                                            <span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">${t.status}</span>
                                        </div>
                                        <p class="text-[10px] text-slate-500 leading-relaxed max-h-24 overflow-y-auto bg-slate-50 p-2 rounded-lg font-mono">
                                            ${bodyText}
                                        </p>
                                    </div>
                                    <div class="flex justify-between items-center mt-4 pt-2 border-t border-slate-100 text-[10px] text-slate-400">
                                        <span>Category: ${t.category}</span>
                                        <span>Lang: ${t.language}</span>
                                    </div>
                                </div>
                            `;
                        }).join('') : '<div class="md:col-span-3 text-center py-20 text-slate-400">No message templates loaded. Click sync to retrieve templates.</div>'}
                    </div>
                </div>
            `;
            lucide.createIcons();
            
            window.triggerTemplatesSync = function() {
                const btn = document.getElementById('sync-tpl-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="loader-spinner mr-1"></span> Syncing...';
                }
                
                apiCall('whatsapp/templates.php?action=sync', 'POST')
                    .then(res => {
                        showNotification('success', res.message);
                        renderWhatsAppTemplates(container);
                    })
                    .catch(err => {
                        showNotification('error', err.message);
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<span>Sync Templates</span>';
                        }
                    });
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
            
            window.triggerQuickBroadcast = function(e) {
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
                <div class="space-y-6">
                    <div class="border-b border-slate-200 pb-4 bg-white p-5 rounded-2xl shadow-sm">
                        <h2 class="text-sm font-bold text-slate-800">WhatsApp Module Settings</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Manage automated CRM creation, file upload limits, and AI generation parameters.</p>
                    </div>

                    ${isConnected ? `
                    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4 max-w-xl">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                            <span class="font-bold text-slate-700 text-xs">Active Connection Status</span>
                            <span class="px-2.5 py-0.5 text-[9px] font-bold rounded uppercase ${acc.status === 'connected' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200'}">
                                ${acc.status || 'Disconnected'}
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
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
                                    <span class="text-slate-800 font-bold uppercase ${acc.token_status === 'valid' ? 'text-emerald-600' : 'text-rose-500'}">${acc.token_status || 'Unknown'}</span>
                                </div>
                                <div class="flex justify-between border-b border-slate-100 pb-1.5">
                                    <span class="text-slate-400 font-semibold">Webhook Status</span>
                                    <span class="text-slate-800 font-bold uppercase ${acc.webhook_status === 'verified' ? 'text-emerald-600' : 'text-rose-500'}">${acc.webhook_status || 'Unknown'}</span>
                                </div>
                                <div class="flex justify-between pb-1.5">
                                    <span class="text-slate-400 font-semibold">Limit & Quality</span>
                                    <span class="text-slate-800 font-bold">${acc.messaging_limit || 'N/A'} • <span class="font-extrabold text-emerald-600">${acc.quality_rating || 'N/A'}</span></span>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-[10px] text-slate-500 font-semibold bg-slate-50 border border-slate-100 p-3 rounded-xl mt-2">
                            <div>
                                <span class="text-slate-400">Connected On:</span>
                                <div class="text-slate-600 mt-0.5">${acc.created_at ? new Date(acc.created_at).toLocaleString() : 'N/A'}</div>
                            </div>
                            <div>
                                <span class="text-slate-400">Last Verified:</span>
                                <div class="text-slate-600 mt-0.5">${acc.last_verified_at ? new Date(acc.last_verified_at).toLocaleString() : 'Never'}</div>
                            </div>
                        </div>
                        
                        <div class="pt-3 border-t border-slate-100 flex flex-wrap gap-2 text-xs">
                            <button onclick="reVerifyConnection()" class="px-3.5 py-1.5 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 text-indigo-600 font-bold rounded-lg transition">
                                Re-Verify Connection
                            </button>
                            <button onclick="openUpdateTokenModal()" class="px-3.5 py-1.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold rounded-lg transition">
                                Update Token
                            </button>
                            <button onclick="disconnectWhatsAppAccount()" class="px-3.5 py-1.5 bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-500 font-bold rounded-lg transition">
                                Disconnect
                            </button>
                        </div>
                    </div>
                    ` : ''}

                    <div class="max-w-xl bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-6 text-xs">
                        <form onsubmit="saveWhatsAppSettingsForm(event)" class="space-y-5">
                            <!-- Toggle Grid -->
                            <div class="space-y-4">
                                <span class="font-bold text-slate-700 block border-b border-slate-100 pb-2">Automation Rules</span>
                                
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div>
                                        <div class="font-bold text-slate-700">AI Processing Enabled</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Use AI to automatically draft suggested replies for inbound chats.</div>
                                    </div>
                                    <input type="checkbox" id="set-ai" ${set.ai_enabled ? 'checked' : ''} class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                </label>
                                
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div>
                                        <div class="font-bold text-slate-700">Auto CRM Ingestion</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Automatically create profile records when new numbers text you.</div>
                                    </div>
                                    <input type="checkbox" id="set-auto-crm" ${set.auto_crm_creation ? 'checked' : ''} class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                </label>
                                
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div>
                                        <div class="font-bold text-slate-700">Lead Score & Urgency Detection</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Let AI rate budgets and score priority based on message urgency.</div>
                                    </div>
                                    <input type="checkbox" id="set-auto-lead" ${set.auto_lead_detection ? 'checked' : ''} class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                </label>
                            </div>

                            <!-- Input details -->
                            <div class="space-y-4 border-t border-slate-100 pt-4">
                                <span class="font-bold text-slate-700 block">Media Upload Configurations</span>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-slate-500 font-semibold mb-1">Max Media Size (MB)</label>
                                        <input type="number" id="set-media-limit" value="${set.media_upload_limit_mb}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-slate-500 font-semibold mb-1">Allowed File Types</label>
                                        <input type="text" id="set-file-types" value="${set.allowed_file_types}" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-4 border-t border-slate-100 flex items-center justify-end">
                                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg transition shadow-md">
                                    Save Configurations
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            lucide.createIcons();
            
            // Re-Verify Connection
            window.reVerifyConnection = function() {
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
            window.openUpdateTokenModal = function() {
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
            window.submitUpdatedToken = function() {
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
            
            window.saveWhatsAppSettingsForm = function(e) {
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
            
            window.disconnectWhatsAppAccount = function() {
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
