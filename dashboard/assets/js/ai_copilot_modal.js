// dashboard/assets/js/ai_copilot_modal.js

(function () {
    let currentParsedData = null;
    let currentIdempotencyToken = null;

    // Inject HTML modal structure into document body
    function injectCopilotModalHTML() {
        if (document.getElementById('copilot-modal-root')) return;

        const modalDiv = document.createElement('div');
        modalDiv.id = 'copilot-modal-root';
        modalDiv.innerHTML = `
            <!-- Floating Co-Pilot Launch Button -->
            <button id="copilot-floating-btn" onclick="openCopilotModal()" class="fixed bottom-6 right-20 z-40 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold px-4 py-3 rounded-full shadow-xl flex items-center space-x-2 transition transform hover:scale-105 border border-white/20">
                <i data-lucide="sparkles" class="h-4.5 w-4.5 animate-pulse text-amber-300"></i>
                <span class="text-xs tracking-wide">AI Co-Pilot</span>
                <kbd class="hidden sm:inline-block bg-white/20 text-white text-[9px] px-1.5 py-0.5 rounded font-mono">⌘K</kbd>
            </button>

            <!-- Modal Backdrop -->
            <div id="copilot-modal-backdrop" onclick="closeCopilotModal(event)" class="hidden fixed inset-0 bg-slate-950/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 transition-opacity duration-300 opacity-0">
                <div onclick="event.stopPropagation()" class="bg-white rounded-2xl shadow-2xl max-w-xl w-full border border-slate-200 overflow-hidden flex flex-col transform transition-all duration-300 scale-95" id="copilot-modal-card">
                    <!-- Modal Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                        <div class="flex items-center space-x-2.5">
                            <div class="h-8 w-8 rounded-xl bg-blue-600/30 border border-blue-400/40 flex items-center justify-center">
                                <i data-lucide="bot" class="h-4.5 w-4.5 text-blue-400"></i>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-sm text-white">LinkPilot Autonomous AI Co-Pilot</h3>
                                <p class="text-[10px] text-slate-400">Type any natural language command (Email, WhatsApp, Task, Invoice)</p>
                            </div>
                        </div>
                        <button onclick="closeCopilotModal()" class="text-slate-400 hover:text-white transition p-1 rounded-lg hover:bg-slate-800">
                            <i data-lucide="x" class="h-4.5 w-4.5"></i>
                        </button>
                    </div>

                    <!-- Command Prompt Input Body -->
                    <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-700">Command Prompt:</label>
                            <div class="relative">
                                <textarea id="copilot-prompt-input" rows="3" placeholder="e.g. 'Send an email to Rahul telling him proposal is ready', 'WhatsApp Sarah offering 15% discount', 'Schedule this email for tomorrow at 10 AM', 'Create invoice for $1500 for Acme Corp'" class="w-full text-xs p-3.5 pr-10 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-slate-800 placeholder-slate-400 resize-none shadow-inner"></textarea>
                                <button onclick="parseCopilotCommand()" id="copilot-parse-btn" class="absolute bottom-3 right-3 bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-lg transition flex items-center justify-center shadow-md">
                                    <i data-lucide="send" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Action Suggestions Pills -->
                        <div class="flex flex-wrap gap-1.5 text-[10px]">
                            <span class="text-slate-400 font-bold self-center mr-1">Quick Prompts:</span>
                            <button onclick="setCopilotPrompt('Send Rahul the proposal and tell him we can start Monday.')" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-50 hover:text-blue-600 text-slate-600 rounded-lg border border-slate-200 transition font-medium">📧 Email Rahul Proposal</button>
                            <button onclick="setCopilotPrompt('Send the same message to Rahul on WhatsApp.')" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-600 text-slate-600 rounded-lg border border-slate-200 transition font-medium">💬 WhatsApp Message</button>
                            <button onclick="setCopilotPrompt('Schedule this email for tomorrow at 10 AM.')" class="px-2.5 py-1 bg-slate-100 hover:bg-purple-50 hover:text-purple-600 text-slate-600 rounded-lg border border-slate-200 transition font-medium">📅 Schedule 10 AM</button>
                            <button onclick="setCopilotPrompt('Create invoice of $1,500 for Web Development services')" class="px-2.5 py-1 bg-slate-100 hover:bg-amber-50 hover:text-amber-600 text-slate-600 rounded-lg border border-slate-200 transition font-medium">🧾 Create Invoice</button>
                        </div>

                        <!-- Loading State -->
                        <div id="copilot-loader" class="hidden py-8 flex flex-col items-center justify-center space-y-2 text-slate-500">
                            <i data-lucide="loader-2" class="h-6 w-6 animate-spin text-blue-600"></i>
                            <span class="text-xs font-bold text-slate-600">Parsing intent & resolving contacts/files...</span>
                        </div>

                        <!-- Ambiguous Contacts Picker Card -->
                        <div id="copilot-ambiguous-card" class="hidden border border-amber-300 bg-amber-50/60 rounded-xl p-4 space-y-3">
                            <div class="flex items-center space-x-2 text-amber-800 font-bold text-xs">
                                <i data-lucide="help-circle" class="h-4 w-4 text-amber-600"></i>
                                <span>Multiple contacts matched. Please select the recipient:</span>
                            </div>
                            <div id="copilot-candidates-list" class="space-y-1.5"></div>
                        </div>

                        <!-- Dynamic Interactive Action Preview Card -->
                        <div id="copilot-action-preview-card" class="hidden border border-blue-200 bg-blue-50/40 rounded-xl p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-blue-200/60 pb-2">
                                <span id="copilot-badge-action-type" class="px-2.5 py-0.5 bg-blue-600 text-white font-extrabold text-[10px] rounded-full uppercase"></span>
                                <span id="copilot-matched-contact-info" class="text-[11px] font-semibold text-slate-600"></span>
                            </div>

                            <p id="copilot-action-summary" class="text-xs text-slate-700 font-medium italic"></p>

                            <!-- Dynamic Action Input Fields Container -->
                            <div id="copilot-action-fields-container" class="space-y-2.5 text-xs"></div>

                            <!-- Resolved Attachments Chips -->
                            <div id="copilot-attachments-wrapper" class="hidden pt-2 border-t border-blue-200/60">
                                <span class="text-[10px] font-bold text-slate-500 block mb-1">Attached Files:</span>
                                <div id="copilot-attachments-chips" class="flex flex-wrap gap-1.5"></div>
                            </div>
                        </div>

                        <!-- Error Provider Connection Alert Banner -->
                        <div id="copilot-error-banner" class="hidden border border-rose-300 bg-rose-50 rounded-xl p-3.5 text-xs text-rose-700 flex items-start space-x-3">
                            <i data-lucide="alert-triangle" class="h-4 w-4 text-rose-600 shrink-0 mt-0.5"></i>
                            <div class="flex-grow">
                                <span id="copilot-error-text" class="font-semibold block"></span>
                                <a id="copilot-error-action-btn" href="setup.html?step=2" class="inline-block mt-2 px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg text-[10px] transition">Connect Integration</a>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer Controls -->
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                        <button onclick="closeCopilotModal()" class="px-4 py-2 bg-white hover:bg-slate-100 border border-slate-300 text-slate-700 font-bold rounded-xl text-xs transition">Cancel</button>
                        <div class="flex items-center space-x-2">
                            <button onclick="executeCopilotAction(true)" id="copilot-schedule-btn" class="hidden px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-bold rounded-xl text-xs transition shadow-md flex items-center space-x-1">
                                <i data-lucide="calendar" class="h-3.5 w-3.5"></i>
                                <span>Schedule</span>
                            </button>
                            <button onclick="executeCopilotAction(false)" id="copilot-execute-btn" class="hidden px-5 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center space-x-1.5">
                                <i data-lucide="send" class="h-4 w-4"></i>
                                <span>Send Now</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modalDiv);

        if (window.lucide) lucide.createIcons();

        // Keyboard Shortcut Cmd+K / Ctrl+K listener
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                window.openCopilotModal();
            }
        });
    }

    window.openCopilotModal = function () {
        injectCopilotModalHTML();
        currentIdempotencyToken = 'idem_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
        const backdrop = document.getElementById('copilot-modal-backdrop');
        const card = document.getElementById('copilot-modal-card');
        if (!backdrop || !card) return;

        backdrop.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            card.classList.remove('scale-95');
        }, 20);

        const input = document.getElementById('copilot-prompt-input');
        if (input) input.focus();
        if (window.lucide) lucide.createIcons();
    };

    window.closeCopilotModal = function (e) {
        const backdrop = document.getElementById('copilot-modal-backdrop');
        const card = document.getElementById('copilot-modal-card');
        if (!backdrop || !card) return;

        backdrop.classList.add('opacity-0');
        card.classList.add('scale-95');
        setTimeout(() => {
            backdrop.classList.add('hidden');
        }, 250);
    };

    window.setCopilotPrompt = function (txt) {
        const input = document.getElementById('copilot-prompt-input');
        if (input) {
            input.value = txt;
            window.parseCopilotCommand();
        }
    };

    window.selectCandidateContact = function (id, name, email, phone) {
        if (!currentParsedData) return;
        currentParsedData.matched_contact = { id, name, email, phone };
        if (currentParsedData.target_contact) {
            currentParsedData.target_contact.name = name;
            currentParsedData.target_contact.email = email;
            currentParsedData.target_contact.phone = phone;
        }

        const ambCard = document.getElementById('copilot-ambiguous-card');
        if (ambCard) ambCard.classList.add('hidden');

        renderActionPreview(currentParsedData);
    };

    window.parseCopilotCommand = async function () {
        const input = document.getElementById('copilot-prompt-input');
        const prompt = input ? input.value.trim() : '';
        if (!prompt) {
            if (window.showNotification) showNotification('warning', 'Please enter a command prompt.');
            return;
        }

        const loader = document.getElementById('copilot-loader');
        const ambCard = document.getElementById('copilot-ambiguous-card');
        const previewCard = document.getElementById('copilot-action-preview-card');
        const errBanner = document.getElementById('copilot-error-banner');
        const executeBtn = document.getElementById('copilot-execute-btn');
        const scheduleBtn = document.getElementById('copilot-schedule-btn');

        if (loader) loader.classList.remove('hidden');
        if (ambCard) ambCard.classList.add('hidden');
        if (previewCard) previewCard.classList.add('hidden');
        if (errBanner) errBanner.classList.add('hidden');
        if (executeBtn) executeBtn.classList.add('hidden');
        if (scheduleBtn) scheduleBtn.classList.add('hidden');

        try {
            const token = localStorage.getItem('linkpilot_token');
            const res = await fetch('../backend/api/crm/copilot_action.php?action=parse', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ prompt })
            });

            const data = await res.json();
            if (loader) loader.classList.add('hidden');

            if (data.status === 'success' && data.data) {
                if (data.data.ambiguous && data.data.candidates) {
                    // Render Ambiguous Contact Selector Card
                    currentParsedData = data.data.parsed;
                    renderAmbiguousPicker(data.data.candidates);
                } else if (data.data.parsed) {
                    currentParsedData = data.data.parsed;
                    renderActionPreview(currentParsedData);
                }
            } else {
                if (window.showNotification) showNotification('error', data.message || 'Failed to parse AI command.');
            }
        } catch (err) {
            if (loader) loader.classList.add('hidden');
            if (window.showNotification) showNotification('error', 'Network error: ' + err.message);
        }
    };

    function renderAmbiguousPicker(candidates) {
        const ambCard = document.getElementById('copilot-ambiguous-card');
        const list = document.getElementById('copilot-candidates-list');
        if (!ambCard || !list) return;

        list.innerHTML = candidates.map(c => `
            <button onclick="selectCandidateContact(${c.id}, '${escapeQuotes(c.name)}', '${escapeQuotes(c.email || '')}', '${escapeQuotes(c.phone || c.whatsapp || '')}')" class="w-full p-2.5 bg-white hover:bg-amber-100/60 border border-amber-200 rounded-xl text-left transition flex items-center justify-between shadow-xs">
                <div>
                    <span class="font-bold text-slate-800 text-xs">${c.name}</span>
                    <span class="text-[10px] text-slate-500 block">${c.email ? c.email : (c.phone ? c.phone : 'No details')}</span>
                </div>
                <span class="px-2 py-1 bg-amber-600 text-white font-bold text-[9px] rounded-lg">Select</span>
            </button>
        `).join('');

        ambCard.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    function renderActionPreview(parsed) {
        const previewCard = document.getElementById('copilot-action-preview-card');
        const badge = document.getElementById('copilot-badge-action-type');
        const contactInfo = document.getElementById('copilot-matched-contact-info');
        const summary = document.getElementById('copilot-action-summary');
        const fieldsContainer = document.getElementById('copilot-action-fields-container');
        const attachmentsWrapper = document.getElementById('copilot-attachments-wrapper');
        const attachmentsChips = document.getElementById('copilot-attachments-chips');
        const executeBtn = document.getElementById('copilot-execute-btn');
        const scheduleBtn = document.getElementById('copilot-schedule-btn');

        if (!previewCard || !fieldsContainer) return;

        badge.textContent = (parsed.action_type || 'ACTION').replace('_', ' ');
        summary.textContent = parsed.summary || 'Parsed workspace action';

        const matched = parsed.matched_contact;
        if (matched) {
            contactInfo.textContent = `To: ${matched.name} <${matched.email || matched.phone || 'N/A'}>`;
        } else {
            contactInfo.textContent = `To: ${parsed.target_contact?.name || 'Prospect'}`;
        }

        let fieldsHTML = '';

        if (parsed.action_type === 'SEND_EMAIL') {
            const draft = parsed.email_draft || {};
            const emailAddr = matched?.email || parsed.target_contact?.email || '';
            fieldsHTML = `
                <div>
                    <label class="font-bold text-slate-700">Recipient Email (To):</label>
                    <input type="email" id="copilot-field-email" value="${emailAddr}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1 font-mono">
                </div>
                <div>
                    <label class="font-bold text-slate-700">Subject:</label>
                    <input type="text" id="copilot-field-subject" value="${draft.subject || ''}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                </div>
                <div>
                    <label class="font-bold text-slate-700">Message Content:</label>
                    <textarea id="copilot-field-body" rows="4" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1 resize-none">${draft.body || ''}</textarea>
                </div>
            `;
        } else if (parsed.action_type === 'SEND_WHATSAPP') {
            const draft = parsed.whatsapp_draft || {};
            const phone = matched?.phone || matched?.whatsapp || parsed.target_contact?.phone || '';
            fieldsHTML = `
                <div>
                    <label class="font-bold text-slate-700">Recipient Phone (WhatsApp):</label>
                    <input type="text" id="copilot-field-phone" value="${phone}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1 font-mono">
                </div>
                <div>
                    <label class="font-bold text-slate-700">WhatsApp Message:</label>
                    <textarea id="copilot-field-wamsg" rows="4" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1 resize-none">${draft.message || ''}</textarea>
                </div>
            `;
        } else if (parsed.action_type === 'CREATE_INVOICE') {
            const draft = parsed.invoice_draft || {};
            fieldsHTML = `
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="font-bold text-slate-700">Client Name:</label>
                        <input type="text" id="copilot-field-inv-client" value="${draft.client_name || matched?.name || ''}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700">Amount (${draft.currency || 'INR'}):</label>
                        <input type="number" id="copilot-field-inv-amount" value="${draft.amount || 0}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                    </div>
                </div>
                <div>
                    <label class="font-bold text-slate-700">Description:</label>
                    <input type="text" id="copilot-field-inv-desc" value="${draft.description || ''}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                </div>
            `;
        } else {
            const draft = parsed.task_draft || {};
            fieldsHTML = `
                <div>
                    <label class="font-bold text-slate-700">Task/Meeting Title:</label>
                    <input type="text" id="copilot-field-task-title" value="${draft.title || ''}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="font-bold text-slate-700">Due Date:</label>
                        <input type="date" id="copilot-field-task-date" value="${draft.due_date || ''}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                    </div>
                    <div>
                        <label class="font-bold text-slate-700">Priority:</label>
                        <select id="copilot-field-task-priority" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                            <option value="high" ${draft.priority === 'high' ? 'selected' : ''}>High</option>
                            <option value="medium" ${draft.priority === 'medium' ? 'selected' : ''}>Medium</option>
                            <option value="low" ${draft.priority === 'low' ? 'selected' : ''}>Low</option>
                        </select>
                    </div>
                </div>
            `;
        }

        // Render Schedule Controls
        const sched = parsed.scheduling || {};
        fieldsHTML += `
            <div class="pt-2 border-t border-blue-200/60 mt-2">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" id="copilot-checkbox-schedule" ${sched.is_scheduled ? 'checked' : ''} onchange="toggleCopilotScheduleInput(this.checked)" class="rounded text-blue-600 focus:ring-blue-500">
                    <span class="font-bold text-slate-700">Schedule for future delivery</span>
                </label>
                <div id="copilot-schedule-time-box" class="${sched.is_scheduled ? '' : 'hidden'} mt-2">
                    <label class="font-bold text-slate-700 block mb-1">Select Schedule Date & Time:</label>
                    <input type="datetime-local" id="copilot-field-schedule-time" value="${sched.scheduled_at ? sched.scheduled_at.replace(' ', 'T') : ''}" class="w-full p-2 border border-slate-300 rounded-lg text-xs font-mono">
                </div>
            </div>
        `;

        fieldsContainer.innerHTML = fieldsHTML;

        // Render Resolved Attachments Chips
        const atts = parsed.resolved_attachments || [];
        if (atts.length > 0 && attachmentsWrapper && attachmentsChips) {
            attachmentsChips.innerHTML = atts.map(a => `
                <span class="inline-flex items-center space-x-1 px-2.5 py-1 bg-white border border-blue-300 text-blue-700 rounded-lg font-medium text-[10px]">
                    <i data-lucide="paperclip" class="h-3 w-3"></i>
                    <span>${a.name}</span>
                </span>
            `).join('');
            attachmentsWrapper.classList.remove('hidden');
        } else if (attachmentsWrapper) {
            attachmentsWrapper.classList.add('hidden');
        }

        previewCard.classList.remove('hidden');
        if (executeBtn) executeBtn.classList.remove('hidden');
        if (scheduleBtn) scheduleBtn.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    window.toggleCopilotScheduleInput = function (checked) {
        const box = document.getElementById('copilot-schedule-time-box');
        if (box) {
            if (checked) box.classList.remove('hidden');
            else box.classList.add('hidden');
        }
    };

    window.executeCopilotAction = async function (forceSchedule = false) {
        if (!currentParsedData) return;

        const executeBtn = document.getElementById('copilot-execute-btn');
        const scheduleBtn = document.getElementById('copilot-schedule-btn');
        const errBanner = document.getElementById('copilot-error-banner');
        const errText = document.getElementById('copilot-error-text');

        if (errBanner) errBanner.classList.add('hidden');

        if (executeBtn) executeBtn.disabled = true;
        if (scheduleBtn) scheduleBtn.disabled = true;

        const isSchedule = forceSchedule || (document.getElementById('copilot-checkbox-schedule')?.checked);
        const schedTime = document.getElementById('copilot-field-schedule-time')?.value;

        if (isSchedule && !schedTime) {
            if (window.showNotification) showNotification('warning', 'Please select a date and time for scheduling.');
            if (executeBtn) executeBtn.disabled = false;
            if (scheduleBtn) scheduleBtn.disabled = false;
            return;
        }

        const payload = { ...currentParsedData };
        const matched = currentParsedData.matched_contact;
        payload.contact_id = matched ? matched.id : null;
        payload.idempotency_token = currentIdempotencyToken;

        payload.scheduling = {
            is_scheduled: isSchedule,
            scheduled_at: isSchedule ? schedTime.replace('T', ' ') + ':00' : null
        };

        // Extract updated form values from preview card
        if (payload.action_type === 'SEND_EMAIL') {
            payload.email_draft = {
                recipient_email: document.getElementById('copilot-field-email')?.value || '',
                subject: document.getElementById('copilot-field-subject')?.value || '',
                body: document.getElementById('copilot-field-body')?.value || ''
            };
        } else if (payload.action_type === 'SEND_WHATSAPP') {
            payload.whatsapp_draft = {
                recipient_phone: document.getElementById('copilot-field-phone')?.value || '',
                message: document.getElementById('copilot-field-wamsg')?.value || ''
            };
        } else if (payload.action_type === 'CREATE_INVOICE') {
            payload.invoice_draft = {
                client_name: document.getElementById('copilot-field-inv-client')?.value || '',
                amount: parseFloat(document.getElementById('copilot-field-inv-amount')?.value || 0),
                description: document.getElementById('copilot-field-inv-desc')?.value || ''
            };
        } else {
            payload.task_draft = {
                title: document.getElementById('copilot-field-task-title')?.value || '',
                due_date: document.getElementById('copilot-field-task-date')?.value || '',
                priority: document.getElementById('copilot-field-task-priority')?.value || 'medium'
            };
        }

        try {
            const token = localStorage.getItem('linkpilot_token');
            const res = await fetch('../backend/api/crm/copilot_action.php?action=execute', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.status === 'success') {
                if (window.showNotification) showNotification('success', data.message || 'Action executed successfully!');
                window.closeCopilotModal();

                // Auto refresh active workspace views
                if (typeof window.updateGlobalTaskBadges === 'function') window.updateGlobalTaskBadges();
                if (window.currentView && typeof window.navigateTo === 'function') window.navigateTo(window.currentView);
            } else {
                // Surface actionable provider error banner if integration is missing
                if (errBanner && errText) {
                    errText.textContent = data.message || 'Execution failed.';
                    errBanner.classList.remove('hidden');
                } else if (window.showNotification) {
                    showNotification('error', data.message || 'Execution failed.');
                }
                if (executeBtn) executeBtn.disabled = false;
                if (scheduleBtn) scheduleBtn.disabled = false;
            }
        } catch (err) {
            if (window.showNotification) showNotification('error', 'Network error: ' + err.message);
            if (executeBtn) executeBtn.disabled = false;
            if (scheduleBtn) scheduleBtn.disabled = false;
        }
    };

    function escapeQuotes(str) {
        return (str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // Auto initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectCopilotModalHTML);
    } else {
        injectCopilotModalHTML();
    }
})();
