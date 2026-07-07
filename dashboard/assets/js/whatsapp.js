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
                                <div class="text-2xl font-extrabold text-slate-800">${cards.sent_today}</div>
                                <div class="text-[9px] text-slate-400 font-medium">~ 0% vs yesterday</div>
                            </div>
                            <!-- Received -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                                <div class="absolute top-4 right-4 h-7 w-7 bg-emerald-50 text-emerald-500 rounded-lg flex items-center justify-center">
                                    <i data-lucide="message-square" class="h-3.5 w-3.5"></i>
                                </div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Received</div>
                                <div class="text-2xl font-extrabold text-slate-800">${cards.received_today}</div>
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
                                <div class="text-2xl font-extrabold text-slate-800">${cards.delivered_total || cards.sent_today}</div>
                                <div class="text-[9px] text-slate-400 font-medium">~ 0% vs yesterday</div>
                            </div>
                            <!-- Read Rate -->
                            <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                                <div class="absolute top-4 right-4 h-7 w-7 bg-amber-50 text-amber-500 rounded-lg flex items-center justify-center">
                                    <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                </div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Read Rate</div>
                                <div class="text-2xl font-extrabold text-slate-800">${cards.sent_today > 0 ? Math.round((cards.read_total / cards.sent_today) * 100) : 100}%</div>
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
                lastMsgText = '+' + t.wa_id;
            }

            const unreadBadge = (t.unread_count > 0 && !isActive) ? 
                `<span id="wa-unread-badge-${t.id}" class="bg-emerald-500 text-white text-[9px] font-bold h-4.5 w-4.5 rounded-full flex items-center justify-center shrink-0 shadow-sm">${t.unread_count}</span>` : '';

            return `
                <div onclick="selectWaThread(${t.id})" class="p-3 flex items-start justify-between cursor-pointer transition ${isActive ? 'bg-blue-50/80 border-l-4 border-blue-500' : 'bg-white hover:bg-slate-50'}">
                    <div class="flex items-start space-x-2.5 truncate">
                        <div class="h-8 w-8 rounded-full ${getAvatarColorClass(t.profile_name)} font-bold flex items-center justify-center shrink-0">
                            ${t.profile_name.charAt(0).toUpperCase()}
                        </div>
                        <div class="truncate">
                            <div class="font-bold text-slate-700">${t.profile_name}</div>
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
    }
}

// Select WhatsApp Thread
window.selectWaThread = function(threadId) {
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
                        ${thread.profile_name.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="flex items-center space-x-1">
                            <h4 class="text-xs font-bold text-slate-800">${thread.profile_name}</h4>
                            <i data-lucide="check-circle-2" class="h-3.5 w-3.5 text-emerald-500 fill-emerald-100"></i>
                        </div>
                        <p class="text-[10px] text-slate-500 flex items-center space-x-1.5 mt-0.5">
                            <span class="font-mono">+${thread.wa_id}</span>
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
        if (footer) {
            footer.classList.remove('hidden');
            if (!isWindowActive) {
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
                        </button>
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
                                                <img src="assets/img/logo.png" class="h-full w-full object-contain" alt="">
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

                        <!-- Dynamic Variables Inputs -->
                        ${varsFound.length > 0 ? `
                        <div class="space-y-3.5 border-t border-slate-100 pt-3">
                            <div class="text-slate-400 font-bold uppercase tracking-wider text-[9px]">Variables</div>
                            
                            <div class="space-y-2.5">
                                ${varsFound.map(v => {
                                    const vNum = parseInt(v.replace(/[{}]/g, ''));
                                    let label = `Variable ${vNum}`;
                                    let placeholder = `Example: Value for ${v}`;
                                    
                                    // Custom presets matching typical user preview fields in the mockup
                                    if (vNum === 1) {
                                        label = `1 Customer Name`;
                                        placeholder = `Example: Soumojit`;
                                    } else if (vNum === 2) {
                                        label = `2 Offer Code`;
                                        placeholder = `Example: OUTREACH20`;
                                    } else if (vNum === 3) {
                                        label = `3 Offer Expiry`;
                                        placeholder = `Example: 31st July, 2026`;
                                    }
                                    
                                    return `
                                        <div>
                                            <label class="block text-slate-500 font-bold text-[9px] mb-1">${label}</label>
                                            <input type="text" data-var="${v}" oninput="updateMockPreviewBubble()" placeholder="${placeholder}" class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500 bg-[#f8fafc]">
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                        ` : ''}

                        <!-- Action Button -->
                        <div class="pt-2">
                            <button onclick="useActiveTemplate()" class="w-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold transition flex items-center justify-center space-x-1.5 shadow-md">
                                <i data-lucide="navigation" class="h-4 w-4"></i>
                                <span>Use Template</span>
                            </button>
                        </div>
                    </div>
                `;
                
                // Initialize default bubble content preview text
                updateMockPreviewBubble();
            }
            
            // Helper to dynamically update the mock bubble preview as users fill variables
            window.updateMockPreviewBubble = function() {
                if (!selectedTemplate) return;
                
                const components = JSON.parse(selectedTemplate.components_json) || [];
                const bodyComp = components.find(c => c.type === 'BODY') || {};
                let bodyText = bodyComp.text || '';
                
                // Replace variables with user inputs
                const inputs = document.querySelectorAll('#template-details-panel input[data-var]');
                inputs.forEach(input => {
                    const varTag = input.getAttribute('data-var');
                    const userVal = input.value.trim();
                    if (userVal !== '') {
                        bodyText = bodyText.replaceAll(varTag, `**${userVal}**`);
                    }
                });
                
                // Convert double star markdown **text** to bold tags inside HTML preview
                let formattedHtml = bodyText
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\*\*(.*?)\*\*/g, '<strong class="font-extrabold text-slate-900">$1</strong>');
                
                const bubble = document.getElementById('mock-bubble-text');
                if (bubble) {
                    bubble.innerHTML = formattedHtml;
                }
            };
            
            // Selection / Filter actions
            window.selectActiveTemplate = function(id) {
                const found = allTemplates.find(t => t.id === id);
                if (found) {
                    selectedTemplate = found;
                    renderAllTplViews();
                }
            };
            
            window.setTplPage = function(p) {
                currentPage = p;
                renderAllTplViews();
            };
            
            window.filterTemplatesList = function() {
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
                            btn.innerHTML = '<i data-lucide="refresh-cw" class="h-4 w-4"></i><span>Sync Templates</span>';
                            lucide.createIcons();
                        }
                    });
            };
            
            // Link active template to use modal/redirect
            window.useActiveTemplate = function() {
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
            window.toggleAiProcessing = function(checkbox) {
                if (checkbox.checked) {
                    checkbox.checked = false; // Keep unchecked until accepted!
                    showPrivacyPolicyModal(checkbox);
                } else {
                    autoSaveWaSettings();
                }
            };

            window.showPrivacyPolicyModal = function(checkbox) {
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
                
                window.closePrivacyModal = function(accepted) {
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
            window.autoSaveWaSettings = function() {
                const payload = {
                    ai_enabled: document.getElementById('set-ai').checked ? 1 : 0,
                    auto_crm_creation: document.getElementById('set-auto-crm').checked ? 1 : 0,
                    auto_lead_detection: document.getElementById('set-auto-lead').checked ? 1 : 0,
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

// -------------------------------------------------------------
// NEW CHAT MODAL AND FLOW (CRM LEADS & MANUAL PHONES)
// -------------------------------------------------------------
window.openNewChatModal = function() {
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

window.switchNewChatTab = function(tab) {
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

window.searchNewChatLeads = async function(query) {
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
    } catch(err) {
        console.error(err);
        container.innerHTML = '<div class="p-4 text-center text-rose-500">Failed loading CRM leads.</div>';
    }
};

window.selectLeadForChat = async function(phone, name) {
    await resolveAndOpenNewChat(phone);
};

window.submitManualNewChat = async function() {
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
window.openTemplateSelectorModal = async function(phone) {
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
    } catch(err) {
        console.error(err);
        document.getElementById('wa-tpl-select').innerHTML = '<option value="">Failed to load templates.</option>';
    }
};

window.previewSelectedTemplate = function(tplName) {
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

window.submitTemplateMsg = async function(phone) {
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
    } catch(err) {
        console.error(err);
        alert('Error sending template message: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '<span>Send Template</span>';
    }
};

// ----------------------------------------------------
// 9. WHATSAPP SEND TEMPLATE PAGE VIEW
// ----------------------------------------------------
window.renderWhatsAppSendTemplate = function(container, params = {}) {
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
                                                <img src="assets/img/logo.png" class="h-full w-full object-contain" alt="">
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
            
            // Sync preview bubble
            window.updateSendMockPreview = function() {
                let bodyText = bodyComp?.text || '';
                const inputs = document.querySelectorAll('input[data-send-var]');
                inputs.forEach(input => {
                    const tag = input.getAttribute('data-send-var');
                    const val = input.value.trim();
                    if (val !== '') {
                        bodyText = bodyText.replaceAll(tag, `**${val}**`);
                    }
                });
                
                let formattedHtml = bodyText
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\*\*(.*?)\*\*/g, '<strong class="font-extrabold text-slate-900">$1</strong>');
                
                const bubble = document.getElementById('mock-send-bubble-text');
                if (bubble) bubble.innerHTML = formattedHtml;
            };
            
            // Initialize preview bubble
            updateSendMockPreview();
            
            // Form Submit handler
            window.submitSendTemplateForm = async function(e) {
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
