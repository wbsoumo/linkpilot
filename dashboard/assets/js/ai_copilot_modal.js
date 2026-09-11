// dashboard/assets/js/ai_copilot_modal.js

(function () {
    let currentParsedData = null;

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
                                <p class="text-[10px] text-slate-400">Type any workspace command (Email, WhatsApp, Invoice, Task, Deal)</p>
                            </div>
                        </div>
                        <button onclick="closeCopilotModal()" class="text-slate-400 hover:text-white transition p-1 rounded-lg hover:bg-slate-800">
                            <i data-lucide="x" class="h-4.5 w-4.5"></i>
                        </button>
                    </div>

                    <!-- Command Prompt Input Body -->
                    <div class="p-6 space-y-4">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-700">Command Prompt:</label>
                            <div class="relative">
                                <textarea id="copilot-prompt-input" rows="3" placeholder="e.g. 'Send an email to Alex about proposal update', 'Create invoice for $1500 for Acme Corp', 'WhatsApp Sarah with 15% discount', 'Schedule meeting with Rahul tomorrow at 3 PM'" class="w-full text-xs p-3.5 pr-10 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none text-slate-800 placeholder-slate-400 resize-none shadow-inner"></textarea>
                                <button onclick="parseCopilotCommand()" id="copilot-parse-btn" class="absolute bottom-3 right-3 bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-lg transition flex items-center justify-center shadow-md">
                                    <i data-lucide="send" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Action Suggestions Pills -->
                        <div class="flex flex-wrap gap-1.5 text-[10px]">
                            <span class="text-slate-400 font-bold self-center mr-1">Quick Suggestions:</span>
                            <button onclick="setCopilotPrompt('Send email to contact about project proposal update')" class="px-2.5 py-1 bg-slate-100 hover:bg-blue-50 hover:text-blue-600 text-slate-600 rounded-lg border border-slate-200 transition font-medium">📧 Send Email</button>
                            <button onclick="setCopilotPrompt('WhatsApp contact offering 15% discount on software plan')" class="px-2.5 py-1 bg-slate-100 hover:bg-emerald-50 hover:text-emerald-600 text-slate-600 rounded-lg border border-slate-200 transition font-medium">💬 WhatsApp Message</button>
                            <button onclick="setCopilotPrompt('Create invoice of $1,500 for Web Development services due in 7 days')" class="px-2.5 py-1 bg-slate-100 hover:bg-amber-50 hover:text-amber-600 text-slate-600 rounded-lg border border-slate-200 transition font-medium">🧾 Create Invoice</button>
                            <button onclick="setCopilotPrompt('Schedule call tomorrow at 4 PM to discuss contract details')" class="px-2.5 py-1 bg-slate-100 hover:bg-purple-50 hover:text-purple-600 text-slate-600 rounded-lg border border-slate-200 transition font-medium">📅 Schedule Meeting</button>
                        </div>

                        <!-- Loading State -->
                        <div id="copilot-loader" class="hidden py-8 flex flex-col items-center justify-center space-y-2 text-slate-500">
                            <i data-lucide="loader-2" class="h-6 w-6 animate-spin text-blue-600"></i>
                            <span class="text-xs font-bold text-slate-600">AI is parsing intent & resolving contact...</span>
                        </div>

                        <!-- Dynamic Interactive Action Preview Card -->
                        <div id="copilot-action-preview-card" class="hidden border border-blue-200 bg-blue-50/40 rounded-xl p-4 space-y-3">
                            <div class="flex items-center justify-between border-b border-blue-200/60 pb-2">
                                <span id="copilot-badge-action-type" class="px-2.5 py-0.5 bg-blue-600 text-white font-extrabold text-[10px] rounded-full uppercase"></span>
                                <span id="copilot-matched-contact-info" class="text-[11px] font-semibold text-slate-600"></span>
                            </div>

                            <p id="copilot-action-summary" class="text-xs text-slate-700 font-medium italic"></p>

                            <!-- Dynamic Action Input Fields Container -->
                            <div id="copilot-action-fields-container" class="space-y-2 text-xs"></div>
                        </div>
                    </div>

                    <!-- Modal Footer Controls -->
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                        <button onclick="closeCopilotModal()" class="px-4 py-2 bg-white hover:bg-slate-100 border border-slate-300 text-slate-700 font-bold rounded-xl text-xs transition">Cancel</button>
                        <button onclick="executeCopilotAction()" id="copilot-execute-btn" class="hidden px-5 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-extrabold rounded-xl text-xs transition shadow-md flex items-center space-x-1.5">
                            <i data-lucide="check-circle" class="h-4 w-4"></i>
                            <span>Confirm & Execute</span>
                        </button>
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

    window.parseCopilotCommand = async function () {
        const input = document.getElementById('copilot-prompt-input');
        const prompt = input ? input.value.trim() : '';
        if (!prompt) {
            if (window.showNotification) showNotification('warning', 'Please enter a command prompt.');
            return;
        }

        const loader = document.getElementById('copilot-loader');
        const previewCard = document.getElementById('copilot-action-preview-card');
        const executeBtn = document.getElementById('copilot-execute-btn');

        if (loader) loader.classList.remove('hidden');
        if (previewCard) previewCard.classList.add('hidden');
        if (executeBtn) executeBtn.classList.add('hidden');

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

            if (data.status === 'success' && data.data && data.data.parsed) {
                currentParsedData = data.data.parsed;
                renderActionPreview(currentParsedData);
            } else {
                if (window.showNotification) showNotification('error', data.message || 'Failed to parse AI command.');
            }
        } catch (err) {
            if (loader) loader.classList.add('hidden');
            if (window.showNotification) showNotification('error', 'Network error: ' + err.message);
        }
    };

    function renderActionPreview(parsed) {
        const previewCard = document.getElementById('copilot-action-preview-card');
        const badge = document.getElementById('copilot-badge-action-type');
        const contactInfo = document.getElementById('copilot-matched-contact-info');
        const summary = document.getElementById('copilot-action-summary');
        const fieldsContainer = document.getElementById('copilot-action-fields-container');
        const executeBtn = document.getElementById('copilot-execute-btn');

        if (!previewCard || !fieldsContainer) return;

        badge.textContent = (parsed.action_type || 'ACTION').replace('_', ' ');
        summary.textContent = parsed.summary || 'Parsed workspace action';

        const matched = parsed.matched_contact;
        if (matched) {
            contactInfo.textContent = `Matched Contact: ${matched.name} (${matched.email || matched.phone || 'N/A'})`;
        } else {
            contactInfo.textContent = `Target: ${parsed.target_contact?.name || 'New Prospect'}`;
        }

        let fieldsHTML = '';

        if (parsed.action_type === 'SEND_EMAIL') {
            const draft = parsed.email_draft || {};
            const emailAddr = matched?.email || parsed.target_contact?.email || '';
            fieldsHTML = `
                <div>
                    <label class="font-bold text-slate-700">Recipient Email:</label>
                    <input type="email" id="copilot-field-email" value="${emailAddr}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                </div>
                <div>
                    <label class="font-bold text-slate-700">Subject:</label>
                    <input type="text" id="copilot-field-subject" value="${draft.subject || ''}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
                </div>
                <div>
                    <label class="font-bold text-slate-700">Email Body:</label>
                    <textarea id="copilot-field-body" rows="4" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1 resize-none">${draft.body || ''}</textarea>
                </div>
            `;
        } elseif (parsed.action_type === 'SEND_WHATSAPP') {
            const draft = parsed.whatsapp_draft || {};
            const phone = matched?.phone || parsed.target_contact?.phone || '';
            fieldsHTML = `
                <div>
                    <label class="font-bold text-slate-700">Recipient Phone (WhatsApp):</label>
                    <input type="text" id="copilot-field-phone" value="${phone}" class="w-full p-2 border border-slate-300 rounded-lg text-xs mt-1">
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
                        <label class="font-bold text-slate-700">Amount:</label>
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

        fieldsContainer.innerHTML = fieldsHTML;
        previewCard.classList.remove('hidden');
        if (executeBtn) executeBtn.classList.remove('hidden');
        if (window.lucide) lucide.createIcons();
    }

    window.executeCopilotAction = async function () {
        if (!currentParsedData) return;

        const executeBtn = document.getElementById('copilot-execute-btn');
        if (executeBtn) {
            executeBtn.disabled = true;
            executeBtn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i><span>Executing...</span>`;
            if (window.lucide) lucide.createIcons();
        }

        const payload = { ...currentParsedData };
        const matched = currentParsedData.matched_contact;
        payload.contact_id = matched ? matched.id : null;

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
                if (window.showNotification) showNotification('error', data.message || 'Execution failed.');
                if (executeBtn) {
                    executeBtn.disabled = false;
                    executeBtn.innerHTML = `<i data-lucide="check-circle" class="h-4 w-4"></i><span>Confirm & Execute</span>`;
                    if (window.lucide) lucide.createIcons();
                }
            }
        } catch (err) {
            if (window.showNotification) showNotification('error', 'Network error: ' + err.message);
            if (executeBtn) {
                executeBtn.disabled = false;
                executeBtn.innerHTML = `<i data-lucide="check-circle" class="h-4 w-4"></i><span>Confirm & Execute</span>`;
                if (window.lucide) lucide.createIcons();
            }
        }
    };

    // Auto initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectCopilotModalHTML);
    } else {
        injectCopilotModalHTML();
    }
})();
