<?php
$filePath = '/Users/wbsoumo/Desktop/LinkPilot AI/dashboard/assets/js/whatsapp.js';
$content = file_get_contents($filePath);

// Find renderWhatsAppSetup start and end
$startMarker = 'function renderWhatsAppSetup(container, settings) {';
$endMarker = "    drawWizard();\n}";

$startPos = strpos($content, $startMarker);
if ($startPos === false) {
    die("Start marker not found");
}

$endPos = strpos($content, $endMarker, $startPos);
if ($endPos === false) {
    die("End marker not found");
}
$endPos += strlen($endMarker);

$newFunctionContent = <<<'JS'
function renderWhatsAppSetup(container, settings) {
    let currentStep = 1;
    let accessToken = '';
    let businessId = '';
    
    // Discovered Assets
    let businesses = [];
    let wabas = [];
    let phones = [];
    
    // Selections
    let selectedBiz = null;
    let selectedWaba = null;
    let selectedPhone = null;
    
    // Status metrics
    let tokenVerifiedInfo = null;
    let verifyError = '';
    let businessVerifiedInfo = null;
    let businessError = '';
    let healthChecklist = null;
    let healthDetails = null;
    let healthError = '';
    let connectedDetails = {};
    
    // Search filters
    let businessSearch = '';

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
                            <button onclick="goWizardStep(2)" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">Next: Business ID →</button>
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
            if (businessError) {
                stepHtml = `
                    <div class="space-y-4 py-2 text-center">
                        <div class="h-10 w-10 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center mx-auto text-base font-bold">✕</div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800">Business Verification Failed</h3>
                            <p class="text-xs text-rose-500 mt-2 bg-rose-50 border border-rose-100 rounded-lg p-3 text-left font-semibold">${businessError}</p>
                        </div>
                        <div class="pt-4 flex justify-between">
                            <button onclick="clearBusinessError()" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                            <button onclick="verifyBusinessId()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">Retry Verification</button>
                        </div>
                    </div>
                `;
            } else if (businessVerifiedInfo) {
                stepHtml = `
                    <div class="space-y-4 py-2">
                        <div class="text-center">
                            <div class="h-10 w-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center mx-auto text-base font-bold">✓</div>
                            <h3 class="text-xs font-bold text-emerald-600 mt-2">✓ Business Account Verified</h3>
                        </div>
                        
                        <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-xs space-y-2.5 max-w-xs mx-auto">
                            <div class="flex justify-between border-b border-slate-200/50 pb-1.5">
                                <span class="text-slate-500 font-semibold">Business Name</span>
                                <span class="text-slate-800 font-bold">${businessVerifiedInfo.business_name}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500 font-semibold">Business ID</span>
                                <span class="text-slate-800 font-bold font-mono text-[10px]">${businessVerifiedInfo.business_id}</span>
                            </div>
                        </div>
                        
                        <div class="pt-4 flex justify-between">
                            <button onclick="goWizardStep(2); businessVerifiedInfo = null;" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Change ID</button>
                            <button onclick="fetchWabas()" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">Next: Select WABA →</button>
                        </div>
                    </div>
                `;
            } else {
                stepHtml = `
                    <div class="space-y-4">
                        <div class="text-center pb-2">
                            <h3 class="text-sm font-bold text-slate-800">Meta Business ID</h3>
                            <p class="text-xs text-slate-500 mt-1">Specify your Meta Business Manager Account ID.</p>
                        </div>
                        
                        <div class="space-y-3.5 text-left">
                            <div>
                                <label class="block text-slate-600 font-semibold text-[11px] mb-1">Meta Business Manager ID</label>
                                <input type="text" id="wa-business-id" value="${businessId}" placeholder="e.g. 123456789012345" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500 font-mono">
                            </div>
                        </div>
                        
                        <div class="pt-4 flex justify-between">
                            <button onclick="goWizardStep(1)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                            <button id="btn-verify-business" onclick="verifyBusinessId()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-lg transition shadow-md">
                                Verify Business Manager
                            </button>
                        </div>
                    </div>
                `;
            }
        }
        
        else if (currentStep === 3) {
            // Select WABA Account
            stepHtml = `
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xs font-bold text-slate-800">Choose WhatsApp Business Account</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Select the WhatsApp Business Account (WABA) to link.</p>
                    </div>
                    
                    <div class="space-y-2.5 max-h-56 overflow-y-auto pr-1">
                        ${wabas.map(w => `
                            <div onclick="selectWaba('${w.id}')" class="p-3 border rounded-xl cursor-pointer transition flex justify-between items-center ${selectedWaba && selectedWaba.id === w.id ? 'border-blue-500 bg-blue-50/20' : 'border-slate-200 hover:bg-slate-50/60'}">
                                <div class="space-y-0.5 text-left">
                                    <div class="text-xs text-slate-700 font-extrabold">${w.name}</div>
                                    <div class="text-[10px] text-slate-400 font-semibold font-mono">WABA ID: ${w.id}</div>
                                    <div class="text-[10px] text-slate-500 font-medium">Phones count: ${w.phone_count}</div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded ${w.status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">${w.status}</span>
                                </div>
                            </div>
                        `).join('')}
                        ${wabas.length === 0 ? '<p class="text-xs text-slate-400 text-center py-4">No WhatsApp Business Accounts found in this Business Manager.</p>' : ''}
                    </div>
                    
                    <div class="pt-4 flex justify-between">
                        <button onclick="goWizardStep(2)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                        <button onclick="fetchPhones()" ${!selectedWaba ? 'disabled' : ''} class="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg transition shadow-md">Next: Select Phone →</button>
                    </div>
                </div>
            `;
        }
        
        else if (currentStep === 4) {
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
                        <button onclick="goWizardStep(3)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                        <button onclick="triggerHealthCheck()" ${!selectedPhone ? 'disabled' : ''} class="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg transition shadow-md">Next: Health Check →</button>
                    </div>
                </div>
            `;
        }
        
        else if (currentStep === 5) {
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
                            <button onclick="goWizardStep(4)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
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
                            ${renderCheck(healthChecklist.business_found, 'Business Found')}
                            ${renderCheck(healthChecklist.waba_found, 'WABA Found')}
                            ${renderCheck(healthChecklist.phone_found, 'Phone Number Found')}
                            ${renderCheck(healthChecklist.cloud_api_enabled, 'Cloud API Enabled')}
                            ${renderCheck(healthChecklist.ready_to_send, 'Ready to Send')}
                        </div>

                        ${healthDetails ? `
                        <div class="bg-slate-50 border border-slate-100 rounded-xl p-3 text-xs space-y-1.5 max-w-sm mx-auto mt-2">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Business Name</span>
                                <span class="text-slate-700 font-bold text-right">${healthDetails.business_name}</span>
                            </div>
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
                            <button onclick="goWizardStep(4)" class="px-4 py-2 border border-slate-200 text-slate-600 text-xs font-bold rounded-lg transition hover:bg-slate-50">Back</button>
                            <button onclick="saveConnection()" ${!passes ? 'disabled' : ''} class="px-4 py-2 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold rounded-lg transition shadow-md">Next: Save & Connect →</button>
                        </div>
                    </div>
                `;
            }
        }
        
        else if (currentStep === 6 || currentStep === 7) {
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
                    <span class="${currentStep === 2 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">2. Business</span>
                    <span class="text-slate-300">•</span>
                    <span class="${currentStep === 3 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">3. WABA</span>
                    <span class="text-slate-300">•</span>
                    <span class="${currentStep === 4 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">4. Phone</span>
                    <span class="text-slate-300">•</span>
                    <span class="${currentStep === 5 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">5. Health</span>
                    <span class="text-slate-300">•</span>
                    <span class="${currentStep >= 6 ? 'text-blue-600 border-b-2 border-blue-600 pb-1.5' : ''}">6. Success</span>
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
                            <li>business_management</li>
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

    window.verifyBusinessId = function() {
        const input = document.getElementById('wa-business-id');
        const bizVal = input ? input.value.trim() : businessId;
        
        if (!bizVal) {
            showNotification('error', 'Please enter your Meta Business Manager ID.');
            return;
        }
        
        businessId = bizVal;
        businessError = '';
        businessVerifiedInfo = null;
        drawWizard();
        
        apiCall('whatsapp/setup.php?action=verify_business_id', 'POST', {
            access_token: accessToken,
            business_id: businessId
        }).then(res => {
            businessVerifiedInfo = res.data || res;
            selectedBiz = { id: businessVerifiedInfo.business_id, name: businessVerifiedInfo.business_name };
            drawWizard();
        }).catch(err => {
            businessError = err.message || 'Business verification failed.';
            drawWizard();
        });
    };

    window.clearBusinessError = function() {
        businessError = '';
        businessVerifiedInfo = null;
        drawWizard();
    };
    
    window.selectWaba = function(id) {
        selectedWaba = wabas.find(w => w.id === id);
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
    
    // 3. Fetch WABAs
    window.fetchWabas = function() {
        currentStep = 3;
        wabas = [];
        selectedWaba = null;
        drawWizard();
        
        apiCall('whatsapp/setup.php?action=get_owned_wabas', 'POST', {
            access_token: accessToken,
            business_id: businessId
        }).then(res => {
            wabas = res.wabas || [];
            if (wabas.length === 0) {
                return apiCall('whatsapp/setup.php?action=get_client_wabas', 'POST', {
                    access_token: accessToken,
                    business_id: businessId
                });
            } else {
                if (wabas.length === 1) {
                    selectedWaba = wabas[0];
                }
                drawWizard();
            }
        }).then(res => {
            if (res) {
                wabas = res.wabas || [];
                if (wabas.length === 1) {
                    selectedWaba = wabas[0];
                }
                drawWizard();
            }
        }).catch(err => {
            showNotification('error', 'Failed retrieving WhatsApp accounts: ' + err.message);
            goWizardStep(2);
        });
    };
    
    // 4. Fetch Phone Numbers
    window.fetchPhones = function() {
        if (!selectedWaba) return;
        currentStep = 4;
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
            goWizardStep(3);
        });
    };
    
    // 5. Diagnostics Checklist health check
    window.triggerHealthCheck = function() {
        currentStep = 5;
        healthChecklist = null;
        healthDetails = null;
        healthError = '';
        drawWizard();
        
        apiCall('whatsapp/setup.php?action=health_check', 'POST', {
            access_token: accessToken,
            business_id: businessId,
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
    
    // 6. Save connection details
    window.saveConnection = function() {
        apiCall('whatsapp/setup.php?action=save_connection', 'POST', {
            access_token: accessToken,
            business_id: businessId,
            business_name: selectedBiz ? selectedBiz.name : 'Manual Biz',
            waba_id: selectedWaba.id,
            waba_name: selectedWaba.name,
            phone_number_id: selectedPhone.id,
            phone_number: selectedPhone.display_phone_number,
            display_name: selectedPhone.verified_name || selectedWaba.name
        }).then(res => {
            connectedDetails = res.data || res;
            currentStep = 6;
            drawWizard();
        }).catch(err => {
            showNotification('error', 'Failed saving connection: ' + err.message);
            currentStep = 5;
            drawWizard();
        });
    };

    drawWizard();
}
