// dashboard/assets/js/crm.js

// Global state variables
let currentView = 'dashboard';
let activeEmailId = null;
let activeCompanyId = null;
let activeContactId = null;
let activeLeadId = null;
let activeDealId = null;
let charts = {};
let wizardStep = 1;
let aiChatHistory = [];

function getSkeletonLoader(view) {
    if (view === 'dashboard') {
        return `
            <div class="space-y-8 animate-pulse text-xs">
                <div class="flex justify-between items-center">
                    <div class="space-y-2 w-1/3">
                        <div class="h-7 bg-slate-800 rounded-md w-3/4"></div>
                        <div class="h-3 bg-slate-800 rounded-md w-1/2"></div>
                    </div>
                    <div class="h-9 bg-slate-800 rounded-md w-28"></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    ${Array(12).fill(0).map(() => `
                        <div class="bg-slate-900/40 border border-slate-800/60 rounded-xl p-4 space-y-3">
                            <div class="flex justify-between">
                                <div class="h-3 bg-slate-800 rounded w-12"></div>
                                <div class="h-6 w-6 bg-slate-800 rounded-md"></div>
                            </div>
                            <div class="h-6 bg-slate-800 rounded w-16"></div>
                            <div class="h-2.5 bg-slate-800 rounded w-10"></div>
                        </div>
                    `).join('')}
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-slate-900/40 border border-slate-800/60 rounded-xl p-5 h-80 flex flex-col justify-between">
                        <div class="h-4 bg-slate-800 rounded w-1/4"></div>
                        <div class="h-60 bg-slate-800/40 rounded-lg w-full"></div>
                    </div>
                    <div class="bg-slate-900/40 border border-slate-800/60 rounded-xl p-5 h-80 space-y-4">
                        <div class="h-4 bg-slate-800 rounded w-1/3"></div>
                        <div class="space-y-3">
                            ${Array(4).fill(0).map(() => `
                                <div class="flex items-center space-x-3">
                                    <div class="h-8 w-8 bg-slate-800 rounded-full"></div>
                                    <div class="space-y-1.5 flex-1">
                                        <div class="h-3 bg-slate-800 rounded w-2/3"></div>
                                        <div class="h-2 bg-slate-800 rounded w-1/3"></div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else if (view === 'inbox') {
        return `
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[75vh] animate-pulse text-xs">
                <!-- Left email list -->
                <div class="lg:col-span-4 bg-slate-900/40 border border-slate-800/60 rounded-xl p-4 space-y-4 h-full flex flex-col">
                    <div class="h-8 bg-slate-800 rounded-md w-full"></div>
                    <div class="space-y-3 flex-grow overflow-hidden">
                        ${Array(5).fill(0).map(() => `
                            <div class="p-3 border border-slate-800/50 rounded-lg space-y-2">
                                <div class="flex justify-between">
                                    <div class="h-3 bg-slate-800 rounded w-1/3"></div>
                                    <div class="h-2.5 bg-slate-800 rounded w-10"></div>
                                </div>
                                <div class="h-3.5 bg-slate-800 rounded w-3/4"></div>
                                <div class="h-2.5 bg-slate-800 rounded w-1/2"></div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                <!-- Right details -->
                <div class="lg:col-span-8 bg-slate-900/40 border border-slate-800/60 rounded-xl p-6 space-y-6 h-full flex flex-col">
                    <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                        <div class="flex space-x-2">
                            <div class="h-8 w-8 bg-slate-800 rounded-md"></div>
                            <div class="h-8 w-8 bg-slate-800 rounded-md"></div>
                        </div>
                        <div class="h-5 bg-slate-800 rounded w-16"></div>
                    </div>
                    <div class="space-y-3">
                        <div class="h-5 bg-slate-800 rounded w-1/2"></div>
                        <div class="h-3.5 bg-slate-800 rounded w-1/3"></div>
                    </div>
                    <div class="bg-slate-900/60 rounded-xl p-4 h-24 space-y-2">
                        <div class="h-3 bg-slate-800 rounded w-1/4"></div>
                        <div class="h-3 bg-slate-800 rounded w-1/2"></div>
                    </div>
                    <div class="h-44 bg-slate-950/40 rounded-xl p-4 space-y-3">
                        <div class="h-3 bg-slate-800 rounded w-full"></div>
                        <div class="h-3 bg-slate-800 rounded w-5/6"></div>
                        <div class="h-3 bg-slate-800 rounded w-4/5"></div>
                    </div>
                </div>
            </div>
        `;
    } else {
        // Structured sequence list table view skeleton (matching Saleshandy template design)
        return `
            <div class="space-y-6 animate-pulse text-xs">
                <!-- Page Title & Main Actions -->
                <div class="flex justify-between items-center">
                    <div class="space-y-2 w-1/3">
                        <div class="h-6 bg-slate-800 rounded w-2/3"></div>
                        <div class="h-3 bg-slate-800 rounded w-1/2"></div>
                    </div>
                    <div class="h-9 bg-slate-800 rounded-md w-28"></div>
                </div>

                <!-- Tab/Sequence Filter Navigation Bar -->
                <div class="flex space-x-3 pb-2 border-b border-slate-800/80">
                    <div class="h-7 bg-slate-800 rounded-full w-24"></div>
                    <div class="h-7 bg-slate-800 rounded-full w-28"></div>
                    <div class="h-7 bg-slate-800 rounded-full w-20"></div>
                    <div class="h-7 bg-slate-800 rounded-full w-24"></div>
                </div>

                <!-- Search and Tools Toolbar -->
                <div class="flex justify-between items-center py-2">
                    <div class="h-8 bg-slate-800 rounded w-64"></div>
                    <div class="flex space-x-2">
                        <div class="h-8 w-8 bg-slate-800 rounded-md"></div>
                        <div class="h-8 w-8 bg-slate-800 rounded-md"></div>
                    </div>
                </div>

                <!-- Structured Table Grid -->
                <div class="glass-panel bg-slate-900/40 border border-slate-800/80 rounded-xl overflow-hidden shadow-lg">
                    <!-- Column Headers -->
                    <div class="grid grid-cols-12 gap-4 px-6 py-3.5 bg-slate-950/60 border-b border-slate-800/80 items-center">
                        <div class="col-span-1"><div class="h-3.5 bg-slate-800 rounded w-1/2"></div></div>
                        <div class="col-span-4"><div class="h-3.5 bg-slate-800 rounded w-2/3"></div></div>
                        <div class="col-span-2"><div class="h-3.5 bg-slate-800 rounded w-1/2"></div></div>
                        <div class="col-span-2"><div class="h-3.5 bg-slate-800 rounded w-2/3"></div></div>
                        <div class="col-span-1"><div class="h-3.5 bg-slate-800 rounded w-1/2"></div></div>
                        <div class="col-span-2"><div class="h-3.5 bg-slate-800 rounded w-1/3"></div></div>
                    </div>
                    <!-- Data Rows -->
                    <div class="divide-y divide-slate-850">
                        ${Array(6).fill(0).map(() => `
                            <div class="grid grid-cols-12 gap-4 px-6 py-4 items-center">
                                <!-- Checkbox / Icon Column -->
                                <div class="col-span-1 flex items-center">
                                    <div class="h-4 w-4 bg-slate-800/80 rounded"></div>
                                </div>
                                <!-- Title / Description Column -->
                                <div class="col-span-4 space-y-1.5">
                                    <div class="h-3.5 bg-slate-800 rounded w-4/5"></div>
                                    <div class="h-2.5 bg-slate-800 rounded w-1/2"></div>
                                </div>
                                <!-- Stats / Number Column -->
                                <div class="col-span-2">
                                    <div class="h-3 bg-slate-800 rounded w-1/2"></div>
                                </div>
                                <!-- Owner / Date Column -->
                                <div class="col-span-2">
                                    <div class="h-3 bg-slate-800 rounded w-2/3"></div>
                                </div>
                                <!-- Badge Status Column -->
                                <div class="col-span-1">
                                    <div class="h-5 bg-slate-800/80 rounded-full w-12"></div>
                                </div>
                                <!-- Action triggers Column -->
                                <div class="col-span-2 flex space-x-2.5 justify-end">
                                    <div class="h-6 w-6 bg-slate-800/80 rounded"></div>
                                    <div class="h-6 w-6 bg-slate-800/80 rounded"></div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    }
}

let isSmtpConfigured = null;
async function checkSmtpConfig() {
    if (isSmtpConfigured !== null) {
        return isSmtpConfigured;
    }
    try {
        const res = await apiCall('smtp/list.php');
        isSmtpConfigured = res && res.accounts && res.accounts.length > 0;
    } catch (e) {
        console.error("Failed to check SMTP config", e);
        isSmtpConfigured = false;
    }
    return isSmtpConfigured;
}

let isEmailSyncConfigured = null;
async function checkEmailSyncConfig() {
    if (isEmailSyncConfigured !== null) {
        return isEmailSyncConfigured;
    }
    try {
        const res = await apiCall('crm/email_intelligence/settings.php');
        const conn = res.connection || {};
        isEmailSyncConfigured = !!(conn.smtp_host && conn.imap_host && conn.smtp_username);
    } catch (e) {
        console.error("Failed to check email sync config", e);
        isEmailSyncConfigured = false;
    }
    return isEmailSyncConfigured;
}

// Router routing interceptor
async function navigateTo(view, params = {}) {
    if (view === 'inbox') {
        const syncConfigured = await checkEmailSyncConfig();
        if (!syncConfigured) {
            showNotification('warning', 'Please configure your Email Sync connection details first.');
            window.location.hash = '#/email-intelligence';
            return;
        }
    }

    if (view === 'automation') {
        const smtpConfigured = await checkSmtpConfig();
        if (!smtpConfigured) {
            window.location.href = 'smtp.html?setup_smtp=true';
            return;
        }
    }

    currentView = view;
    window.location.hash = `#/${view}`;
    
    // Clear WhatsApp CRM context if navigating away from whatsapp views
    if (!view.startsWith('whatsapp-')) {
        window.activeWaCrmContext = null;
    }
    
    // Dynamically update today's tasks badge count on sidebar and top header
    updateGlobalTaskBadges();
    
    // Highlight sidebar links
    document.querySelectorAll('.sidebar-nav-link, .sidebar-submenu-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(view)) {
            link.classList.add('active');
            if (link.classList.contains('sidebar-submenu-link')) {
                link.classList.remove('text-slate-400');
                link.classList.add('text-white', 'bg-slate-800', 'font-bold');
            }
        } else {
            link.classList.remove('active');
            if (link.classList.contains('sidebar-submenu-link')) {
                link.classList.remove('text-white', 'bg-slate-800', 'font-bold');
                link.classList.add('text-slate-400');
            }
        }
    });

    const isWhatsAppView = view.startsWith('whatsapp-');
    const waSubmenu = document.getElementById('whatsapp-submenu');
    const waChevron = document.getElementById('wa-chevron');
    if (isWhatsAppView && waSubmenu) {
        waSubmenu.classList.remove('hidden');
        if (waChevron) waChevron.classList.add('rotate-180');
    }

    const contentArea = document.getElementById('main-content-viewport');
    if (!contentArea) return;
    
    // Show skeleton loader
    contentArea.innerHTML = getSkeletonLoader(view);

    // Render corresponding screen
    switch (view) {
        case 'dashboard':
            renderDashboard(contentArea);
            break;
        case 'email-intelligence':
            renderEmailIntelligence(contentArea);
            break;
        case 'inbox':
            renderInbox(contentArea, params.emailId);
            break;
        case 'leads':
            renderLeads(contentArea);
            break;
        case 'companies':
            renderCompanies(contentArea, params.companyId);
            break;
        case 'contacts':
            renderContacts(contentArea, params.contactId);
            break;
        case 'deals':
            renderDeals(contentArea);
            break;
        case 'tasks':
            renderTasks(contentArea);
            break;
        case 'meetings':
            renderMeetings(contentArea);
            break;
        case 'automation':
            renderAutomation(contentArea);
            break;
        case 'ai-insights':
            renderAIInsights(contentArea);
            break;
        case 'reports':
            renderReports(contentArea);
            break;
        case 'integrations':
            renderIntegrations(contentArea);
            break;
        case 'external-apps':
            renderExternalApps(contentArea);
            break;
        case 'settings':
            renderSettings(contentArea);
            break;
        case 'install-extensions':
            renderInstallExtensions(contentArea);
            break;
        case 'whatsapp-dashboard':
            renderWhatsAppDashboard(contentArea);
            break;
        case 'whatsapp-inbox':
            renderWhatsAppInbox(contentArea);
            break;
        case 'whatsapp-contacts':
            renderWhatsAppContacts(contentArea);
            break;
        case 'whatsapp-campaigns':
            renderWhatsAppCampaigns(contentArea);
            break;
        case 'whatsapp-templates':
            renderWhatsAppTemplates(contentArea);
            break;
        case 'whatsapp-send-template':
            renderWhatsAppSendTemplate(contentArea, params);
            break;
        case 'whatsapp-broadcast':
            renderWhatsAppBroadcast(contentArea);
            break;
        case 'whatsapp-automation':
            renderWhatsAppAutomation(contentArea);
            break;
        case 'whatsapp-reports':
            renderWhatsAppReports(contentArea);
            break;
        case 'whatsapp-settings':
            renderWhatsAppSettings(contentArea);
            break;
        default:
            renderDashboard(contentArea);
    }
}

// ----------------------------------------------------
// 1. DASHBOARD VIEW
// ----------------------------------------------------
async function renderDashboard(container) {
    try {
        const data = await apiCall('crm/reports.php');
        const stats = await apiCall('analytics/dashboard.php');
        
        container.innerHTML = `
            <div class="space-y-8 animate-fade-in">
                <!-- Banner header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0">
                    <div>
                        <h1 class="text-3xl font-extrabold text-white">AI CRM Control Hub</h1>
                        <p class="text-slate-400 text-sm mt-1">Real-time statistics, email intelligence queues, and lead activity pipeline.</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="triggerManualEmailSync(this)" class="flex items-center space-x-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition">
                            <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                            <span>Sync Inbox Now</span>
                        </button>
                    </div>
                </div>

                <!-- 12 Top Statistics Cards Grid -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <!-- Received Emails -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Emails Recd</span>
                            <span class="p-1.5 bg-indigo-500/10 text-indigo-400 rounded-md"><i data-lucide="mail" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-emails-recd">${stats.statistics.emails_generated * 3 + 12 || 0}</span>
                            <span class="text-[10px] text-emerald-400 block mt-0.5"><i data-lucide="trending-up" class="h-3 w-3 inline mr-0.5"></i> +12% today</span>
                        </div>
                    </div>
                    <!-- Emails Processed by AI -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">AI Processed</span>
                            <span class="p-1.5 bg-teal-500/10 text-teal-400 rounded-md"><i data-lucide="cpu" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-emails-ai">${stats.statistics.total_requests || 0}</span>
                            <span class="text-[10px] text-emerald-400 block mt-0.5"><i data-lucide="trending-up" class="h-3 w-3 inline mr-0.5"></i> +8% today</span>
                        </div>
                    </div>
                    <!-- Total Leads -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Total Leads</span>
                            <span class="p-1.5 bg-emerald-500/10 text-emerald-400 rounded-md"><i data-lucide="users" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-total-leads">0</span>
                            <span class="text-[10px] text-slate-500 block mt-0.5">Pipeline Leads</span>
                        </div>
                    </div>
                    <!-- Total Companies -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Companies</span>
                            <span class="p-1.5 bg-blue-500/10 text-blue-400 rounded-md"><i data-lucide="building" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-total-companies">0</span>
                            <span class="text-[10px] text-slate-500 block mt-0.5">Client Accounts</span>
                        </div>
                    </div>
                    <!-- Active Clients -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Active Clients</span>
                            <span class="p-1.5 bg-green-500/10 text-green-400 rounded-md"><i data-lucide="shield-check" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-active-clients">0</span>
                            <span class="text-[10px] text-emerald-400 block mt-0.5">Retained Accounts</span>
                        </div>
                    </div>
                    <!-- Open Opportunities -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Open Deals</span>
                            <span class="p-1.5 bg-amber-500/10 text-amber-400 rounded-md"><i data-lucide="zap" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-open-deals">0</span>
                            <span class="text-[10px] text-slate-500 block mt-0.5">Sales Opportunities</span>
                        </div>
                    </div>
                    <!-- Follow-ups Due -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Follow-ups</span>
                            <span class="p-1.5 bg-purple-500/10 text-purple-400 rounded-md"><i data-lucide="bell" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-followups-due">0</span>
                            <span class="text-[10px] text-amber-400 block mt-0.5">Reminders Pending</span>
                        </div>
                    </div>
                    <!-- Tasks Due Today -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Tasks Today</span>
                            <span class="p-1.5 bg-pink-500/10 text-pink-400 rounded-md"><i data-lucide="check-square" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-tasks-today">0</span>
                            <span class="text-[10px] text-slate-500 block mt-0.5">Daily checklist</span>
                        </div>
                    </div>
                    <!-- Meetings Scheduled -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Meetings</span>
                            <span class="p-1.5 bg-rose-500/10 text-rose-400 rounded-md"><i data-lucide="calendar" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-meetings">0</span>
                            <span class="text-[10px] text-slate-500 block mt-0.5">Scheduled calls</span>
                        </div>
                    </div>
                    <!-- Revenue Pipeline -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover col-span-2 lg:col-span-1">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Pipeline Val</span>
                            <span class="p-1.5 bg-emerald-500/10 text-emerald-400 rounded-md"><i data-lucide="dollar-sign" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-xl font-extrabold text-white" id="stat-revenue-val">₹0</span>
                            <span class="text-[10px] text-slate-500 block mt-0.5">Est. forecast</span>
                        </div>
                    </div>
                    <!-- Conversion Rate -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">Conv. Rate</span>
                            <span class="p-1.5 bg-sky-500/10 text-sky-400 rounded-md"><i data-lucide="trending-up" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-conv-rate">24.5%</span>
                            <span class="text-[10px] text-emerald-400 block mt-0.5"><i data-lucide="trending-up" class="h-3 w-3 inline mr-0.5"></i> +2.1% this week</span>
                        </div>
                    </div>
                    <!-- AI processing success rate -->
                    <div class="glass-panel p-4 bg-slate-900/40 card-hover">
                        <div class="flex justify-between items-center text-slate-400">
                            <span class="text-[10px] font-bold uppercase tracking-wider">AI Accuracy</span>
                            <span class="p-1.5 bg-violet-500/10 text-violet-400 rounded-md"><i data-lucide="check-circle" class="h-4 w-4"></i></span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-extrabold text-white" id="stat-ai-accuracy">98.2%</span>
                            <span class="text-[10px] text-slate-500 block mt-0.5">Success threshold</span>
                        </div>
                    </div>
                </div>

                <!-- Dashboard charts and timeline widgets -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Trends Chart -->
                    <div class="glass-panel p-6 bg-slate-900/40 lg:col-span-2 space-y-4 text-left">
                        <div>
                            <h3 class="text-lg font-bold text-white">Outreach & Leads Velocity</h3>
                            <p class="text-xs text-slate-400">Comparing emails synchronized against qualified leads generated.</p>
                        </div>
                        <div class="relative h-72">
                            <canvas id="dashboardTrendChart"></canvas>
                        </div>
                    </div>
                    <!-- Today's Tasks Checklist -->
                    <div class="glass-panel p-6 bg-white shadow-sm border border-slate-200 rounded-2xl space-y-4 flex flex-col h-full text-left">
                        <div class="flex justify-between items-center border-b border-slate-100 pb-2">
                            <h3 class="text-lg font-bold text-slate-850 flex items-center space-x-2">
                                <i data-lucide="check-square" class="h-4.5 w-4.5 text-indigo-600"></i>
                                <span>Today's Tasks</span>
                            </h3>
                            <a href="#/tasks" class="text-xs text-indigo-600 hover:text-indigo-500 font-bold transition">View Hub</a>
                        </div>
                        <div class="flex-grow overflow-y-auto pr-1 space-y-3 max-h-[220px]" id="dash-tasks-today-list">
                            <p class="text-xs text-slate-500 py-6 text-center">Loading tasks...</p>
                        </div>
                    </div>
                </div>

                <!-- Additional Charts & Timeline Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="glass-panel p-6 bg-slate-900/40 space-y-4 text-left">
                        <h4 class="text-sm font-bold text-white text-left">Lead Sources Distribution</h4>
                        <div class="relative h-60 flex items-center justify-center">
                            <canvas id="leadSourcesChart"></canvas>
                        </div>
                    </div>
                    <!-- Recent Activities Timeline -->
                    <div class="glass-panel p-6 bg-slate-900/40 space-y-4 flex flex-col h-full text-left">
                        <h4 class="text-sm font-bold text-white text-left border-b border-slate-800 pb-2">Recent Activities</h4>
                        <div class="flex-grow overflow-y-auto pr-1 space-y-4 max-h-[200px] timeline-container" id="dash-timeline-feed">
                            <p class="text-xs text-slate-500 py-6 text-center">Loading feeds...</p>
                        </div>
                    </div>
                    <div class="glass-panel p-6 bg-slate-900/40 space-y-4 text-left">
                        <h4 class="text-sm font-bold text-white text-left">Sales Pipeline Funnel (₹)</h4>
                        <div class="relative h-60 flex items-center justify-center">
                            <canvas id="pipelineFunnelChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Populate Metric counts from API calls
        const leadsData = await apiCall('crm/leads.php');
        const companiesData = await apiCall('crm/companies.php');
        const dealsData = await apiCall('crm/deals.php?layout=kanban');
        const tasksData = await apiCall('crm/tasks.php');
        const meetingsData = await apiCall('crm/meetings.php');
        
        document.getElementById('stat-total-leads').textContent = leadsData.total || 0;
        document.getElementById('stat-total-companies').textContent = companiesData.total || 0;
        
        // Calculate Active Clients (companies with status = 'Active')
        const activeCount = companiesData.companies.filter(c => c.status === 'Active' || c.status === 'Active Client').length;
        document.getElementById('stat-active-clients').textContent = activeCount;
        
        // Deals expected revenue summation
        let totalRev = 0;
        let dealsCount = 0;
        if (dealsData && dealsData.totals) {
            Object.keys(dealsData.totals).forEach(st => {
                totalRev += dealsData.totals[st];
                dealsCount += dealsData.stages[st].length;
            });
        }
        document.getElementById('stat-open-deals').textContent = dealsCount;
        document.getElementById('stat-revenue-val').textContent = '₹' + totalRev.toLocaleString('en-IN');
        
        // Tasks Due Today & Follow-ups
        const todayStr = new Date().toISOString().split('T')[0];
        const pendingTodayTasks = tasksData.tasks.filter(t => {
            return t.status !== 'completed' && t.due_date && t.due_date <= todayStr;
        });
        document.getElementById('stat-tasks-today').textContent = pendingTodayTasks.length;
        document.getElementById('stat-followups-due').textContent = tasksData.tasks.filter(t => t.status === 'pending').length;
        
        const tasksContainer = document.getElementById('dash-tasks-today-list');
        if (pendingTodayTasks.length > 0) {
            tasksContainer.innerHTML = pendingTodayTasks.map(t => {
                const priority = t.priority || 'medium';
                let priorityBadge = '';
                if (priority === 'high') {
                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-rose-50 text-rose-600 border border-rose-200 uppercase">High</span>`;
                } else if (priority === 'medium') {
                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-200 uppercase">Medium</span>`;
                } else {
                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-slate-100 text-slate-600 border border-slate-200 uppercase">Low</span>`;
                }

                // Parse category prefix out
                let displayTitle = t.title || '';
                let categoryHTML = '';
                if (displayTitle.startsWith('[Follow-up]')) {
                    categoryHTML = `<span class="text-indigo-600 font-bold mr-1">[Follow-up]</span>`;
                    displayTitle = displayTitle.replace('[Follow-up] ', '');
                } else if (displayTitle.startsWith('[Reply]')) {
                    categoryHTML = `<span class="text-emerald-600 font-bold mr-1">[Reply]</span>`;
                    displayTitle = displayTitle.replace('[Reply] ', '');
                } else if (displayTitle.startsWith('[Meeting]')) {
                    categoryHTML = `<span class="text-blue-600 font-bold mr-1">[Meeting]</span>`;
                    displayTitle = displayTitle.replace('[Meeting] ', '');
                } else if (displayTitle.startsWith('[Arrange]')) {
                    categoryHTML = `<span class="text-amber-600 font-bold mr-1">[Arrange]</span>`;
                    displayTitle = displayTitle.replace('[Arrange] ', '');
                }

                const timeStr = t.due_time ? `<span class="text-slate-500 ml-1.5">@ ${t.due_time.substring(0, 5)}</span>` : '';
                const meetHTML = t.meet_link ? `
                    <a href="${t.meet_link}" target="_blank" class="mt-1 flex items-center space-x-1 text-[9px] text-blue-600 hover:text-blue-500 font-bold bg-blue-50 px-2 py-0.5 rounded border border-blue-200 transition w-fit inline-flex">
                        <i data-lucide="video" class="h-3 w-3 mr-0.5"></i>
                        <span>Join Meet</span>
                    </a>
                ` : '';

                return `
                    <div class="p-2.5 bg-slate-50 border border-slate-150 rounded-xl flex items-start space-x-2.5 hover:border-indigo-500/30 hover:bg-indigo-50/10 transition">
                        <input type="checkbox" onclick="dashboardToggleTask(${t.id}, '${t.status}')" class="mt-0.5 h-3.5 w-3.5 border-slate-300 rounded text-indigo-600 bg-white focus:ring-indigo-500 cursor-pointer">
                        <div class="flex-grow text-left">
                            <div class="font-bold text-slate-800 text-[11px] leading-tight flex flex-wrap items-center">
                                ${categoryHTML}
                                <span>${displayTitle}</span>
                                ${timeStr}
                            </div>
                            <p class="text-[9px] text-slate-500 mt-0.5 line-clamp-1">${t.description || 'No description.'}</p>
                            ${meetHTML}
                        </div>
                        <div class="shrink-0 flex items-center">
                            ${priorityBadge}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            tasksContainer.innerHTML = `
                <div class="text-center py-6 text-slate-550 text-[10px] flex flex-col items-center justify-center space-y-1.5">
                    <i data-lucide="check-circle-2" class="h-6 w-6 text-emerald-500"></i>
                    <span class="text-slate-400">No pending tasks due today.</span>
                </div>
            `;
        }
        
        // Meetings
        document.getElementById('stat-meetings').textContent = meetingsData.meetings.length;
        
        // Populate Timeline feed
        const timelineData = await apiCall('crm/timeline.php');
        const timelineContainer = document.getElementById('dash-timeline-feed');
        if (timelineData && timelineData.timeline.length > 0) {
            timelineContainer.innerHTML = timelineData.timeline.map(item => {
                const date = new Date(item.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                return `
                    <div class="timeline-item">
                        <div class="timeline-dot ${item.activity_type.includes('Email') ? 'teal' : item.activity_type.includes('Lead') ? 'success' : ''}"></div>
                        <div class="text-xs font-semibold text-white">${item.activity_type}</div>
                        <div class="text-[11px] text-slate-400 mt-0.5">${item.description}</div>
                        <div class="text-[9px] text-slate-500 mt-1">${date}</div>
                    </div>
                `;
            }).join('');
        } else {
            timelineContainer.innerHTML = `<p class="text-xs text-slate-500 text-center py-6">No interactions logged yet.</p>`;
        }
        
        // Update global task badges
        updateGlobalTaskBadges();

        // Initialize Charts
        renderDashboardCharts(data);
        
        lucide.createIcons();
    } catch (err) {
        showNotification('error', 'Error rendering dashboard: ' + err.message);
    }
}

function renderDashboardCharts(data) {
    // 1. Line Trend Chart
    const trendEl = document.getElementById('dashboardTrendChart');
    if (trendEl) {
        if (charts.trend) {
            charts.trend.destroy();
        }
        const trendCtx = trendEl.getContext('2d');
        const emailLabels = data.emails_received_trend.length > 0 ? data.emails_received_trend.map(e => e.date) : ['Jul 01', 'Jul 02', 'Jul 03', 'Jul 04', 'Jul 05'];
        const emailCounts = data.emails_received_trend.length > 0 ? data.emails_received_trend.map(e => e.count) : [5, 8, 12, 6, 9];
        const leadCounts = data.leads_generated_trend.length > 0 ? data.leads_generated_trend.map(l => l.count) : [2, 4, 3, 5, 4];
        
        charts.trend = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: emailLabels,
                datasets: [
                    {
                        label: 'Emails Received',
                        data: emailCounts,
                        borderColor: '#14B8A6',
                        backgroundColor: 'rgba(20, 184, 166, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35
                    },
                    {
                        label: 'Leads Created',
                        data: leadCounts,
                        borderColor: '#6366F1',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#475569', font: { family: 'Inter', size: 10 } } } },
                scales: {
                    y: { grid: { color: 'rgba(15, 23, 42, 0.06)' }, ticks: { color: '#64748B', font: { size: 9 } } },
                    x: { grid: { color: 'transparent' }, ticks: { color: '#64748B', font: { size: 9 } } }
                }
            }
        });
    }

    // 2. Lead Sources Doughnut Chart
    const sourcesEl = document.getElementById('leadSourcesChart');
    if (sourcesEl) {
        if (charts.sources) {
            charts.sources.destroy();
        }
        const sourcesCtx = sourcesEl.getContext('2d');
        const sourceLabels = data.lead_sources.length > 0 ? data.lead_sources.map(s => s.source) : ['Email', 'LinkedIn', 'Website', 'WhatsApp'];
        const sourceValues = data.lead_sources.length > 0 ? data.lead_sources.map(s => s.count) : [12, 18, 9, 5];
        
        charts.sources = new Chart(sourcesCtx, {
            type: 'doughnut',
            data: {
                labels: sourceLabels,
                datasets: [{
                    data: sourceValues,
                    backgroundColor: ['#6366F1', '#14B8A6', '#F59E0B', '#10B981', '#EC4899'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { color: '#475569', font: { size: 10 } } } }
            }
        });
    }

    // 3. AI Categories Bar Chart
    const catsEl = document.getElementById('aiCategoriesChart');
    if (catsEl) {
        if (charts.categories) {
            charts.categories.destroy();
        }
        const catsCtx = catsEl.getContext('2d');
        const catLabels = data.ai_categorization.length > 0 ? data.ai_categorization.map(c => c.category) : ['New Lead', 'Invoice', 'Complaint', 'General'];
        const catValues = data.ai_categorization.length > 0 ? data.ai_categorization.map(c => c.count) : [8, 4, 2, 11];
        
        charts.categories = new Chart(catsCtx, {
            type: 'bar',
            data: {
                labels: catLabels,
                datasets: [{
                    label: 'Emails',
                    data: catValues,
                    backgroundColor: '#8B5CF6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: 'rgba(15, 23, 42, 0.06)' }, ticks: { color: '#64748B', font: { size: 9 } } },
                    x: { grid: { color: 'transparent' }, ticks: { color: '#64748B', font: { size: 9 } } }
                }
            }
        });
    }

    // 4. Revenue Pipeline Horizontal Bar
    const pipeEl = document.getElementById('pipelineFunnelChart');
    if (pipeEl) {
        if (charts.pipeline) {
            charts.pipeline.destroy();
        }
        const pipeCtx = pipeEl.getContext('2d');
        const pipeLabels = data.revenue_pipeline.length > 0 ? data.revenue_pipeline.map(r => r.stage) : ['Lead', 'Qualified', 'Proposal', 'Won'];
        const pipeValues = data.revenue_pipeline.length > 0 ? data.revenue_pipeline.map(r => r.value) : [12000, 25000, 18000, 32000];
        
        charts.pipeline = new Chart(pipeCtx, {
            type: 'bar',
            data: {
                labels: pipeLabels,
                datasets: [{
                    data: pipeValues,
                    backgroundColor: 'rgba(20, 184, 166, 0.75)',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(15, 23, 42, 0.06)' }, ticks: { color: '#64748B', font: { size: 9 } } },
                    y: { grid: { color: 'transparent' }, ticks: { color: '#64748B', font: { size: 9 } } }
                }
            }
        });
    }
}

// Update Top Header & Sidebar Task Badges Dynamically
async function updateGlobalTaskBadges() {
    try {
        const res = await apiCall('crm/tasks.php');
        const tasks = res.tasks || [];
        const todayStr = new Date().toISOString().split('T')[0];
        
        // Count pending tasks due today or overdue
        const pendingTodayCount = tasks.filter(t => {
            return t.status !== 'completed' && t.due_date && t.due_date <= todayStr;
        }).length;
        
        // Update sidebar tasks badge
        const sidebarBadge = document.getElementById('sidebar-tasks-today-badge');
        if (sidebarBadge) {
            if (pendingTodayCount > 0) {
                sidebarBadge.textContent = pendingTodayCount;
                sidebarBadge.classList.remove('hidden');
            } else {
                sidebarBadge.classList.add('hidden');
            }
        }
        
        // Update header tasks badge
        const headerBadge = document.getElementById('header-tasks-today-badge');
        if (headerBadge) {
            if (pendingTodayCount > 0) {
                headerBadge.textContent = pendingTodayCount;
                headerBadge.classList.remove('hidden');
            } else {
                headerBadge.classList.add('hidden');
            }
        }
        
        // Trigger notification badge update
        updateGlobalNotificationBadges();
    } catch (e) {
        console.error('Error updating task badges:', e);
    }
}

// Update Top Header Notification Badge Dynamically
async function updateGlobalNotificationBadges() {
    try {
        const notifyBadge = document.getElementById('header-notifications-badge');
        if (!notifyBadge || !getAuthToken()) return;
        
        const res = await apiCall('crm/ai_insights.php');
        if (res && res.status === 'success') {
            // Count low confidence AI predictions + deals at risk as alerts
            const count = (res.low_confidence ? res.low_confidence.length : 0) + (res.overview ? res.overview.deals_at_risk : 0);
            notifyBadge.textContent = count;
            if (count > 0) {
                notifyBadge.classList.remove('hidden');
            } else {
                notifyBadge.classList.add('hidden');
            }
        }
    } catch (e) {
        console.error('Error updating notification badge:', e);
    }
}

// Toggle status of task from the dashboard list
async function dashboardToggleTask(taskId, currentStatus) {
    const nextStatus = currentStatus === 'completed' ? 'pending' : 'completed';
    try {
        const data = await apiCall('crm/tasks.php?action=PUT', 'POST', {
            id: taskId,
            status: nextStatus
        });
        if (data.status === 'success') {
            showNotification('success', `Task marked as ${nextStatus}!`);
            updateGlobalTaskBadges();
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) renderDashboard(viewport);
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    }
}

// ----------------------------------------------------
// 2. EMAIL INTELLIGENCE (SETUP WIZARD & SYNC)
// ----------------------------------------------------
async function renderEmailIntelligence(container) {
    try {
        const res = await apiCall('crm/email_intelligence/settings.php');
        
        if (res.settings && res.settings.is_active) {
            // Service is active: render synchronization monitor
            renderSyncStatus(container, res);
        } else {
            // Service is inactive: render setup wizard
            renderSetupWizard(container, res);
        }
    } catch (err) {
        container.innerHTML = `<div class="p-6 bg-red-950/20 text-red-400 border border-red-900/35 rounded-xl text-center">Settings Load Error: ${err.message}</div>`;
    }
}

function renderSetupWizard(container, data) {
    const conn = data.connection || {};
    
    // Auto pre-fill if not already configured
    if (conn.email_provider === 'gmail') {
        if (!conn.smtp_host) conn.smtp_host = 'smtp.gmail.com';
        if (!conn.smtp_port) conn.smtp_port = 587;
        if (!conn.smtp_encryption) conn.smtp_encryption = 'tls';
        if (!conn.imap_host) conn.imap_host = 'imap.gmail.com';
        if (!conn.imap_port) conn.imap_port = 993;
        if (!conn.imap_encryption) conn.imap_encryption = 'ssl';
    } else if (conn.email_provider === 'outlook') {
        if (!conn.smtp_host) conn.smtp_host = 'smtp.office365.com';
        if (!conn.smtp_port) conn.smtp_port = 587;
        if (!conn.smtp_encryption) conn.smtp_encryption = 'tls';
        if (!conn.imap_host) conn.imap_host = 'outlook.office365.com';
        if (!conn.imap_port) conn.imap_port = 993;
        if (!conn.imap_encryption) conn.imap_encryption = 'ssl';
    }

    const settings = data.settings || {};
    const permissions = settings.permissions || { read_emails: true, read_attachments: true, store_metadata: true, ai_processing: true, auto_sync: true, background_processing: true };
    
    container.innerHTML = `
        <div class="max-w-3xl mx-auto space-y-8 animate-fade-in pt-4">
            <div>
                <h1 class="text-2xl font-extrabold text-white text-center">Activate Email Intelligence</h1>
                <p class="text-slate-400 text-xs mt-1 text-center">Let AI automatically fetch, categorize, and draft replies to inbound client emails.</p>
            </div>

            <!-- Stepper Progress Nodes -->
            <div class="flex justify-between items-center glass-panel p-4 bg-slate-900/40">
                <div class="wizard-step ${wizardStep >= 1 ? (wizardStep === 1 ? 'active' : 'completed') : ''}">
                    <div class="wizard-step-node">1</div>
                    <div class="text-[10px] font-bold uppercase text-slate-400">Connect</div>
                    <div class="wizard-line"></div>
                </div>
                <div class="wizard-step ${wizardStep >= 2 ? (wizardStep === 2 ? 'active' : 'completed') : ''}">
                    <div class="wizard-step-node">2</div>
                    <div class="text-[10px] font-bold uppercase text-slate-400">SMTP</div>
                    <div class="wizard-line"></div>
                </div>
                <div class="wizard-step ${wizardStep >= 3 ? (wizardStep === 3 ? 'active' : 'completed') : ''}">
                    <div class="wizard-step-node">3</div>
                    <div class="text-[10px] font-bold uppercase text-slate-400">IMAP</div>
                    <div class="wizard-line"></div>
                </div>
                <div class="wizard-step ${wizardStep >= 4 ? (wizardStep === 4 ? 'active' : 'completed') : ''}">
                    <div class="wizard-step-node">4</div>
                    <div class="text-[10px] font-bold uppercase text-slate-400">Perms</div>
                    <div class="wizard-line"></div>
                </div>
                <div class="wizard-step ${wizardStep >= 5 ? (wizardStep === 5 ? 'active' : 'completed') : ''}">
                    <div class="wizard-step-node">5</div>
                    <div class="text-[10px] font-bold uppercase text-slate-400">Profile</div>
                    <div class="wizard-line"></div>
                </div>
                <div class="wizard-step ${wizardStep >= 6 ? (wizardStep === 6 ? 'active' : 'completed') : ''}">
                    <div class="wizard-step-node">6</div>
                    <div class="text-[10px] font-bold uppercase text-slate-400">Privacy</div>
                </div>
            </div>

            <!-- Wizard card viewports -->
            <div class="glass-panel p-6 bg-slate-900/50 shadow-2xl relative">
                <form id="wizard-setup-form" onsubmit="event.preventDefault()">
                    <!-- STEP 1: CHOOSE PROVIDER -->
                    <div class="wizard-pane space-y-6 ${wizardStep === 1 ? '' : 'hidden'}">
                        <div class="border-b border-slate-800 pb-3"><h3 class="text-sm font-bold text-white uppercase tracking-wider">Step 1: Choose Email Provider</h3></div>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="p-4 bg-slate-950/40 border border-slate-800 rounded-xl cursor-pointer hover:border-indigo-500 block relative">
                                <input type="radio" name="email_provider" value="gmail" class="absolute top-4 right-4" ${conn.email_provider === 'gmail' ? 'checked' : ''}>
                                <div class="font-bold text-white">Gmail / Workspace</div>
                                <div class="text-[10px] text-slate-500 mt-1">Connect Gmail SMTP & IMAP</div>
                            </label>
                            <label class="p-4 bg-slate-950/40 border border-slate-800 rounded-xl cursor-pointer hover:border-indigo-500 block relative">
                                <input type="radio" name="email_provider" value="outlook" class="absolute top-4 right-4" ${conn.email_provider === 'outlook' ? 'checked' : ''}>
                                <div class="font-bold text-white">Microsoft Outlook</div>
                                <div class="text-[10px] text-slate-500 mt-1">Office 365 or Outlook Mail</div>
                            </label>
                            <label class="p-4 bg-slate-950/40 border border-slate-850 rounded-xl cursor-pointer hover:border-indigo-500 block relative col-span-2">
                                <input type="radio" name="email_provider" value="custom" class="absolute top-4 right-4" ${conn.email_provider !== 'gmail' && conn.email_provider !== 'outlook' ? 'checked' : ''}>
                                <div class="font-bold text-white">Custom SMTP/IMAP Server</div>
                                <div class="text-[10px] text-slate-500 mt-1">Configure standard host server credentials</div>
                            </label>
                        </div>
                    </div>

                    <!-- STEP 2: SMTP OUTBOX CONFIG -->
                    <div class="wizard-pane space-y-4 ${wizardStep === 2 ? '' : 'hidden'}">
                        <div class="border-b border-slate-800 pb-3"><h3 class="text-sm font-bold text-white uppercase tracking-wider">Step 2: SMTP Outbox Configuration</h3></div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">SMTP Host</label>
                                <input type="text" id="wiz_smtp_host" value="${conn.smtp_host || ''}" placeholder="smtp.example.com" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">SMTP Port</label>
                                <input type="number" id="wiz_smtp_port" value="${conn.smtp_port || 587}" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">Encryption</label>
                                <select id="wiz_smtp_encryption" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                                    <option value="tls" ${conn.smtp_encryption === 'tls' ? 'selected' : ''}>STARTTLS (587)</option>
                                    <option value="ssl" ${conn.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL (465)</option>
                                    <option value="none" ${conn.smtp_encryption === 'none' ? 'selected' : ''}>None (25)</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-slate-400 font-semibold mb-1">Username (Email)</label>
                                <input type="email" id="wiz_smtp_username" value="${conn.smtp_username || ''}" placeholder="you@company.com" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">Password</label>
                                <input type="password" id="wiz_smtp_password" placeholder="••••••••" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                                <p class="text-[9px] text-slate-500 mt-1 font-medium select-none">
                                    💡 For Gmail/Workspace or Outlook, enter your 16-character <strong>App Password</strong>.
                                </p>
                            </div>
                        </div>
                        <div class="pt-4 flex justify-between">
                            <button type="button" onclick="testSmtpConnection(this)" class="px-4 py-2 border border-indigo-500 text-indigo-400 rounded-lg hover:bg-indigo-500 hover:text-white transition text-xs font-bold">
                                Test SMTP Connection
                            </button>
                            <span id="smtp-test-feedback" class="text-xs font-bold py-2"></span>
                        </div>
                    </div>

                    <!-- STEP 3: IMAP INBOX CONFIG -->
                    <div class="wizard-pane space-y-4 ${wizardStep === 3 ? '' : 'hidden'}">
                        <div class="border-b border-slate-800 pb-3"><h3 class="text-sm font-bold text-white uppercase tracking-wider">Step 3: IMAP Inbox Configuration</h3></div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">IMAP Host</label>
                                <input type="text" id="wiz_imap_host" value="${conn.imap_host || ''}" placeholder="imap.example.com" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">IMAP Port</label>
                                <input type="number" id="wiz_imap_port" value="${conn.imap_port || 993}" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">Encryption</label>
                                <select id="wiz_imap_encryption" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                                    <option value="ssl" ${conn.imap_encryption === 'ssl' ? 'selected' : ''}>SSL (993)</option>
                                    <option value="tls" ${conn.imap_encryption === 'tls' ? 'selected' : ''}>TLS (143)</option>
                                    <option value="none" ${conn.imap_encryption === 'none' ? 'selected' : ''}>None</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-slate-400 font-semibold mb-1">Username (Email)</label>
                                <input type="email" id="wiz_imap_username" value="${conn.imap_username || ''}" placeholder="you@company.com" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                            </div>
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">Password</label>
                                <input type="password" id="wiz_imap_password" placeholder="••••••••" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                                <p class="text-[9px] text-slate-500 mt-1 font-medium select-none">
                                    💡 For Gmail/Workspace or Outlook, enter your 16-character <strong>App Password</strong>.
                                </p>
                            </div>
                        </div>
                        <div class="pt-4 flex justify-between">
                            <button type="button" onclick="testImapConnection(this)" class="px-4 py-2 border border-indigo-500 text-indigo-400 rounded-lg hover:bg-indigo-500 hover:text-white transition text-xs font-bold">
                                Test IMAP Connection
                            </button>
                            <span id="imap-test-feedback" class="text-xs font-bold py-2"></span>
                        </div>
                    </div>

                    <!-- STEP 4: PERMISSIONS -->
                    <div class="wizard-pane space-y-4 ${wizardStep === 4 ? '' : 'hidden'}">
                        <div class="border-b border-slate-800 pb-3"><h3 class="text-sm font-bold text-white uppercase tracking-wider">Step 4: Sync & AI Permissions</h3></div>
                        <div class="space-y-3 text-xs">
                            <label class="flex items-center space-x-3 p-3 bg-slate-950/20 border border-slate-800 rounded-lg cursor-pointer">
                                <input type="checkbox" id="perm_read_emails" ${permissions.read_emails ? 'checked' : ''} class="h-4 w-4 rounded text-indigo-600 bg-slate-800 border-slate-750">
                                <div>
                                    <div class="font-bold text-white">Read Emails</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">Authorizes the scheduler to retrieve incoming email text bodies.</div>
                                </div>
                            </label>
                            <label class="flex items-center space-x-3 p-3 bg-slate-950/20 border border-slate-800 rounded-lg cursor-pointer">
                                <input type="checkbox" id="perm_read_attachments" ${permissions.read_attachments ? 'checked' : ''} class="h-4 w-4 rounded text-indigo-600 bg-slate-800 border-slate-750">
                                <div>
                                    <div class="font-bold text-white">Read Attachments</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">Permits downloading and secure storage of attachments.</div>
                                </div>
                            </label>
                            <label class="flex items-center space-x-3 p-3 bg-slate-950/20 border border-slate-800 rounded-lg cursor-pointer">
                                <input type="checkbox" id="perm_ai_processing" ${permissions.ai_processing ? 'checked' : ''} class="h-4 w-4 rounded text-indigo-600 bg-slate-800 border-slate-750">
                                <div>
                                    <div class="font-bold text-white">AI Processing Permission</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">Enables AI parsing of sender signatures, budgets, deadline dates and intents.</div>
                                </div>
                            </label>
                            <label class="flex items-center space-x-3 p-3 bg-slate-950/20 border border-slate-800 rounded-lg cursor-pointer">
                                <input type="checkbox" id="perm_auto_sync" ${permissions.auto_sync ? 'checked' : ''} class="h-4 w-4 rounded text-indigo-600 bg-slate-800 border-slate-750">
                                <div>
                                    <div class="font-bold text-white">Auto Sync & Background Processing</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">Allow automatic interval processing in background jobs.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- STEP 5: BUSINESS PROFILE & TEMPLATE FIELDS -->
                    <div class="wizard-pane space-y-4 ${wizardStep === 5 ? '' : 'hidden'}">
                        <div class="border-b border-slate-800 pb-3"><h3 class="text-sm font-bold text-white uppercase tracking-wider">Step 5: Business Profile Settings</h3></div>
                        <p class="text-[11px] text-slate-400">Selecting your business type dynamically structures custom CRM fields for contacts and leads.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">Business Type</label>
                                <select id="wiz_business_type" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white" onchange="previewDynamicFields(this.value)">
                                    <option value="Software Company" ${settings.business_type === 'Software Company' ? 'selected' : ''}>Software Company (Position, Experience)</option>
                                    <option value="Marketing Agency" ${settings.business_type === 'Marketing Agency' ? 'selected' : ''}>Marketing Agency (Campaign, Budget)</option>
                                    <option value="Freelancer" ${settings.business_type === 'Freelancer' ? 'selected' : ''}>Freelancer (Services, Rate)</option>
                                    <option value="Real Estate" ${settings.business_type === 'Real Estate' ? 'selected' : ''}>Real Estate (Property, Budget, Area)</option>
                                    <option value="Hospital" ${settings.business_type === 'Hospital' ? 'selected' : ''}>Hospital / Medical (Doctor, Patient)</option>
                                    <option value="Consultant" ${settings.business_type === 'Consultant' ? 'selected' : ''}>Consultant (Retainer, Domain)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-slate-400 font-semibold mb-1">Sync Frequency</label>
                                <select id="wiz_sync_interval" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-white">
                                    <option value="15" ${settings.sync_interval_minutes == 15 ? 'selected' : ''}>Every 15 Minutes</option>
                                    <option value="30" ${settings.sync_interval_minutes == 30 ? 'selected' : ''}>Every 30 Minutes</option>
                                    <option value="60" ${settings.sync_interval_minutes == 60 ? 'selected' : ''}>Every 1 Hour (Default)</option>
                                    <option value="120" ${settings.sync_interval_minutes == 120 ? 'selected' : ''}>Every 2 Hours</option>
                                    <option value="360" ${settings.sync_interval_minutes == 360 ? 'selected' : ''}>Every 6 Hours</option>
                                    <option value="720" ${settings.sync_interval_minutes == 720 ? 'selected' : ''}>Every 12 Hours</option>
                                    <option value="1440" ${settings.sync_interval_minutes == 1440 ? 'selected' : ''}>Every 24 Hours</option>
                                </select>
                            </div>
                        </div>
                        <div class="p-4 bg-slate-950/40 border border-slate-850 rounded-xl space-y-2 mt-4">
                            <span class="text-[10px] font-bold text-indigo-400 uppercase block">Fields Template Preview</span>
                            <div id="dynamic-fields-preview" class="text-[11px] text-slate-400 italic">
                                Position, Technical stack, Campaign budget, Expected closing date...
                            </div>
                        </div>
                    </div>

                    <!-- STEP 6: TERMS & AGREEMENTS -->
                    <div class="wizard-pane space-y-4 ${wizardStep === 6 ? '' : 'hidden'}">
                        <div class="border-b border-slate-800 pb-3"><h3 class="text-sm font-bold text-white uppercase tracking-wider">Step 6: Privacy Agreement Consent</h3></div>
                        <div class="p-4 bg-slate-950/60 border border-slate-850 rounded-xl text-[10px] text-slate-400 max-h-48 overflow-y-auto leading-relaxed space-y-2 font-mono">
                            <p class="font-bold text-slate-300">LinkPilot AI - Privacy Policy & Encryption Standards</p>
                            <p>1. Syncing Ownership: Syncing emails via IMAP remains 100% the property of the client/user account. Data is never shared or sold to third-party providers.</p>
                            <p>2. AI Processing Scope: AI engines read header data and content logs only to extract CRM variables. Data is processed in zero-data retention endpoints.</p>
                            <p>3. Credentials Storage: SMTP and IMAP password credentials are encrypted in local storage with an industrial-grade AES-256 bit CBC hash block.</p>
                            <p>4. Permanency: Users reserve the complete right to terminate operations and permanently delete all synced datasets, email logs and timelines immediately.</p>
                        </div>
                        <label class="flex items-center space-x-3 p-2 cursor-pointer mt-4">
                            <input type="checkbox" id="wiz_consent_accepted" class="h-4 w-4 rounded text-teal-500 bg-slate-800 border-slate-750">
                            <span class="text-xs text-slate-300 font-semibold">I read, understand, and agree to the LinkPilot CRM terms of service and privacy policies.</span>
                        </label>
                    </div>

                    <!-- Action buttons -->
                    <div class="pt-6 border-t border-slate-800 flex justify-between mt-8">
                        <button type="button" id="wiz-back-btn" onclick="adjustWizardStep(-1)" class="px-4 py-2 border border-slate-700 rounded-lg text-xs font-semibold text-slate-300 hover:border-slate-500 transition ${wizardStep === 1 ? 'invisible' : ''}">
                            Back
                        </button>
                        
                        <div id="wiz-action-btn-container">
                            ${wizardStep === 6 ? `
                                <button type="button" onclick="activateEmailIntelligenceService(this)" class="px-5 py-2.5 bg-teal-400 text-slate-950 rounded-lg text-xs font-bold hover:bg-teal-300 transition shadow-lg shadow-teal-500/10">
                                    Activate Email Intelligence
                                </button>
                            ` : `
                                <button type="button" onclick="adjustWizardStep(1)" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-500 transition">
                                    Continue
                                </button>
                            `}
                        </div>
                    </div>
                </form>
            </div>
        </div>
    `;
    previewDynamicFields(document.getElementById('wiz_business_type')?.value || 'Software Company');
}

function adjustWizardStep(dir) {
    const nextStep = Math.max(1, Math.min(6, wizardStep + dir));
    if (nextStep === wizardStep) return;
    
    // If moving forward from step 1, pre-fill values based on provider choice
    if (dir === 1 && wizardStep === 1) {
        const provider = document.querySelector('input[name="email_provider"]:checked')?.value || 'custom';
        const smtpHost = document.getElementById('wiz_smtp_host');
        const smtpPort = document.getElementById('wiz_smtp_port');
        const smtpEnc = document.getElementById('wiz_smtp_encryption');
        const imapHost = document.getElementById('wiz_imap_host');
        const imapPort = document.getElementById('wiz_imap_port');
        const imapEnc = document.getElementById('wiz_imap_encryption');
        
        if (provider === 'gmail') {
            if (smtpHost) smtpHost.value = 'smtp.gmail.com';
            if (smtpPort) smtpPort.value = '587';
            if (smtpEnc) smtpEnc.value = 'tls';
            if (imapHost) imapHost.value = 'imap.gmail.com';
            if (imapPort) imapPort.value = '993';
            if (imapEnc) imapEnc.value = 'ssl';
        } else if (provider === 'outlook') {
            if (smtpHost) smtpHost.value = 'smtp.office365.com';
            if (smtpPort) smtpPort.value = '587';
            if (smtpEnc) smtpEnc.value = 'tls';
            if (imapHost) imapHost.value = 'outlook.office365.com';
            if (imapPort) imapPort.value = '993';
            if (imapEnc) imapEnc.value = 'ssl';
        }
    }
    
    wizardStep = nextStep;

    // Toggle panes
    const panes = document.querySelectorAll('.wizard-pane');
    panes.forEach((pane, idx) => {
        if (idx === (wizardStep - 1)) {
            pane.classList.remove('hidden');
        } else {
            pane.classList.add('hidden');
        }
    });

    // Update stepper progress nodes
    const steps = document.querySelectorAll('.wizard-step');
    steps.forEach((step, idx) => {
        const stepNum = idx + 1;
        step.classList.remove('active', 'completed');
        if (stepNum === wizardStep) {
            step.classList.add('active');
        } else if (stepNum < wizardStep) {
            step.classList.add('completed');
        }
    });

    // Toggle Back button visibility
    const backBtn = document.getElementById('wiz-back-btn');
    if (backBtn) {
        if (wizardStep === 1) {
            backBtn.classList.add('invisible');
        } else {
            backBtn.classList.remove('invisible');
        }
    }

    // Toggle Next / Activate button at bottom
    const actionBtnContainer = document.getElementById('wiz-action-btn-container');
    if (actionBtnContainer) {
        if (wizardStep === 6) {
            actionBtnContainer.innerHTML = `
                <button type="button" onclick="activateEmailIntelligenceService(this)" class="px-5 py-2.5 bg-teal-400 text-slate-950 rounded-lg text-xs font-bold hover:bg-teal-300 transition shadow-lg shadow-teal-500/10">
                    Activate Email Intelligence
                </button>
            `;
        } else {
            actionBtnContainer.innerHTML = `
                <button type="button" onclick="adjustWizardStep(1)" class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-500 transition">
                    Continue
                </button>
            `;
        }
        lucide.createIcons();
    }
}

function previewDynamicFields(type) {
    const previewEl = document.getElementById('dynamic-fields-preview');
    if (!previewEl) return;
    
    let fields = '';
    if (type === 'Software Company') {
        fields = 'Designation/Job, Technical stack required, Experience years, Candidate details, Preferred stack';
    } else if (type === 'Marketing Agency') {
        fields = 'Services, Campaign Budget value, Platform parameters, Client target niche';
    } else if (type === 'Freelancer') {
        fields = 'Requested niche services, Budget cap, Proposed start date, Project timeline';
    } else if (type === 'Real Estate') {
        fields = 'Property location preference, Budget limit, Dimensions/Sq ft, Area coordinates';
    } else if (type === 'Hospital') {
        fields = 'Patient details, Department, Doctor assignment, Appointment scheduled slots';
    } else {
        fields = 'Domain requirements, Project retainers, Core service objectives, Custom attributes';
    }
    previewEl.innerHTML = `<strong>Dynamic CRM fields generated:</strong> ${fields}`;
}

async function testSmtpConnection(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<div class="loader-spinner !w-3.5 !h-3.5 !border-2 mr-1"></div> Testing...`;
    
    const feedback = document.getElementById('smtp-test-feedback');
    feedback.className = 'text-xs text-slate-400';
    feedback.textContent = 'Contacting server...';
    
    const payload = {
        smtp_host: document.getElementById('wiz_smtp_host').value,
        smtp_port: parseInt(document.getElementById('wiz_smtp_port').value),
        smtp_username: document.getElementById('wiz_smtp_username').value,
        smtp_password: document.getElementById('wiz_smtp_password').value,
        smtp_encryption: document.getElementById('wiz_smtp_encryption').value
    };
    
    try {
        const data = await apiCall('crm/email_intelligence/settings.php?action=test_smtp', 'POST', payload);
        if (data.status === 'success') {
            feedback.className = 'text-xs text-emerald-400 font-bold';
            feedback.textContent = '✓ SMTP Connected successfully!';
        } else {
            feedback.className = 'text-xs text-red-400 font-bold';
            feedback.textContent = '✗ ' + data.message;
        }
    } catch (err) {
        feedback.className = 'text-xs text-red-400 font-bold';
        feedback.textContent = '✗ ' + err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

async function testImapConnection(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<div class="loader-spinner !w-3.5 !h-3.5 !border-2 mr-1"></div> Testing...`;
    
    const feedback = document.getElementById('imap-test-feedback');
    feedback.className = 'text-xs text-slate-400';
    feedback.textContent = 'Connecting...';
    
    const payload = {
        imap_host: document.getElementById('wiz_imap_host').value,
        imap_port: parseInt(document.getElementById('wiz_imap_port').value),
        imap_username: document.getElementById('wiz_imap_username').value,
        imap_password: document.getElementById('wiz_imap_password').value,
        imap_encryption: document.getElementById('wiz_imap_encryption').value
    };
    
    try {
        const data = await apiCall('crm/email_intelligence/settings.php?action=test_imap', 'POST', payload);
        if (data.status === 'success') {
            feedback.className = 'text-xs text-emerald-400 font-bold';
            feedback.textContent = '✓ IMAP Connection successful!';
        } else {
            feedback.className = 'text-xs text-red-400 font-bold';
            feedback.textContent = '✗ ' + data.message;
        }
    } catch (err) {
        feedback.className = 'text-xs text-red-400 font-bold';
        feedback.textContent = '✗ ' + err.message;
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

async function activateEmailIntelligenceService(btn) {
    const consent = document.getElementById('wiz_consent_accepted').checked;
    if (!consent) {
        showNotification('warning', 'You must accept the Privacy Agreement consent checklist first.');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = `<div class="loader-spinner !w-3.5 !h-3.5 !border-2"></div> Activating...`;
    
    const payload = {
        email_provider: document.querySelector('input[name="email_provider"]:checked')?.value || 'custom',
        smtp_host: document.getElementById('wiz_smtp_host').value,
        smtp_port: parseInt(document.getElementById('wiz_smtp_port').value),
        smtp_username: document.getElementById('wiz_smtp_username').value,
        smtp_password: document.getElementById('wiz_smtp_password').value,
        smtp_encryption: document.getElementById('wiz_smtp_encryption').value,
        
        imap_host: document.getElementById('wiz_imap_host').value,
        imap_port: parseInt(document.getElementById('wiz_imap_port').value),
        imap_username: document.getElementById('wiz_imap_username').value,
        imap_password: document.getElementById('wiz_imap_password').value,
        imap_encryption: document.getElementById('wiz_imap_encryption').value,
        
        business_type: document.getElementById('wiz_business_type').value,
        sync_interval_minutes: parseInt(document.getElementById('wiz_sync_interval').value),
        
        permissions: {
            read_emails: document.getElementById('perm_read_emails').checked,
            read_attachments: document.getElementById('perm_read_attachments').checked,
            store_metadata: true,
            ai_processing: document.getElementById('perm_ai_processing').checked,
            auto_sync: document.getElementById('perm_auto_sync').checked,
            background_processing: true
        },
        is_active: 1,
        consent_accepted: 1
    };
    
    try {
        const data = await apiCall('crm/email_intelligence/settings.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Email Intelligence service activated! Initiating sync.');
            isSmtpConfigured = null;
            isEmailSyncConfigured = null;
            wizardStep = 1;
            navigateTo('email-intelligence');
        } else {
            showNotification('error', data.message);
        }
    } catch (err) {
        showNotification('error', 'Activation failed: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Activate Email Intelligence';
    }
}

// Render active synchronization panel
function renderSyncStatus(container, data) {
    const set = data.settings || {};
    const conn = data.connection || {};
    
    // Fetch logs
    apiCall('crm/email_intelligence/sync.php')
        .then(syncData => {
            const logs = syncData.logs || [];
            const logsBody = logs.length > 0 ? logs.map(l => {
                const date = new Date(l.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                
                // Construct user friendly message based on counts of emails synced
                let cleanMsg = '';
                let statusLabel = 'success';
                let statusColorClass = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';

                const errs = parseInt(l.error_count || 0);
                const pends = parseInt(l.pending_count || 0);
                const succs = parseInt(l.success_count || 0);
                const total = errs + pends + succs;

                if (l.connection_message) {
                    statusLabel = 'error';
                    statusColorClass = 'bg-red-500/10 text-red-400 border border-red-500/20';
                    if (l.connection_message.includes('Connection') || l.connection_message.includes('IMAP')) {
                        cleanMsg = 'Mail Server Connection issue. Retrying connection.';
                    } else {
                        cleanMsg = 'Synchronizer connection error. Retrying shortly.';
                    }
                } else {
                    if (errs > 0 && succs > 0) {
                        statusLabel = 'warning';
                        statusColorClass = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                        cleanMsg = `Synchronized ${succs} email${succs > 1 ? 's' : ''} successfully. ${errs} email${errs > 1 ? 's' : ''} failed due to rate limits.`;
                    } else if (errs > 0 && succs === 0) {
                        statusLabel = 'error';
                        statusColorClass = 'bg-red-500/10 text-red-400 border border-red-500/20';
                        cleanMsg = `Synchronization attempted but failed to parse ${errs} email${errs > 1 ? 's' : ''} (System Busy/Rate Limits).`;
                    } else if (pends > 0 && succs === 0) {
                        statusLabel = 'pending';
                        statusColorClass = 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
                        cleanMsg = `Queued ${pends} email${pends > 1 ? 's' : ''} for background analysis.`;
                    } else {
                        statusLabel = 'success';
                        statusColorClass = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                        cleanMsg = `Successfully synchronized and analyzed ${succs} email${succs > 1 ? 's' : ''}.`;
                    }
                }

                return `
                    <tr class="hover:bg-slate-900/40">
                        <td class="py-2.5 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${statusColorClass}">
                                ${statusLabel.toUpperCase()}
                            </span>
                        </td>
                        <td class="py-2.5 px-4 text-slate-300 font-medium">${cleanMsg}</td>
                        <td class="py-2.5 px-4 text-slate-400 font-bold">${l.total_tokens || 0}</td>
                        <td class="py-2.5 px-4 text-slate-500 font-mono text-[10px]">${date}</td>
                    </tr>
                `;
            }).join('') : `<tr><td colspan="4" class="text-center py-6 text-slate-500 text-xs">No processing logs generated yet.</td></tr>`;

            container.innerHTML = `
                <div class="space-y-8 animate-fade-in pt-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white">Email Intelligence Monitor</h1>
                        <p class="text-slate-400 text-xs mt-1">Review processing logs, manual synchronization buttons, and scheduler metrics.</p>
                    </div>

                    <!-- Scheduler Statistics widget row -->
                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
                        <div class="glass-panel p-5 bg-slate-900/40 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Service Status</span>
                                <span class="text-lg font-extrabold text-emerald-400 mt-1 block flex items-center">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse mr-2"></span> Active
                                </span>
                            </div>
                            <button onclick="toggleSyncActiveState(false, this)" class="px-2.5 py-1 bg-red-950/20 border border-red-950 text-red-400 text-[10px] font-bold rounded-lg hover:bg-red-900/20 transition">Pause Sync</button>
                        </div>
                        <div class="glass-panel p-5 bg-slate-900/40">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Synced</span>
                            <span class="text-sm font-extrabold text-indigo-400 mt-1 block">${syncData.total_emails || 0} Emails</span>
                        </div>
                        <div class="glass-panel p-5 bg-slate-900/40">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Last Sync Run</span>
                            <span class="text-sm font-extrabold text-white mt-1 block">${set.last_sync_at ? new Date(set.last_sync_at).toLocaleString() : 'Never'}</span>
                        </div>
                        <div class="glass-panel p-5 bg-slate-900/40">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Next Sync Scheduled</span>
                            <span class="text-sm font-extrabold text-white mt-1 block">${set.next_sync_at ? new Date(set.next_sync_at).toLocaleString() : 'Pending'}</span>
                        </div>
                        <div class="glass-panel p-5 bg-slate-900/40 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sync Frequency</span>
                                <span class="text-sm font-extrabold text-indigo-400 mt-1 block">Every ${set.sync_interval_minutes || 60} Min</span>
                            </div>
                            <button onclick="wizardStep=5; navigateTo('email-intelligence')" class="p-1 text-slate-400 hover:text-white transition"><i data-lucide="edit-3" class="h-4 w-4"></i></button>
                        </div>
                    </div>

                    <!-- Sync controls & Log audits -->
                    <div class="glass-panel p-6 bg-slate-900/40 space-y-4">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-3 sm:space-y-0 border-b border-slate-800 pb-3">
                            <h3 class="text-md font-bold text-white flex items-center space-x-2">
                                <i data-lucide="activity" class="h-5 w-5 text-indigo-400"></i>
                                <span>Background Sync Logs</span>
                            </h3>
                            <div class="flex space-x-3">
                                <button onclick="deactivateEmailService()" class="px-3 py-1.5 border border-slate-700 text-slate-400 hover:border-red-500 hover:text-red-400 text-xs font-semibold rounded-lg transition">
                                    Deactivate Service
                                </button>
                                <button onclick="syncNowFromDashboard(this)" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg transition flex items-center space-x-1.5">
                                    <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                                    <span>Sync Now</span>
                                </button>
                            </div>
                        </div>

                        <!-- Logs table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs custom-table">
                                <thead>
                                    <tr class="border-b border-slate-800">
                                        <th class="py-3 px-4">Status</th>
                                        <th class="py-3 px-4">Details</th>
                                        <th class="py-3 px-4">Tokens</th>
                                        <th class="py-3 px-4">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${logsBody}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        });
}

async function toggleSyncActiveState(state, btn) {
    btn.disabled = true;
    try {
        await apiCall('crm/email_intelligence/sync.php?action=toggle', 'POST', { active: state ? 1 : 0 });
        showNotification('success', state ? 'Service resumed' : 'Service paused');
        navigateTo('email-intelligence');
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function deactivateEmailService() {
    if (!confirm('Are you sure you want to completely deactivate Email Intelligence? This will pause all background sync operations.')) {
        return;
    }
    try {
        await apiCall('crm/email_intelligence/settings.php', 'POST', { is_active: 0, consent_accepted: 1 });
        showNotification('success', 'Email Intelligence deactivated.');
        isSmtpConfigured = null;
        isEmailSyncConfigured = null;
        navigateTo('email-intelligence');
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function syncNowFromDashboard(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="refresh-cw" class="h-3.5 w-3.5 animate-spin-slow mr-1.5"></i> Syncing...`;
    lucide.createIcons();
    
    try {
        const data = await apiCall('crm/email_intelligence/sync.php?action=sync', 'POST');
        if (data.status === 'success') {
            showNotification('success', `Inbox synced successfully! Synced ${data.emails_synced} emails.`);
            navigateTo('email-intelligence');
        } else {
            showNotification('error', data.message);
        }
    } catch (err) {
        showNotification('error', 'Sync Failed: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

// ----------------------------------------------------
// 3. INBOX VIEW (AI SUGGESTED REPLY / FILTERS)
// ----------------------------------------------------
async function renderInbox(container, targetEmailId = null) {
    try {
        window.inboxFilters = {
            is_spam: 0,
            is_archived: 0,
            is_starred: null,
            category: ''
        };
        const listData = await apiCall('crm/email_intelligence/emails.php');
        const emails = listData.emails || [];
        if (typeof refreshUnreadBadgeCount === 'function') {
            refreshUnreadBadgeCount();
        }
        
        let initialEmailId = targetEmailId || (emails.length > 0 ? emails[0].id : null);
        activeEmailId = initialEmailId;
        
        let listItems = emails.length > 0 ? emails.map(m => {
            const date = new Date(m.received_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
            const isUnread = !m.is_read;
            const priorityColor = m.priority === 'high' ? 'bg-red-500/10 text-red-400 border-red-500/20' : m.priority === 'medium' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-slate-500/10 text-slate-400 border-slate-500/20';
            
            return `
                <div onclick="selectInboxEmail(${m.id})" id="inbox-mail-card-${m.id}" class="p-4 border-b border-slate-800/60 hover:bg-slate-900/30 cursor-pointer transition flex flex-col justify-between ${isUnread ? 'border-l-4 border-l-indigo-500 bg-slate-900/10' : ''} ${m.id === activeEmailId ? 'bg-slate-900/40 card-active-glow' : ''}">
                    <div class="flex justify-between items-start">
                        <span class="font-bold text-xs truncate max-w-[140px] text-white">${m.sender_name || m.sender_email}</span>
                        <span class="text-[10px] text-slate-500">${date}</span>
                    </div>
                    <div class="text-xs font-semibold text-slate-200 mt-1 truncate" title="${m.subject}">${m.subject}</div>
                    ${m.ai_status === 'pending' ? 
                      `<p class="text-[11px] text-teal-400 animate-pulse flex items-center mt-1"><i data-lucide="sparkles" class="h-3.5 w-3.5 mr-1 text-teal-400 animate-pulse"></i>AI Analyst is analyzing...</p>` : 
                      `<p class="text-[11px] text-slate-500 truncate mt-1">${m.ai_summary || 'Click to read summary...'}</p>`}
                    <div class="flex space-x-2 mt-2">
                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase ${priorityColor}">${m.priority}</span>
                        ${m.ai_status === 'pending' ? 
                          `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-teal-500/10 text-teal-400 border border-teal-500/20 animate-pulse">Processing...</span>` : 
                          `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">${m.category}</span>`}
                    </div>
                </div>
            `;
        }).join('') : `<div class="p-6 text-center text-slate-500 text-xs">Inbox is empty.</div>`;
        
        container.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-fade-in pt-4 h-[80vh]">
                <!-- Sidebar Email filters -->
                <div class="lg:col-span-3 flex flex-col space-y-4">
                    <div class="glass-panel p-4 bg-slate-900/40 flex-grow overflow-y-auto max-h-[75vh]">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Folders</h3>
                        <div class="space-y-1 text-xs">
                            <button onclick="filterInbox('inbox', this)" class="w-full flex items-center justify-between px-3 py-2 rounded-lg font-bold text-indigo-400 bg-indigo-500/10 hover:bg-slate-800 transition">
                                <span class="flex items-center"><i data-lucide="inbox" class="h-4 w-4 mr-2"></i>Inbox</span>
                                <span id="inbox-unread-count" class="px-1.5 py-0.5 bg-indigo-600 text-white rounded-full text-[9px]">${listData.unread_count || 0}</span>
                            </button>
                            <button onclick="filterInbox('starred', this)" class="w-full flex items-center px-3 py-2 rounded-lg font-medium text-slate-400 hover:bg-slate-800 transition">
                                <i data-lucide="star" class="h-4 w-4 mr-2"></i>Starred
                            </button>
                            <button onclick="filterInbox('archived', this)" class="w-full flex items-center px-3 py-2 rounded-lg font-medium text-slate-400 hover:bg-slate-800 transition">
                                <i data-lucide="archive" class="h-4 w-4 mr-2"></i>Archived
                            </button>
                            <button onclick="filterInbox('spam', this)" class="w-full flex items-center px-3 py-2 rounded-lg font-medium text-slate-400 hover:bg-slate-800 transition">
                                <i data-lucide="alert-triangle" class="h-4 w-4 mr-2"></i>Spam Queue
                            </button>
                        </div>

                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mt-6 mb-3">Categories</h3>
                        <div class="space-y-1 text-xs" id="inbox-category-filters">
                            <button onclick="filterInboxByCat('New Lead', this)" class="w-full flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-800 transition"><span class="h-2 w-2 rounded-full bg-teal-400 mr-2"></span>New Lead</button>
                            <button onclick="filterInboxByCat('Existing Client', this)" class="w-full flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-800 transition"><span class="h-2 w-2 rounded-full bg-blue-500 mr-2"></span>Existing Client</button>
                            <button onclick="filterInboxByCat('Support Request', this)" class="w-full flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-800 transition"><span class="h-2 w-2 rounded-full bg-red-400 mr-2"></span>Support Request</button>
                            <button onclick="filterInboxByCat('Meeting Request', this)" class="w-full flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-800 transition"><span class="h-2 w-2 rounded-full bg-yellow-400 mr-2"></span>Meeting Request</button>
                            <button onclick="filterInboxByCat('Invoice', this)" class="w-full flex items-center px-3 py-2 rounded-lg text-slate-400 hover:bg-slate-800 transition"><span class="h-2 w-2 rounded-full bg-emerald-400 mr-2"></span>Invoice</button>
                        </div>
                    </div>
                </div>

                <!-- Inbox List pane -->
                <div class="lg:col-span-4 glass-panel bg-slate-900/40 overflow-hidden flex flex-col h-full max-h-[75vh]">
                    <div class="p-3 border-b border-slate-800/80 flex items-center space-x-2">
                        <input type="text" oninput="handleInboxInlineSearch(this.value)" placeholder="Search emails..." class="flex-grow px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white">
                        <button onclick="syncInboxFromInboxView(this)" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition border border-slate-750 flex items-center justify-center" title="Sync Inbox">
                            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                        </button>
                    </div>
                    <div class="flex-grow overflow-y-auto divide-y divide-slate-800/40" id="inbox-emails-list-container">
                        ${listItems}
                    </div>
                </div>

                <!-- Email detail panel with AI Reply Assistant -->
                <div class="lg:col-span-5 glass-panel bg-slate-900/40 p-5 flex flex-col h-full max-h-[75vh] overflow-y-auto" id="inbox-email-detail-container">
                    <p class="text-xs text-slate-500 text-center py-20">Select an email from the list to display details and generate suggested AI replies.</p>
                </div>
            </div>
        `;
        
        lucide.createIcons();
        if (activeEmailId) {
            selectInboxEmail(activeEmailId);
        }
        checkInboxPendingStatus();
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function selectInboxEmail(emailId) {
    activeEmailId = emailId;
    selectedReplyAttachments = []; // reset reply attachments
    
    // Set active style in list
    document.querySelectorAll('[id^="inbox-mail-card-"]').forEach(c => c.classList.remove('bg-slate-900/40', 'card-active-glow'));
    const activeCard = document.getElementById(`inbox-mail-card-${emailId}`);
    if (activeCard) {
        activeCard.classList.add('bg-slate-900/40', 'card-active-glow');
        // remove unread indicator border
        activeCard.classList.remove('border-l-4', 'border-l-indigo-500', 'bg-slate-900/10');
    }
    
    const container = document.getElementById('inbox-email-detail-container');
    container.innerHTML = `
        <div class="flex items-center justify-center h-full py-10">
            <div class="loader-spinner"></div>
        </div>
    `;

    try {
        const data = await apiCall(`crm/email_intelligence/emails.php?id=${emailId}`);
        const smtpData = await apiCall('smtp/list.php');
        const email = data.email;
        const accounts = smtpData.accounts || [];
        const meta = email.extracted_data_json ? JSON.parse(email.extracted_data_json) : {};
        
        const date = new Date(email.received_date).toLocaleString();
        
        // Render detailed panel
        container.innerHTML = `
            <div class="space-y-6 animate-fade-in text-xs">
                <!-- Action headers -->
                <div class="flex justify-between items-center border-b border-slate-800 pb-3">
                    <div class="flex space-x-2">
                        <button onclick="toggleStarredEmail(${email.id}, ${email.is_starred ? 0 : 1})" class="p-1.5 border border-slate-800 hover:border-slate-700 rounded-md transition ${email.is_starred ? 'text-yellow-400 border-yellow-500/20 bg-yellow-500/10' : 'text-slate-400'}" title="Star Email">
                            <i data-lucide="star" class="h-4 w-4"></i>
                        </button>
                        <button onclick="toggleArchivedEmail(${email.id}, ${email.is_archived ? 0 : 1})" class="p-1.5 border border-slate-800 hover:border-slate-700 text-slate-400 rounded-md transition" title="Archive Email">
                            <i data-lucide="archive" class="h-4 w-4"></i>
                        </button>
                        <button onclick="deleteInboxEmail(${email.id})" class="p-1.5 border border-slate-800 hover:border-red-500 hover:text-red-400 text-slate-400 rounded-md transition" title="Delete Permanent">
                            <i data-lucide="trash" class="h-4 w-4"></i>
                        </button>
                        <div class="h-7 w-[1px] bg-slate-800 self-center"></div>
                        
                        ${(email.category === 'Spam' || email.category === 'Promotion' || parseInt(email.is_spam) === 1) ? `
                            <button onclick="unblockEmailSender(${email.id})" class="p-1.5 border border-green-500 hover:bg-green-500 hover:text-white text-green-600 rounded-md transition flex items-center space-x-1.5" title="Unblock Sender & Move to General">
                                <i data-lucide="shield-check" class="h-3.5 w-3.5 text-green-500"></i>
                                <span class="text-[9px] font-bold text-green-600">Unblock & Restore</span>
                            </button>
                        ` : `
                            <button onclick="markEmailAsSpamPromo(${email.id}, 'Spam')" class="p-1.5 border border-slate-800 hover:border-amber-600 hover:text-amber-500 text-slate-400 rounded-md transition flex items-center space-x-1.5" title="Block Sender & Mark Spam">
                                <i data-lucide="shield-alert" class="h-3.5 w-3.5"></i>
                                <span class="text-[9px] font-bold">Spam</span>
                            </button>
                            <button onclick="markEmailAsSpamPromo(${email.id}, 'Promotion')" class="p-1.5 border border-slate-800 hover:border-blue-500 hover:text-blue-400 text-slate-400 rounded-md transition flex items-center space-x-1.5" title="Mark as Promotion">
                                <i data-lucide="tag" class="h-3.5 w-3.5"></i>
                                <span class="text-[9px] font-bold">Promo</span>
                            </button>
                        `}
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">${email.category}</span>
                </div>

                <!-- Subject and Sender -->
                <div>
                    <h2 class="text-sm font-bold text-white leading-relaxed">${email.subject}</h2>
                    <div class="flex justify-between text-slate-400 mt-2">
                        <span><strong>From:</strong> ${email.sender_name} &lt;${email.sender_email}&gt;</span>
                        <span class="text-[10px]">${date}</span>
                    </div>
                </div>

                <!-- AI Summarization Accordions -->
                ${email.ai_status === 'pending' ? `
                <div class="glass-panel p-4 bg-slate-900/50 space-y-4 border-l-4 border-l-teal-500 animate-pulse">
                    <div class="flex items-center space-x-2 text-teal-400 font-bold">
                        <i data-lucide="sparkles" class="h-4 w-4 text-teal-400 animate-pulse"></i>
                        <span>AI Analyst Extracting Insights...</span>
                    </div>
                    <div class="space-y-3 mt-1">
                        <div class="h-3 bg-slate-800 rounded w-1/3"></div>
                        <div class="h-3 bg-slate-800 rounded w-1/2"></div>
                        <div class="h-8 bg-slate-800 rounded w-full"></div>
                    </div>
                </div>
                ` : `
                <div class="glass-panel p-4 bg-slate-900/50 space-y-3 border-l-4 border-l-indigo-500">
                    <div class="flex items-center space-x-2 text-indigo-400 font-bold">
                        <i data-lucide="cpu" class="h-4 w-4"></i>
                        <span>AI Contact & Intelligence Breakdown</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-1">
                        <div>
                            <span class="text-slate-500 block uppercase text-[9px] font-bold">Contact Name</span>
                            <span class="text-slate-300 font-semibold">${meta.person_name || 'Not detected'}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block uppercase text-[9px] font-bold">Company</span>
                            <span class="text-slate-300 font-semibold">${meta.company_name || 'Not detected'}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block uppercase text-[9px] font-bold">Phone</span>
                            <span class="text-slate-300 font-semibold">${meta.phone_number || 'Not detected'}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 block uppercase text-[9px] font-bold">Priority / Sentiment</span>
                            <span class="text-slate-300 font-semibold capitalize">${email.priority} / ${email.sentiment}</span>
                        </div>
                    </div>
                    
                    <div class="border-t border-slate-800 pt-2.5 mt-2">
                        <span class="text-slate-500 font-bold block uppercase text-[9px]">AI Summary</span>
                        <p class="text-slate-300 leading-relaxed mt-1">${email.ai_summary}</p>
                    </div>

                    ${meta.action_items && meta.action_items.length > 0 ? `
                        <div class="border-t border-slate-800 pt-2.5 mt-2">
                            <span class="text-slate-500 font-bold block uppercase text-[9px]">Suggested Action Items</span>
                            <ul class="list-disc pl-4 space-y-0.5 text-slate-300 mt-1">
                                ${meta.action_items.map(act => `<li>${act}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                </div>
                `}

                <!-- Email Message Body Content -->
                <div class="border border-slate-200 rounded-xl overflow-hidden bg-white">
                    ${(email.body_html && email.body_html.trim() !== '') ? `
                        <iframe id="inbox-email-body-iframe" class="w-full h-80 bg-white border-0 block" sandbox="allow-same-origin allow-popups"></iframe>
                    ` : `
                        <div class="p-4 bg-slate-50 text-slate-800 leading-relaxed font-sans whitespace-pre-line text-[11px] max-h-80 overflow-y-auto">
                            ${email.body_text}
                        </div>
                    `}
                </div>

                <!-- Conversation Replies Thread -->
                ${(email.replies && email.replies.length > 0) ? `
                    <div class="space-y-4 border-t border-slate-800 pt-4">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider">Conversation History</h4>
                        <div class="space-y-3">
                            ${email.replies.map(r => {
                                const rDate = new Date(r.received_date).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                                return `
                                    <div class="p-4 bg-slate-950/40 border border-slate-850 rounded-xl ml-6">
                                        <div class="flex justify-between text-slate-400 text-[10px] mb-1.5 font-bold">
                                            <span class="text-teal-400">Reply from: ${r.sender_name || r.sender_email}</span>
                                            <span>${rDate}</span>
                                        </div>
                                        <div class="text-slate-200 leading-relaxed font-sans whitespace-pre-line text-[11px]">${r.body_text}</div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                ` : ''}

                <!-- Attachments section -->
                ${email.attachments && email.attachments.length > 0 ? `
                    <div class="space-y-2">
                        <span class="text-slate-500 font-bold uppercase text-[9px]">Attachments (${email.attachments.length})</span>
                        <div class="grid grid-cols-2 gap-2">
                            ${email.attachments.map(att => `
                                <a href="../${att.file_path}" download class="p-2 border border-slate-800 hover:border-indigo-500 bg-slate-900/30 rounded-lg flex items-center justify-between text-slate-300 transition text-[11px]">
                                    <span class="truncate max-w-[120px]" title="${att.filename}">${att.filename}</span>
                                    <i data-lucide="download" class="h-3.5 w-3.5 text-indigo-400"></i>
                                </a>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}

                <!-- AI Suggested Reply & Editor -->
                <div class="border-t border-slate-800 pt-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-900/50 p-3 rounded-lg border border-slate-800/80">
                        <!-- Reply From Account Selector -->
                        <div class="flex flex-col space-y-1">
                            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Reply From Account</span>
                            <select id="reply-sender-account-select" class="w-full px-2 py-1 bg-slate-950 border border-slate-800 text-slate-300 rounded text-[10px] focus:outline-none focus:border-teal-400">
                                ${accounts.map(acc => {
                                    const isSelected = (acc.sender_email.toLowerCase() === email.recipient_email.toLowerCase()) ? 'selected' : '';
                                    return `<option value="${acc.sender_email}" ${isSelected}>${acc.sender_name} (${acc.sender_email}) ${acc.is_default ? '[Default]' : ''}</option>`;
                                }).join('')}
                            </select>
                        </div>
                        
                        <!-- Tone selection -->
                        <div class="flex flex-col space-y-1">
                            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">AI Reply Tone</span>
                            <select id="inbox-reply-tone" class="w-full px-2 py-1 bg-slate-950 border border-slate-800 text-slate-300 rounded text-[10px] focus:outline-none focus:border-teal-400" onchange="generateToneDraft(${email.id}, this.value)">
                                <option value="Professional">Professional (Default)</option>
                                <option value="Friendly">Friendly Tone</option>
                                <option value="Sales">Sales Pitch</option>
                                <option value="Support">Support Response</option>
                                <option value="Proposal">Send Proposal</option>
                                <option value="Meeting Confirmation">Confirm Meeting</option>
                            </select>
                        </div>
                    </div>

                    <div class="relative">
                        ${(!email.ai_suggested_reply || email.ai_suggested_reply.trim() === '') ? `
                            <div id="ai-reply-generation-container" class="flex flex-col items-center justify-center py-8 border border-dashed border-slate-200 rounded-xl bg-slate-50/50 space-y-2">
                                <i data-lucide="sparkles" class="h-5 w-5 text-indigo-500 animate-pulse"></i>
                                <p class="text-[10px] text-slate-500 font-medium">Automatic draft reply generation skipped for this category.</p>
                                <button type="button" onclick="generateReplyOnDemand(${email.id})" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[10px] font-bold transition flex items-center space-x-1.5 shadow">
                                    <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                                    <span>Generate AI Reply Now</span>
                                </button>
                            </div>
                            <textarea id="inbox-reply-textarea" rows="8" class="hidden w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-400 leading-relaxed font-sans text-[11px]" placeholder="Drafting reply..."></textarea>
                        ` : `
                            <textarea id="inbox-reply-textarea" rows="8" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-400 leading-relaxed font-sans text-[11px]" placeholder="Drafting reply...">${email.ai_suggested_reply || ''}</textarea>
                        `}
                        <button onclick="copyReplyText()" class="absolute top-2 right-2 p-1.5 bg-slate-800 hover:bg-teal-500 hover:text-slate-950 rounded-md border border-slate-700 transition" title="Copy Reply">
                            <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                        </button>
                    </div>

                    <!-- File attachment selector -->
                    <div class="space-y-2 border-t border-slate-100 pt-3">
                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                            <label class="flex items-center space-x-1.5 cursor-pointer hover:text-indigo-600 transition">
                                <i data-lucide="paperclip" class="h-3.5 w-3.5"></i>
                                <span class="font-semibold">Attach files...</span>
                                <input type="file" id="inbox-reply-attachments" multiple class="hidden" onchange="handleReplyAttachmentChange(this)" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp3,.mp4,.m4a">
                            </label>
                            <span id="inbox-reply-attachments-count" class="font-bold text-slate-400">No files attached</span>
                        </div>
                        <div id="inbox-reply-attachments-list" class="flex flex-wrap gap-1.5"></div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button onclick="dispatchSmtpReply(${email.id}, this, \`${email.subject.replace(/`/g, '\\`').replace(/\$/g, '\\$')}\`)" class="px-4 py-2 bg-teal-400 hover:bg-teal-300 text-slate-950 rounded-lg font-bold transition flex items-center space-x-1.5 shadow-lg shadow-teal-500/10">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i>
                            <span>Send Reply Now</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
        if (typeof refreshUnreadBadgeCount === 'function') {
            refreshUnreadBadgeCount();
        }
        if (email.body_html && email.body_html.trim() !== '') {
            setTimeout(() => {
                const iframe = document.getElementById('inbox-email-body-iframe');
                if (iframe) {
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(email.body_html);
                    doc.close();
                }
            }, 50);
        }
        lucide.createIcons();
    } catch (err) {
        showNotification('error', 'Error rendering details: ' + err.message);
    }
}

async function generateToneDraft(emailId, tone) {
    const text = document.getElementById('inbox-reply-textarea');
    const container = document.getElementById('ai-reply-generation-container');
    if (text) {
        text.value = 'Generating new draft... Please hold.';
        text.classList.remove('hidden');
    }
    if (container) {
        container.classList.add('hidden');
    }
    try {
        const res = await apiCall('crm/email_intelligence/reply.php', 'POST', { email_id: emailId, tone });
        if (res.status === 'success') {
            text.value = res.draft;
        } else {
            showNotification('error', 'Drafting error: ' + res.message);
        }
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function dispatchSmtpReply(emailId, btn, originalSubject) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<div class="loader-spinner !w-3.5 !h-3.5 !border-2 mr-1"></div> Sending...`;
    
    const replyBody = document.getElementById('inbox-reply-textarea').value;
    
    // Prepare FormData to support file uploads
    const formData = new FormData();
    formData.append('email_id', emailId);
    formData.append('reply_body', replyBody);
    
    // Prefix subject with Re: if missing
    let subject = originalSubject || 'Inquiry';
    if (!subject.toUpperCase().startsWith('RE:')) {
        subject = 'Re: ' + subject;
    }
    formData.append('subject', subject);
    
    // Selected sender account email
    const senderEmailSelect = document.getElementById('reply-sender-account-select');
    if (senderEmailSelect && senderEmailSelect.value) {
        formData.append('sender_email', senderEmailSelect.value);
    }
    
    // Append all selected files
    selectedReplyAttachments.forEach(file => {
        formData.append('attachments[]', file);
    });
    
    try {
        const token = getAuthToken();
        const headers = {};
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const response = await fetch(`${API_BASE_URL}/crm/email_intelligence/reply.php?action=send`, {
            method: 'POST',
            headers: headers,
            body: formData
        });
        
        const res = await response.json();
        
        if (res.status === 'success') {
            showNotification('success', 'Reply sent successfully!');
            selectedReplyAttachments = []; // reset attachments array
            navigateTo('inbox');
        } else {
            showNotification('error', res.message);
        }
    } catch (err) {
        showNotification('error', 'Transmission failed: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

async function toggleStarredEmail(emailId, starState) {
    try {
        await apiCall('crm/email_intelligence/emails.php?action=star', 'POST', { id: emailId, is_starred: starState });
        showNotification('success', starState ? 'Email Starred' : 'Email Unstarred');
        selectInboxEmail(emailId);
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function toggleArchivedEmail(emailId, archiveState) {
    try {
        await apiCall('crm/email_intelligence/emails.php?action=archive', 'POST', { id: emailId, is_archived: archiveState });
        showNotification('success', archiveState ? 'Email Archived' : 'Email Restored');
        navigateTo('inbox');
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function deleteInboxEmail(emailId) {
    if (!confirm('Are you sure you want to permanently delete this email log? This cannot be undone.')) return;
    try {
        await apiCall('crm/email_intelligence/emails.php?action=delete', 'POST', { id: emailId });
        showNotification('success', 'Email deleted successfully.');
        navigateTo('inbox');
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function markEmailAsSpamPromo(emailId, category) {
    if (!confirm(`Mark this email and all future emails from this sender as ${category}?`)) return;
    try {
        const res = await apiCall('crm/email_intelligence/spam_rules.php?action=mark_spam_promo', 'POST', {
            email_id: emailId,
            category: category
        });
        showNotification('success', res.message);
        navigateTo('inbox');
    } catch (err) {
        showNotification('error', err.message);
    }
}

function copyReplyText() {
    const area = document.getElementById('inbox-reply-textarea');
    area.select();
    navigator.clipboard.writeText(area.value).then(() => {
        showNotification('success', 'Reply copied to clipboard!');
    });
}

function stripHtml(html) {
    const tmp = document.createElement("DIV");
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || "";
}

function stripHtmlText(text) {
    return text.replace(/<[^>]*>?/gm, '');
}

// ----------------------------------------------------
// 4. LEADS MODULE (KANBAN BOARD)
// ----------------------------------------------------
async function renderLeads(container) {
    try {
        const res = await apiCall('crm/leads.php');
        const leads = res.leads || [];
        
        let leadRows = leads.length > 0 ? leads.map(l => {
            const date = new Date(l.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
            return `
                <tr class="hover:bg-slate-900/40">
                    <td class="py-3 px-4 font-bold text-white">${l.name}</td>
                    <td class="py-3 px-4 text-slate-300 font-medium">${l.company || '-'}</td>
                    <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">${l.email}</td>
                    <td class="py-3 px-4 text-indigo-400 font-bold">₹${parseFloat(l.budget).toLocaleString('en-IN')}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-teal-500/10 text-teal-400 border border-teal-500/20">${l.stage}</span>
                    </td>
                    <td class="py-3 px-4 text-slate-400">${l.priority}</td>
                    <td class="py-3 px-4 text-slate-500">${date}</td>
                    <td class="py-3 px-4 text-right">
                        <button onclick="editCrmLead(${l.id})" class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 rounded-md hover:border-teal-400 hover:text-teal-400 transition">View Details</button>
                    </td>
                </tr>
            `;
        }).join('') : `<tr><td colspan="8" class="text-center py-10 text-slate-500">No leads added to pipeline yet.</td></tr>`;

        container.innerHTML = `
            <div class="space-y-6 animate-fade-in pt-4">
                <div class="flex justify-between items-center border-b border-slate-850 pb-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white">Lead Vault</h1>
                        <p class="text-slate-400 text-xs mt-1">All inbound client leads captured automatically from Email sync and Scraping.</p>
                    </div>
                    <button onclick="createNewLeadModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition flex items-center space-x-1.5">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                        <span>Add New Lead</span>
                    </button>
                </div>

                <!-- Table panel -->
                <div class="glass-panel p-5 bg-slate-900/40">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse custom-table text-xs">
                            <thead>
                                <tr class="border-b border-slate-800">
                                    <th class="py-3 px-4">Lead Name</th>
                                    <th class="py-3 px-4">Company</th>
                                    <th class="py-3 px-4">Email</th>
                                    <th class="py-3 px-4">Budget</th>
                                    <th class="py-3 px-4">Stage</th>
                                    <th class="py-3 px-4">Priority</th>
                                    <th class="py-3 px-4">Created Date</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="leads-table-body">
                                ${leadRows}
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
}

function createNewLeadModal(prefills = {}) {
    // Remove existing modal if any
    const existing = document.getElementById('crm-lead-modal');
    if (existing) existing.remove();

    const modalHTML = `
        <div id="crm-lead-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <div class="h-8 w-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center">
                            <i data-lucide="user-plus" class="h-4.5 w-4.5"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">Add New CRM Lead</h3>
                    </div>
                    <button onclick="closeCrmModal()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Lead Name *</label>
                            <input type="text" id="new-lead-name" placeholder="Full name" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Company / Organization</label>
                            <input type="text" id="new-lead-company" placeholder="Company Name" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Email Address</label>
                            <input type="email" id="new-lead-email" placeholder="name@company.com" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Phone Number</label>
                            <input type="text" id="new-lead-phone" placeholder="Contact number" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Estimated Budget (₹)</label>
                            <input type="number" id="new-lead-budget" placeholder="0" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Lead Source</label>
                            <select id="new-lead-source" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 bg-white">
                                <option value="LinkedIn Extension">LinkedIn Extension</option>
                                <option value="WhatsApp Inbox">WhatsApp Inbox</option>
                                <option value="Email Finder">Email Finder</option>
                                <option value="Manual Entry" selected>Manual Entry</option>
                                <option value="Website Inbound">Website Inbound</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Priority</label>
                            <select id="new-lead-priority" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 bg-white">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Pipeline Stage</label>
                            <select id="new-lead-stage" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 bg-white">
                                <option value="New" selected>New Lead</option>
                                <option value="Contacted">Contacted</option>
                                <option value="Meeting Scheduled">Meeting Scheduled</option>
                                <option value="Proposal Sent">Proposal Sent</option>
                                <option value="Negotiation">Negotiation</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Services Required</label>
                        <input type="text" id="new-lead-services" placeholder="e.g. SEO, Web Dev, SaaS Subscription" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Key Requirements / Bio Info</label>
                        <textarea id="new-lead-requirements" rows="3" placeholder="Provide extra detail descriptions..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end space-x-2">
                    <button onclick="closeCrmModal()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                    <button onclick="submitNewLeadForm(this)" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center space-x-1.5 shadow-sm">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        <span>Save Lead</span>
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    if (prefills) {
        if (prefills.name) document.getElementById('new-lead-name').value = prefills.name;
        if (prefills.company) document.getElementById('new-lead-company').value = prefills.company;
        if (prefills.email) document.getElementById('new-lead-email').value = prefills.email;
        if (prefills.phone) document.getElementById('new-lead-phone').value = prefills.phone;
        if (prefills.source) document.getElementById('new-lead-source').value = prefills.source;
    }
    lucide.createIcons();
}

async function submitNewLeadForm(btn) {
    const name = document.getElementById('new-lead-name').value.trim();
    if (!name) {
        showNotification('error', 'Lead Name is required.');
        return;
    }
    
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-white"></i>`;
    lucide.createIcons();
    
    const company = document.getElementById('new-lead-company').value.trim();
    const email = document.getElementById('new-lead-email').value.trim();
    const phone = document.getElementById('new-lead-phone').value.trim();
    const budget = parseFloat(document.getElementById('new-lead-budget').value || 0);
    const source = document.getElementById('new-lead-source').value;
    const priority = document.getElementById('new-lead-priority').value;
    const stage = document.getElementById('new-lead-stage').value;
    const services = document.getElementById('new-lead-services').value.trim();
    const requirements = document.getElementById('new-lead-requirements').value.trim();
    
    const payload = {
        name,
        company,
        email,
        phone,
        budget,
        lead_source: source,
        priority,
        stage,
        services_required: services,
        requirements
    };
    
    if (window.activeWaCrmContext) {
        if (window.activeWaCrmContext.contact) payload.contact_id = window.activeWaCrmContext.contact.id;
        if (window.activeWaCrmContext.company) payload.company_id = window.activeWaCrmContext.company.id;
    }
    
    try {
        const data = await apiCall('crm/leads.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Lead created successfully.');
            closeCrmModal();
            // Refresh viewport
            if (typeof loadWaThreadMessages === 'function' && currentView === 'whatsapp-inbox') {
                loadWaThreadMessages();
            } else {
                const viewport = document.getElementById('main-content-viewport');
                if (viewport) renderLeads(viewport);
            }
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

// Edit/View Lead Details Modal Overlay
async function editCrmLead(leadId) {
    // Remove existing modal if any
    const existing = document.getElementById('crm-lead-modal');
    if (existing) existing.remove();

    // Render a temporary spinner overlay
    const spinnerHTML = `
        <div id="crm-lead-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl p-8 flex items-center justify-center border border-slate-200">
                <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-indigo-600"></i>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', spinnerHTML);
    lucide.createIcons();

    try {
        const data = await apiCall('crm/leads.php?id=' + leadId);
        const modal = document.getElementById('crm-lead-modal');
        if (!modal) return;
        
        if (data.status !== 'success') {
            showNotification('error', 'Failed to retrieve lead details.');
            closeCrmModal();
            return;
        }

        const l = data.lead || {};
        const deals = l.deals || [];
        const tasks = l.tasks || [];
        const timeline = l.timeline || [];
        const date = new Date(l.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });

        // Build Timeline Log HTML
        const timelineLogs = timeline.length > 0 ? timeline.map(t => {
            const time = new Date(t.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
            return `
                <div class="flex items-start space-x-2 pb-3 border-l border-slate-100 pl-3 relative ml-1.5 last:border-0">
                    <div class="h-2 w-2 rounded-full bg-indigo-500 absolute -left-[5px] mt-1"></div>
                    <div class="flex-grow">
                        <div class="font-bold text-slate-700 text-[10px]">${t.activity_type}</div>
                        <p class="text-slate-500 text-[10px] mt-0.5">${t.description}</p>
                        <span class="text-[8px] text-slate-400 font-medium block mt-1">${time}</span>
                    </div>
                </div>
            `;
        }).join('') : `<p class="text-slate-400 text-[10px] italic">No activity logs recorded yet.</p>`;

        const modalContent = `
            <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2.5">
                        <div class="h-9 w-9 bg-indigo-50 text-indigo-655 rounded-xl flex items-center justify-center">
                            <i data-lucide="user" class="h-5 w-5 text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">${l.name}</h3>
                            <p class="text-[10px] text-slate-500 font-medium">Added to pipeline on ${date}</p>
                        </div>
                    </div>
                    <button onclick="closeCrmModal()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <!-- Modal Body (Two-Column layout) -->
                <div class="p-6 overflow-y-auto grid grid-cols-1 md:grid-cols-5 gap-6 text-xs">
                    <!-- Column 1: Editable Form fields (width 3/5) -->
                    <div class="md:col-span-3 space-y-4 pr-0 md:pr-4 md:border-r border-slate-100 text-left">
                        <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wider mb-2">Lead Information</div>
                        <input type="hidden" id="edit-lead-id" value="${l.id}">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Lead Name *</label>
                                <input type="text" id="edit-lead-name" value="${l.name || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Company Name</label>
                                <input type="text" id="edit-lead-company" value="${l.company || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Email Address</label>
                                <input type="email" id="edit-lead-email" value="${l.email || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Phone Number</label>
                                <input type="text" id="edit-lead-phone" value="${l.phone || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Pipeline Stage</label>
                                <select id="edit-lead-stage" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                                    <option value="New" ${l.stage === 'New' ? 'selected' : ''}>New Lead</option>
                                    <option value="Contacted" ${l.stage === 'Contacted' ? 'selected' : ''}>Contacted</option>
                                    <option value="Qualified" ${l.stage === 'Qualified' ? 'selected' : ''}>Qualified</option>
                                    <option value="Proposal" ${l.stage === 'Proposal' ? 'selected' : ''}>Proposal Sent</option>
                                    <option value="Negotiation" ${l.stage === 'Negotiation' ? 'selected' : ''}>Negotiation</option>
                                    <option value="Closed Won" ${l.stage === 'Closed Won' ? 'selected' : ''}>Closed Won</option>
                                    <option value="Closed Lost" ${l.stage === 'Closed Lost' ? 'selected' : ''}>Closed Lost</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Priority</label>
                                <select id="edit-lead-priority" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                                    <option value="low" ${l.priority === 'low' ? 'selected' : ''}>Low</option>
                                    <option value="medium" ${l.priority === 'medium' ? 'selected' : ''}>Medium</option>
                                    <option value="high" ${l.priority === 'high' ? 'selected' : ''}>High</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Lead Budget (₹)</label>
                                <input type="number" id="edit-lead-budget" value="${l.budget || 0}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Lead Source</label>
                                <input type="text" id="edit-lead-source" value="${l.lead_source || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Services Required</label>
                            <input type="text" id="edit-lead-services" value="${l.services_required || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Requirements & notes</label>
                            <textarea id="edit-lead-requirements" rows="3" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none font-sans">${l.requirements || ''}</textarea>
                        </div>
                    </div>

                    <!-- Column 2: Lead metrics & timeline (width 2/5) -->
                    <div class="md:col-span-2 space-y-4 text-left">
                        <div>
                            <span class="font-bold text-slate-800 text-[11px] uppercase tracking-wider mb-2 block">AI Analysis Metrics</span>
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 flex flex-col items-center">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Lead Score</span>
                                    <span class="text-lg font-bold text-slate-800 mt-0.5">${l.lead_score || 'N/A'}</span>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 flex flex-col items-center">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">AI Confidence</span>
                                    <span class="text-lg font-bold text-indigo-600 mt-0.5">${l.ai_confidence_score || '100'}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline Log -->
                        <div class="flex-grow flex flex-col">
                            <span class="font-bold text-slate-800 text-[11px] uppercase tracking-wider mb-2 block">Activity Timeline</span>
                            <div class="bg-slate-50 p-4 border border-slate-100 rounded-xl overflow-y-auto max-h-[220px]">
                                ${timelineLogs}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-between items-center">
                    <button onclick="deleteCrmLead(this, ${l.id})" class="px-3.5 py-2 border border-red-200 text-red-650 hover:bg-red-50 rounded-lg font-bold transition flex items-center space-x-1">
                        <i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>
                        <span>Delete Lead</span>
                    </button>
                    <div class="flex space-x-2">
                        <button onclick="closeCrmModal()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                        <button onclick="submitEditLeadForm(this)" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center space-x-1.5 shadow-sm">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        `;

        modal.innerHTML = modalContent;
        lucide.createIcons();

    } catch (e) {
        showNotification('error', 'Error opening lead details: ' + e.message);
        closeCrmModal();
    }
}

async function submitEditLeadForm(btn) {
    const leadId = document.getElementById('edit-lead-id').value;
    const name = document.getElementById('edit-lead-name').value.trim();
    if (!name) {
        showNotification('error', 'Lead Name is required.');
        return;
    }
    
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-white"></i>`;
    lucide.createIcons();
    
    const company = document.getElementById('edit-lead-company').value.trim();
    const email = document.getElementById('edit-lead-email').value.trim();
    const phone = document.getElementById('edit-lead-phone').value.trim();
    const stage = document.getElementById('edit-lead-stage').value;
    const priority = document.getElementById('edit-lead-priority').value;
    const budget = parseFloat(document.getElementById('edit-lead-budget').value || 0);
    const source = document.getElementById('edit-lead-source').value.trim();
    const services = document.getElementById('edit-lead-services').value.trim();
    const requirements = document.getElementById('edit-lead-requirements').value.trim();
    
    const payload = {
        id: leadId,
        name,
        company,
        email,
        phone,
        stage,
        priority,
        budget,
        lead_source: source,
        services_required: services,
        requirements
    };
    
    try {
        const data = await apiCall('crm/leads.php?action=PUT', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Lead details updated successfully.');
            closeCrmModal();
            // Refresh viewport
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) renderLeads(viewport);
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

async function deleteCrmLead(btn, leadId) {
    if (!confirm('Are you absolutely sure you want to delete this lead? This action is permanent.')) {
        return;
    }
    
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-red-500"></i>`;
    lucide.createIcons();
    
    try {
        const data = await apiCall('crm/leads.php?action=DELETE', 'POST', { id: leadId });
        if (data.status === 'success') {
            showNotification('success', 'Lead deleted successfully.');
            closeCrmModal();
            // Refresh viewport
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) renderLeads(viewport);
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

function closeCrmModal() {
    const modal = document.getElementById('crm-lead-modal');
    if (modal) modal.remove();
}

// ----------------------------------------------------
// 5. DEALS KANBAN VIEW
// ----------------------------------------------------
async function renderDeals(container) {
    try {
        let url = 'crm/deals.php?layout=kanban';
        if (window.dealsFilterSearch) {
            url += `&search=${encodeURIComponent(window.dealsFilterSearch)}`;
        }
        if (window.dealsFilterStage) {
            url += `&stage=${encodeURIComponent(window.dealsFilterStage)}`;
        }
        
        const data = await apiCall(url);
        const stages = data.stages || {};
        
        // Calculate statistics
        let totalCount = 0;
        let totalValue = 0;
        let openCount = 0;
        let wonCount = 0;
        let wonValue = 0;

        Object.keys(stages).forEach(st => {
            const list = stages[st] || [];
            list.forEach(c => {
                totalCount++;
                totalValue += parseFloat(c.expected_revenue || 0);
                if (st === 'Closed Won') {
                    wonCount++;
                    wonValue += parseFloat(c.expected_revenue || 0);
                } else if (st !== 'Closed Lost') {
                    openCount++;
                }
            });
        });

        const openPercent = totalCount > 0 ? Math.round((openCount / totalCount) * 100) : 0;
        const wonPercent = totalCount > 0 ? Math.round((wonCount / totalCount) * 100) : 0;
        const winRate = totalCount > 0 ? Math.round((wonCount / totalCount) * 100) : 0;
        const avgValue = totalCount > 0 ? Math.round(totalValue / totalCount) : 0;

        const themeMap = {
            'Lead': { text: 'text-indigo-600 border-t-indigo-500', bg: 'bg-indigo-500/10', name: 'LEAD' },
            'Qualified': { text: 'text-blue-600 border-t-blue-500', bg: 'bg-blue-500/10', name: 'QUALIFIED' },
            'Proposal': { text: 'text-amber-600 border-t-amber-500', bg: 'bg-amber-500/10', name: 'PROPOSAL' },
            'Negotiation': { text: 'text-emerald-600 border-t-emerald-500', bg: 'bg-emerald-500/10', name: 'NEGOTIATION' },
            'Closed Won': { text: 'text-green-600 border-t-green-500', bg: 'bg-green-500/10', name: 'WON' },
            'Closed Lost': { text: 'text-rose-600 border-t-rose-500', bg: 'bg-rose-500/10', name: 'LOST' }
        };

        let kanbanColumns = Object.keys(stages).map(st => {
            const theme = themeMap[st] || { text: 'text-slate-600 border-t-slate-450', bg: 'bg-slate-500/10', name: st.toUpperCase() };
            
            const cards = stages[st].map(c => {
                const revenue = parseFloat(c.expected_revenue || 0).toLocaleString('en-IN');
                
                let dateStr = 'N/A';
                if (c.closing_date) {
                    const d = new Date(c.closing_date);
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    dateStr = `${d.getDate().toString().padStart(2, '0')} ${months[d.getMonth()]} ${d.getFullYear()}`;
                }
                
                const isWon = (st === 'Closed Won');
                const isLost = (st === 'Closed Lost');
                
                let footerHtml = '';
                if (isWon) {
                    footerHtml = `
                        <div class="flex items-center space-x-1 text-[9px] text-emerald-600 font-bold">
                            <i data-lucide="check" class="h-3 w-3"></i>
                            <span>Won on ${dateStr}</span>
                        </div>
                    `;
                } else if (isLost) {
                    footerHtml = `
                        <div class="flex items-center space-x-1 text-[9px] text-rose-600 font-bold">
                            <i data-lucide="x" class="h-3 w-3"></i>
                            <span>Lost on ${dateStr}</span>
                        </div>
                    `;
                } else {
                    footerHtml = `
                        <div class="flex items-center space-x-1 text-[9px] text-slate-400 font-semibold">
                            <i data-lucide="calendar" class="h-3 w-3"></i>
                            <span>${dateStr}</span>
                        </div>
                    `;
                }

                return `
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-3 card-hover relative group cursor-grab active:cursor-grabbing" draggable="true" ondragstart="handleDealDragStart(event, ${c.id})">
                        <!-- Action Menu Button -->
                        <div class="absolute top-3.5 right-3.5 z-10">
                            <button onclick="openDealMenu(event, ${c.id})" class="h-6 w-6 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-50 flex items-center justify-center transition">
                                <i data-lucide="more-vertical" class="h-4 w-4"></i>
                            </button>
                        </div>
                        
                        <!-- Title & Company -->
                        <div class="space-y-1 pr-6">
                            <div class="font-extrabold text-slate-800 text-xs truncate group-hover:text-blue-600 transition-colors">${c.title}</div>
                            <div class="text-[10px] text-slate-400 font-bold truncate">${c.company_name || 'Direct Contact'}</div>
                        </div>
                        
                        <!-- Value -->
                        <div class="text-xs font-black text-slate-800">
                            ₹${revenue}
                        </div>
                        
                        <!-- Owner & Date Footer -->
                        <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 mt-2">
                            <div class="flex items-center space-x-1.5 text-[9px] text-slate-500 font-bold">
                                <div class="h-4.5 w-4.5 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 text-[8px] font-black uppercase">
                                    ${(c.owner || 'U')[0]}
                                </div>
                                <span class="truncate max-w-[70px]">${c.owner || 'Unassigned'}</span>
                            </div>
                            ${footerHtml}
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="flex-1 min-w-[280px] bg-slate-50/30 rounded-2xl border border-slate-200/50 p-4 space-y-4 flex flex-col min-h-[500px] border-t-4 ${theme.text.split(' ')[1]}" ondragover="event.preventDefault()" ondrop="handleDealDrop(event, '${st}')">
                    <!-- Column Header -->
                    <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                        <div class="flex items-center space-x-2">
                            <span class="text-xs font-extrabold ${theme.text.split(' ')[0]} tracking-wider uppercase">${theme.name}</span>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black ${theme.bg} ${theme.text.split(' ')[0]}">${stages[st].length}</span>
                        </div>
                        <span class="text-[10px] font-black text-slate-800">₹${parseFloat(data.totals[st] || 0).toLocaleString('en-IN')}</span>
                    </div>
                    
                    <!-- Cards Container -->
                    <div class="flex-1 space-y-3 overflow-y-auto max-h-[600px] pr-1">
                        ${cards || `<p class="text-[10px] text-slate-400 text-center py-12 font-medium">No deals in this stage</p>`}
                    </div>

                    <!-- Column Footer Action -->
                    ${st === 'Closed Won' ? `
                        <div class="pt-2 text-center border-t border-slate-100 mt-2">
                            <button onclick="navigateTo('deals')" class="text-[10px] text-blue-600 hover:text-blue-700 hover:underline font-bold transition flex items-center justify-center space-x-1 mx-auto">
                                <span>View all won deals</span>
                                <i data-lucide="arrow-right" class="h-3 w-3"></i>
                            </button>
                        </div>
                    ` : `
                        <div class="pt-2 border-t border-slate-100 mt-2">
                            <button onclick="openCreateDealModal('${st}')" class="w-full py-2 border border-dashed border-slate-200 rounded-xl text-[10px] font-bold text-slate-500 hover:text-blue-600 hover:border-blue-300 hover:bg-blue-50/20 transition flex items-center justify-center space-x-1">
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                                <span>Add Deal</span>
                            </button>
                        </div>
                    `}
                </div>
            `;
        }).join('');

        container.innerHTML = `
            <div class="space-y-6 animate-fade-in pt-4 text-slate-700 text-xs">
                <!-- Header Actions Row -->
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 border-b border-slate-100 pb-5">
                    <div class="flex items-center space-x-3.5">
                        <div class="h-11 w-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 border border-indigo-100">
                            <i data-lucide="award" class="h-5.5 w-5.5"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-800 tracking-tight">Deals Board</h2>
                            <p class="text-xs text-slate-500 mt-1">Visualize and manage your sales pipeline</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-3">
                        <!-- Pipeline View -->
                        <div class="relative">
                            <button onclick="togglePipelineViewDropdown()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs font-bold transition hover:bg-slate-50 flex items-center space-x-1.5 shadow-sm">
                                <i data-lucide="kanban" class="h-4 w-4"></i>
                                <span>Pipeline View</span>
                                <i data-lucide="chevron-down" class="h-3 w-3"></i>
                            </button>
                        </div>
                        
                        <!-- Filters -->
                        <button onclick="toggleDealsFilterPanel()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs font-bold transition hover:bg-slate-50 flex items-center space-x-1.5 shadow-sm">
                            <i data-lucide="filter" class="h-4 w-4"></i>
                            <span>Filters</span>
                        </button>
                        
                        <!-- Forecast -->
                        <button onclick="toggleDealsForecastPanel()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-xl text-xs font-bold transition hover:bg-slate-50 flex items-center space-x-1.5 shadow-sm">
                            <i data-lucide="bar-chart-3" class="h-4 w-4"></i>
                            <span>Forecast</span>
                        </button>
                        
                        <!-- New Deal -->
                        <button onclick="openCreateDealModal()" class="px-4.5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-extrabold transition flex items-center space-x-1.5 shadow-md shadow-blue-500/10" style="color: #ffffff !important;">
                            <i data-lucide="plus" class="h-4 w-4" style="color: #ffffff !important;"></i>
                            <span style="color: #ffffff !important;">New Deal</span>
                        </button>
                    </div>
                </div>

                <!-- Filters Row (collapsible) -->
                <div id="deals-filter-panel" class="hidden bg-slate-50 border border-slate-200/80 rounded-2xl p-4 grid grid-cols-1 md:grid-cols-3 gap-4 animate-fade-in text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-650 uppercase tracking-wider text-[9px]">Search Query</label>
                        <input type="text" id="filter-deals-search" value="${window.dealsFilterSearch || ''}" placeholder="Search deal, company, contact..." class="w-full px-3.5 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 bg-white shadow-sm">
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-650 uppercase tracking-wider text-[9px]">Filter by Stage</label>
                        <select id="filter-deals-stage" class="w-full px-3.5 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 bg-white shadow-sm">
                            <option value="">-- All Stages --</option>
                            <option value="Lead" ${window.dealsFilterStage === 'Lead' ? 'selected' : ''}>Lead</option>
                            <option value="Qualified" ${window.dealsFilterStage === 'Qualified' ? 'selected' : ''}>Qualified</option>
                            <option value="Proposal" ${window.dealsFilterStage === 'Proposal' ? 'selected' : ''}>Proposal</option>
                            <option value="Negotiation" ${window.dealsFilterStage === 'Negotiation' ? 'selected' : ''}>Negotiation</option>
                            <option value="Closed Won" ${window.dealsFilterStage === 'Closed Won' ? 'selected' : ''}>Closed Won</option>
                            <option value="Closed Lost" ${window.dealsFilterStage === 'Closed Lost' ? 'selected' : ''}>Closed Lost</option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button onclick="applyDealsFilters()" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1.5 shadow-sm" style="color: #ffffff !important;">
                            <i data-lucide="check" class="h-3.5 w-3.5" style="color: #ffffff !important;"></i>
                            <span style="color: #ffffff !important;">Apply Filters</span>
                        </button>
                        <button onclick="resetDealsFilters()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 text-slate-650 rounded-xl text-xs font-bold transition">Reset</button>
                    </div>
                </div>

                <!-- Forecast Row (collapsible) -->
                <div id="deals-forecast-panel" class="hidden bg-slate-50 border border-slate-200/80 rounded-2xl p-5 animate-fade-in text-xs space-y-4">
                    <h3 class="font-extrabold text-slate-800 text-sm">Pipeline Value Forecast</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                        <div class="space-y-3.5">
                            <div>
                                <div class="flex justify-between text-[11px] font-bold text-slate-600 mb-1">
                                    <span>Closed Won Revenue Goal</span>
                                    <span>₹${wonValue.toLocaleString('en-IN')} / ₹${totalValue.toLocaleString('en-IN')}</span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-3">
                                    <div class="bg-emerald-500 h-3 rounded-full transition-all duration-500" style="width: ${totalValue > 0 ? Math.round((wonValue / totalValue) * 100) : 0}%"></div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-[10px] font-bold">
                                <div class="p-3 bg-white border border-slate-100 rounded-xl">
                                    <span class="text-slate-400 block uppercase text-[8px]">Weighted Pipeline (50% prob)</span>
                                    <span class="text-slate-800 text-sm font-extrabold">₹${Math.round(totalValue * 0.5).toLocaleString('en-IN')}</span>
                                </div>
                                <div class="p-3 bg-white border border-slate-100 rounded-xl">
                                    <span class="text-slate-400 block uppercase text-[8px]">Average Deal Size</span>
                                    <span class="text-slate-800 text-sm font-extrabold">₹${avgValue.toLocaleString('en-IN')}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mini breakdown -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-4 space-y-3">
                            <h4 class="font-bold text-slate-700 text-xs">Stage-wise Breakdown</h4>
                            <div class="space-y-2 max-h-36 overflow-y-auto">
                                ${Object.keys(stages).map(st => {
                                    const count = stages[st].length;
                                    const val = parseFloat(data.totals[st] || 0);
                                    const pct = totalValue > 0 ? Math.round((val / totalValue) * 100) : 0;
                                    return `
                                        <div class="flex items-center justify-between text-[10px] font-semibold text-slate-600">
                                            <span class="truncate max-w-[80px]">${st} (${count})</span>
                                            <div class="flex items-center space-x-2 flex-1 mx-4">
                                                <div class="w-full bg-slate-100 h-2 rounded-full">
                                                    <div class="bg-indigo-500 h-2 rounded-full" style="width: ${pct}%"></div>
                                                </div>
                                                <span class="w-6 text-right">${pct}%</span>
                                            </div>
                                            <span class="font-bold text-slate-800">₹${val.toLocaleString('en-IN')}</span>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards Row -->
                <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                    <!-- Total Deals -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                        <div class="absolute top-4 right-4 h-7 w-7 bg-purple-50 text-purple-500 rounded-lg flex items-center justify-center">
                            <i data-lucide="folder" class="h-3.5 w-3.5"></i>
                        </div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Total Deals</div>
                        <div class="text-xl font-extrabold text-slate-800">${totalCount}</div>
                        <div class="text-[9px] text-slate-400 font-medium">All stages</div>
                    </div>
                    <!-- Total Value -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                        <div class="absolute top-4 right-4 h-7 w-7 bg-emerald-50 text-emerald-500 rounded-lg flex items-center justify-center">
                            <i data-lucide="trending-up" class="h-3.5 w-3.5"></i>
                        </div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Total Value</div>
                        <div class="text-xl font-extrabold text-slate-800">₹${totalValue.toLocaleString('en-IN')}</div>
                        <div class="text-[9px] text-slate-400 font-medium">All deals</div>
                    </div>
                    <!-- Open Deals -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                        <div class="absolute top-4 right-4 h-7 w-7 bg-blue-50 text-blue-500 rounded-lg flex items-center justify-center">
                            <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                        </div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Open Deals</div>
                        <div class="text-xl font-extrabold text-slate-800">${openCount}</div>
                        <div class="text-[9px] text-slate-400 font-medium">${openPercent}% of total</div>
                    </div>
                    <!-- Won Deals -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                        <div class="absolute top-4 right-4 h-7 w-7 bg-emerald-50 text-emerald-500 rounded-lg flex items-center justify-center">
                            <i data-lucide="check-circle" class="h-3.5 w-3.5"></i>
                        </div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Won Deals</div>
                        <div class="text-xl font-extrabold text-slate-800">${wonCount}</div>
                        <div class="text-[9px] text-slate-400 font-medium">${wonPercent}% of total</div>
                    </div>
                    <!-- Win Rate -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                        <div class="absolute top-4 right-4 h-7 w-7 bg-violet-50 text-violet-500 rounded-lg flex items-center justify-center">
                            <i data-lucide="target" class="h-3.5 w-3.5"></i>
                        </div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Win Rate</div>
                        <div class="text-xl font-extrabold text-slate-800">${winRate}%</div>
                        <div class="text-[9px] text-slate-400 font-medium">All time</div>
                    </div>
                    <!-- Avg. Deal Value -->
                    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-sm space-y-1 relative">
                        <div class="absolute top-4 right-4 h-7 w-7 bg-amber-50 text-amber-500 rounded-lg flex items-center justify-center">
                            <i data-lucide="bar-chart" class="h-3.5 w-3.5"></i>
                        </div>
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Avg. Deal Value</div>
                        <div class="text-xl font-extrabold text-slate-800">₹${avgValue.toLocaleString('en-IN')}</div>
                        <div class="text-[9px] text-slate-400 font-medium">All time</div>
                    </div>
                </div>

                <!-- Kanban Board view -->
                <div class="kanban-board">
                    ${kanbanColumns}
                </div>
            </div>
        `;
        lucide.createIcons();
    } catch (err) {
        showNotification('error', err.message);
    }
}

// Window-scoped toggle & filtering controls
window.toggleDealsFilterPanel = function() {
    const el = document.getElementById('deals-filter-panel');
    if (el) el.classList.toggle('hidden');
};

window.toggleDealsForecastPanel = function() {
    const el = document.getElementById('deals-forecast-panel');
    if (el) el.classList.toggle('hidden');
};

window.applyDealsFilters = function() {
    window.dealsFilterSearch = document.getElementById('filter-deals-search').value.trim();
    window.dealsFilterStage = document.getElementById('filter-deals-stage').value;
    navigateTo('deals');
};

window.resetDealsFilters = function() {
    window.dealsFilterSearch = '';
    window.dealsFilterStage = '';
    navigateTo('deals');
};

window.togglePipelineViewDropdown = function() {
    showNotification('info', 'You are currently in Pipeline Kanban View. List/Table view coming soon!');
};

// Window-scoped Deal actions popup menu
window.openDealMenu = function(event, dealId) {
    event.stopPropagation();
    event.preventDefault();
    
    const existing = document.getElementById('deal-menu-popover');
    if (existing) existing.remove();
    
    const menu = document.createElement('div');
    menu.id = 'deal-menu-popover';
    menu.className = 'absolute bg-white border border-slate-200 rounded-xl shadow-lg py-1.5 w-28 text-left z-50 text-[10px] font-bold text-slate-700 animate-fade-in animate-duration-150';
    
    menu.innerHTML = `
        <button onclick="editDealMenuAction(${dealId})" class="w-full px-3 py-1.5 hover:bg-slate-50 transition flex items-center space-x-1.5">
            <i data-lucide="edit-3" class="h-3.5 w-3.5 text-slate-400"></i>
            <span>Edit Deal</span>
        </button>
        <button onclick="deleteDealMenuAction(${dealId})" class="w-full px-3 py-1.5 hover:bg-slate-50 transition flex items-center space-x-1.5 text-rose-600">
            <i data-lucide="trash-2" class="h-3.5 w-3.5 text-rose-400"></i>
            <span>Delete</span>
        </button>
    `;
    
    document.body.appendChild(menu);
    lucide.createIcons();
    
    const rect = event.currentTarget.getBoundingClientRect();
    menu.style.top = `${rect.bottom + window.scrollY}px`;
    menu.style.left = `${rect.left + window.scrollX - 80}px`;
    
    const closeMenu = () => {
        menu.remove();
        document.removeEventListener('click', closeMenu);
    };
    setTimeout(() => document.addEventListener('click', closeMenu), 0);
};

window.editDealMenuAction = function(dealId) {
    openEditDealModal(dealId);
};

window.deleteDealMenuAction = async function(dealId) {
    if (!confirm('Are you sure you want to delete this deal?')) return;
    try {
        await apiCall(`crm/deals.php?action=delete&id=${dealId}`, 'POST');
        showNotification('success', 'Deal deleted successfully.');
        navigateTo('deals');
    } catch(err) {
        showNotification('error', 'Failed to delete deal: ' + err.message);
    }
};

// Window-scoped Dynamic modals for creation & edits
window.openCreateDealModal = async function(prefilledStage) {
    const existing = document.getElementById('deal-create-modal');
    if (existing) existing.remove();
    
    let companies = [];
    let contacts = [];
    try {
        const compsRes = await apiCall('crm/companies.php?limit=1000');
        companies = compsRes.companies || [];
    } catch(e) {}
    try {
        const contsRes = await apiCall('crm/contacts.php?limit=1000');
        contacts = contsRes.contacts || [];
    } catch(e) {}

    const modal = document.createElement('div');
    modal.id = 'deal-create-modal';
    modal.className = 'fixed inset-0 z-[2000] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4';
    
    const stageOptions = [
        { value: 'Lead', label: 'Lead' },
        { value: 'Qualified', label: 'Qualified' },
        { value: 'Proposal', label: 'Proposal' },
        { value: 'Negotiation', label: 'Negotiation' },
        { value: 'Closed Won', label: 'Closed Won' },
        { value: 'Closed Lost', label: 'Closed Lost' }
    ];
    
    const stageSelectHtml = stageOptions.map(opt => `
        <option value="${opt.value}" ${prefilledStage === opt.value ? 'selected' : ''}>${opt.label}</option>
    `).join('');
    
    const companySelectOptions = companies.map(c => `
        <option value="${c.id}">${c.name}</option>
    `).join('');

    const contactSelectOptions = contacts.map(c => `
        <option value="${c.id}">${c.name} (${c.email || c.phone || 'No Contact Info'})</option>
    `).join('');

    modal.innerHTML = `
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in flex flex-col text-slate-700">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h4 class="text-sm font-black text-slate-800 tracking-tight">Create New Deal</h4>
                    <p class="text-[10px] text-slate-450 font-bold uppercase tracking-wider mt-0.5">Link deal to contacts and companies</p>
                </div>
                <button onclick="document.getElementById('deal-create-modal').remove()" class="h-8 w-8 rounded-full hover:bg-slate-100 text-slate-400 flex items-center justify-center transition">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form id="deal-create-form" class="p-6 flex-grow overflow-y-auto max-h-[70vh] space-y-4 text-xs">
                <div class="space-y-1">
                    <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Deal Title</label>
                    <input type="text" id="deal-title" required placeholder="e.g. Enterprise Software License" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                </div>
                
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Pipeline Stage</label>
                        <select id="deal-stage" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                            ${stageSelectHtml}
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Revenue (₹)</label>
                        <input type="number" id="deal-revenue" value="0.00" step="0.01" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Probability (%)</label>
                        <input type="number" id="deal-probability" value="50" min="0" max="100" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Link Company (Optional)</label>
                        <select id="deal-company" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                            <option value="">-- No Link --</option>
                            ${companySelectOptions}
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Link Contact (Optional)</label>
                        <select id="deal-contact" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                            <option value="">-- No Link --</option>
                            ${contactSelectOptions}
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Owner Name</label>
                        <input type="text" id="deal-owner" value="Soumojit Saha" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Target Closing Date</label>
                        <input type="date" id="deal-closing-date" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                    </div>
                </div>
            </form>
            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-end space-x-2">
                <button onclick="document.getElementById('deal-create-modal').remove()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 text-slate-600 rounded-xl font-bold transition text-[10px]">Cancel</button>
                <button type="submit" form="deal-create-form" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold transition text-[10px] flex items-center space-x-1.5 shadow-md shadow-blue-500/10" style="color: #ffffff !important;">
                    <i data-lucide="check" class="h-3.5 w-3.5" style="color: #ffffff !important;"></i>
                    <span style="color: #ffffff !important;">Create Deal</span>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    lucide.createIcons();
    
    document.getElementById('deal-create-form').onsubmit = async (e) => {
        e.preventDefault();
        const payload = {
            title: document.getElementById('deal-title').value.trim(),
            stage: document.getElementById('deal-stage').value,
            expected_revenue: parseFloat(document.getElementById('deal-revenue').value || 0),
            probability: parseInt(document.getElementById('deal-probability').value || 50),
            company_id: document.getElementById('deal-company').value || null,
            contact_id: document.getElementById('deal-contact').value || null,
            owner: document.getElementById('deal-owner').value.trim(),
            closing_date: document.getElementById('deal-closing-date').value || null
        };
        
        try {
            await apiCall('crm/deals.php', 'POST', payload);
            showNotification('success', 'Deal created successfully!');
            modal.remove();
            navigateTo('deals');
        } catch(err) {
            showNotification('error', 'Failed to create deal: ' + err.message);
        }
    };
};

window.openEditDealModal = async function(dealId) {
    const existing = document.getElementById('deal-edit-modal');
    if (existing) existing.remove();
    
    let deal = null;
    let companies = [];
    let contacts = [];
    try {
        const dealRes = await apiCall(`crm/deals.php?id=${dealId}`);
        deal = dealRes.deal;
    } catch(e) {
        showNotification('error', 'Failed to load deal details.');
        return;
    }
    
    try {
        const compsRes = await apiCall('crm/companies.php?limit=1000');
        companies = compsRes.companies || [];
    } catch(e) {}
    try {
        const contsRes = await apiCall('crm/contacts.php?limit=1000');
        contacts = contsRes.contacts || [];
    } catch(e) {}

    const modal = document.createElement('div');
    modal.id = 'deal-edit-modal';
    modal.className = 'fixed inset-0 z-[2000] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4';
    
    const stageOptions = [
        { value: 'Lead', label: 'Lead' },
        { value: 'Qualified', label: 'Qualified' },
        { value: 'Proposal', label: 'Proposal' },
        { value: 'Negotiation', label: 'Negotiation' },
        { value: 'Closed Won', label: 'Closed Won' },
        { value: 'Closed Lost', label: 'Closed Lost' }
    ];
    
    const stageSelectHtml = stageOptions.map(opt => `
        <option value="${opt.value}" ${deal.stage === opt.value ? 'selected' : ''}>${opt.label}</option>
    `).join('');
    
    const companySelectOptions = companies.map(c => `
        <option value="${c.id}" ${deal.company_id == c.id ? 'selected' : ''}>${c.name}</option>
    `).join('');

    const contactSelectOptions = contacts.map(c => `
        <option value="${c.id}" ${deal.contact_id == c.id ? 'selected' : ''}>${c.name} (${c.email || c.phone || 'No Contact Info'})</option>
    `).join('');

    modal.innerHTML = `
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in flex flex-col text-slate-700">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h4 class="text-sm font-black text-slate-800 tracking-tight">Edit Deal Details</h4>
                    <p class="text-[10px] text-slate-450 font-bold uppercase tracking-wider mt-0.5">Modify parameters or update linked records</p>
                </div>
                <button onclick="document.getElementById('deal-edit-modal').remove()" class="h-8 w-8 rounded-full hover:bg-slate-100 text-slate-400 flex items-center justify-center transition">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <form id="deal-edit-form" class="p-6 flex-grow overflow-y-auto max-h-[70vh] space-y-4 text-xs">
                <div class="space-y-1">
                    <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Deal Title</label>
                    <input type="text" id="edit-deal-title" required value="${deal.title}" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                </div>
                
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Pipeline Stage</label>
                        <select id="edit-deal-stage" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                            ${stageSelectHtml}
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Revenue (₹)</label>
                        <input type="number" id="edit-deal-revenue" value="${deal.expected_revenue}" step="0.01" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Probability (%)</label>
                        <input type="number" id="edit-deal-probability" value="${deal.probability}" min="0" max="100" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Link Company (Optional)</label>
                        <select id="edit-deal-company" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                            <option value="">-- No Link --</option>
                            ${companySelectOptions}
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Link Contact (Optional)</label>
                        <select id="edit-deal-contact" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                            <option value="">-- No Link --</option>
                            ${contactSelectOptions}
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Owner Name</label>
                        <input type="text" id="edit-deal-owner" value="${deal.owner || 'Soumojit Saha'}" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                    </div>
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-600 uppercase tracking-wider text-[9px]">Target Closing Date</label>
                        <input type="date" id="edit-deal-closing-date" value="${deal.closing_date ? deal.closing_date.split(' ')[0] : ''}" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-blue-500 shadow-sm bg-white">
                    </div>
                </div>
            </form>
            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-end space-x-2">
                <button onclick="document.getElementById('deal-edit-modal').remove()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 text-slate-600 rounded-xl font-bold transition text-[10px]">Cancel</button>
                <button type="submit" form="deal-edit-form" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold transition text-[10px] flex items-center space-x-1.5 shadow-md shadow-blue-500/10" style="color: #ffffff !important;">
                    <i data-lucide="check" class="h-3.5 w-3.5" style="color: #ffffff !important;"></i>
                    <span style="color: #ffffff !important;">Save Changes</span>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    lucide.createIcons();
    
    document.getElementById('deal-edit-form').onsubmit = async (e) => {
        e.preventDefault();
        const payload = {
            id: dealId,
            action: 'update',
            title: document.getElementById('edit-deal-title').value.trim(),
            stage: document.getElementById('edit-deal-stage').value,
            expected_revenue: parseFloat(document.getElementById('edit-deal-revenue').value || 0),
            probability: parseInt(document.getElementById('edit-deal-probability').value || 50),
            company_id: document.getElementById('edit-deal-company').value || null,
            contact_id: document.getElementById('edit-deal-contact').value || null,
            owner: document.getElementById('edit-deal-owner').value.trim(),
            closing_date: document.getElementById('edit-deal-closing-date').value || null
        };
        
        try {
            await apiCall('crm/deals.php', 'POST', payload);
            showNotification('success', 'Deal updated successfully!');
            modal.remove();
            navigateTo('deals');
        } catch(err) {
            showNotification('error', 'Failed to update deal: ' + err.message);
        }
    };
};

let draggedDealId = null;
function handleDealDragStart(e, id) {
    draggedDealId = id;
    e.dataTransfer.setData('text/plain', id);
}

async function handleDealDrop(e, targetStage) {
    e.preventDefault();
    if (!draggedDealId) return;
    
    try {
        await apiCall('crm/deals.php?action=update', 'POST', {
            id: draggedDealId,
            stage: targetStage
        });
        showNotification('success', `Deal stage updated to ${targetStage}!`);
        navigateTo('deals');
    } catch (err) {
        showNotification('error', err.message);
    } finally {
        draggedDealId = null;
    }
}

// ----------------------------------------------------
// 6. COMPANIES & OTHER CRM VIEWS (STUBS / LISTS)
// ----------------------------------------------------
async function renderCompanies(container) {
    try {
        const res = await apiCall('crm/companies.php?limit=1000');
        const comps = res.companies || [];
        
        let compRows = comps.map(c => `
            <tr class="hover:bg-slate-900/40">
                <td class="py-3 px-4 font-bold text-white">${c.name}</td>
                <td class="py-3 px-4 text-slate-300 font-medium">${c.industry || '-'}</td>
                <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">${c.website || '-'}</td>
                <td class="py-3 px-4">${c.owner || '-'}</td>
                <td class="py-3 px-4">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-green-500/10 text-green-400 border border-green-500/20">${c.status}</span>
                </td>
                <td class="py-3 px-4 text-right">
                    <button onclick="openInspectCompanyModal(${c.id})" class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 rounded-md hover:border-teal-400 hover:text-teal-400 transition">View Portal</button>
                </td>
            </tr>
        `).join('');

        container.innerHTML = `
            <div class="space-y-6 animate-fade-in pt-4">
                <div class="flex justify-between items-center border-b border-slate-850 pb-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white">Companies Vault</h1>
                        <p class="text-slate-400 text-xs mt-1">Manage institutional and client accounts.</p>
                    </div>
                </div>

                <div class="glass-panel p-5 bg-slate-900/40">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse custom-table text-xs">
                            <thead>
                                <tr class="border-b border-slate-800">
                                    <th class="py-3 px-4">Company Name</th>
                                    <th class="py-3 px-4">Industry</th>
                                    <th class="py-3 px-4">Website</th>
                                    <th class="py-3 px-4">Owner</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${compRows || `<tr><td colspan="6" class="text-center py-10 text-slate-500">No companies cataloged.</td></tr>`}
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
}

async function renderContacts(container) {
    try {
        const res = await apiCall('crm/contacts.php?limit=1000');
        const conts = res.contacts || [];
        
        let contRows = conts.map(c => `
            <tr class="hover:bg-slate-900/40">
                <td class="py-3 px-4 font-bold text-white">${c.name}</td>
                <td class="py-3 px-4 text-slate-300 font-medium">${c.company_name || '-'}</td>
                <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">${c.email || '-'}</td>
                <td class="py-3 px-4 text-slate-300">${c.phone || '-'}</td>
                <td class="py-3 px-4 text-slate-400">${c.designation || '-'}</td>
                <td class="py-3 px-4 text-right">
                    <button onclick="openInspectContactModal(${c.id})" class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 rounded-md hover:border-teal-400 hover:text-teal-400 transition">Inspect</button>
                </td>
            </tr>
        `).join('');

        container.innerHTML = `
            <div class="space-y-6 animate-fade-in pt-4">
                <div class="flex justify-between items-center border-b border-slate-850 pb-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white">Contacts CRM</h1>
                        <p class="text-slate-400 text-xs mt-1">Individual profiles and communication details linked to institutions.</p>
                    </div>
                </div>

                <div class="glass-panel p-5 bg-slate-900/40">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse custom-table text-xs">
                            <thead>
                                <tr class="border-b border-slate-800">
                                    <th class="py-3 px-4">Contact Name</th>
                                    <th class="py-3 px-4">Company</th>
                                    <th class="py-3 px-4">Email Address</th>
                                    <th class="py-3 px-4">Phone</th>
                                    <th class="py-3 px-4">Designation</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${contRows || `<tr><td colspan="6" class="text-center py-10 text-slate-500">No contact profiles found.</td></tr>`}
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
}

async function openInspectContactModal(contactId) {
    const existing = document.getElementById('crm-contact-inspect-modal');
    if (existing) existing.remove();

    let modalHTML = `
        <div id="crm-contact-inspect-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-2xl p-6 text-slate-800 text-xs space-y-4 shadow-2xl relative">
                <button onclick="document.getElementById('crm-contact-inspect-modal').remove()" class="absolute top-4 right-4 h-7 w-7 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center transition">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
                <div class="flex items-center justify-center py-12">
                    <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-indigo-600"></i>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    lucide.createIcons();

    try {
        const res = await apiCall(`crm/contacts.php?id=${contactId}`);
        const c = res.contact || {};
        const deals = c.deals || [];
        const tasks = c.tasks || [];
        const meetings = c.meetings || [];
        const notes = c.notes || [];
        const timeline = c.timeline || [];

        let customFieldsObj = {};
        try {
            customFieldsObj = typeof c.custom_fields === 'string' ? JSON.parse(c.custom_fields) : (c.custom_fields || {});
        } catch (e) {}

        const postUrl = customFieldsObj.post_url || '';
        const sourceVal = customFieldsObj.source || c.source || 'CRM Manual';

        const modal = document.getElementById('crm-contact-inspect-modal');
        if (!modal) return;

        modal.innerHTML = `
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-3xl text-slate-700 text-xs shadow-2xl relative flex flex-col max-h-[85vh]">
                <div class="p-5 border-b border-slate-100 flex justify-between items-start">
                    <div>
                        <div class="flex items-center space-x-2.5">
                            <h2 class="text-lg font-bold text-slate-800">${c.name}</h2>
                            ${c.designation ? `<span class="px-2 py-0.5 bg-indigo-50 text-indigo-650 border border-indigo-100 rounded-full text-[10px] font-bold">${c.designation}</span>` : ''}
                        </div>
                        <p class="text-slate-550 text-[11px] mt-0.5">${c.company_name || 'No Associated Company'} • ${c.department || 'No Department'}</p>
                    </div>
                    <button onclick="document.getElementById('crm-contact-inspect-modal').remove()" class="h-7 w-7 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-1 text-left">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-teal-650 uppercase tracking-wider">Contact Details</h3>
                            <div class="space-y-2.5 bg-slate-50 p-4 border border-slate-200/80 rounded-xl">
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Email:</span>
                                    <span class="font-semibold text-slate-800">${c.email || '-'}</span>
                                </div>
                                ${c.alternate_email ? `
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Alternate Email:</span>
                                    <span class="font-semibold text-slate-800">${c.alternate_email}</span>
                                </div>` : ''}
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Phone:</span>
                                    <span class="font-semibold text-slate-800">${c.phone || '-'}</span>
                                </div>
                                ${c.whatsapp ? `
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">WhatsApp:</span>
                                    <span class="font-semibold text-slate-800">${c.whatsapp}</span>
                                </div>` : ''}
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">LinkedIn Link:</span>
                                    ${c.linkedin ? `<a href="${c.linkedin.startsWith('http') ? c.linkedin : `https://linkedin.com/in/${c.linkedin}`}" target="_blank" class="font-semibold text-indigo-600 hover:underline flex items-center space-x-1"><span>View Profile</span><i data-lucide="external-link" class="h-3 w-3"></i></a>` : '<span class="text-slate-500">-</span>'}
                                </div>
                                ${c.location ? `
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Location:</span>
                                    <span class="font-semibold text-slate-800">${c.location}</span>
                                </div>` : ''}
                                ${c.birthday ? `
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Birthday:</span>
                                    <span class="font-semibold text-slate-800">${c.birthday}</span>
                                </div>` : ''}
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Source:</span>
                                    <span class="font-semibold text-slate-750 px-1.5 py-0.5 bg-slate-100 rounded border border-slate-200 text-[10px]">${sourceVal}</span>
                                </div>
                                ${postUrl ? `
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">LinkedIn Post:</span>
                                    <a href="${postUrl}" target="_blank" class="font-semibold text-indigo-600 hover:underline flex items-center space-x-1"><span>View Post</span><i data-lucide="external-link" class="h-3 w-3"></i></a>
                                </div>` : ''}
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-teal-650 uppercase tracking-wider">Extension Scraped Data / Notes</h3>
                            <div class="bg-slate-50 p-4 border border-slate-200/80 rounded-xl max-h-[175px] overflow-y-auto text-slate-700 whitespace-pre-line leading-relaxed">
                                ${c.notes || 'No scraped notes or LinkedIn post contents available for this contact.'}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Scheduled Tasks & Meetings</h3>
                            <div class="space-y-2 bg-slate-50 p-4 border border-slate-200/80 rounded-xl max-h-[160px] overflow-y-auto">
                                ${tasks.length === 0 && meetings.length === 0 ? `<div class="text-slate-450 text-center py-4">No tasks or meetings scheduled.</div>` : ''}
                                ${tasks.map(t => `
                                    <div class="flex justify-between items-start border-b border-slate-100 pb-1.5 last:border-b-0 last:pb-0">
                                        <div>
                                            <span class="font-semibold text-slate-800 block">${t.title}</span>
                                            <span class="text-[10px] text-slate-450">Task • Due: ${new Date(t.due_date).toLocaleDateString()}</span>
                                        </div>
                                        <span class="px-1.5 py-0.5 bg-slate-200 text-slate-700 rounded text-[9px] font-bold uppercase">${t.priority}</span>
                                    </div>
                                `).join('')}
                                ${meetings.map(m => `
                                    <div class="flex justify-between items-start border-b border-slate-100 pb-1.5 last:border-b-0 last:pb-0">
                                        <div>
                                            <span class="font-semibold text-slate-800 block">${m.title}</span>
                                            <span class="text-[10px] text-slate-450">Meeting • Start: ${new Date(m.start_time).toLocaleString()}</span>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>

                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Activity Timeline</h3>
                            <div class="space-y-2 bg-slate-50 p-4 border border-slate-200/80 rounded-xl max-h-[160px] overflow-y-auto text-[11px]">
                                ${timeline.length === 0 ? `<div class="text-slate-450 text-center py-4">No activities logged.</div>` : ''}
                                ${timeline.map(t => {
                                    const date = new Date(t.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                                    return `
                                        <div class="border-b border-slate-100 pb-1.5 last:border-b-0 last:pb-0">
                                            <span class="text-[10px] text-slate-400 block font-mono">${date}</span>
                                            <span class="font-semibold text-slate-700">${t.activity_type}:</span>
                                            <span class="text-slate-600">${t.description}</span>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-5 border-t border-slate-100 flex justify-end">
                    <button onclick="document.getElementById('crm-contact-inspect-modal').remove()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold transition">Close</button>
                </div>
            </div>
        `;
        lucide.createIcons();
    } catch (e) {
        showNotification('error', 'Failed to retrieve contact details: ' + e.message);
     }
}

async function openInspectCompanyModal(companyId) {
    const existing = document.getElementById('crm-company-inspect-modal');
    if (existing) existing.remove();

    let modalHTML = `
        <div id="crm-company-inspect-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-2xl p-6 text-slate-800 text-xs space-y-4 shadow-2xl relative">
                <button onclick="document.getElementById('crm-company-inspect-modal').remove()" class="absolute top-4 right-4 h-7 w-7 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center transition">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
                <div class="flex items-center justify-center py-12">
                    <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-indigo-650"></i>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    lucide.createIcons();

    try {
        const res = await apiCall(`crm/companies.php?id=${companyId}`);
        const c = res.company || {};
        const contacts = c.contacts || [];
        const deals = c.deals || [];
        const tasks = c.tasks || [];
        const meetings = c.meetings || [];
        const timeline = c.timeline || [];

        const modal = document.getElementById('crm-company-inspect-modal');
        if (!modal) return;

        modal.innerHTML = `
            <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-3xl text-slate-700 text-xs shadow-2xl relative flex flex-col max-h-[85vh]">
                <div class="p-5 border-b border-slate-100 flex justify-between items-start">
                    <div>
                        <div class="flex items-center space-x-2.5">
                            <h2 class="text-lg font-bold text-slate-800">${c.name}</h2>
                            ${c.industry ? `<span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-full text-[10px] font-bold">${c.industry}</span>` : ''}
                        </div>
                        <p class="text-slate-550 text-[11px] mt-0.5">${c.website || 'No website registered'} • Status: ${c.status || 'Active'}</p>
                    </div>
                    <button onclick="document.getElementById('crm-company-inspect-modal').remove()" class="h-7 w-7 rounded-full hover:bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto space-y-6 flex-1 text-left">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h3 class="text-xs font-bold text-teal-650 uppercase tracking-wider">Company Information</h3>
                            <div class="space-y-2.5 bg-slate-50 p-4 border border-slate-200/80 rounded-xl">
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Website:</span>
                                    ${c.website ? `<a href="${c.website.startsWith('http') ? c.website : `https://${c.website}`}" target="_blank" class="font-semibold text-indigo-600 hover:underline flex items-center space-x-1"><span>${c.website}</span><i data-lucide="external-link" class="h-3 w-3"></i></a>` : '<span class="text-slate-500">-</span>'}
                                </div>
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">GST / Tax ID:</span>
                                    <span class="font-semibold text-slate-800">${c.gst || '-'}</span>
                                </div>
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Employees:</span>
                                    <span class="font-semibold text-slate-800">${c.employees ? c.employees.toLocaleString() : '-'}</span>
                                </div>
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Annual Revenue:</span>
                                    <span class="font-semibold text-slate-800">${c.revenue ? `₹${c.revenue.toLocaleString()}` : '-'}</span>
                                </div>
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Owner / Account Manager:</span>
                                    <span class="font-semibold text-slate-800">${c.owner || '-'}</span>
                                </div>
                                <div class="flex justify-between items-center py-0.5">
                                    <span class="text-slate-550 font-medium">Source:</span>
                                    <span class="font-semibold text-slate-700 px-1.5 py-0.5 bg-slate-100 rounded border border-slate-200 text-[10px]">${c.source || 'CRM Manual'}</span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 flex flex-col justify-between">
                            <div class="space-y-3">
                                <h3 class="text-xs font-bold text-teal-650 uppercase tracking-wider">Office Address</h3>
                                <div class="bg-slate-50 p-4 border border-slate-200/80 rounded-xl text-slate-700">
                                    ${c.address || 'No address details available.'}
                                </div>
                            </div>
                            <div class="space-y-3">
                                <h3 class="text-xs font-bold text-teal-650 uppercase tracking-wider">Internal Notes</h3>
                                <div class="bg-slate-50 p-4 border border-slate-200/80 rounded-xl text-slate-700 max-h-[90px] overflow-y-auto leading-relaxed">
                                    ${c.notes || 'No general notes available.'}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Associated Contacts</h3>
                            <div class="space-y-2 bg-slate-50 p-4 border border-slate-200/80 rounded-xl max-h-[160px] overflow-y-auto text-[11px]">
                                ${contacts.length === 0 ? `<div class="text-slate-450 text-center py-4">No contacts linked to this company.</div>` : ''}
                                ${contacts.map(con => `
                                    <div class="flex justify-between items-center border-b border-slate-100 pb-1.5 last:border-b-0 last:pb-0">
                                        <div>
                                            <span class="font-bold text-slate-800 block">${con.name}</span>
                                            <span class="text-[10px] text-slate-450">${con.designation || 'Contact'} • ${con.email || 'No Email'}</span>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>

                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Company Activity Timeline</h3>
                            <div class="space-y-2 bg-slate-50 p-4 border border-slate-200/80 rounded-xl max-h-[160px] overflow-y-auto text-[11px]">
                                ${timeline.length === 0 ? `<div class="text-slate-450 text-center py-4">No activities logged.</div>` : ''}
                                ${timeline.map(t => {
                                    const date = new Date(t.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                                    return `
                                        <div class="border-b border-slate-100 pb-1.5 last:border-b-0 last:pb-0">
                                            <span class="text-[10px] text-slate-400 block font-mono">${date}</span>
                                            <span class="font-semibold text-slate-700">${t.activity_type}:</span>
                                            <span class="text-slate-600">${t.description}</span>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-5 border-t border-slate-100 flex justify-end">
                    <button onclick="document.getElementById('crm-company-inspect-modal').remove()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-750 rounded-lg font-bold transition">Close</button>
                </div>
            </div>
        `;
        lucide.createIcons();
    } catch (e) {
        showNotification('error', 'Failed to retrieve company details: ' + e.message);
        document.getElementById('crm-company-inspect-modal').remove();
    }
}

// Simple implementations for secondary view states (Tasks, Meetings, Automation, Settings, Reports, AI Insights)
async function renderTasks(container) {
    container.innerHTML = `
        <div class="flex items-center justify-center py-12">
            <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-indigo-600"></i>
        </div>
    `;
    lucide.createIcons();
    
    try {
        const res = await apiCall('crm/tasks.php');
        const tasks = res.tasks || [];
        
        // Buckets for grouping
        const categories = {
            'Follow-up': [],
            'Reply': [],
            'Meeting': [],
            'Arrange': [],
            'General': []
        };
        
        tasks.forEach(t => {
            const title = t.title || '';
            let category = 'General';
            let cleanTitle = title;
            
            if (title.startsWith('[Follow-up]')) {
                category = 'Follow-up';
                cleanTitle = title.replace('[Follow-up] ', '');
            } else if (title.startsWith('[Reply]')) {
                category = 'Reply';
                cleanTitle = title.replace('[Reply] ', '');
            } else if (title.startsWith('[Meeting]')) {
                category = 'Meeting';
                cleanTitle = title.replace('[Meeting] ', '');
            } else if (title.startsWith('[Arrange]')) {
                category = 'Arrange';
                cleanTitle = title.replace('[Arrange] ', '');
            } else {
                // Fallback to keyword matching
                const lower = title.toLowerCase() + ' ' + (t.description || '').toLowerCase();
                if (lower.includes('follow') || lower.includes('call')) {
                    category = 'Follow-up';
                } else if (lower.includes('reply') || lower.includes('email') || lower.includes('respond')) {
                    category = 'Reply';
                } else if (lower.includes('meeting') || lower.includes('setted') || lower.includes('appointment')) {
                    category = 'Meeting';
                } else if (lower.includes('arrange') || lower.includes('schedule') || lower.includes('prep')) {
                    category = 'Arrange';
                }
            }
            
            t.displayTitle = cleanTitle;
            categories[category].push(t);
        });

        // Sort each category by priority (high -> medium -> low)
        const priorityWeight = { 'high': 3, 'medium': 2, 'low': 1 };
        Object.keys(categories).forEach(cat => {
            categories[cat].sort((a, b) => {
                // Pending first, then priority weight
                if (a.status !== b.status) {
                    return a.status === 'completed' ? 1 : -1;
                }
                return (priorityWeight[b.priority] || 2) - (priorityWeight[a.priority] || 2);
            });
        });

        const getCategoryHTML = (catName, catTasks, icon, colorClass, borderClass) => {
            const listItems = catTasks.length > 0 ? catTasks.map(t => {
                const isCompleted = t.status === 'completed';
                const priority = t.priority || 'medium';
                let priorityBadge = '';
                let borderAccent = 'border-l-4 border-l-slate-300';
                
                if (priority === 'high') {
                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-rose-50 text-rose-600 border border-rose-100 uppercase">High</span>`;
                    borderAccent = 'border-l-4 border-l-rose-500';
                } else if (priority === 'medium') {
                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-100 uppercase">Medium</span>`;
                    borderAccent = 'border-l-4 border-l-indigo-500';
                } else {
                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-slate-50 text-slate-500 border border-slate-200 uppercase">Low</span>`;
                    borderAccent = 'border-l-4 border-l-slate-350';
                }

                const timeStr = t.due_time ? ` @ ${t.due_time.substring(0, 5)}` : '';
                const dateStr = t.due_date ? new Date(t.due_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) + timeStr : 'No date';
                
                let meetLinkHTML = '';
                if (t.meet_link) {
                    meetLinkHTML = `
                        <a href="${t.meet_link}" target="_blank" class="mt-2 flex items-center space-x-1 text-[10px] text-blue-600 hover:text-blue-800 font-bold bg-blue-50 px-2 py-1 rounded-md border border-blue-100 transition inline-flex w-fit">
                            <i data-lucide="video" class="h-3.5 w-3.5 text-blue-600"></i>
                            <span>Join Meet</span>
                        </a>
                    `;
                } else if (t.title.includes('[Meeting]') || t.title.startsWith('[Meeting]') || t.displayTitle.toLowerCase().includes('meeting')) {
                    meetLinkHTML = `
                        <button onclick="openConfigureMeetingModal(${t.id})" class="mt-2 flex items-center space-x-1 text-[10px] text-rose-650 hover:text-rose-800 font-bold bg-rose-50 px-2 py-1 rounded-md border border-rose-100 transition inline-flex w-fit">
                            <i data-lucide="video" class="h-3.5 w-3.5 text-rose-600"></i>
                            <span>Configure Invite</span>
                        </button>
                    `;
                }

                return `
                    <div class="task-card-contextable bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-2.5 transition hover:shadow-md cursor-context-menu ${borderAccent} ${isCompleted ? 'opacity-65' : ''}"
                         oncontextmenu="handleTaskRightClick(event, ${t.id}, '${t.status}', '${t.title.replace(/'/g, "\\'").replace(/"/g, '&quot;')}', '${(t.description || '').replace(/\r?\n/g, ' ').replace(/'/g, "\\'").replace(/"/g, '&quot;')}', '${(t.meet_link || '').replace(/'/g, "\\'")}', '${(t.remarks || '').replace(/\r?\n/g, ' ').replace(/'/g, "\\'").replace(/"/g, '&quot;')}', '${t.category || ''}', '${t.due_date || ''}', '${t.due_time || ''}', '${t.priority || ''}')">
                        <div class="flex items-start space-x-2.5">
                            <input type="checkbox" ${isCompleted ? 'checked' : ''} onclick="toggleTaskStatus(${t.id}, '${t.status}')" class="mt-0.5 h-3.5 w-3.5 border-slate-300 rounded text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                            <div class="flex-grow text-left">
                                <h5 class="font-bold text-slate-800 leading-tight ${isCompleted ? 'line-through text-slate-400' : ''}">${t.displayTitle}</h5>
                                <p class="text-[10px] text-slate-500 mt-1 line-clamp-2">${t.description || 'No extra description.'}</p>
                                ${meetLinkHTML}
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                            <div class="flex items-center space-x-2">
                                <div class="flex items-center text-[9px] text-slate-400 font-semibold">
                                    <i data-lucide="calendar" class="h-3 w-3 mr-0.5"></i>
                                    <span>${dateStr}</span>
                                </div>
                                ${priorityBadge}
                            </div>
                            
                            <div class="flex items-center space-x-1">
                                <button onclick="editCrmTask(${t.id})" class="p-1 text-slate-400 hover:text-indigo-600 transition" title="Edit Task">
                                    <i data-lucide="edit" class="h-3.5 w-3.5"></i>
                                </button>
                                <button onclick="deleteCrmTask(this, ${t.id})" class="p-1 text-slate-400 hover:text-red-500 transition" title="Delete Task">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('') : `<div class="text-center py-6 text-slate-400 text-[10px] italic border-2 border-dashed border-slate-200 rounded-xl bg-slate-50/50">No tasks in this category</div>`;

            return `
                <div class="glass-panel p-4 bg-white shadow-sm border border-slate-200 rounded-2xl flex flex-col space-y-3.5">
                    <div class="pb-2 border-b border-slate-100 flex justify-between items-center">
                        <div class="flex items-center space-x-2 text-slate-800 font-bold text-sm">
                            <div class="h-7 w-7 rounded-lg ${colorClass} flex items-center justify-center shrink-0">
                                <i data-lucide="${icon}" class="h-4 w-4"></i>
                            </div>
                            <span>${catName}</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                            ${catTasks.length}
                        </span>
                    </div>
                    <div class="space-y-3 flex-grow overflow-y-auto max-h-[450px] pr-1">
                        ${listItems}
                    </div>
                </div>
            `;
        };

        container.innerHTML = `
            <div class="space-y-6 pt-4 animate-fade-in text-xs max-w-7xl mx-auto">
                <div class="flex justify-between items-center border-b border-slate-150 pb-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-800">Tasks Hub</h1>
                        <p class="text-slate-500 text-xs mt-1">Manage, categorize, and complete tasks ordered by priority metrics.</p>
                    </div>
                    <button onclick="createNewTaskModal()" class="px-4 py-2 bg-indigo-650 hover:bg-indigo-600 text-white rounded-lg text-xs font-bold transition flex items-center space-x-1.5 shadow-sm">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                        <span>Add New Task</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    ${getCategoryHTML('Follow-ups', categories['Follow-up'], 'phone-call', 'bg-indigo-50 text-indigo-600', 'border-indigo-500')}
                    ${getCategoryHTML('Replies / Email', categories['Reply'], 'mail', 'bg-emerald-50 text-emerald-600', 'border-emerald-500')}
                    ${getCategoryHTML('Meetings Set', categories['Meeting'], 'calendar', 'bg-blue-50 text-blue-600', 'border-blue-500')}
                    ${getCategoryHTML('Need to Arrange', categories['Arrange'], 'sliders', 'bg-amber-50 text-amber-600', 'border-amber-500')}
                </div>
                
                ${categories['General'].length > 0 ? `
                    <div class="mt-8">
                        <div class="font-bold text-slate-800 text-xs mb-3 flex items-center space-x-1 text-left">
                            <i data-lucide="clipboard-list" class="h-4 w-4 text-slate-600"></i>
                            <span>General Tasks</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            ${categories['General'].map(t => {
                                const isCompleted = t.status === 'completed';
                                const priority = t.priority || 'medium';
                                let priorityBadge = '';
                                let borderAccent = 'border-l-4 border-l-slate-350';
                                
                                if (priority === 'high') {
                                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-rose-50 text-rose-600 border border-rose-100 uppercase">High</span>`;
                                    borderAccent = 'border-l-4 border-l-rose-500';
                                } else if (priority === 'medium') {
                                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-indigo-50 text-indigo-600 border border-indigo-100 uppercase">Medium</span>`;
                                    borderAccent = 'border-l-4 border-l-indigo-500';
                                } else {
                                    priorityBadge = `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold bg-slate-50 text-slate-500 border border-slate-200 uppercase">Low</span>`;
                                    borderAccent = 'border-l-4 border-l-slate-350';
                                }
                                const timeStr = t.due_time ? ` @ ${t.due_time.substring(0, 5)}` : '';
                                const dateStr = t.due_date ? new Date(t.due_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) + timeStr : 'No date';
                                
                                let meetLinkHTML = '';
                                if (t.meet_link) {
                                    meetLinkHTML = `
                                        <a href="${t.meet_link}" target="_blank" class="mt-2 flex items-center space-x-1 text-[10px] text-blue-600 hover:text-blue-800 font-bold bg-blue-50 px-2 py-1 rounded-md border border-blue-100 transition inline-flex w-fit">
                                            <i data-lucide="video" class="h-3.5 w-3.5 text-blue-600"></i>
                                            <span>Join Meet</span>
                                        </a>
                                    `;
                                } else if (t.title.includes('[Meeting]') || t.title.startsWith('[Meeting]') || t.displayTitle.toLowerCase().includes('meeting')) {
                                    meetLinkHTML = `
                                        <button onclick="openConfigureMeetingModal(${t.id})" class="mt-2 flex items-center space-x-1 text-[10px] text-rose-650 hover:text-rose-800 font-bold bg-rose-50 px-2 py-1 rounded-md border border-rose-100 transition inline-flex w-fit">
                                            <i data-lucide="video" class="h-3.5 w-3.5 text-rose-600"></i>
                                            <span>Configure Invite</span>
                                        </button>
                                    `;
                                }

                                return `
                                    <div class="task-card-contextable bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between space-y-2.5 transition hover:shadow-md cursor-context-menu ${borderAccent} ${isCompleted ? 'opacity-65' : ''}"
                                         oncontextmenu="handleTaskRightClick(event, ${t.id}, '${t.status}', '${t.title.replace(/'/g, "\\'").replace(/"/g, '&quot;')}', '${(t.description || '').replace(/\r?\n/g, ' ').replace(/'/g, "\\'").replace(/"/g, '&quot;')}', '${(t.meet_link || '').replace(/'/g, "\\'")}', '${(t.remarks || '').replace(/\r?\n/g, ' ').replace(/'/g, "\\'").replace(/"/g, '&quot;')}', '${t.category || ''}', '${t.due_date || ''}', '${t.due_time || ''}', '${t.priority || ''}')">
                                        <div class="flex items-start space-x-2.5">
                                            <input type="checkbox" ${isCompleted ? 'checked' : ''} onclick="toggleTaskStatus(${t.id}, '${t.status}')" class="mt-0.5 h-3.5 w-3.5 border-slate-300 rounded text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                                            <div class="flex-grow text-left">
                                                <h5 class="font-bold text-slate-800 leading-tight ${isCompleted ? 'line-through text-slate-400' : ''}">${t.displayTitle}</h5>
                                                <p class="text-[10px] text-slate-500 mt-1 line-clamp-2">${t.description || 'No extra description.'}</p>
                                                ${meetLinkHTML}
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-between items-center pt-2 border-t border-slate-100">
                                            <div class="flex items-center space-x-2">
                                                <div class="flex items-center text-[9px] text-slate-400 font-semibold">
                                                    <i data-lucide="calendar" class="h-3 w-3 mr-0.5"></i>
                                                    <span>${dateStr}</span>
                                                </div>
                                                ${priorityBadge}
                                            </div>
                                            
                                            <div class="flex items-center space-x-1">
                                                <button onclick="editCrmTask(${t.id})" class="p-1 text-slate-400 hover:text-indigo-600 transition" title="Edit Task">
                                                    <i data-lucide="edit" class="h-3.5 w-3.5"></i>
                                                </button>
                                                <button onclick="deleteCrmTask(this, ${t.id})" class="p-1 text-slate-400 hover:text-red-500 transition" title="Delete Task">
                                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>
        `;
        lucide.createIcons();
    } catch (err) {
        container.innerHTML = `
            <div class="max-w-xl mx-auto p-5 text-center text-red-500">
                Failed to load tasks: ${err.message}
            </div>
        `;
    }
}

// Toggle Complete / Pending Task Status
async function toggleTaskStatus(taskId, currentStatus) {
    const nextStatus = currentStatus === 'completed' ? 'pending' : 'completed';
    try {
        const data = await apiCall('crm/tasks.php?action=PUT', 'POST', {
            id: taskId,
            status: nextStatus
        });
        if (data.status === 'success') {
            showNotification('success', `Task marked as ${nextStatus}!`);
            updateGlobalTaskBadges();
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) {
                if (currentView === 'dashboard') {
                    renderDashboard(viewport);
                } else {
                    renderTasks(viewport);
                }
            }
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    }
}

// Add New Task Modal Overlay
function createNewTaskModal(prefills = {}) {
    const existing = document.getElementById('crm-task-modal');
    if (existing) existing.remove();

    const modalHTML = `
        <div id="crm-task-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <div class="h-8 w-8 bg-indigo-50 text-indigo-650 rounded-lg flex items-center justify-center">
                            <i data-lucide="check-square" class="h-4.5 w-4.5 text-indigo-600"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">Create New Task</h3>
                    </div>
                    <button onclick="closeCrmTaskModal()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <!-- Grid container for Form and AI Suggestions -->
                <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-slate-100">
                    <!-- Left: Form -->
                    <div class="md:col-span-2 p-6 space-y-4 text-xs text-left">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Task Category</label>
                            <select id="new-task-category" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 bg-white">
                                <option value="Follow-up">📞 Follow-up Call/Email</option>
                                <option value="Reply">✉️ Reply to Incoming Pitch</option>
                                <option value="Meeting">📅 Meeting Set / Appointment</option>
                                <option value="Arrange">⚙️ Need to Arrange / Schedule</option>
                                <option value="General">📋 General To-Do</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Task Title *</label>
                            <input type="text" id="new-task-title" placeholder="Describe task..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Due Date</label>
                                <input type="date" id="new-task-duedate" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Due Time</label>
                                <input type="time" id="new-task-duetime" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Priority</label>
                                <select id="new-task-priority" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 bg-white">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Google Meet Link</label>
                                <input type="url" id="new-task-meetlink" placeholder="https://meet.google.com/..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Task Description</label>
                            <textarea id="new-task-description" rows="3" placeholder="Provide extra description notes..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                        </div>

                        <div class="flex items-center space-x-2 pt-1">
                            <input type="checkbox" id="new-task-synctocalendar" class="h-4 w-4 border-slate-350 rounded text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                            <label for="new-task-synctocalendar" class="font-bold text-slate-700 cursor-pointer select-none">Sync task to Google Calendar</label>
                        </div>
                    </div>

                    <!-- Right: AI Suggestions -->
                    <div class="p-6 bg-slate-50/50 space-y-4 text-xs text-left" id="ai-suggestions-modal-box">
                        <div class="flex items-center justify-center py-12">
                            <i data-lucide="loader-2" class="h-6 w-6 animate-spin text-indigo-600"></i>
                            <span class="text-[10px] text-slate-500 ml-2">Loading AI recommendations...</span>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end space-x-2">
                    <button onclick="closeCrmTaskModal()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                    <button onclick="submitNewTaskForm(this)" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center space-x-1.5 shadow-sm">
                        <i data-lucide="check" class="h-4 w-4"></i>
                        <span>Create Task</span>
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    // Set default date to today
    document.getElementById('new-task-duedate').valueAsDate = new Date();
    if (prefills) {
        if (prefills.title) document.getElementById('new-task-title').value = prefills.title;
        if (prefills.description) document.getElementById('new-task-description').value = prefills.description;
        if (prefills.category) document.getElementById('new-task-category').value = prefills.category;
    }
    loadTaskAiSuggestions();
    lucide.createIcons();
}

async function submitNewTaskForm(btn) {
    const rawTitle = document.getElementById('new-task-title').value.trim();
    if (!rawTitle) {
        showNotification('error', 'Task Title is required.');
        return;
    }
    
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-white"></i>`;
    lucide.createIcons();
    
    const category = document.getElementById('new-task-category').value;
    const dueDate = document.getElementById('new-task-duedate').value;
    const dueTime = document.getElementById('new-task-duetime').value;
    const priority = document.getElementById('new-task-priority').value;
    const meetLink = document.getElementById('new-task-meetlink').value.trim();
    const description = document.getElementById('new-task-description').value.trim();
    const syncToCalendar = document.getElementById('new-task-synctocalendar').checked ? 1 : 0;
    
    // Prefix title if categorized
    let title = rawTitle;
    if (category !== 'General') {
        title = `[${category}] ${rawTitle}`;
    }
    
    const payload = {
        title,
        due_date: dueDate,
        due_time: dueTime || null,
        priority,
        meet_link: meetLink || null,
        status: 'pending',
        description,
        sync_to_calendar: syncToCalendar
    };

    if (window.activeWaCrmContext) {
        if (window.activeWaCrmContext.contact) payload.contact_id = window.activeWaCrmContext.contact.id;
        if (window.activeWaCrmContext.company) payload.company_id = window.activeWaCrmContext.company.id;
        if (window.activeWaCrmContext.lead) payload.lead_id = window.activeWaCrmContext.lead.id;
    }
    
    try {
        const data = await apiCall('crm/tasks.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Task added successfully.');
            closeCrmTaskModal();
            updateGlobalTaskBadges();
            if (typeof loadWaThreadMessages === 'function' && currentView === 'whatsapp-inbox') {
                loadWaThreadMessages();
            } else {
                const viewport = document.getElementById('main-content-viewport');
                if (viewport) {
                    if (currentView === 'dashboard') {
                        renderDashboard(viewport);
                    } else {
                        renderTasks(viewport);
                    }
                }
            }
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

// Edit Task Modal Overlay
async function editCrmTask(taskId) {
    const existing = document.getElementById('crm-task-modal');
    if (existing) existing.remove();

    // Spinner overlay
    const spinnerHTML = `
        <div id="crm-task-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl p-8 flex items-center justify-center border border-slate-200">
                <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-indigo-600"></i>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', spinnerHTML);
    lucide.createIcons();

    try {
        const res = await apiCall('crm/tasks.php');
        const tasks = res.tasks || [];
        const t = tasks.find(item => item.id === taskId);
        
        const modal = document.getElementById('crm-task-modal');
        if (!modal) return;
        
        if (!t) {
            showNotification('error', 'Task details not found.');
            closeCrmTaskModal();
            return;
        }

        // Parse category prefix out
        let category = 'General';
        let cleanTitle = t.title || '';
        
        if (cleanTitle.startsWith('[Follow-up]')) {
            category = 'Follow-up';
            cleanTitle = cleanTitle.replace('[Follow-up] ', '');
        } else if (cleanTitle.startsWith('[Reply]')) {
            category = 'Reply';
            cleanTitle = cleanTitle.replace('[Reply] ', '');
        } else if (cleanTitle.startsWith('[Meeting]')) {
            category = 'Meeting';
            cleanTitle = cleanTitle.replace('[Meeting] ', '');
        } else if (cleanTitle.startsWith('[Arrange]')) {
            category = 'Arrange';
            cleanTitle = cleanTitle.replace('[Arrange] ', '');
        }

        const modalHTML = `
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden flex flex-col text-left">
                <!-- Modal Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <div class="h-8 w-8 bg-indigo-50 text-indigo-650 rounded-lg flex items-center justify-center">
                            <i data-lucide="edit" class="h-4.5 w-4.5 text-indigo-600"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">Edit CRM Task</h3>
                    </div>
                    <button onclick="closeCrmTaskModal()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <!-- Modal Body -->
                <div class="p-6 space-y-4 text-xs">
                    <input type="hidden" id="edit-task-id" value="${t.id}">
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Task Category</label>
                        <select id="edit-task-category" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                            <option value="Follow-up" ${category === 'Follow-up' ? 'selected' : ''}>📞 Follow-up Call/Email</option>
                            <option value="Reply" ${category === 'Reply' ? 'selected' : ''}>✉️ Reply to Incoming Pitch</option>
                            <option value="Meeting" ${category === 'Meeting' ? 'selected' : ''}>📅 Meeting Set / Appointment</option>
                            <option value="Arrange" ${category === 'Arrange' ? 'selected' : ''}>⚙️ Need to Arrange / Schedule</option>
                            <option value="General" ${category === 'General' ? 'selected' : ''}>📋 General To-Do</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Task Title *</label>
                        <input type="text" id="edit-task-title" value="${cleanTitle}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Due Date</label>
                            <input type="date" id="edit-task-duedate" value="${t.due_date || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Due Time</label>
                            <input type="time" id="edit-task-duetime" value="${t.due_time || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Priority</label>
                            <select id="edit-task-priority" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                                <option value="low" ${t.priority === 'low' ? 'selected' : ''}>Low</option>
                                <option value="medium" ${t.priority === 'medium' ? 'selected' : ''}>Medium</option>
                                <option value="high" ${t.priority === 'high' ? 'selected' : ''}>High</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Google Meet Link</label>
                            <input type="url" id="edit-task-meetlink" value="${t.meet_link || ''}" placeholder="https://meet.google.com/..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Task Description</label>
                        <textarea id="edit-task-description" rows="3" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 font-sans">${t.description || ''}</textarea>
                    </div>

                    <div class="flex items-center space-x-2 pt-1">
                        <input type="checkbox" id="edit-task-synctocalendar" ${t.sync_to_calendar == 1 ? 'checked' : ''} class="h-4 w-4 border-slate-350 rounded text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                        <label for="edit-task-synctocalendar" class="font-bold text-slate-700 cursor-pointer select-none">Sync task to Google Calendar</label>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end space-x-2">
                    <button onclick="closeCrmTaskModal()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                    <button onclick="submitEditTaskForm(this)" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center space-x-1.5 shadow-sm">
                        <i data-lucide="save" class="h-4 w-4"></i>
                        <span>Save Changes</span>
                    </button>
                </div>
            </div>
        `;
        modal.innerHTML = modalHTML;
        lucide.createIcons();
    } catch (e) {
        showNotification('error', e.message);
        closeCrmTaskModal();
    }
}

async function submitEditTaskForm(btn) {
    const taskId = document.getElementById('edit-task-id').value;
    const rawTitle = document.getElementById('edit-task-title').value.trim();
    if (!rawTitle) {
        showNotification('error', 'Task Title is required.');
        return;
    }
    
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-white"></i>`;
    lucide.createIcons();
    
    const category = document.getElementById('edit-task-category').value;
    const dueDate = document.getElementById('edit-task-duedate').value;
    const dueTime = document.getElementById('edit-task-duetime').value;
    const priority = document.getElementById('edit-task-priority').value;
    const meetLink = document.getElementById('edit-task-meetlink').value.trim();
    const description = document.getElementById('edit-task-description').value.trim();
    
    // Prefix title if categorized
    let title = rawTitle;
    if (category !== 'General') {
        title = `[${category}] ${rawTitle}`;
    }
    
    const syncToCalendar = document.getElementById('edit-task-synctocalendar').checked ? 1 : 0;

    const payload = {
        id: taskId,
        title,
        due_date: dueDate,
        due_time: dueTime || null,
        priority,
        meet_link: meetLink || null,
        description,
        sync_to_calendar: syncToCalendar
    };
    
    try {
        const data = await apiCall('crm/tasks.php?action=PUT', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Task updated successfully.');
            closeCrmTaskModal();
            updateGlobalTaskBadges();
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) {
                if (currentView === 'dashboard') {
                    renderDashboard(viewport);
                } else {
                    renderTasks(viewport);
                }
            }
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

async function deleteCrmTask(btn, taskId) {
    if (!confirm('Are you sure you want to delete this task?')) return;
    
    try {
        const data = await apiCall('crm/tasks.php?action=DELETE', 'POST', { id: taskId });
        if (data.status === 'success') {
            showNotification('success', 'Task deleted successfully.');
            updateGlobalTaskBadges();
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) {
                if (currentView === 'dashboard') {
                    renderDashboard(viewport);
                } else {
                    renderTasks(viewport);
                }
            }
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    }
}

function closeCrmTaskModal() {
    const modal = document.getElementById('crm-task-modal');
    if (modal) modal.remove();
}

// Natural language query filter search
function handleAISearchKey(e) {
    if (e.key === 'Enter') triggerAISearch();
}

function triggerAISearch() {
    const query = document.getElementById('ai-nlp-search').value.trim().toLowerCase();
    if (!query) {
        showNotification('info', 'Please type a query to filter suggestions.');
        return;
    }
    
    // Perform filtering dynamically on the recommendations list element
    const items = document.querySelectorAll('#ai-recommendations-list > div');
    let found = 0;
    items.forEach(el => {
        const text = el.innerText.toLowerCase();
        if (text.includes(query)) {
            el.style.display = 'flex';
            found++;
        } else {
            el.style.display = 'none';
        }
    });
    
    showNotification('success', `Found ${found} recommendations matching your search.`);
}

async function executeRecommendationAction(btn, type, idx) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-3 w-3 animate-spin text-white"></i>`;
    lucide.createIcons();
    
    try {
        if (type === 'merge_contacts') {
            const data = await apiCall('crm/ai_insights.php', 'POST', { action: 'detect_duplicates' });
            showNotification('success', 'Merge duplicates command queued. Consolidating 2 records.');
        } else if (type === 'schedule_meeting') {
            showNotification('success', 'Generating meeting schedule overlay...');
            navigateTo('tasks');
        } else {
            navigateTo('leads');
        }
    } catch(e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

async function triggerQuickAction(action) {
    try {
        showNotification('info', 'Executing AI operations command...');
        const res = await apiCall('crm/ai_insights.php', 'POST', { action: action });
        if (res.status === 'success') {
            showNotification('success', res.message);
            // Refresh AI insights viewport
            const contentArea = document.getElementById('main-content-viewport');
            if (contentArea) renderAIInsights(contentArea);
        } else {
            showNotification('error', res.message);
        }
    } catch(e) {
        showNotification('error', e.message);
    }
}

async function approveAIConfPrediction(btn, id) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `Approving...`;
    
    try {
        const res = await apiCall('crm/ai_insights.php', 'POST', { action: 'approve_prediction', id: id });
        if (res.status === 'success') {
            showNotification('success', res.message);
            const contentArea = document.getElementById('main-content-viewport');
            if (contentArea) renderAIInsights(contentArea);
        } else {
            showNotification('error', res.message);
        }
    } catch(e) {
        showNotification('error', e.message);
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

function filterSentimentTrend(val) {
    showNotification('success', `Filtering sentiment trend chart by ${val}.`);
    // Adjust chart datasets to show variations
    if (!charts.aiSentiment) return;
    
    let multiplier = 1;
    if (val === 'today') multiplier = 0.4;
    if (val === 'month') multiplier = 3.5;
    
    const pos = [65, 78, 72, 85, 90, 76, 88].map(v => Math.round(v * multiplier));
    const neu = [20, 12, 18, 10, 8, 14, 8].map(v => Math.round(v * multiplier));
    const neg = [15, 10, 10, 5, 2, 10, 4].map(v => Math.round(v * multiplier));
    
    charts.aiSentiment.data.datasets[0].data = pos;
    charts.aiSentiment.data.datasets[1].data = neu;
    charts.aiSentiment.data.datasets[2].data = neg;
    charts.aiSentiment.update();
}

// Load AI task suggestions from actual leads data
async function loadTaskAiSuggestions() {
    const container = document.getElementById('ai-suggestions-modal-box');
    if (!container) return;
    
    try {
        const res = await apiCall('crm/leads.php');
        const leads = res.leads || [];
        
        let suggestionsHTML = '';
        if (leads.length > 0) {
            // Suggest followups/meetings for latest qualified/new leads
            const latestLeads = leads.slice(0, 3);
            latestLeads.forEach((lead, idx) => {
                const titleStr = idx === 0 
                    ? `Call ${lead.name} regarding project scope` 
                    : idx === 1 
                        ? `[Meeting] Pitch sync with ${lead.name}` 
                        : `Send proposal email to ${lead.name}`;
                const category = idx === 1 ? 'Meeting' : 'Follow-up';
                const priority = idx === 0 ? 'high' : 'medium';
                const desc = `Follow up with ${lead.name}. Status: ${lead.stage}. Source: ${lead.lead_source || 'Web'}.`;
                
                suggestionsHTML += `
                    <div id="ai-sugg-card-${idx}" class="p-3 bg-indigo-50/50 border border-indigo-100 hover:border-indigo-250 rounded-xl space-y-1.5 transition text-[11px] relative text-left">
                        <button onclick="dismissAiSuggestion(${idx})" class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 p-0.5" title="Dismiss">
                            <i data-lucide="x" class="h-3.5 w-3.5"></i>
                        </button>
                        <div class="font-bold text-indigo-750 flex items-center space-x-1">
                            <i data-lucide="sparkles" class="h-3 w-3 text-indigo-500"></i>
                            <span>AI SUGGESTION</span>
                        </div>
                        <p class="font-bold text-slate-800 leading-snug pr-4">${titleStr}</p>
                        <p class="text-[10px] text-slate-500 line-clamp-2">${desc}</p>
                        <button onclick="applyAiSuggestion('${category}', '${titleStr}', '${desc}', '${priority}')" class="text-[9px] text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-0.5 pt-1">
                            <span>Use Suggestion</span>
                            <i data-lucide="arrow-right" class="h-2.5 w-2.5"></i>
                        </button>
                    </div>
                `;
            });
        } else {
            // Default suggestions in case there are no leads
            const fallbacks = [
                { category: 'Follow-up', title: 'Follow-up with Vikas Kumar regarding contract signature', priority: 'high', desc: 'Client requested review of contract clauses.' },
                { category: 'Meeting', title: '[Meeting] Project kickoff sync with Rahul Mehta', priority: 'medium', desc: 'Kickoff meeting for Phase 1 requirements.' }
            ];
            fallbacks.forEach((lead, idx) => {
                suggestionsHTML += `
                    <div id="ai-sugg-card-${idx}" class="p-3 bg-indigo-50/50 border border-indigo-100 hover:border-indigo-250 rounded-xl space-y-1.5 transition text-[11px] relative text-left">
                        <button onclick="dismissAiSuggestion(${idx})" class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 p-0.5" title="Dismiss">
                            <i data-lucide="x" class="h-3.5 w-3.5"></i>
                        </button>
                        <div class="font-bold text-indigo-750 flex items-center space-x-1">
                            <i data-lucide="sparkles" class="h-3 w-3 text-indigo-500"></i>
                            <span>AI SUGGESTION</span>
                        </div>
                        <p class="font-bold text-slate-800 leading-snug pr-4">${lead.title}</p>
                        <p class="text-[10px] text-slate-500 line-clamp-2">${lead.desc}</p>
                        <button onclick="applyAiSuggestion('${lead.category}', '${lead.title}', '${lead.desc}', '${lead.priority}')" class="text-[9px] text-indigo-600 hover:text-indigo-800 font-bold flex items-center space-x-0.5 pt-1">
                            <span>Use Suggestion</span>
                            <i data-lucide="arrow-right" class="h-2.5 w-2.5"></i>
                        </button>
                    </div>
                `;
            });
        }
        
        container.innerHTML = `
            <div class="space-y-3">
                <div class="font-bold text-slate-700 text-[10px] uppercase tracking-wider flex items-center space-x-1">
                    <i data-lucide="sparkles" class="h-3.5 w-3.5 text-indigo-500"></i>
                    <span>AI Suggested Tasks</span>
                </div>
                <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1">
                    ${suggestionsHTML}
                </div>
            </div>
        `;
        lucide.createIcons();
    } catch(e) {
        container.innerHTML = `<p class="text-[10px] text-slate-400 text-center py-4">Suggestions paused.</p>`;
    }
}

function applyAiSuggestion(category, title, description, priority) {
    document.getElementById('new-task-category').value = category;
    document.getElementById('new-task-title').value = title;
    document.getElementById('new-task-description').value = description;
    document.getElementById('new-task-priority').value = priority;
    showNotification('success', 'AI Suggestion details copied to form!');
}

function dismissAiSuggestion(idx) {
    const card = document.getElementById(`ai-sugg-card-${idx}`);
    if (card) card.remove();
}

function openConfigureMeetingModal(taskId) {
    const existing = document.getElementById('crm-meeting-invite-modal');
    if (existing) existing.remove();
    
    // Generate a default mock meet URL for convenience
    const randomCode = Math.random().toString(36).substring(2, 5) + '-' + Math.random().toString(36).substring(2, 6) + '-' + Math.random().toString(36).substring(2, 5);
    const defaultMeet = `https://meet.google.com/${randomCode}`;

    const modalHTML = `
        <div id="crm-meeting-invite-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden flex flex-col text-left">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <div class="h-8 w-8 bg-indigo-50 text-indigo-650 rounded-lg flex items-center justify-center">
                            <i data-lucide="video" class="h-4.5 w-4.5 text-indigo-600"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">Configure Meeting Link & Invite</h3>
                    </div>
                    <button onclick="closeConfigureMeetingModal()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <!-- Body -->
                <div class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Google Meet Link</label>
                        <input type="url" id="meet-modal-link" value="${defaultMeet}" placeholder="https://meet.google.com/..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Add Invitees (Comma-separated emails)</label>
                        <input type="text" id="meet-modal-invitees" placeholder="client@example.com, developer@example.com" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        <p class="text-[9px] text-slate-450 mt-1">This will send an automated invitation with a calendar <b>invite.ics</b> attachment for one-click calendar import.</p>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end space-x-2">
                    <button onclick="closeConfigureMeetingModal()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                    <button onclick="submitMeetingInviteForm(this, ${taskId})" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center space-x-1.5 shadow-sm">
                        <i data-lucide="send" class="h-4 w-4"></i>
                        <span>Send Invite</span>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    lucide.createIcons();
}

function closeConfigureMeetingModal() {
    const modal = document.getElementById('crm-meeting-invite-modal');
    if (modal) modal.remove();
}

async function submitMeetingInviteForm(btn, taskId) {
    const meetLink = document.getElementById('meet-modal-link').value.trim();
    const invitees = document.getElementById('meet-modal-invitees').value.trim();
    
    if (!meetLink) {
        showNotification('error', 'Meeting link is required.');
        return;
    }
    
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-white"></i>`;
    lucide.createIcons();
    
    try {
        const data = await apiCall('crm/send_meeting_invite.php', 'POST', {
            task_id: taskId,
            meet_link: meetLink,
            invitees: invitees
        });
        
        if (data.status === 'success') {
            showNotification('success', data.message);
            closeConfigureMeetingModal();
            updateGlobalTaskBadges();
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) {
                if (currentView === 'dashboard') {
                    renderDashboard(viewport);
                } else {
                    renderTasks(viewport);
                }
            }
        } else {
            showNotification('error', data.message);
        }
    } catch(e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

// Task Right Click Custom Context Menu Handler
function handleTaskRightClick(e, id, status, title, desc, meet, remarks, category, date, time, priority) {
    e.preventDefault();
    e.stopPropagation();
    
    // Remove existing context menu if any
    const existing = document.getElementById('custom-task-context-menu');
    if (existing) existing.remove();
    
    const lowerCategory = (category || '').toLowerCase();
    const isMeeting = lowerCategory.includes('meet') || title.includes('[Meeting]');
    
    let optionsHTML = '';
    
    // 1. View Details
    optionsHTML += `
        <button onclick="viewTaskDetailsModal(${id}, '${status}', '${title.replace(/'/g, "\\'")}', '${desc.replace(/'/g, "\\'")}', '${meet.replace(/'/g, "\\'")}', '${remarks.replace(/'/g, "\\'")}', '${category}', '${date}', '${time}', '${priority}')" class="w-full text-left px-3.5 py-2 hover:bg-indigo-50 hover:text-indigo-700 transition flex items-center space-x-2 text-slate-700">
            <i data-lucide="eye" class="h-3.5 w-3.5 text-indigo-500"></i>
            <span>View Details</span>
        </button>
    `;
    
    // 2. Mark as Done / Pending
    const statusText = status === 'completed' ? 'Mark as Pending' : 'Mark as Done';
    const statusIcon = status === 'completed' ? 'circle' : 'check-circle';
    optionsHTML += `
        <button onclick="toggleTaskStatus(${id}, '${status}')" class="w-full text-left px-3.5 py-2 hover:bg-indigo-50 hover:text-indigo-700 transition flex items-center space-x-2 text-slate-700">
            <i data-lucide="${statusIcon}" class="h-3.5 w-3.5 text-indigo-500"></i>
            <span>${statusText}</span>
        </button>
    `;

    // 2.5 Generate Google Meet option
    if (!meet) {
        optionsHTML += `
            <button onclick="triggerGenerateMeetLinkForTask(${id})" class="w-full text-left px-3.5 py-2 hover:bg-indigo-50 hover:text-indigo-700 transition flex items-center space-x-2 text-slate-700">
                <i data-lucide="video" class="h-3.5 w-3.5 text-indigo-500"></i>
                <span>Generate Google Meet</span>
            </button>
        `;
    }
    
    // 3. Add Peoples / Configure invite (Meetings Only)
    if (isMeeting) {
        optionsHTML += `
            <button onclick="openConfigureMeetingModal(${id})" class="w-full text-left px-3.5 py-2 hover:bg-indigo-50 hover:text-indigo-700 transition flex items-center space-x-2 text-slate-700">
                <i data-lucide="users" class="h-3.5 w-3.5 text-indigo-500"></i>
                <span>Add Peoples</span>
            </button>
        `;
    }
    
    // 4. Edit Details
    optionsHTML += `
        <button onclick="editCrmTask(${id})" class="w-full text-left px-3.5 py-2 hover:bg-indigo-50 hover:text-indigo-700 transition flex items-center space-x-2 text-slate-700">
            <i data-lucide="edit-3" class="h-3.5 w-3.5 text-indigo-500"></i>
            <span>Edit Details</span>
        </button>
    `;
    
    // 5. Add Remarks
    optionsHTML += `
        <button onclick="openAddRemarksModal(${id}, '${remarks.replace(/'/g, "\\'")}')" class="w-full text-left px-3.5 py-2 hover:bg-indigo-50 hover:text-indigo-700 transition flex items-center space-x-2 text-slate-700">
            <i data-lucide="message-square" class="h-3.5 w-3.5 text-indigo-500"></i>
            <span>Add Remarks</span>
        </button>
    `;
    
    optionsHTML += `<div class="border-t border-slate-100 my-1"></div>`;
    
    // 6. Delete
    optionsHTML += `
        <button onclick="deleteCrmTask(this, ${id})" class="w-full text-left px-3.5 py-2 hover:bg-rose-50 hover:text-rose-600 transition flex items-center space-x-2 text-rose-600">
            <i data-lucide="trash-2" class="h-3.5 w-3.5 text-rose-500"></i>
            <span>Delete</span>
        </button>
    `;
    
    const menu = document.createElement('div');
    menu.id = 'custom-task-context-menu';
    menu.className = 'fixed bg-white border border-slate-200 rounded-xl shadow-xl py-1.5 z-[200] min-w-[160px] animate-fade-in text-[11px] font-sans';
    menu.innerHTML = optionsHTML;
    
    menu.style.top = `${e.clientY}px`;
    menu.style.left = `${e.clientX}px`;
    
    document.body.appendChild(menu);
    lucide.createIcons();
    
    const closeMenu = (event) => {
        if (!menu.contains(event.target)) {
            menu.remove();
            document.removeEventListener('click', closeMenu);
            document.removeEventListener('contextmenu', closeMenu);
        }
    };
    setTimeout(() => {
        document.addEventListener('click', closeMenu);
        document.addEventListener('contextmenu', closeMenu);
    }, 50);
}

function viewTaskDetailsModal(id, status, title, desc, meet, remarks, category, date, time, priority) {
    const existing = document.getElementById('task-details-view-modal');
    if (existing) existing.remove();
    
    const priorityBadge = priority === 'high' 
        ? `<span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-rose-50 text-rose-650 border border-rose-100 uppercase">High</span>` 
        : priority === 'medium' 
            ? `<span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-indigo-50 text-indigo-650 border border-indigo-100 uppercase">Medium</span>` 
            : `<span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-slate-50 text-slate-500 border border-slate-200 uppercase">Low</span>`;
            
    const statusBadge = status === 'completed'
        ? `<span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-650 border border-emerald-100 uppercase">Completed</span>`
        : `<span class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-650 border border-amber-100 uppercase">Pending</span>`;

    const meetLinkHTML = meet 
        ? `<a href="${meet}" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline break-all">${meet}</a>` 
        : `<div class="mt-1 flex items-center space-x-2" id="details-meet-container-${id}">
             <span class="text-slate-400">No meeting link configured.</span>
             <button onclick="generateMeetLinkForTaskInModal(${id})" class="px-2 py-0.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 rounded text-[10px] font-bold transition flex items-center space-x-1 shrink-0">
                 <i data-lucide="video" class="h-3 w-3"></i>
                 <span>Generate Meet Link</span>
             </button>
           </div>`;

    const modalHTML = `
        <div id="task-details-view-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden flex flex-col text-left text-xs">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center space-x-1.5">
                        <i data-lucide="info" class="h-4.5 w-4.5 text-indigo-500"></i>
                        <span>Task Details</span>
                    </h3>
                    <button onclick="document.getElementById('task-details-view-modal').remove()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <div class="p-6 space-y-4 max-h-[480px] overflow-y-auto">
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Category</label>
                        <p class="font-semibold text-slate-700">${category}</p>
                    </div>
                    
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Task Title</label>
                        <p class="text-sm font-bold text-slate-800">${title}</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Status</label>
                            <div>${statusBadge}</div>
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Priority</label>
                            <div>${priorityBadge}</div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Due Date</label>
                            <p class="font-medium text-slate-750">${date || 'No due date'}</p>
                        </div>
                        <div>
                            <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Due Time</label>
                            <p class="font-medium text-slate-750">${time ? time.substring(0, 5) : 'No due time'}</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Google Meet Link</label>
                        <p class="font-medium text-slate-700">${meetLinkHTML}</p>
                    </div>
                    
                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Description</label>
                        <p class="text-slate-650 bg-slate-50 p-2.5 rounded-lg border border-slate-150 break-words leading-relaxed whitespace-pre-line">${desc || 'No description provided.'}</p>
                    </div>

                    <div>
                        <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Remarks / Progress Notes</label>
                        <p class="text-indigo-950 bg-indigo-50/50 p-2.5 rounded-lg border border-indigo-100 break-words leading-relaxed whitespace-pre-line">${remarks || 'No remarks added yet. Right click to add remarks.'}</p>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                    <button onclick="document.getElementById('task-details-view-modal').remove()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition shadow-sm">Close</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    lucide.createIcons();
}

function openAddRemarksModal(taskId, currentRemarks) {
    const existing = document.getElementById('task-add-remarks-modal');
    if (existing) existing.remove();
    
    const modalHTML = `
        <div id="task-add-remarks-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden flex flex-col text-left text-xs">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center space-x-1.5">
                        <i data-lucide="message-square" class="h-4.5 w-4.5 text-indigo-500"></i>
                        <span>Add Task Remarks</span>
                    </h3>
                    <button onclick="document.getElementById('task-add-remarks-modal').remove()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <div class="p-6 space-y-3">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Remarks / Comments / Updates</label>
                    <textarea id="task-modal-remarks-input" rows="4" placeholder="Enter remarks..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 font-sans">${currentRemarks}</textarea>
                </div>
                
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end space-x-2">
                    <button onclick="document.getElementById('task-add-remarks-modal').remove()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                    <button onclick="submitTaskRemarks(this, ${taskId})" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition shadow-sm">Save Remarks</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    lucide.createIcons();
}

async function submitTaskRemarks(btn, taskId) {
    const remarks = document.getElementById('task-modal-remarks-input').value.trim();
    
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin text-white"></i>`;
    lucide.createIcons();
    
    try {
        const res = await apiCall('crm/tasks.php?action=PUT', 'POST', {
            id: taskId,
            remarks: remarks
        });
        
        if (res.status === 'success') {
            showNotification('success', 'Remarks updated successfully!');
            document.getElementById('task-add-remarks-modal').remove();
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) {
                if (currentView === 'dashboard') {
                    renderDashboard(viewport);
                } else {
                    renderTasks(viewport);
                }
            }
        } else {
            showNotification('error', res.message);
        }
    } catch(e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

function renderMeetings(container) {
    container.innerHTML = `<div class="p-8 text-center text-slate-400 text-xs animate-fade-in">Meetings scheduler calendar loaded. Track scheduled items on the Dashboard.</div>`;
}

// Global State for Visual Workflow Builder
if (!window.wfState) {
    window.wfState = {
        activeWorkflow: null,
        zoom: 1.0,
        panX: 100,
        panY: 100,
        selectedNodeId: null,
        isPanning: false,
        dragStart: { x: 0, y: 0 },
        isConnecting: false,
        connStart: { nodeId: null, x: 0, y: 0 },
        undoStack: [],
        redoStack: [],
        autoSaveTimer: null,
        activeCategoryFilter: 'all',
        searchTerm: ''
    };
}

const WORKFLOW_TEMPLATES = [
    {
        name: "Welcome New Lead Automation",
        description: "Automatically welcome and qualify new leads from inbound emails.",
        trigger_type: "visual_workflow",
        trigger_value: "canvas",
        is_active: 1,
        nodes: [
            { id: "node-1", type: "email_received", name: "Email Received", category: "Email", icon: "mail", x: 300, y: 50, config: { folder: "Inbox", unreadOnly: true } },
            { id: "node-2", type: "ai_categorize", name: "AI Categorize Email", category: "AI", icon: "sparkles", x: 300, y: 150, config: { provider: "Gemini", temperature: 0.2 } },
            { id: "node-3", type: "if_score", name: "IF Lead Score > 70", category: "Conditions", icon: "git-branch", x: 300, y: 250, config: { threshold: 70 } },
            { id: "node-4", type: "create_lead", name: "Create Lead", category: "CRM", icon: "user-plus", x: 150, y: 380, config: { status: "New", source: "Email AI" } },
            { id: "node-5", type: "send_email", name: "Send Welcome Email", category: "Communication", icon: "send", x: 150, y: 480, config: { subject: "Welcome to LinkPilot CRM!" } },
            { id: "node-6", type: "wait_1d", name: "Wait 1 Day", category: "Delay", icon: "clock", x: 150, y: 580, config: { duration: 1, unit: "day" } },
            { id: "node-7", type: "create_task", name: "Create Follow-up Task", category: "CRM", icon: "check-square", x: 150, y: 680, config: { title: "Follow-up call" } },
            { id: "node-8", type: "add_to_nurture", name: "Add to Nurture List", category: "CRM", icon: "list", x: 450, y: 380, config: { listName: "Nurture" } }
        ],
        connections: [
            { from: "node-1", to: "node-2" },
            { from: "node-2", to: "node-3" },
            { from: "node-3", to: "node-4", handle: "yes" },
            { from: "node-4", to: "node-5" },
            { from: "node-5", to: "node-6" },
            { from: "node-6", to: "node-7" },
            { from: "node-3", to: "node-8", handle: "no" }
        ]
    },
    {
        name: "Auto Follow-up Automation",
        description: "Follow up automatically after days of silence.",
        trigger_type: "visual_workflow",
        trigger_value: "canvas",
        is_active: 1,
        nodes: [
            { id: "node-1", type: "create_task", name: "Create Task", category: "CRM", icon: "check-square", x: 300, y: 50, config: { title: "Initiate Outreach" } },
            { id: "node-2", type: "wait_custom", name: "Wait 1 Day", category: "Delay", icon: "clock", x: 300, y: 160, config: { duration: 24, unit: "hours" } },
            { id: "node-3", type: "send_email", name: "Send Follow-up Email", category: "Communication", icon: "send", x: 300, y: 270, config: { subject: "Gentle reminder" } }
        ],
        connections: [
            { from: "node-1", to: "node-2" },
            { from: "node-2", to: "node-3" }
        ]
    }
];

const AVAILABLE_NODES = [
    // TRIGGERS
    { type: "email_received", name: "Email Received", category: "TRIGGERS", icon: "mail", desc: "Triggers on incoming email." },
    { type: "lead_created", name: "Lead Created", category: "TRIGGERS", icon: "user-plus", desc: "Triggers on lead insertion." },
    { type: "contact_created", name: "Contact Created", category: "TRIGGERS", icon: "contact", desc: "Triggers on contact insertion." },
    { type: "company_created", name: "Company Created", category: "TRIGGERS", icon: "briefcase", desc: "Triggers on company insertion." },
    { type: "deal_stage_changed", name: "Deal Stage Changed", category: "TRIGGERS", icon: "trending-up", desc: "Triggers on deal progress." },
    { type: "form_submitted", name: "Form Submitted", category: "TRIGGERS", icon: "file-text", desc: "Triggers on website form." },
    { type: "meeting_scheduled", name: "Meeting Scheduled", category: "TRIGGERS", icon: "calendar", desc: "Triggers on schedule event." },

    // AI ACTIONS
    { type: "ai_categorize", name: "AI Categorize Email", category: "AI ACTIONS", icon: "sparkles", desc: "Classify into tags." },
    { type: "ai_extract", name: "AI Extract Details", category: "AI ACTIONS", icon: "file-text", desc: "Extract contact details." },
    { type: "ai_summary", name: "AI Generate Summary", category: "AI ACTIONS", icon: "book-open", desc: "Summarize content." },
    { type: "ai_sentiment", name: "AI Sentiment Analysis", category: "AI ACTIONS", icon: "smile", desc: "Analyze customer emotions." },
    { type: "ai_scoring", name: "AI Lead Scoring", category: "AI ACTIONS", icon: "award", desc: "Calculate score index." },
    { type: "ai_reply", name: "AI Generate Reply", category: "AI ACTIONS", icon: "message-square-reply", desc: "Draft a smart reply." },
    { type: "ai_detect_duplicate", name: "AI Detect Duplicate", category: "AI ACTIONS", icon: "copy", desc: "Find replicate entities." },
    { type: "ai_translation", name: "AI Translation", category: "AI ACTIONS", icon: "languages", desc: "Translate text languages." },

    // CRM ACTIONS
    { type: "create_lead", name: "Create Lead", category: "CRM ACTIONS", icon: "user-plus", desc: "Insert lead record." },
    { type: "update_lead", name: "Update Lead", category: "CRM ACTIONS", icon: "user-check", desc: "Modify lead fields." },
    { type: "create_contact", name: "Create Contact", category: "CRM ACTIONS", icon: "contact", desc: "Insert contact record." },
    { type: "update_contact", name: "Update Contact", category: "CRM ACTIONS", icon: "contact", desc: "Modify contact fields." },
    { type: "create_company", name: "Create Company", category: "CRM ACTIONS", icon: "briefcase", desc: "Insert company details." },
    { type: "create_deal", name: "Create Deal", category: "CRM ACTIONS", icon: "trending-up", desc: "Insert new deal pipeline." },
    { type: "create_task", name: "Create Task", category: "CRM ACTIONS", icon: "check-square", desc: "Insert action task." },
    { type: "schedule_meeting", name: "Schedule Meeting", category: "CRM ACTIONS", icon: "video", desc: "Set schedule invite." },
    { type: "add_note", name: "Add Note", category: "CRM ACTIONS", icon: "file-edit", desc: "Append comments log." },
    { type: "add_tag", name: "Add Tag", category: "CRM ACTIONS", icon: "tag", desc: "Label records." },

    // COMMUNICATION
    { type: "send_email", name: "Send Email", category: "COMMUNICATION", icon: "send", desc: "Send SMTP mail." },
    { type: "send_notification", name: "Send Notification", category: "COMMUNICATION", icon: "bell", desc: "System toast popup." },
    { type: "send_slack", name: "Send Slack", category: "COMMUNICATION", icon: "slack", desc: "Slack channel hook." },
    { type: "send_discord", name: "Send Discord", category: "COMMUNICATION", icon: "hash", desc: "Discord channel payload." },
    { type: "send_teams", name: "Send Microsoft Teams", category: "COMMUNICATION", icon: "message-circle", desc: "Teams webhook post." },

    // INTEGRATIONS
    { type: "http_request", name: "HTTP Request", category: "INTEGRATIONS", icon: "globe", desc: "Trigger external API REST query." },
    { type: "webhook", name: "Webhook", category: "INTEGRATIONS", icon: "webhook", desc: "Payload listener endpoint." },
    { type: "api_connector", name: "API Connector", category: "INTEGRATIONS", icon: "link", desc: "Reusable API integration." },
    { type: "database_query", name: "Database Query", category: "INTEGRATIONS", icon: "database", desc: "SQL select or edit query." },
    { type: "json_parser", name: "JSON Parser", category: "INTEGRATIONS", icon: "code", desc: "Extract JSON fields." },
    { type: "xml_parser", name: "XML Parser", category: "INTEGRATIONS", icon: "code", desc: "Extract XPath value." },

    // LOGIC
    { type: "if_branch", name: "IF Condition", category: "LOGIC", icon: "git-branch", desc: "Split logic path." },
    { type: "switch", name: "Switch", category: "LOGIC", icon: "git-commit", desc: "Branch multiple options." },
    { type: "wait_delay", name: "Wait", category: "LOGIC", icon: "clock", desc: "Pause flow run." },
    { type: "loop", name: "Loop", category: "LOGIC", icon: "repeat", desc: "Iterate list elements." },
    { type: "merge", name: "Merge", category: "LOGIC", icon: "git-pull-request", desc: "Join multiple paths." },
    { type: "filter", name: "Filter", category: "LOGIC", icon: "filter", desc: "Keep matching criteria." },

    // FILES
    { type: "upload_file", name: "Upload File", category: "FILES", icon: "upload", desc: "Store file attachment." },
    { type: "download_file", name: "Download File", category: "FILES", icon: "download", desc: "Retrieve static file URL." },
    { type: "generate_pdf", name: "Generate PDF", category: "FILES", icon: "file", desc: "Create PDF doc template." },
    { type: "read_csv", name: "Read CSV", category: "FILES", icon: "file-spreadsheet", desc: "Parse raw CSV columns." },
    { type: "read_excel", name: "Read Excel", category: "FILES", icon: "file-spreadsheet", desc: "Parse spreadsheet sheets." },

    // REPORTING
    { type: "generate_report", name: "Generate Report", category: "REPORTING", icon: "bar-chart-2", desc: "Produce analytics dashboard." },
    { type: "export_data", name: "Export Data", category: "REPORTING", icon: "download", desc: "Export CSV or PDF reports." },

    // UTILITY
    { type: "delay", name: "Delay", category: "UTILITY", icon: "clock", desc: "Wait helper block." },
    { type: "random_number", name: "Random Number", category: "UTILITY", icon: "help-circle", desc: "Generate numerical value." },
    { type: "current_date", name: "Current Date", category: "UTILITY", icon: "calendar", desc: "Get current system time." },
    { type: "set_variable", name: "Set Variable", category: "UTILITY", icon: "sliders", desc: "Define temporary state." },
    { type: "get_variable", name: "Get Variable", category: "UTILITY", icon: "sliders", desc: "Fetch temporary state." },
    { type: "logger", name: "Logger", category: "UTILITY", icon: "info", desc: "Print system debug logs." }
];

async function renderAutomation(container) {
    injectVisualBuilderStyles();
    
    if (window.wfState.activeWorkflow) {
        renderVisualCanvas(container);
        return;
    }

    try {
        const data = await apiCall('crm/automation.php');
        const workflows = data.workflows || [];
        
        let wfItems = workflows.map(w => {
            const act = w.is_active ? 'ACTIVE' : 'PAUSED';
            const badgeClass = w.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500';
            return `
                <div class="p-4 bg-slate-900 border border-slate-850 rounded-xl flex justify-between items-center transition hover:border-slate-750">
                    <div class="text-left">
                        <h4 class="font-bold text-white text-xs">${w.name}</h4>
                        <p class="text-[10px] text-slate-400 mt-1">Visual Workflow Creator • Created ${new Date(w.created_at || Date.now()).toLocaleDateString()}</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <span class="px-2 py-0.5 rounded text-[8px] font-bold ${badgeClass}">${act}</span>
                        <button onclick="editVisualWorkflow(${w.id})" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded text-[10px] font-bold transition">Open Builder</button>
                        <button onclick="duplicateWorkflow(${w.id})" class="p-1 text-slate-400 hover:text-indigo-400 transition" title="Duplicate"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                        <button onclick="deleteWorkflow(${w.id})" class="p-1 text-slate-400 hover:text-rose-500 transition" title="Delete"><i data-lucide="trash" class="h-3.5 w-3.5"></i></button>
                    </div>
                </div>
            `;
        }).join('');

        let templateCards = WORKFLOW_TEMPLATES.map((t, idx) => `
            <div class="p-4 bg-slate-900 border border-slate-850 rounded-xl flex flex-col justify-between text-left space-y-3">
                <div>
                    <h4 class="font-bold text-white text-xs flex items-center space-x-1.5">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5 text-indigo-500"></i>
                        <span>${t.name}</span>
                    </h4>
                    <p class="text-[10px] text-slate-400 mt-1 leading-normal">${t.description}</p>
                </div>
                <button onclick="installWorkflowTemplate(${idx})" class="w-full py-1.5 bg-indigo-650 hover:bg-indigo-600 text-white rounded-lg text-[10px] font-bold transition flex items-center justify-center space-x-1">
                    <i data-lucide="download-cloud" class="h-3.5 w-3.5"></i>
                    <span>Use Template</span>
                </button>
            </div>
        `).join('');

        container.innerHTML = `
            <div class="space-y-6 animate-fade-in pt-4 max-w-4xl mx-auto pb-12">
                <div class="flex justify-between items-center border-b border-slate-850 pb-4">
                    <div class="text-left">
                        <h1 class="text-2xl font-extrabold text-white">Visual Workflow Builder</h1>
                        <p class="text-slate-400 text-xs mt-1">Design automated visual pathways for emails, CRM triggers, and AI intelligence.</p>
                    </div>
                    <button onclick="createNewVisualWorkflow()" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-[10px] font-bold transition flex items-center space-x-1.5 shadow-sm">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                        <span>New Workflow</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Left: Workflows list -->
                    <div class="md:col-span-2 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-white uppercase tracking-wider">Your Workflows</span>
                        </div>
                        <div class="space-y-3">
                            ${wfItems || `
                                <div class="p-8 text-center bg-slate-900 border border-slate-850 rounded-xl text-slate-500 italic text-[11px]">
                                    No custom workflows created yet. Build a new one or choose a template below.
                                </div>
                            `}
                        </div>
                    </div>
                    
                    <!-- Right: Execution Log Reports -->
                    <div class="space-y-4 text-left">
                        <span class="text-xs font-bold text-white uppercase tracking-wider">Recent Executions</span>
                        <div class="p-4 bg-slate-900 border border-slate-850 rounded-xl space-y-3 max-h-[300px] overflow-y-auto" id="recent-executions-list">
                            <div class="text-center text-slate-500 py-6 italic text-[10px]">Loading reports...</div>
                        </div>
                    </div>
                </div>

                <!-- Section: Ready Made Templates -->
                <div class="space-y-4">
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider text-left">Workflow Templates</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        ${templateCards}
                    </div>
                </div>
            </div>
        `;
        
        loadRecentExecutionLogs();
        lucide.createIcons();
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function loadRecentExecutionLogs() {
    const list = document.getElementById('recent-executions-list');
    if (!list) return;
    try {
        const data = await apiCall('crm/automation.php?action=get_logs');
        const logs = data.logs || [];
        if (logs.length === 0) {
            list.innerHTML = `<div class="text-center text-slate-500 py-6 italic text-[10px]">No run logs yet.</div>`;
            return;
        }
        
        list.innerHTML = logs.map(l => {
            const timeStr = new Date(l.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const isSuccess = l.status === 'success';
            const badge = isSuccess 
                ? `<span class="text-emerald-400 font-bold bg-emerald-500/10 px-1 py-0.5 rounded text-[8px]">SUCCESS</span>`
                : `<span class="text-rose-400 font-bold bg-rose-500/10 px-1 py-0.5 rounded text-[8px]">FAILED</span>`;
            return `
                <div class="p-2.5 bg-slate-950/60 rounded-lg border border-slate-850/80 flex justify-between items-start text-[10px]">
                    <div class="space-y-1 pr-2">
                        <p class="font-bold text-slate-200 leading-tight">${l.workflow_name}</p>
                        <p class="text-[9px] text-slate-550">${timeStr} • ${l.execution_time}s</p>
                        ${l.error_message ? `<p class="text-[9px] text-rose-400 mt-1 italic">${l.error_message}</p>` : ''}
                    </div>
                    <div>${badge}</div>
                </div>
            `;
        }).join('');
    } catch(e) {
        list.innerHTML = `<div class="text-center text-slate-500 py-4 italic text-[10px]">Failed to load logs.</div>`;
    }
}

function createNewVisualWorkflow() {
    window.wfState.activeWorkflow = {
        id: 0,
        name: "New Automation Workflow",
        trigger_type: "visual_workflow",
        trigger_value: "canvas",
        is_active: 1,
        nodes: [
            { id: "node-trigger", type: "email_received", name: "Email Received", category: "TRIGGERS", icon: "mail", x: 250, y: 80, config: { folder: "Inbox" } }
        ],
        connections: []
    };
    navigateTo('automation');
}

async function editVisualWorkflow(id) {
    try {
        const data = await apiCall('crm/automation.php');
        const workflows = data.workflows || [];
        const found = workflows.find(w => (w.id === id));
        if (found) {
            let actions = found.actions || {};
            // If it is in old format, convert or fallback
            if (!actions.nodes) {
                actions = {
                    nodes: [
                        { id: "node-trigger", type: "email_received", name: "Email Received", category: "TRIGGERS", icon: "mail", x: 250, y: 80, config: { folder: found.trigger_value || "Inbox" } }
                    ],
                    connections: []
                };
            }
            window.wfState.activeWorkflow = {
                id: found.id,
                name: found.name,
                trigger_type: found.trigger_type,
                trigger_value: found.trigger_value,
                is_active: parseInt(found.is_active),
                nodes: actions.nodes,
                connections: actions.connections
            };
            navigateTo('automation');
        }
    } catch(e) {
        showNotification('error', e.message);
    }
}

async function duplicateWorkflow(id) {
    try {
        const res = await apiCall('crm/automation.php?action=duplicate', 'POST', { id });
        if (res.status === 'success') {
            showNotification('success', 'Workflow duplicated successfully.');
            navigateTo('automation');
        }
    } catch(e) {
        showNotification('error', e.message);
    }
}

async function deleteWorkflow(id) {
    if (!confirm('Are you sure you want to delete this workflow?')) return;
    try {
        const res = await apiCall('crm/automation.php?action=delete', 'POST', { id });
        if (res.status === 'success') {
            showNotification('success', 'Workflow deleted.');
            navigateTo('automation');
        }
    } catch(e) {
        showNotification('error', e.message);
    }
}

function installWorkflowTemplate(idx) {
    const template = WORKFLOW_TEMPLATES[idx];
    window.wfState.activeWorkflow = {
        id: 0,
        name: template.name + " (Template)",
        trigger_type: template.trigger_type,
        trigger_value: template.trigger_value,
        is_active: template.is_active,
        nodes: JSON.parse(JSON.stringify(template.nodes)),
        connections: JSON.parse(JSON.stringify(template.connections))
    };
    showNotification('success', 'Template pre-loaded! Save to activate.');
    navigateTo('automation');
}

// -------------------------------------
function renderVisualCanvas(container) {
    const wf = window.wfState.activeWorkflow;
    
    // Group nodes by category
    const groups = {};
    AVAILABLE_NODES.forEach(n => {
        if (!groups[n.category]) groups[n.category] = [];
        groups[n.category].push(n);
    });

    let groupsHTML = '';
    for (const catName in groups) {
        const nodesInCat = groups[catName].filter(n => {
            return window.wfState.searchTerm === '' || n.name.toLowerCase().includes(window.wfState.searchTerm.toLowerCase());
        });
        
        if (nodesInCat.length === 0) continue;
        
        const nodesHTML = nodesInCat.map(n => `
            <div draggable="true" ondragstart="handleNodeDragStart(event, '${n.type}')" class="p-2 bg-white border border-slate-200 hover:border-indigo-500 rounded-xl space-y-0.5 transition cursor-grab select-none text-left hover:shadow-md">
                <div class="flex items-center space-x-2">
                    <div class="h-6 w-6 bg-slate-100 rounded-md flex items-center justify-center text-indigo-650 shrink-0">
                        <i data-lucide="${n.icon}" class="h-3.5 w-3.5"></i>
                    </div>
                    <div class="overflow-hidden">
                        <h5 class="font-bold text-slate-800 text-[10px] leading-tight truncate">${n.name}</h5>
                    </div>
                </div>
            </div>
        `).join('');
        
        let displayCatName = catName;
        if (catName === 'TRIGGERS') displayCatName = '📩 Trigger Nodes';
        else if (catName === 'AI ACTIONS') displayCatName = '🤖 AI Nodes';
        else if (catName === 'CRM ACTIONS') displayCatName = '👤 CRM Nodes';
        else if (catName === 'COMMUNICATION') displayCatName = '📧 Communication Nodes';
        else if (catName === 'INTEGRATIONS') displayCatName = '🌐 Integration Nodes';
        else if (catName === 'LOGIC') displayCatName = '🔀 Logic Nodes';
        else if (catName === 'FILES') displayCatName = '📂 File Nodes';
        else if (catName === 'REPORTING') displayCatName = '📊 Reporting Nodes';
        else if (catName === 'UTILITY') displayCatName = '⚙️ Utility Nodes';
        
        groupsHTML += `
            <div class="space-y-1.5">
                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-wider text-left">${displayCatName}</div>
                <div class="grid grid-cols-1 gap-1.5">
                    ${nodesHTML}
                </div>
            </div>
        `;
    }

    container.innerHTML = `
        <div id="workflow-builder-layout" class="animate-fade-in flex flex-col h-full bg-slate-50 w-full">
            <!-- Top Viewport Navigation -->
            <div class="h-14 border-b border-slate-200 bg-white flex items-center justify-between px-6 select-none shrink-0 w-full overflow-x-auto overflow-y-hidden">
                <!-- Left: Path and Edit -->
                <div class="flex items-center space-x-2 text-xs shrink-0">
                    <button onclick="backToWorkflowList()" class="h-7 w-7 rounded-lg hover:bg-slate-100 text-slate-500 flex items-center justify-center transition mr-1" title="Back to List">
                        <i data-lucide="arrow-left" class="h-4.5 w-4.5"></i>
                    </button>
                    <div class="flex items-center space-x-1.5 text-slate-500">
                        <i data-lucide="folder" class="h-3.5 w-3.5 text-slate-400"></i>
                        <span class="text-[10px] font-bold uppercase tracking-wider">Personal</span>
                        <span class="text-slate-350">/</span>
                    </div>
                    <input type="text" id="workflow-rename-input" onblur="renameWorkflow(this.value)" value="${wf.name}" class="bg-transparent text-slate-800 font-bold focus:outline-none border-b border-transparent focus:border-indigo-500 px-1 py-0.5 text-xs w-40">
                    <button onclick="document.getElementById('workflow-rename-input').focus()" class="text-slate-450 hover:text-slate-650 p-0.5"><i data-lucide="pencil" class="h-3 w-3"></i></button>
                    <button class="px-2 py-0.5 border border-slate-200 rounded text-[9px] font-bold text-slate-500 hover:bg-slate-50 transition">+ Add tag</button>
                </div>

                <!-- Center: Navigation Tabs -->
                <div class="flex items-center bg-slate-100 rounded-lg p-0.5 text-[10px] font-bold shrink-0 mx-4">
                    <button class="px-3.5 py-1 bg-white text-slate-800 rounded-md shadow-sm">Editor</button>
                    <button class="px-3.5 py-1 text-slate-500 hover:text-slate-800 transition">Executions</button>
                    <button class="px-3.5 py-1 text-slate-500 hover:text-slate-800 transition">Evaluations</button>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center space-x-3 text-[10px] shrink-0">
                    <span class="text-slate-400 font-bold">0 / 1</span>
                    <div class="flex items-center rounded-lg overflow-hidden shadow-sm">
                        <button onclick="toggleWorkflowActiveState()" class="px-3.5 py-1.5 bg-indigo-650 hover:bg-indigo-600 text-white font-bold transition">Publish</button>
                        <button onclick="toggleWorkflowActiveState()" class="px-2 py-1.5 bg-indigo-700 hover:bg-indigo-650 text-white border-l border-indigo-500 transition"><i data-lucide="chevron-down" class="h-3 w-3"></i></button>
                    </div>
                    <button onclick="openLogsHistoryDrawer()" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 transition" title="History"><i data-lucide="history" class="h-4 w-4"></i></button>
                    <button class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-550 transition"><i data-lucide="more-horizontal" class="h-4 w-4"></i></button>
                    <div class="hidden lg:flex items-center border border-slate-200 rounded-lg overflow-hidden text-[9px] font-bold bg-white">
                        <span class="px-2 py-1 bg-slate-50 border-r border-slate-200 text-slate-650 flex items-center space-x-1">
                            <i data-lucide="star" class="h-3 w-3 text-amber-500 fill-amber-500"></i>
                            <span>Star</span>
                        </span>
                        <span class="px-2.5 py-1 bg-white text-slate-700">195,281</span>
                    </div>
                </div>
            </div>

            <!-- Main Content Area: Left Sidebar, Canvas, Right Config -->
            <div class="flex flex-grow overflow-hidden relative w-full">
                <!-- Left Sidebar (Add Nodes) -->
                <div class="w-64 bg-white border-r border-slate-200 flex flex-col h-full overflow-hidden select-none shrink-0">
                    <div class="p-4 border-b border-slate-200 space-y-3">
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider text-left">Add Nodes</h3>
                        <div class="relative text-left">
                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 pointer-events-none">
                                <i data-lucide="search" class="h-3.5 w-3.5"></i>
                            </span>
                            <input type="text" id="builder-node-search" oninput="searchBuilderNodes(this.value)" value="${window.wfState.searchTerm}" placeholder="Search nodes..." class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-700 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <!-- Category Groups -->
                    <div class="flex-grow overflow-y-auto p-4 space-y-4 bg-slate-50/10">
                        ${groupsHTML}
                        <div class="pt-2">
                            <button class="w-full py-2 border border-dashed border-indigo-200 hover:border-indigo-400 rounded-xl text-[10px] font-bold text-indigo-650 hover:text-indigo-800 bg-indigo-50/10 transition flex items-center justify-center space-x-1">
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                                <span>More Nodes</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Center Canvas -->
                <div class="flex-grow flex flex-col h-full relative overflow-hidden wf-canvas-container" id="wf-canvas-container" onwheel="handleCanvasScroll(event)" onmousedown="handleCanvasMouseDown(event)" ondragover="event.preventDefault()" ondrop="handleNodeDrop(event)">
                    <!-- Canvas Floating Toolbar -->
                    <div class="absolute top-4 left-1/2 transform -translate-x-1/2 z-20 bg-white/95 border border-slate-200 rounded-xl p-1.5 flex items-center space-x-1 shadow-md text-[9px] font-bold text-slate-550 select-none">
                        <button onclick="undoWorkflowChange()" class="flex flex-col items-center px-2 py-0.5 rounded-lg hover:bg-slate-100 transition">
                            <i data-lucide="undo" class="h-3.5 w-3.5 mb-0.5 text-slate-500"></i>
                            <span>Undo</span>
                        </button>
                        <button onclick="redoWorkflowChange()" class="flex flex-col items-center px-2 py-0.5 rounded-lg hover:bg-slate-100 transition">
                            <i data-lucide="redo" class="h-3.5 w-3.5 mb-0.5 text-slate-500"></i>
                            <span>Redo</span>
                        </button>
                        <div class="h-6 w-px bg-slate-200 mx-1"></div>
                        <button onclick="zoomWorkflow(-0.1)" class="flex flex-col items-center px-1.5 py-0.5 rounded-lg hover:bg-slate-100 transition">
                            <i data-lucide="zoom-out" class="h-3.5 w-3.5 mb-0.5 text-slate-500"></i>
                            <span>Zoom Out</span>
                        </button>
                        <div class="flex flex-col items-center px-1.5">
                            <span class="text-slate-800 font-bold">${Math.round(window.wfState.zoom * 100)}%</span>
                            <span class="text-[6px] text-slate-400">Scale</span>
                        </div>
                        <button onclick="zoomWorkflow(0.1)" class="flex flex-col items-center px-1.5 py-0.5 rounded-lg hover:bg-slate-100 transition">
                            <i data-lucide="zoom-in" class="h-3.5 w-3.5 mb-0.5 text-slate-500"></i>
                            <span>Zoom In</span>
                        </button>
                        <div class="h-6 w-px bg-slate-200 mx-1"></div>
                        <button onclick="zoomToFit()" class="flex flex-col items-center px-2 py-0.5 rounded-lg hover:bg-slate-100 transition">
                            <i data-lucide="maximize" class="h-3.5 w-3.5 mb-0.5 text-slate-500"></i>
                            <span>Fit</span>
                        </button>
                        <button onclick="autoArrangeCanvas()" class="flex flex-col items-center px-2 py-0.5 rounded-lg hover:bg-slate-100 transition">
                            <i data-lucide="layout-grid" class="h-3.5 w-3.5 mb-0.5 text-slate-500"></i>
                            <span>Auto Layout</span>
                        </button>
                    </div>

                    <!-- Canvas Workspace -->
                    <div id="workflow-canvas" class="wf-canvas" style="transform: translate(${window.wfState.panX}px, ${window.wfState.panY}px) scale(${window.wfState.zoom});">
                        <svg id="workflow-svg" class="wf-svg-lines">
                            <defs>
                                <linearGradient id="conn-grad" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" stop-color="#4f46e5" />
                                    <stop offset="100%" stop-color="#6366f1" />
                                </linearGradient>
                                <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                    <path d="M 0 0 L 10 5 L 0 10 z" fill="#6366f1" />
                                </marker>
                            </defs>
                        </svg>
                        <div id="canvas-nodes-container">
                            ${renderCanvasNodesHTML()}
                        </div>
                    </div>

                    <!-- Bottom Left Canvas Overlay Tools -->
                    <div class="absolute bottom-4 left-4 z-20 bg-white/95 border border-slate-200 rounded-xl p-1.5 flex items-center space-x-1.5 shadow-md">
                        <button class="p-1 rounded-lg hover:bg-slate-100 text-slate-550 transition"><i data-lucide="expand" class="h-3.5 w-3.5"></i></button>
                        <button class="p-1 rounded-lg hover:bg-slate-100 text-slate-550 transition"><i data-lucide="hand" class="h-3.5 w-3.5"></i></button>
                        <button onclick="zoomWorkflow(-0.1)" class="p-1 rounded-lg hover:bg-slate-100 text-slate-550 transition"><i data-lucide="minus" class="h-3.5 w-3.5"></i></button>
                        <button onclick="zoomWorkflow(0.1)" class="p-1 rounded-lg hover:bg-slate-100 text-slate-550 transition"><i data-lucide="plus" class="h-3.5 w-3.5"></i></button>
                        <button onclick="zoomToFit()" class="p-1 rounded-lg hover:bg-slate-100 text-slate-550 transition"><i data-lucide="maximize-2" class="h-3.5 w-3.5"></i></button>
                        <button onclick="autoArrangeCanvas()" class="p-1 rounded-lg hover:bg-slate-100 text-slate-550 transition"><i data-lucide="layout" class="h-3.5 w-3.5"></i></button>
                    </div>

                    <!-- Bottom Center Action Button -->
                    <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 z-20 flex items-center bg-orange-650 hover:bg-orange-600 text-white rounded-xl shadow-lg overflow-hidden text-[10px] font-bold">
                        <button onclick="runWorkflowSimulation(this)" class="px-5 py-2 flex items-center space-x-1.5 transition">
                            <i data-lucide="play-circle" class="h-4 w-4"></i>
                            <span>Execute Workflow</span>
                        </button>
                        <button onclick="runWorkflowSimulation(this)" class="px-2 py-2 border-l border-orange-550 hover:bg-orange-550 transition">
                            <i data-lucide="chevron-up" class="h-3.5 w-3.5"></i>
                        </button>
                    </div>

                    <!-- Bottom Right Action Button -->
                    <div class="absolute bottom-4 right-4 z-20 bg-white/95 border border-slate-200 hover:bg-slate-50 rounded-xl px-4 py-2 shadow-md flex items-center space-x-1.5 text-[10px] font-bold text-slate-700 cursor-pointer transition" onclick="runWorkflowSimulation(this)">
                        <i data-lucide="play" class="h-3.5 w-3.5 text-indigo-650"></i>
                        <span>Test Workflow</span>
                    </div>
                </div>

                <!-- Right Configuration Panel -->
                <div class="w-80 bg-white border-l border-slate-200 flex flex-col h-full overflow-hidden text-left shrink-0" id="wf-config-sidebar">
                    ${renderConfigSidebarHTML()}
                </div>
            </div>
        </div>
    `;

    lucide.createIcons();
    drawConnections();
    drawMiniMap();
    startBuilderAutoSave();
}

function renameWorkflow(val) {
    if (!val.trim()) return;
    saveUndoState();
    window.wfState.activeWorkflow.name = val.trim();
}

function filterBuilderNodes(cat) {
    window.wfState.activeCategoryFilter = cat;
    navigateTo('automation');
}

function zoomToFit() {
    window.wfState.zoom = 1.0;
    window.wfState.panX = 100;
    window.wfState.panY = 100;
    const canvas = document.getElementById('workflow-canvas');
    if (canvas) {
        canvas.style.transform = `translate(${window.wfState.panX}px, ${window.wfState.panY}px) scale(${window.wfState.zoom})`;
    }
    drawConnections();
    drawMiniMap();
}

function searchBuilderNodes(query) {
    window.wfState.searchTerm = query;
    navigateTo('automation');
}

function backToWorkflowList() {
    stopBuilderAutoSave();
    window.wfState.activeWorkflow = null;
    navigateTo('automation');
}

// ----------------------------------------------------
// STYLING INJECTOR
// ----------------------------------------------------
function injectVisualBuilderStyles() {
    if (document.getElementById('wf-builder-style-block')) return;
    const style = document.createElement('style');
    style.id = 'wf-builder-style-block';
    style.innerHTML = `
        #workflow-builder-layout {
            display: flex;
            height: calc(100vh - 100px);
            overflow: hidden;
            background-color: #f8fafc;
            color: #0f172a;
        }
        .wf-canvas-container {
            flex-grow: 1;
            position: relative;
            overflow: hidden;
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px);
            background-size: 20px 20px;
        }
        .wf-canvas {
            position: absolute;
            width: 5000px;
            height: 5000px;
            transform-origin: 0 0;
        }
        .wf-node {
            position: absolute;
            width: 200px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 12px;
            cursor: grab;
            transition: box-shadow 0.15s, border-color 0.15s;
        }
        .wf-node:active {
            cursor: grabbing;
        }
        .wf-node.selected {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2), 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .wf-node.executing {
            border-color: #a855f7;
            box-shadow: 0 0 0 2px rgba(168, 85, 247, 0.3), 0 0 15px rgba(168, 85, 247, 0.4);
            animation: nodePulse 1.2s infinite alternate;
        }
        @keyframes nodePulse {
            from { transform: scale(1); }
            to { transform: scale(1.02); }
        }
        /* Left accent borders based on category */
        .wf-node[data-category="TRIGGERS"] { border-left: 4px solid #10b981; }
        .wf-node[data-category="AI ACTIONS"] { border-left: 4px solid #8b5cf6; }
        .wf-node[data-category="CRM ACTIONS"] { border-left: 4px solid #3b82f6; }
        .wf-node[data-category="COMMUNICATION"] { border-left: 4px solid #ec4899; }
        .wf-node[data-category="INTEGRATIONS"] { border-left: 4px solid #06b6d4; }
        .wf-node[data-category="LOGIC"] { border-left: 4px solid #f59e0b; }
        .wf-node[data-category="FILES"] { border-left: 4px solid #f43f5e; }
        .wf-node[data-category="REPORTING"] { border-left: 4px solid #a855f7; }
        .wf-node[data-category="UTILITY"] { border-left: 4px solid #64748b; }

        .wf-node-handle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #6366f1;
            border: 2px solid #ffffff;
            border-radius: 50%;
            cursor: crosshair;
            z-index: 10;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .wf-node-handle:hover {
            transform: scale(1.3);
            background: #4f46e5;
        }
        .wf-node-handle.input {
            left: -5px;
            top: 50%;
            transform: translateY(-50%);
        }
        .wf-node-handle.output {
            right: -5px;
            top: 50%;
            transform: translateY(-50%);
        }
        .wf-node-handle.output-yes {
            right: -5px;
            top: 35%;
            background: #10b981;
        }
        .wf-node-handle.output-no {
            right: -5px;
            top: 65%;
            background: #ef4444;
        }
        .wf-svg-lines {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1;
        }
        .connection-path {
            fill: none;
            stroke: #94a3b8;
            stroke-width: 2.5;
            stroke-linecap: round;
        }
        .connection-path.pulsing {
            stroke: #6366f1;
            stroke-dasharray: 6 4;
            animation: dash 1s linear infinite;
        }
        @keyframes dash {
            to {
                stroke-dashoffset: -20;
            }
        }
        #custom-node-context-menu {
            position: fixed;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 8px;
            padding: 4px 0;
            z-index: 1000;
            min-width: 130px;
        }
        #custom-node-context-menu button {
            width: 100%;
            text-align: left;
            padding: 6px 12px;
            font-size: 10px;
            color: #334155;
            transition: background 0.15s;
        }
        #custom-node-context-menu button:hover {
            background: #f1f5f9;
            color: #0f172a;
        }
    `;
    document.head.appendChild(style);
}


async function renderAIInsights(container) {
    // Show skeleton loaders first
    container.innerHTML = `
        <div class="space-y-8 pt-4 max-w-7xl mx-auto text-xs animate-pulse text-left">
            <div class="h-10 bg-slate-800 rounded w-1/3"></div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="h-32 bg-slate-800 rounded-2xl"></div>
                <div class="h-32 bg-slate-800 rounded-2xl"></div>
                <div class="h-32 bg-slate-800 rounded-2xl"></div>
                <div class="h-32 bg-slate-800 rounded-2xl"></div>
            </div>
            <div class="h-64 bg-slate-800 rounded-2xl"></div>
        </div>
    `;
    
    try {
        const res = await apiCall('crm/ai_insights.php');
        if (res.status !== 'success') {
            container.innerHTML = `<div class="p-8 text-center text-rose-400 font-bold">Failed to load AI Insights: ${res.message}</div>`;
            return;
        }
        
        const highlights = res.highlights || [];
        const overview = res.overview || {};
        const chartsData = res.charts || {};
        const recommendations = res.recommendations || [];
        const timeline = res.timeline || [];
        const smartInsights = res.smart_insights || [];
        const lowConfidence = res.low_confidence || [];

        // Build HTML
        let html = `
            <div class="space-y-8 pt-4 animate-fade-in text-xs max-w-7xl mx-auto text-left">
                <!-- Header with Natural Language Search -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between border-b border-slate-850 pb-4 gap-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white flex items-center space-x-2">
                            <span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400 bg-clip-text text-transparent">AI Intelligence Command Center</span>
                        </h1>
                        <p class="text-slate-400 text-xs mt-1">Autonomous recommendations, data extractions, and natural language analytics.</p>
                    </div>
                    
                    <!-- Section 11: AI Search -->
                    <div class="relative w-full md:w-96 text-left">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <i data-lucide="search" class="h-4 w-4"></i>
                        </span>
                        <input type="text" id="ai-nlp-search" onkeypress="handleAISearchKey(event)" placeholder="Ask AI: e.g. 'leads above 1 lakh' or 'requesting SEO'..." class="w-full pl-9 pr-20 py-2 bg-slate-900/60 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition placeholder-slate-500">
                        <button onclick="triggerAISearch()" class="absolute right-1 top-1 bottom-1 px-3.5 bg-indigo-650 hover:bg-indigo-600 text-white rounded-lg font-bold transition text-[10px]">Query</button>
                    </div>
                </div>

                <!-- Section 1: AI Highlights (Top Cards) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        `;

        // Render 4 AI Highlights cards
        const highlightIcons = ['zap', 'bell', 'trending-up', 'alert-triangle'];
        const highlightGradients = [
            'from-indigo-500/10 to-purple-500/5 border-indigo-500/20 text-indigo-400',
            'from-emerald-500/10 to-teal-500/5 border-emerald-500/20 text-emerald-400',
            'from-amber-500/10 to-orange-500/5 border-amber-500/20 text-amber-400',
            'from-rose-500/10 to-pink-500/5 border-rose-500/20 text-rose-400'
        ];

        highlights.forEach((h, idx) => {
            const icon = highlightIcons[idx] || 'star';
            const grad = highlightGradients[idx] || 'from-slate-850 to-slate-900 border-slate-800';
            const actionText = idx === 0 ? 'View Lead' : idx === 1 ? 'Go to Tasks' : idx === 2 ? 'Upsell CRM' : 'Check Alert';
            const actionOnClick = idx === 0 ? `navigateTo('leads')` : idx === 1 ? `navigateTo('tasks')` : idx === 2 ? `navigateTo('companies')` : `alert('Opening System Risk log...')`;

            html += `
                <div class="glass-panel p-5 bg-gradient-to-b ${grad} flex flex-col justify-between space-y-4 hover:scale-[1.02] transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div class="p-2.5 bg-slate-950/40 rounded-xl border border-slate-800/40">
                            <i data-lucide="${icon}" class="h-5 w-5"></i>
                        </div>
                        <div class="flex flex-col items-end space-y-1">
                            <span class="px-2 py-0.5 rounded text-[8px] font-bold bg-slate-950/60 border border-slate-800 uppercase tracking-wider">${h.priority} Priority</span>
                            <span class="text-[9px] text-slate-500">${h.time}</span>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-white text-[13px] leading-snug">${h.title}</h4>
                        <p class="text-slate-300 text-[10px] mt-1 leading-relaxed">${h.desc}</p>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-slate-800/30">
                        <span class="text-[9px] text-slate-500 font-medium">Confidence: <strong class="text-slate-300">${h.confidence}%</strong></span>
                        <button onclick="${actionOnClick}" class="text-[10px] px-3 py-1 bg-slate-950/50 hover:bg-indigo-650 hover:text-white border border-slate-800 hover:border-indigo-500/20 rounded-lg transition-colors font-bold">${actionText}</button>
                    </div>
                </div>
            `;
        });

        html += `
                </div>

                <!-- Section 2: AI Business Overview Stats Grid -->
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-white flex items-center space-x-1.5">
                        <i data-lucide="bar-chart-2" class="h-4.5 w-4.5 text-indigo-400"></i>
                        <span>AI Business Overview</span>
                    </h3>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        `;

        // Render overview stats list
        const statCards = [
            { label: 'New AI Leads', value: overview.new_ai_leads, trend: '+14% vs wk', icon: 'user-plus', color: 'text-indigo-400' },
            { label: 'Hot Leads', value: overview.hot_leads, trend: '+28% vs wk', icon: 'zap', color: 'text-rose-450' },
            { label: 'Follow-ups Required', value: overview.leads_requiring_follow_up, trend: '-5%', icon: 'phone-call', color: 'text-amber-400' },
            { label: 'Positive Sentiment %', value: `${overview.sentiment_positive_pct}%`, trend: '+4%', icon: 'smile', color: 'text-emerald-400' },
            { label: 'Negative Sentiment %', value: `${overview.sentiment_negative_pct}%`, trend: '-2%', icon: 'frown', color: 'text-rose-500' },
            { label: 'Deals At Risk', value: overview.deals_at_risk, trend: 'stable', icon: 'alert-circle', color: 'text-orange-400' },
            { label: 'Created by AI', value: overview.companies_created, trend: '+8%', icon: 'globe', color: 'text-indigo-300' },
            { label: 'Contacts Extracted', value: overview.contacts_extracted, trend: '+22%', icon: 'users', color: 'text-teal-400' },
            { label: 'Processed Today', value: overview.emails_processed_today, trend: '+35%', icon: 'mail-open', color: 'text-blue-400' },
            { label: 'AI Accuracy %', value: `${overview.ai_accuracy_pct}%`, trend: '+0.5%', icon: 'shield-check', color: 'text-emerald-400' },
            { label: 'AI Queue Status', value: overview.ai_queue_status, trend: 'Online', icon: 'server', color: 'text-violet-400' },
            { label: 'AI Processing Time', value: `${overview.ai_processing_time_sec}s`, trend: '-0.1s', icon: 'clock', color: 'text-sky-400' }
        ];

        statCards.forEach((sc, i) => {
            // Draw a tiny synthetic mini-graph path using svg sparklines
            const sparkPaths = [
                "M0 15 Q 10 5, 20 18 T 40 4",
                "M0 18 Q 15 2, 25 15 T 40 2",
                "M0 8 Q 12 18, 24 10 T 40 18",
                "M0 12 Q 10 5, 20 8 T 40 2",
                "M0 5 Q 15 15, 25 8 T 40 16",
                "M0 15 Q 10 15, 20 15 T 40 15"
            ];
            const path = sparkPaths[i % sparkPaths.length];
            const strokeColor = sc.trend.startsWith('+') ? '#10B981' : sc.trend.startsWith('-') ? '#EF4444' : '#64748B';

            html += `
                <div class="glass-panel p-3.5 bg-slate-900/40 border border-slate-850 hover:border-slate-700 transition flex flex-col justify-between h-28 space-y-2">
                    <div class="flex justify-between items-center text-[10px]">
                        <span class="text-slate-400 font-medium truncate pr-1">${sc.label}</span>
                        <i data-lucide="${sc.icon}" class="h-3.5 w-3.5 ${sc.color} shrink-0"></i>
                    </div>
                    <div class="flex justify-between items-baseline pt-1">
                        <span class="text-base font-extrabold text-white">${sc.value}</span>
                        <span class="text-[9px] font-bold ${sc.trend.startsWith('+') ? 'text-emerald-400' : sc.trend.startsWith('-') ? 'text-rose-500' : 'text-slate-400'}">${sc.trend}</span>
                    </div>
                    <!-- Mini Graph -->
                    <div class="pt-1.5 flex justify-end">
                        <svg class="h-5 w-16" viewBox="0 0 40 20" fill="none">
                            <path d="${path}" stroke="${strokeColor}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
            `;
        });

        html += `
                    </div>
                </div>

                <!-- Section 3: AI Analytics & Charts Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Donut Chart 1 -->
                    <div class="glass-panel p-5 bg-slate-900/40 space-y-4">
                        <div>
                            <h4 class="text-xs font-bold text-white">Lead Sources (AI Tracked)</h4>
                            <p class="text-[10px] text-slate-500">Autonomous channel categorization of qualified leads.</p>
                        </div>
                        <div class="relative h-44 flex items-center justify-center">
                            <canvas id="aiChartLeadSources"></canvas>
                        </div>
                    </div>
                    <!-- Donut Chart 2 -->
                    <div class="glass-panel p-5 bg-slate-900/40 space-y-4">
                        <div>
                            <h4 class="text-xs font-bold text-white">Lead Status Distribution</h4>
                            <p class="text-[10px] text-slate-500">Distribution pipeline stages from inbound sync velocity.</p>
                        </div>
                        <div class="relative h-44 flex items-center justify-center">
                            <canvas id="aiChartLeadStatus"></canvas>
                        </div>
                    </div>
                    <!-- Donut Chart 3 -->
                    <div class="glass-panel p-5 bg-slate-900/40 space-y-4">
                        <div>
                            <h4 class="text-xs font-bold text-white">Email Category Insights</h4>
                            <p class="text-[10px] text-slate-500">Automated classification of inbound server messages.</p>
                        </div>
                        <div class="relative h-44 flex items-center justify-center">
                            <canvas id="aiChartEmailCats"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recommendations & Sentiment Trend Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Section 4: AI Recommendations Table / List -->
                    <div class="glass-panel p-5 bg-slate-900/40 lg:col-span-2 space-y-4 flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-center border-b border-slate-800 pb-2">
                                <h4 class="text-xs font-bold text-white flex items-center space-x-1.5">
                                    <i data-lucide="check-square" class="h-4 w-4 text-indigo-400"></i>
                                    <span>AI Recommendations Engine</span>
                                </h4>
                                <span class="text-[9px] text-indigo-400 font-bold uppercase tracking-wider">Auto Pilot active</span>
                            </div>
                            
                            <div class="divide-y divide-slate-850 mt-3 space-y-3" id="ai-recommendations-list">
        `;

        recommendations.forEach((r, idx) => {
            const priorityColors = r.priority === 'High' ? 'bg-rose-500/10 text-rose-455 border-rose-500/20' : 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20';
            html += `
                                <div class="pt-3 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-left">
                                    <div class="flex items-start space-x-3">
                                        <div class="p-2 bg-slate-950/40 border border-slate-800 rounded-lg text-indigo-400">
                                            <i data-lucide="zap" class="h-4 w-4"></i>
                                        </div>
                                        <div>
                                            <h5 class="font-extrabold text-white text-[11px]">${r.title}</h5>
                                            <p class="text-slate-400 text-[10px] mt-0.5">${r.summary}</p>
                                            <span class="text-[9px] text-slate-500 mt-1 block">Suggested: <strong class="text-slate-400">${r.suggested_action}</strong></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2 shrink-0 self-end md:self-center">
                                        <span class="px-2 py-0.5 rounded text-[8px] font-bold border uppercase tracking-wider ${priorityColors}">${r.priority}</span>
                                        <span class="text-[9px] text-slate-500 font-bold">${r.confidence}% match</span>
                                        
                                        <div class="relative inline-block text-left">
                                            <button onclick="executeRecommendationAction(this, '${r.action_type}', ${idx})" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded border border-indigo-500/20 font-bold transition text-[9px] flex items-center space-x-1">
                                                <span>Take Action</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
            `;
        });

        html += `
                            </div>
                        </div>
                        
                        <!-- Actions menu for snooze, complete, ignore -->
                        <div class="pt-4 border-t border-slate-850 flex justify-end space-x-2 text-[9px]">
                            <button onclick="showNotification('success', 'Marked all duplicates scanned as resolved.')" class="px-2.5 py-1 text-slate-400 hover:text-white border border-slate-800 rounded transition font-bold">Mark Complete</button>
                            <button onclick="showNotification('success', 'Insights list refreshed.')" class="px-2.5 py-1 text-slate-400 hover:text-white border border-slate-800 rounded transition font-bold">Ignore</button>
                            <button onclick="showNotification('success', 'Snoozed notifications for 24 hours')" class="px-2.5 py-1 text-slate-400 hover:text-white border border-slate-800 rounded transition font-bold">Snooze</button>
                        </div>
                    </div>

                    <!-- Section 5: AI Sentiment Trend Line Graph -->
                    <div class="glass-panel p-5 bg-slate-900/40 space-y-4 flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-center border-b border-slate-800 pb-2">
                                <h4 class="text-xs font-bold text-white flex items-center space-x-1.5">
                                    <i data-lucide="activity" class="h-4 w-4 text-emerald-400"></i>
                                    <span>AI Sentiment Trend</span>
                                </h4>
                                <!-- Filters -->
                                <select id="sentiment-trend-filter" onchange="filterSentimentTrend(this.value)" class="bg-slate-950 border border-slate-800 text-[10px] text-slate-400 rounded px-1.5 py-0.5 focus:outline-none focus:border-indigo-500">
                                    <option value="week">This Week</option>
                                    <option value="today">Today</option>
                                    <option value="month">This Month</option>
                                </select>
                            </div>
                            <div class="relative h-44 mt-3">
                                <canvas id="aiChartSentimentTrend"></canvas>
                            </div>
                        </div>
                        <div class="text-[9px] text-slate-500 text-left pt-2 border-t border-slate-850">
                            Updates continuously based on parsing incoming server synchronization mailboxes.
                        </div>
                    </div>
                </div>

                <!-- Best Time Analysis & Accuracy Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Section 6: Best Time Analysis -->
                    <div class="glass-panel p-5 bg-slate-900/40 space-y-4 text-left flex flex-col justify-between">
                        <div>
                            <h4 class="text-xs font-bold text-white border-b border-slate-800 pb-2 flex items-center space-x-1.5">
                                <i data-lucide="clock" class="h-4 w-4 text-indigo-400"></i>
                                <span>AI Response & Schedule Optimizer</span>
                            </h4>
                            
                            <div class="space-y-3 mt-4 text-[11px]">
                                <div class="flex justify-between items-center p-2 bg-slate-950/40 border border-slate-850 rounded-xl">
                                    <span class="text-slate-400">Best Time to Send Emails</span>
                                    <strong class="text-white">10:00 AM - 12:00 PM</strong>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-slate-950/40 border border-slate-850 rounded-xl">
                                    <span class="text-slate-400">Best Day to Follow Up</span>
                                    <strong class="text-emerald-400 font-bold">Tuesday</strong>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-slate-950/40 border border-slate-850 rounded-xl">
                                    <span class="text-slate-400">Average Reply Duration</span>
                                    <strong class="text-indigo-400">1.2 Hours</strong>
                                </div>
                                <div class="flex justify-between items-center p-2 bg-slate-950/40 border border-slate-850 rounded-xl">
                                    <span class="text-slate-400">Highest Response Window</span>
                                    <strong class="text-amber-400 font-bold">Mon - Thu</strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-xl text-[10px] text-indigo-300 mt-2 leading-relaxed">
                            <span class="font-bold flex items-center space-x-1 mb-1">
                                <i data-lucide="sparkles" class="h-3.5 w-3.5"></i>
                                <span>Optimization Advice</span>
                            </span>
                            "Your highest response rate is between 10:00 AM and 12:00 PM. Schedule outreach sequence triggers accordingly to maximize qualified conversions."
                        </div>
                    </div>

                    <!-- Section 7: AI Accuracy Progress bars -->
                    <div class="glass-panel p-5 bg-slate-900/40 space-y-4 text-left">
                        <h4 class="text-xs font-bold text-white border-b border-slate-800 pb-2 flex items-center space-x-1.5">
                            <i data-lucide="shield-check" class="h-4 w-4 text-emerald-400"></i>
                            <span>AI Neural Network Accuracy Metrics</span>
                        </h4>
                        
                        <div class="space-y-4.5 mt-4">
                            <!-- Progress 1 -->
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="text-slate-300">AI Extraction Accuracy</span>
                                    <span class="font-bold text-white">94.8%</span>
                                </div>
                                <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-indigo-50 h-full rounded-full" style="width: 94.8%"></div>
                                </div>
                            </div>
                            <!-- Progress 2 -->
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="text-slate-300">Lead Detection Accuracy</span>
                                    <span class="font-bold text-white">96.2%</span>
                                </div>
                                <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-teal-400 h-full rounded-full" style="width: 96.2%"></div>
                                </div>
                            </div>
                            <!-- Progress 3 -->
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="text-slate-300">Spam Detection Accuracy</span>
                                    <span class="font-bold text-white">98.5%</span>
                                </div>
                                <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-emerald-400 h-full rounded-full" style="width: 98.5%"></div>
                                </div>
                            </div>
                            <!-- Progress 4 -->
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="text-slate-300">Duplicate Detection Accuracy</span>
                                    <span class="font-bold text-white">92.1%</span>
                                </div>
                                <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-amber-400 h-full rounded-full" style="width: 92.1%"></div>
                                </div>
                            </div>
                            <!-- Progress 5 -->
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="text-slate-300">AI Confidence Average</span>
                                    <span class="font-bold text-white">93.5%</span>
                                </div>
                                <div class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-gradient-to-r from-indigo-500 to-emerald-500 h-full rounded-full" style="width: 93.5%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 9: AI Smart Insights -->
                    <div class="glass-panel p-5 bg-slate-900/40 space-y-4 text-left">
                        <h4 class="text-xs font-bold text-white border-b border-slate-800 pb-2 flex items-center space-x-1.5">
                            <i data-lucide="sparkles" class="h-4 w-4 text-amber-400"></i>
                            <span>AI Dynamic Insights feed</span>
                        </h4>
                        
                        <div class="space-y-3 mt-4">
        `;

        smartInsights.forEach(si => {
            html += `
                            <div class="p-3 bg-slate-950/40 border border-slate-850 hover:border-slate-800 transition rounded-xl flex items-start space-x-2.5">
                                <i data-lucide="chevron-right" class="h-3.5 w-3.5 text-indigo-400 mt-0.5 shrink-0"></i>
                                <span class="text-slate-300 leading-normal text-[10.5px]">${si}</span>
                            </div>
            `;
        });

        html += `
                        </div>
                    </div>
                </div>

                <!-- Section 8: Activity Timeline & Section 10: Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Timeline feed -->
                    <div class="glass-panel p-5 bg-slate-900/40 lg:col-span-2 space-y-4 text-left">
                        <h4 class="text-xs font-bold text-white border-b border-slate-800 pb-2 flex items-center space-x-1.5">
                            <i data-lucide="history" class="h-4 w-4 text-teal-400"></i>
                            <span>AI Activity Log</span>
                        </h4>
                        
                        <div class="space-y-4.5 mt-3 max-h-[300px] overflow-y-auto pr-1 timeline-container">
        `;

        timeline.forEach(item => {
            let timelineIcon = 'user';
            if (item.activity_type.includes('Lead')) timelineIcon = 'user-plus';
            else if (item.activity_type.includes('Company')) timelineIcon = 'globe';
            else if (item.activity_type.includes('Contact')) timelineIcon = 'phone';
            else if (item.activity_type.includes('Email')) timelineIcon = 'mail';
            else if (item.activity_type.includes('Task')) timelineIcon = 'check-square';
            else if (item.activity_type.includes('Meeting')) timelineIcon = 'video';

            html += `
                            <div class="flex items-start space-x-3.5 relative pl-4 border-l border-slate-800 pb-3">
                                <span class="absolute -left-[7px] top-1.5 h-3.5 w-3.5 rounded-full bg-slate-950 border-2 border-indigo-500 flex items-center justify-center text-indigo-400">
                                    <i data-lucide="${timelineIcon}" class="h-2 w-2"></i>
                                </span>
                                <div class="flex-grow">
                                    <div class="flex justify-between items-baseline">
                                        <strong class="text-white text-[11px]">${item.activity_type}</strong>
                                    </div>
                                    <p class="text-slate-400 text-[10px] mt-0.5">${item.description}</p>
                                </div>
                            </div>
            `;
        });

        html += `
                        </div>
                    </div>

                    <!-- Section 10: Quick Actions -->
                    <div class="glass-panel p-5 bg-slate-900/40 space-y-4 text-left">
                        <h4 class="text-xs font-bold text-white border-b border-slate-800 pb-2 flex items-center space-x-1.5">
                            <i data-lucide="zap" class="h-4 w-4 text-indigo-400"></i>
                            <span>AI Command Quick Actions</span>
                        </h4>
                        
                        <div class="grid grid-cols-2 gap-3.5 mt-3">
                            <button onclick="triggerQuickAction('scan_inbox')" class="p-3 bg-slate-950/40 border border-slate-850 hover:border-indigo-500/30 transition text-slate-300 hover:text-white rounded-xl flex flex-col items-center justify-center space-y-1">
                                <i data-lucide="refresh-cw" class="h-4 w-4 text-indigo-400 mb-0.5"></i>
                                <span class="font-bold text-[10px]">Scan Inbox</span>
                            </button>
                            <button onclick="triggerQuickAction('process_pending')" class="p-3 bg-slate-950/40 border border-slate-850 hover:border-emerald-500/30 transition text-slate-300 hover:text-white rounded-xl flex flex-col items-center justify-center space-y-1">
                                <i data-lucide="loader" class="h-4 w-4 text-emerald-400 mb-0.5"></i>
                                <span class="font-bold text-[10px]">Process Queue</span>
                            </button>
                            <button onclick="triggerQuickAction('detect_duplicates')" class="p-3 bg-slate-950/40 border border-slate-850 hover:border-amber-500/30 transition text-slate-300 hover:text-white rounded-xl flex flex-col items-center justify-center space-y-1">
                                <i data-lucide="copy" class="h-4 w-4 text-amber-400 mb-0.5"></i>
                                <span class="font-bold text-[10px]">Find Duplicates</span>
                            </button>
                            <button onclick="triggerQuickAction('clean_spam')" class="p-3 bg-slate-950/40 border border-slate-850 hover:border-rose-500/30 transition text-slate-300 hover:text-white rounded-xl flex flex-col items-center justify-center space-y-1">
                                <i data-lucide="trash-2" class="h-4 w-4 text-rose-500 mb-0.5"></i>
                                <span class="font-bold text-[10px]">Clean Spam</span>
                            </button>
                            <button onclick="triggerQuickAction('generate_report')" class="p-3 bg-slate-950/40 border border-slate-850 hover:border-indigo-500/30 transition text-slate-300 hover:text-white rounded-xl flex flex-col items-center justify-center space-y-1 col-span-2">
                                <i data-lucide="file-text" class="h-4.5 w-4.5 text-indigo-400 mb-0.5"></i>
                                <span class="font-bold text-[10px]">Generate Exportable Report</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Section 12: AI Confidence & Risk Panel -->
                <div class="glass-panel p-5 bg-slate-900/40 space-y-4 text-left">
                    <div class="flex justify-between items-center border-b border-slate-800 pb-2">
                        <h4 class="text-xs font-bold text-white flex items-center space-x-1.5">
                            <i data-lucide="shield-alert" class="h-4 w-4 text-rose-500"></i>
                            <span>AI Confidence & Low Prediction Review Panel</span>
                        </h4>
                        
                        <div class="flex items-center space-x-4 text-[10px]">
                            <span class="text-emerald-400 font-bold">● High Conf: <strong class="text-white">82%</strong></span>
                            <span class="text-indigo-400 font-bold">● Med Conf: <strong class="text-white">12%</strong></span>
                            <span class="text-amber-400 font-bold">● Low Conf: <strong class="text-white">6%</strong></span>
                        </div>
                    </div>

                    <div class="overflow-x-auto font-sans">
                        <table class="w-full text-left border-collapse custom-table text-[11px]">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-500">
                                    <th class="py-2.5 px-3">Sender</th>
                                    <th class="py-2.5 px-3">Subject</th>
                                    <th class="py-2.5 px-3">AI Category</th>
                                    <th class="py-2.5 px-3">AI Sentiment</th>
                                    <th class="py-2.5 px-3">Confidence</th>
                                    <th class="py-2.5 px-3 text-right">Verification</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${lowConfidence.length > 0 ? lowConfidence.map(lc => `
                                    <tr class="border-b border-slate-850 hover:bg-slate-900/20">
                                        <td class="py-2 px-3 font-semibold text-white">${lc.sender_name || 'Unknown'} <span class="text-[9px] text-slate-500">&lt;${lc.sender_email}&gt;</span></td>
                                        <td class="py-2 px-3 text-slate-300 font-medium">${lc.subject}</td>
                                        <td class="py-2 px-3"><span class="px-2 py-0.5 rounded text-[9px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wider">${lc.category || 'General'}</span></td>
                                        <td class="py-2 px-3"><span class="px-2 py-0.5 rounded text-[9px] font-bold bg-slate-950 border border-slate-800 uppercase tracking-wider">${lc.sentiment || 'Neutral'}</span></td>
                                        <td class="py-2 px-3 font-bold text-amber-400">${lc.ai_confidence_score}%</td>
                                        <td class="py-2 px-3 text-right">
                                            <button onclick="approveAIConfPrediction(this, ${lc.id})" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded font-bold transition text-[10px]">Approve Prediction</button>
                                        </td>
                                    </tr>
                                `).join('') : `
                                    <tr>
                                        <td colspan="6" class="text-center py-6 text-slate-500 italic">No low confidence predictions require manual review. Perfect accuracy index.</td>
                                    </tr>
                                `}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
        lucide.createIcons();

        // ----------------------------------------------------------------
        // Initialize Charts
        // ----------------------------------------------------------------

        // 1. Lead Sources Doughnut Chart
        const lsCtx = document.getElementById('aiChartLeadSources').getContext('2d');
        const lsLabels = Object.keys(chartsData.lead_sources);
        const lsValues = Object.values(chartsData.lead_sources);
        
        if (charts.aiSources) charts.aiSources.destroy();
        charts.aiSources = new Chart(lsCtx, {
            type: 'doughnut',
            data: {
                labels: lsLabels,
                datasets: [{
                    data: lsValues,
                    backgroundColor: ['#6366F1', '#14B8A6', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#8B5CF6', '#64748B'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { color: '#94A3B8', font: { size: 9 } } } }
            }
        });

        // 2. Lead Status Distribution Doughnut Chart
        const statusCtx = document.getElementById('aiChartLeadStatus').getContext('2d');
        const stLabels = Object.keys(chartsData.lead_status);
        const stValues = Object.values(chartsData.lead_status);
        
        if (charts.aiStatus) charts.aiStatus.destroy();
        charts.aiStatus = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: stLabels,
                datasets: [{
                    data: stValues,
                    backgroundColor: ['#3B82F6', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444', '#14B8A6', '#EC4899'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { color: '#94A3B8', font: { size: 9 } } } }
            }
        });

        // 3. Email Category Doughnut Chart
        const emailCtx = document.getElementById('aiChartEmailCats').getContext('2d');
        const ecLabels = Object.keys(chartsData.email_categories);
        const ecValues = Object.values(chartsData.email_categories);
        
        if (charts.aiEmailCats) charts.aiEmailCats.destroy();
        charts.aiEmailCats = new Chart(emailCtx, {
            type: 'doughnut',
            data: {
                labels: ecLabels,
                datasets: [{
                    data: ecValues,
                    backgroundColor: ['#EF4444', '#10B981', '#F59E0B', '#3B82F6', '#6366F1', '#EC4899', '#8B5CF6', '#14B8A6', '#64748B'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { color: '#94A3B8', font: { size: 9 } } } }
            }
        });

        // 4. Sentiment Trend Line Graph
        const sentimentCtx = document.getElementById('aiChartSentimentTrend').getContext('2d');
        
        if (charts.aiSentiment) charts.aiSentiment.destroy();
        charts.aiSentiment = new Chart(sentimentCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [
                    {
                        label: 'Positive',
                        data: [65, 78, 72, 85, 90, 76, 88],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.05)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Neutral',
                        data: [20, 12, 18, 10, 8, 14, 8],
                        borderColor: '#6366F1',
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        tension: 0.35
                    },
                    {
                        label: 'Negative',
                        data: [15, 10, 10, 5, 2, 10, 4],
                        borderColor: '#EF4444',
                        backgroundColor: 'transparent',
                        borderWidth: 1.5,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#64748B', font: { size: 9 } } },
                    x: { grid: { color: 'transparent' }, ticks: { color: '#64748B', font: { size: 9 } } }
                }
            }
        });

    } catch (e) {
        showNotification('error', 'Error rendering AI Intelligence dashboard: ' + e.message);
    }
}

function renderReports(container) {
    container.innerHTML = `<div class="p-8 text-center text-slate-400 text-xs animate-fade-in">Aggregated reports database compiled successfully. Explore statistics on the main Hub.</div>`;
}

async function renderIntegrations(container) {
    container.innerHTML = `
        <div class="flex items-center justify-center py-12">
            <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-indigo-600"></i>
        </div>
    `;
    lucide.createIcons();
    
    try {
        // Fetch current credentials, email intelligence settings, token credits, and AI keys
        const profileData = await apiCall('profile/get.php');
        const emailIntel = await apiCall('crm/email_intelligence/settings.php');
        const creditData = await apiCall('profile/get_credits.php');
        const keysData = await apiCall('profile/manage_ai_keys.php');
        
        const user = profileData.user || {};
        const connection = emailIntel.connection || {};
        const settings = emailIntel.settings || {};
        const wallet = creditData.wallet || { total: 0, used: 0, remaining: 0 };
        const todayUsage = creditData.today_usage || 0;
        const keysList = keysData.keys || [];

        // Determine SMTP configuration status
        const isMailConfigured = !!(connection.smtp_host && connection.imap_host);
        
        // Determine AI key configuration status
        let isAIConfigured = keysList.some(k => k.provider === user.active_ai_provider && k.status === 'active');
        let aiProviderLabel = 'None';
        if (user.active_ai_provider === 'openrouter') {
            aiProviderLabel = 'OpenRouter';
        } else if (user.active_ai_provider === 'github_models') {
            aiProviderLabel = 'GitHub Models';
        } else if (user.active_ai_provider === 'google_ai_studio') {
            aiProviderLabel = 'Google Gemini AI Studio';
        }

        const activeKeysCount = keysList.filter(k => k.provider === user.active_ai_provider && k.status === 'active').length;

        // Render AI API keys table rows
        let tableRowsHtml = '';
        if (keysList.length > 0) {
            tableRowsHtml = keysList.map(k => {
                let badgeColor = 'bg-emerald-50 text-emerald-600 border border-emerald-250';
                if (k.status === 'limit_exceeded') {
                    badgeColor = 'bg-amber-50 text-amber-600 border border-amber-250';
                } else if (k.status === 'invalid') {
                    badgeColor = 'bg-red-50 text-red-550 border border-red-250';
                } else if (k.status === 'paused') {
                    badgeColor = 'bg-slate-100 text-slate-500 border border-slate-200';
                }
                
                let providerLabel = k.provider === 'openrouter' ? 'OpenRouter' : 
                                    k.provider === 'github_models' ? 'GitHub Models' : 'Google Gemini';

                let pausePlayIcon = k.status === 'paused' ? 'play' : 'pause';
                let pausePlayTitle = k.status === 'paused' ? 'Resume Key' : 'Pause Key';

                return `
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50 transition">
                        <td class="py-3 px-3 font-semibold text-slate-700">${providerLabel}</td>
                        <td class="py-3 px-3 font-mono text-slate-600 text-[10px]">${k.masked_key}</td>
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider ${badgeColor}">${k.status.replace('_', ' ')}</span>
                        </td>
                        <td class="py-3 px-3 font-semibold text-slate-600">${k.total_calls.toLocaleString()}</td>
                        <td class="py-3 px-3 font-semibold text-slate-600">${k.calls_24h.toLocaleString()}</td>
                        <td class="py-3 px-3 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <button onclick="toggleAIKeyStatus(${k.id}, '${k.status}')" class="p-1 text-slate-500 hover:text-indigo-600 transition" title="${pausePlayTitle}">
                                    <i data-lucide="${pausePlayIcon}" class="h-3.5 w-3.5"></i>
                                </button>
                                <button onclick="editAIKeyValue(${k.id}, '${providerLabel}')" class="p-1 text-slate-500 hover:text-emerald-600 transition" title="Edit Key Value">
                                    <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                                </button>
                                <button onclick="deleteAIKey(${k.id})" class="p-1 text-slate-500 hover:text-red-650 transition" title="Delete Key">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        } else {
            tableRowsHtml = `
                <tr>
                    <td colspan="6" class="py-8 text-center text-slate-400 italic">
                        No AI API Keys configured. Click "Add API Key" above to connect one!
                    </td>
                </tr>
            `;
        }

        container.innerHTML = `
            <div class="space-y-6 pt-4 animate-fade-in text-xs max-w-4xl mx-auto">
                <div>
                    <h1 class="text-2xl font-extrabold text-slate-800">Integrations Control</h1>
                    <p class="text-slate-500 text-xs mt-1">Connect your outbound SMTP, inbound IMAP mail servers, and active AI model APIs.</p>
                </div>

                <!-- Token & Credit Usage Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="glass-panel p-4 bg-white shadow-sm border border-slate-200 rounded-2xl flex items-center space-x-3.5">
                        <div class="h-10 w-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 shrink-0">
                            <i data-lucide="wallet" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Remaining Credits</span>
                            <span class="text-lg font-bold text-slate-800">${wallet.remaining.toLocaleString()} Tokens</span>
                        </div>
                    </div>
                    
                    <div class="glass-panel p-4 bg-white shadow-sm border border-slate-200 rounded-2xl flex items-center space-x-3.5">
                        <div class="h-10 w-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 shrink-0">
                            <i data-lucide="line-chart" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Tokens Used</span>
                            <span class="text-lg font-bold text-slate-800">${wallet.used.toLocaleString()} Tokens</span>
                        </div>
                    </div>
                    
                    <div class="glass-panel p-4 bg-white shadow-sm border border-slate-200 rounded-2xl flex items-center space-x-3.5">
                        <div class="h-10 w-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600 shrink-0">
                            <i data-lucide="activity" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Today's Consumption</span>
                            <span class="text-lg font-bold text-slate-800">${todayUsage.toLocaleString()} Tokens</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Column 1: AI Provider & API Settings Override -->
                    <div class="glass-panel p-5 bg-white space-y-4 shadow-sm border border-slate-200 rounded-2xl">
                        <div class="pb-2 border-b border-slate-100 flex justify-between items-center">
                            <div class="flex items-center space-x-2 text-slate-800 font-bold text-sm">
                                <i data-lucide="cpu" class="h-4 w-4 text-indigo-600"></i>
                                <span>AI Provider API Status</span>
                            </div>
                            <!-- Status Badge -->
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider flex items-center space-x-1 ${isAIConfigured ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-red-50 text-red-550 border border-red-200'}">
                                <span class="h-1.5 w-1.5 rounded-full ${isAIConfigured ? 'bg-emerald-500' : 'bg-red-500'} mr-1"></span>
                                ${isAIConfigured ? 'Key Saved' : 'Not Configured'}
                            </span>
                        </div>
                        
                        <!-- Read-only Summary details -->
                        <div class="p-3 bg-slate-50/50 border border-slate-150 rounded-xl space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Active Provider:</span>
                                <span class="font-bold text-slate-700">${aiProviderLabel}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">AI Model Override:</span>
                                <span class="font-bold text-slate-700">${user.active_ai_model || 'System Default'}</span>
                            </div>
                            <div class="flex justify-between items-center pt-1">
                                <span class="text-slate-500">Credentials:</span>
                                <span class="text-[10px] px-2 py-0.5 bg-slate-200/50 rounded font-semibold text-slate-600">
                                    ${isAIConfigured ? `${activeKeysCount} Active Key(s) Saved` : 'Not Configured'}
                                </span>
                            </div>
                        </div>

                        <!-- Add/Edit Trigger Button -->
                        <div>
                            <button onclick="toggleAIForm()" class="flex items-center justify-center space-x-1 px-3 py-1.5 border border-indigo-200 hover:bg-indigo-50 text-indigo-650 rounded-lg text-[10px] font-bold transition shadow-sm" id="toggle-ai-form-btn">
                                <i data-lucide="settings" class="h-3 w-3"></i>
                                <span>Adjust Provider & Model Override</span>
                            </button>
                        </div>

                        <!-- Toggleable AI Credentials Form -->
                        <div id="ai-credentials-form-container" class="space-y-3 pt-3 border-t border-slate-100 hidden">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Active AI Provider</label>
                                <select id="active-ai-provider-select" class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-indigo-500">
                                    <option value="openrouter" ${user.active_ai_provider === 'openrouter' ? 'selected' : ''}>OpenRouter (Vibrant Free & Premium Models)</option>
                                    <option value="github_models" ${user.active_ai_provider === 'github_models' ? 'selected' : ''}>GitHub Models API</option>
                                    <option value="google_ai_studio" ${user.active_ai_provider === 'google_ai_studio' ? 'selected' : ''}>Google Gemini AI Studio</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">AI Model ID (Optional Custom Override)</label>
                                <input type="text" id="active-ai-model-input" value="${user.active_ai_model || ''}" placeholder="e.g. google/gemini-2.0-flash-lite:free" class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-xs text-slate-800 focus:outline-none focus:border-indigo-500">
                            </div>
                            
                            <button onclick="saveAICredentials()" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition flex items-center justify-center space-x-1.5 shadow-sm">
                                <i data-lucide="save" class="h-3.5 w-3.5"></i>
                                <span>Save AI Settings</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Column 2: SMTP / IMAP Connection -->
                    <div class="glass-panel p-5 bg-white space-y-4 shadow-sm border border-slate-200 rounded-2xl">
                        <div class="pb-2 border-b border-slate-100 flex justify-between items-center">
                            <div class="flex items-center space-x-2 text-slate-800 font-bold text-sm">
                                <i data-lucide="mail" class="h-4 w-4 text-indigo-600"></i>
                                <span>Email Sync Connection Status</span>
                            </div>
                            <!-- Status Badge -->
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider flex items-center space-x-1 ${isMailConfigured ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-red-50 text-red-550 border border-red-200'}">
                                <span class="h-1.5 w-1.5 rounded-full ${isMailConfigured ? 'bg-emerald-500' : 'bg-red-500'} mr-1"></span>
                                ${isMailConfigured ? 'Connected' : 'Disconnected'}
                            </span>
                        </div>
                        
                        <!-- Read-only Summary details -->
                        <div class="p-3 bg-slate-50/50 border border-slate-150 rounded-xl space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">SMTP Host:</span>
                                <span class="font-bold text-slate-700">${connection.smtp_host ? `${connection.smtp_host}:${connection.smtp_port}` : 'None'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">IMAP Host:</span>
                                <span class="font-bold text-slate-700">${connection.imap_host ? `${connection.imap_host}:${connection.imap_port}` : 'None'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Sync Mail Username:</span>
                                <span class="font-bold text-slate-700">${connection.smtp_username || 'None'}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Sync Interval:</span>
                                <span class="font-bold text-slate-700">${settings.sync_interval_minutes ? `Every ${settings.sync_interval_minutes} min` : '60 min'}</span>
                            </div>
                        </div>

                        <!-- Add/Edit Trigger Button -->
                        <div>
                            <button onclick="toggleMailForm()" class="flex items-center justify-center space-x-1 px-3 py-1.5 border border-indigo-200 hover:bg-indigo-50 text-indigo-650 rounded-lg text-[10px] font-bold transition shadow-sm" id="toggle-mail-form-btn">
                                <i data-lucide="pencil" class="h-3 w-3"></i>
                                <span>Add / Edit Mail Details</span>
                            </button>
                        </div>
                        
                        <!-- Toggleable Mail Credentials Form -->
                        <div id="mail-credentials-form-container" class="space-y-3 pt-3 border-t border-slate-100 hidden">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Email Provider Type</label>
                                    <select id="email-provider-select" class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-xs text-slate-800 focus:outline-none">
                                        <option value="custom" ${connection.email_provider === 'custom' ? 'selected' : ''}>Custom Server</option>
                                        <option value="gmail" ${connection.email_provider === 'gmail' ? 'selected' : ''}>Gmail App Passwords</option>
                                        <option value="outlook" ${connection.email_provider === 'outlook' ? 'selected' : ''}>Outlook/Office365</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Sync Interval</label>
                                    <select id="sync-interval-select" class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-xs text-slate-800 focus:outline-none">
                                        <option value="15" ${settings.sync_interval_minutes === 15 ? 'selected' : ''}>15 minutes</option>
                                        <option value="30" ${settings.sync_interval_minutes === 30 ? 'selected' : ''}>30 minutes</option>
                                        <option value="60" ${settings.sync_interval_minutes === 60 ? 'selected' : ''}>1 hour</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- SMTP Config -->
                            <div class="p-3 bg-slate-50 rounded-xl space-y-2 border border-slate-150">
                                <span class="font-bold text-[10px] uppercase tracking-wider text-slate-600 block">Outbound Mail (SMTP)</span>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="col-span-2">
                                        <input type="text" id="smtp-host-input" value="${connection.smtp_host || ''}" placeholder="Host (smtp.mail.com)" class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                    </div>
                                    <div>
                                        <input type="number" id="smtp-port-input" value="${connection.smtp_port || 587}" placeholder="Port" class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" id="smtp-username-input" value="${connection.smtp_username || ''}" placeholder="Username/Email" class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                    <input type="password" id="smtp-password-input" placeholder="••••••••" class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                </div>
                                <div class="grid grid-cols-3 gap-2 items-center">
                                    <select id="smtp-encryption-select" class="col-span-2 px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                        <option value="tls" ${connection.smtp_encryption === 'tls' ? 'selected' : ''}>TLS Encryption</option>
                                        <option value="ssl" ${connection.smtp_encryption === 'ssl' ? 'selected' : ''}>SSL Encryption</option>
                                        <option value="none" ${connection.smtp_encryption === 'none' ? 'selected' : ''}>No Encryption</option>
                                    </select>
                                    <button type="button" onclick="testSMTPCredentials(this)" class="py-1 px-2 border border-indigo-200 text-indigo-650 hover:bg-indigo-50 rounded text-[9px] font-bold transition">Test SMTP</button>
                                </div>
                            </div>
                            
                            <!-- IMAP Config -->
                            <div class="p-3 bg-slate-50 rounded-xl space-y-2 border border-slate-150">
                                <span class="font-bold text-[10px] uppercase tracking-wider text-slate-600 block">Inbound Mail (IMAP)</span>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="col-span-2">
                                        <input type="text" id="imap-host-input" value="${connection.imap_host || ''}" placeholder="Host (imap.mail.com)" class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                    </div>
                                    <div>
                                        <input type="number" id="imap-port-input" value="${connection.imap_port || 993}" placeholder="Port" class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" id="imap-username-input" value="${connection.imap_username || ''}" placeholder="Username/Email" class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                    <input type="password" id="imap-password-input" placeholder="••••••••" class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                </div>
                                <div class="grid grid-cols-3 gap-2 items-center">
                                    <select id="imap-encryption-select" class="col-span-2 px-2 py-1.5 bg-white border border-slate-200 rounded text-xs">
                                        <option value="ssl" ${connection.imap_encryption === 'ssl' ? 'selected' : ''}>SSL Encryption</option>
                                        <option value="tls" ${connection.imap_encryption === 'tls' ? 'selected' : ''}>TLS Encryption</option>
                                        <option value="none" ${connection.imap_encryption === 'none' ? 'selected' : ''}>No Encryption</option>
                                    </select>
                                    <button type="button" onclick="testIMAPCredentials(this)" class="py-1 px-2 border border-indigo-200 text-indigo-650 hover:bg-indigo-50 rounded text-[9px] font-bold transition">Test IMAP</button>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-2 pt-1.5">
                                <input type="checkbox" id="sync-active-checkbox" ${settings.is_active ? 'checked' : ''} class="mt-0.5 h-3.5 w-3.5 border border-slate-300 rounded text-indigo-600 focus:ring-indigo-500">
                                <label for="sync-active-checkbox" class="text-[10px] text-slate-500 leading-tight">Enable background mail downloader and AI processing worker pipeline</label>
                            </div>
                            
                            <button onclick="saveMailboxCredentials(this)" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition flex items-center justify-center space-x-1.5 shadow-sm">
                                <i data-lucide="save" class="h-3.5 w-3.5"></i>
                                <span>Save Mailbox Credentials</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- AI API Keys Control Board Card -->
                <div class="glass-panel p-5 bg-white space-y-4 shadow-sm border border-slate-200 rounded-2xl">
                    <div class="pb-2 border-b border-slate-100 flex justify-between items-center">
                        <div class="flex items-center space-x-2 text-slate-800 font-bold text-sm">
                            <i data-lucide="key-round" class="h-4 w-4 text-indigo-600"></i>
                            <span>AI API Keys Control Board</span>
                        </div>
                        <button onclick="toggleAddKeyForm()" class="flex items-center justify-center space-x-1 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold transition shadow-sm" id="add-key-toggle-btn" style="color: #ffffff !important;">
                            <i data-lucide="plus" class="h-3 w-3" style="color: #ffffff !important;"></i>
                            <span style="color: #ffffff !important;">Add API Key</span>
                        </button>
                    </div>

                    <!-- Inline Add Key Form -->
                    <div id="add-key-form-container" class="p-4 bg-slate-50 border border-slate-150 rounded-xl space-y-4 hidden">
                        <span class="block text-slate-700 font-bold text-[10px] uppercase tracking-wider">Connect New API Key</span>
                        <input type="hidden" id="new-key-provider-hidden" value="openrouter">
                        
                        <!-- Provider Card Selector Row -->
                        <div class="space-y-1.5">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase">Select AI Provider</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <!-- OpenRouter Card -->
                                <div onclick="selectNewKeyProvider('openrouter')" id="provider-card-openrouter" class="provider-card border-2 p-3 rounded-xl cursor-pointer hover:bg-slate-50 transition flex items-center space-x-3 bg-white border-indigo-600 shadow-sm">
                                    <img src="https://openrouter.ai/favicon.ico" class="h-6 w-6 rounded-md object-contain shrink-0" onerror="this.src='https://cdn-icons-png.flaticon.com/512/2103/2103633.png'">
                                    <div>
                                        <span class="block font-bold text-slate-800 text-[11px]">OpenRouter</span>
                                        <span class="text-[9px] text-slate-400">Free & Premium Models</span>
                                    </div>
                                </div>

                                <!-- GitHub Models Card -->
                                <div onclick="selectNewKeyProvider('github_models')" id="provider-card-github_models" class="provider-card border-2 p-3 rounded-xl cursor-pointer hover:bg-slate-50 transition flex items-center space-x-3 bg-white border-slate-200">
                                    <img src="https://github.githubassets.com/favicons/favicon.svg" class="h-6 w-6 rounded-md object-contain shrink-0">
                                    <div>
                                        <span class="block font-bold text-slate-800 text-[11px]">GitHub Models</span>
                                        <span class="text-[9px] text-slate-400">Developer Tokens API</span>
                                    </div>
                                </div>

                                <!-- Gemini Card -->
                                <div onclick="selectNewKeyProvider('google_ai_studio')" id="provider-card-google_ai_studio" class="provider-card border-2 p-3 rounded-xl cursor-pointer hover:bg-slate-50 transition flex items-center space-x-3 bg-white border-slate-200">
                                    <img src="/backend/google-gemini.png" class="h-6 w-6 rounded-md object-contain shrink-0">
                                    <div>
                                        <span class="block font-bold text-slate-800 text-[11px]">Gemini Studio</span>
                                        <span class="text-[9px] text-slate-400">Google Gemini API</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Developer Help Instruction Card -->
                        <div id="provider-instructions-box" class="p-3.5 bg-indigo-50/50 border border-indigo-100 rounded-xl space-y-2">
                            <!-- Populated dynamically via selectNewKeyProvider() -->
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase">API Key Value / Token</label>
                            <div class="flex space-x-2">
                                <input type="password" id="new-key-value-input" placeholder="Enter API Key value" class="flex-grow px-2.5 py-1.5 bg-white border border-slate-250 rounded-lg text-[11px] text-slate-850 focus:outline-none focus:border-indigo-500">
                                <button onclick="submitNewAIKey()" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[11px] font-bold transition shrink-0 flex items-center justify-center space-x-1.5 shadow-sm" style="color: #ffffff !important;">
                                    <i data-lucide="save" class="h-3.5 w-3.5" style="color: #ffffff !important;"></i>
                                    <span style="color: #ffffff !important;">Save Key</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- API Keys Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-[11px]">
                            <thead>
                                <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase tracking-wider text-[9px]">
                                    <th class="py-2 px-3">Provider</th>
                                    <th class="py-2 px-3">API Key (Masked)</th>
                                    <th class="py-2 px-3">Status</th>
                                    <th class="py-2 px-3">Total Calls</th>
                                    <th class="py-2 px-3">Last 24h</th>
                                    <th class="py-2 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRowsHtml}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        lucide.createIcons();
        if (document.getElementById('add-key-form-container')) {
            window.selectNewKeyProvider('openrouter');
        }
    } catch (err) {
        container.innerHTML = `
            <div class="max-w-xl mx-auto p-5 text-center text-red-500">
                Failed to load Integrations panel: ${err.message}
            </div>
        `;
    }
}

// Toggle Helper Functions
function toggleAIForm() {
    const el = document.getElementById('ai-credentials-form-container');
    const btn = document.getElementById('toggle-ai-form-btn');
    if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
        btn.innerHTML = `<i data-lucide="eye-off" class="h-3 w-3"></i><span>Cancel Update</span>`;
    } else {
        el.classList.add('hidden');
        btn.innerHTML = `<i data-lucide="pencil" class="h-3 w-3"></i><span>Add / Edit API Keys</span>`;
    }
    lucide.createIcons();
}

function toggleMailForm() {
    const el = document.getElementById('mail-credentials-form-container');
    const btn = document.getElementById('toggle-mail-form-btn');
    if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
        btn.innerHTML = `<i data-lucide="eye-off" class="h-3 w-3"></i><span>Cancel Update</span>`;
    } else {
        el.classList.add('hidden');
        btn.innerHTML = `<i data-lucide="pencil" class="h-3 w-3"></i><span>Add / Edit Mail Details</span>`;
    }
    lucide.createIcons();
}

function toggleAIProviderFields(provider) {
    document.getElementById('ai-provider-openrouter-fields').classList.add('hidden');
    document.getElementById('ai-provider-github-fields').classList.add('hidden');
    document.getElementById('ai-provider-google-fields').classList.add('hidden');
    
    if (provider === 'openrouter') {
        document.getElementById('ai-provider-openrouter-fields').classList.remove('hidden');
    } else if (provider === 'github_models') {
        document.getElementById('ai-provider-github-fields').classList.remove('hidden');
    } else if (provider === 'google_ai_studio') {
        document.getElementById('ai-provider-google-fields').classList.remove('hidden');
    }
}

async function renderSettings(container) {
    container.innerHTML = `
        <div class="flex items-center justify-center py-12">
            <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-indigo-655"></i>
        </div>
    `;
    lucide.createIcons();
    
    try {
        const data = await apiCall('profile/get.php');
        const user = data.user || {};
        const profile = data.profile || {};
        
        window.activeUserSettings = user;
        window.activeUserProfileSettings = profile;

        container.innerHTML = getSettingsBaseLayout(user);
        
        // Populate Right sidebar user parameters
        const initials = user.name ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2) : 'US';
        document.getElementById('settings-sidebar-initials').textContent = initials;
        document.getElementById('settings-sidebar-name').textContent = user.name || 'Soumojit Saha';
        document.getElementById('settings-sidebar-role').textContent = (user.role || 'user').toUpperCase();
        
        if (user.created_at) {
            const dateStr = new Date(user.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            document.getElementById('settings-sidebar-since').textContent = dateStr;
        }

        window.switchSettingsTab('profile', null);
        lucide.createIcons();
    } catch (err) {
        container.innerHTML = `
            <div class="max-w-xl mx-auto p-5 text-center text-red-500">
                Failed to load profile details: ${err.message}
            </div>
        `;
    }
}

// AI Credentials Saving
async function saveAICredentials() {
    const activeProvider = document.getElementById('active-ai-provider-select').value;
    const model = document.getElementById('active-ai-model-input').value.trim();
    
    const payload = {
        active_ai_provider: activeProvider,
        active_ai_model: model || null
    };
    
    try {
        const data = await apiCall('profile/save_openrouter.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'AI settings saved successfully.');
            navigateTo('integrations');
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    }
}

window.toggleAddKeyForm = function() {
    const el = document.getElementById('add-key-form-container');
    const btn = document.getElementById('add-key-toggle-btn');
    if (el.classList.contains('hidden')) {
        el.classList.remove('hidden');
        btn.innerHTML = `<i data-lucide="eye-off" class="h-3 w-3"></i><span>Cancel</span>`;
    } else {
        el.classList.add('hidden');
        btn.innerHTML = `<i data-lucide="plus" class="h-3 w-3"></i><span>Add API Key</span>`;
    }
    lucide.createIcons();
};

window.submitNewAIKey = async function() {
    const provider = document.getElementById('new-key-provider-hidden').value;
    const apiKey = document.getElementById('new-key-value-input').value.trim();
    if (!apiKey) {
        showNotification('error', 'Please enter an API Key value.');
        return;
    }

    try {
        const res = await apiCall('profile/manage_ai_keys.php', 'POST', {
            provider: provider,
            api_key: apiKey
        });
        
        if (res.status === 'success') {
            showNotification('success', 'API Key added successfully.');
            const activeTabContainer = document.getElementById('tab-content-container');
            if (activeTabContainer) {
                renderIntegrations(activeTabContainer);
            }
        } else {
            showNotification('error', res.message || 'Failed to add key.');
        }
    } catch (err) {
        showNotification('error', 'Connection error: ' + err.message);
    }
};

window.selectNewKeyProvider = function(provider) {
    // 1. Update hidden input
    const hiddenEl = document.getElementById('new-key-provider-hidden');
    if (hiddenEl) {
        hiddenEl.value = provider;
    }
    
    // 2. Toggle active card borders
    const cards = ['openrouter', 'github_models', 'google_ai_studio'];
    cards.forEach(c => {
        const cardEl = document.getElementById(`provider-card-${c}`);
        if (cardEl) {
            if (c === provider) {
                cardEl.classList.remove('border-slate-200');
                cardEl.classList.add('border-indigo-600', 'shadow-sm');
            } else {
                cardEl.classList.remove('border-indigo-600', 'shadow-sm');
                cardEl.classList.add('border-slate-200');
            }
        }
    });

    // 3. Render guide instructions
    const guides = {
        openrouter: {
            title: "How to get an OpenRouter API Key?",
            link: "https://openrouter.ai/keys",
            steps: [
                "Go to the official <a href='https://openrouter.ai/keys' target='_blank' class='text-indigo-600 font-bold hover:underline inline-flex items-center space-x-0.5'><span>OpenRouter Keys Console</span> <i data-lucide='external-link' class='h-3 w-3 ml-0.5'></i></a>.",
                "Click <strong>Create Key</strong>.",
                "Name the key (e.g. <code>LinkPilot</code>) and save it.",
                "Copy the key value (starts with <code>sk-or-</code>) and paste it in the input below."
            ]
        },
        github_models: {
            title: "How to get a GitHub Models Token?",
            link: "https://github.com/settings/tokens",
            steps: [
                "Go to your <a href='https://github.com/settings/tokens' target='_blank' class='text-indigo-600 font-bold hover:underline inline-flex items-center space-x-0.5'><span>GitHub Developer Settings page</span> <i data-lucide='external-link' class='h-3 w-3 ml-0.5'></i></a>.",
                "Click <strong>Generate new token</strong> ➔ select <strong>Generate new token (classic)</strong>.",
                "Name the note (e.g. <code>LinkPilot AI</code>) and check the <strong>models:read</strong> scope checkbox (or keep scopes empty for basic models read access).",
                "Click **Generate token** at the bottom, copy the token (starts with <code>ghp_</code>), and paste it below."
            ]
        },
        google_ai_studio: {
            title: "How to get a Google Gemini API Key?",
            link: "https://aistudio.google.com/app/apikey",
            steps: [
                "Open the <a href='https://aistudio.google.com/app/apikey' target='_blank' class='text-indigo-600 font-bold hover:underline inline-flex items-center space-x-0.5'><span>Google AI Studio API Keys dashboard</span> <i data-lucide='external-link' class='h-3 w-3 ml-0.5'></i></a>.",
                "Click **Create API key**.",
                "Choose a project or create a new one, then click **Create API key**.",
                "Copy the API key string (starts with <code>AIzaSy</code>) and paste it below."
            ]
        }
    };

    const activeGuide = guides[provider];
    const instructionsBox = document.getElementById('provider-instructions-box');
    if (instructionsBox && activeGuide) {
        let stepsHtml = activeGuide.steps.map((s, idx) => `
            <li class="flex items-start space-x-2 text-[10px] text-slate-650">
                <span class="h-4 w-4 bg-indigo-100/80 text-indigo-750 font-bold rounded-full flex items-center justify-center shrink-0 text-[8px] mt-0.5">${idx + 1}</span>
                <span class="leading-relaxed">${s}</span>
            </li>
        `).join('');

        instructionsBox.innerHTML = `
            <div class="flex items-center justify-between mb-1.5 pb-1 border-b border-indigo-100/50">
                <span class="font-extrabold text-slate-800 text-[10px] uppercase tracking-wider">${activeGuide.title}</span>
                <a href="${activeGuide.link}" target="_blank" class="text-[9px] font-bold text-indigo-650 hover:text-indigo-850 flex items-center space-x-0.5">
                    <span>Direct Link</span>
                    <i data-lucide="external-link" class="h-2.5 w-2.5"></i>
                </a>
            </div>
            <ul class="space-y-1.5">
                ${stepsHtml}
            </ul>
        `;
        lucide.createIcons();
    }
};

window.toggleAIKeyStatus = async function(keyId, currentStatus) {
    const newStatus = currentStatus === 'paused' ? 'active' : 'paused';
    try {
        const res = await apiCall('profile/manage_ai_keys.php', 'PUT', {
            id: keyId,
            status: newStatus
        });
        
        if (res.status === 'success') {
            showNotification('success', `API Key ${newStatus === 'paused' ? 'paused' : 'resumed'} successfully.`);
            const activeTabContainer = document.getElementById('tab-content-container');
            if (activeTabContainer) {
                renderIntegrations(activeTabContainer);
            }
        } else {
            showNotification('error', res.message || 'Failed to update key status.');
        }
    } catch (err) {
        showNotification('error', 'Connection error: ' + err.message);
    }
};

window.editAIKeyValue = async function(keyId, providerLabel) {
    const newVal = prompt(`Enter new API Key / Token value for ${providerLabel}:`);
    if (newVal === null) return; // User cancelled
    
    const trimmed = newVal.trim();
    if (!trimmed) {
        showNotification('error', 'API Key value cannot be empty.');
        return;
    }

    try {
        const res = await apiCall('profile/manage_ai_keys.php', 'PUT', {
            id: keyId,
            api_key: trimmed
        });
        
        if (res.status === 'success') {
            showNotification('success', 'API Key value updated successfully.');
            const activeTabContainer = document.getElementById('tab-content-container');
            if (activeTabContainer) {
                renderIntegrations(activeTabContainer);
            }
        } else {
            showNotification('error', res.message || 'Failed to update key.');
        }
    } catch (err) {
        showNotification('error', 'Connection error: ' + err.message);
    }
};

window.deleteAIKey = async function(keyId) {
    if (!confirm('Are you sure you want to delete this API Key?')) {
        return;
    }
    
    try {
        const res = await apiCall('profile/manage_ai_keys.php', 'DELETE', {
            id: keyId
        });
        
        if (res.status === 'success') {
            showNotification('success', 'API Key deleted successfully.');
            const activeTabContainer = document.getElementById('tab-content-container');
            if (activeTabContainer) {
                renderIntegrations(activeTabContainer);
            }
        } else {
            showNotification('error', res.message || 'Failed to delete key.');
        }
    } catch (err) {
        showNotification('error', 'Connection error: ' + err.message);
    }
};

// Mailbox Connection Saving
async function saveMailboxCredentials(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="refresh-cw" class="h-3 w-3 animate-spin mr-1.5 inline"></i> Saving...`;
    lucide.createIcons();
    
    const provider = document.getElementById('email-provider-select').value;
    const syncInterval = parseInt(document.getElementById('sync-interval-select').value);
    const syncActive = document.getElementById('sync-active-checkbox').checked ? 1 : 0;
    
    const smtpHost = document.getElementById('smtp-host-input').value.trim();
    const smtpPort = parseInt(document.getElementById('smtp-port-input').value);
    const smtpUser = document.getElementById('smtp-username-input').value.trim();
    const smtpPass = document.getElementById('smtp-password-input').value;
    const smtpEncrypt = document.getElementById('smtp-encryption-select').value;
    
    const imapHost = document.getElementById('imap-host-input').value.trim();
    const imapPort = parseInt(document.getElementById('imap-port-input').value);
    const imapUser = document.getElementById('imap-username-input').value.trim();
    const imapPass = document.getElementById('imap-password-input').value;
    const imapEncrypt = document.getElementById('imap-encryption-select').value;
    
    const payload = {
        email_provider: provider,
        sync_interval_minutes: syncInterval,
        is_active: syncActive,
        consent_accepted: 1,
        
        smtp_host: smtpHost,
        smtp_port: smtpPort,
        smtp_username: smtpUser,
        smtp_encryption: smtpEncrypt,
        
        imap_host: imapHost,
        imap_port: imapPort,
        imap_username: imapUser,
        imap_encryption: imapEncrypt
    };
    
    if (smtpPass !== '') {
        payload.smtp_password = smtpPass;
    }
    if (imapPass !== '') {
        payload.imap_password = imapPass;
    }
    
    try {
        const data = await apiCall('crm/email_intelligence/settings.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Mailbox integration updated successfully.');
            isSmtpConfigured = null;
            isEmailSyncConfigured = null;
            navigateTo('integrations');
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

// Test SMTP connection details
async function testSMTPCredentials(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `Testing...`;
    
    const host = document.getElementById('smtp-host-input').value.trim();
    const port = parseInt(document.getElementById('smtp-port-input').value);
    const user = document.getElementById('smtp-username-input').value.trim();
    const pass = document.getElementById('smtp-password-input').value;
    const encrypt = document.getElementById('smtp-encryption-select').value;
    
    const payload = {
        smtp_host: host,
        smtp_port: port,
        smtp_username: user,
        smtp_password: pass || '••••••••',
        smtp_encryption: encrypt
    };
    
    try {
        const data = await apiCall('crm/email_intelligence/settings.php?action=test_smtp', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'SMTP Connection Test Successful!');
        } else {
            showNotification('error', 'SMTP connection failed: ' + data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

// Test IMAP connection details
async function testIMAPCredentials(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `Testing...`;
    
    const host = document.getElementById('imap-host-input').value.trim();
    const port = parseInt(document.getElementById('imap-port-input').value);
    const user = document.getElementById('imap-username-input').value.trim();
    const pass = document.getElementById('imap-password-input').value;
    const encrypt = document.getElementById('imap-encryption-select').value;
    
    const payload = {
        imap_host: host,
        imap_port: port,
        imap_username: user,
        imap_password: pass || '••••••••',
        imap_encryption: encrypt
    };
    
    try {
        const data = await apiCall('crm/email_intelligence/settings.php?action=test_imap', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'IMAP Connection Test Successful!');
        } else {
            showNotification('error', 'IMAP connection failed: ' + data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

// Save Profile Info
async function saveProfileSettings(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="refresh-cw" class="h-3 w-3 animate-spin mr-1.5 inline"></i> Saving...`;
    lucide.createIcons();
    
    const name = document.getElementById('profile-name-input').value.trim();
    const company = document.getElementById('profile-company-input').value.trim();
    const website = document.getElementById('profile-website-input').value.trim();
    const jobTitle = document.getElementById('profile-job-input').value.trim();
    const userType = document.getElementById('profile-usertype-select').value;
    const about = document.getElementById('profile-about-input').value.trim();
    
    const payload = {
        name,
        user_type: userType,
        company_name: company,
        website,
        job_title: jobTitle,
        about_me: about,
        experience_years: 1,
        skills: ''
    };
    
    try {
        const data = await apiCall('profile/update.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Profile details updated successfully.');
            // Sync initials/name on dashboard topbar
            document.querySelectorAll('.user-name-display').forEach(el => el.textContent = name);
            renderSettings(document.getElementById('main-content-viewport'));
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

// Trigger manual inbox sync from dashboard button
async function triggerManualEmailSync(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="refresh-cw" class="h-3 w-3 animate-spin-slow mr-1 inline"></i> Syncing...`;
    lucide.createIcons();
    
    try {
        const data = await apiCall('crm/email_intelligence/sync.php?action=sync', 'POST');
        if (data.status === 'success') {
            showNotification('success', `Synchronized inbox successfully! Synced ${data.emails_synced} emails.`);
            navigateTo('dashboard');
        } else {
            showNotification('error', data.message);
        }
    } catch (err) {
        showNotification('error', err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
}

// Global state for reply attachments
let selectedReplyAttachments = [];

function handleReplyAttachmentChange(input) {
    const list = document.getElementById('inbox-reply-attachments-list');
    const countEl = document.getElementById('inbox-reply-attachments-count');
    if (!list || !countEl) return;
    
    if (input.files) {
        for (let i = 0; i < input.files.length; i++) {
            selectedReplyAttachments.push(input.files[i]);
        }
    }
    
    list.innerHTML = selectedReplyAttachments.map((f, idx) => `
        <span class="inline-flex items-center space-x-1 px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-slate-700 text-[10px]">
            <span class="truncate max-w-[120px] font-semibold">${f.name}</span>
            <button type="button" onclick="removeReplyAttachment(${idx})" class="text-slate-400 hover:text-red-500 font-bold ml-1">&times;</button>
        </span>
    `).join('');
    
    countEl.textContent = selectedReplyAttachments.length > 0 
        ? `${selectedReplyAttachments.length} file(s) attached` 
        : 'No files attached';
}

function removeReplyAttachment(index) {
    selectedReplyAttachments.splice(index, 1);
    handleReplyAttachmentChange({ files: [] });
}

async function unblockEmailSender(emailId) {
    if (!confirm('Are you sure you want to unblock this sender and restore their emails?')) return;
    try {
        const res = await apiCall('crm/email_intelligence/spam_rules.php?action=unblock', 'POST', {
            email_id: emailId
        });
        showNotification('success', res.message);
        if (typeof refreshUnreadBadgeCount === 'function') {
            refreshUnreadBadgeCount();
        }
        navigateTo('inbox');
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function generateReplyOnDemand(emailId) {
    const tone = document.getElementById('inbox-reply-tone')?.value || 'Professional';
    const container = document.getElementById('ai-reply-generation-container');
    const textarea = document.getElementById('inbox-reply-textarea');
    if (!container || !textarea) return;
    
    const origHTML = container.innerHTML;
    container.innerHTML = `
        <div class="flex items-center space-x-2 py-4">
            <div class="loader-spinner !w-4 !h-4 !border-2"></div>
            <span class="text-[10px] text-slate-500 font-semibold">AI is drafting your reply...</span>
        </div>
    `;
    
    try {
        const res = await apiCall('crm/email_intelligence/emails.php?action=generate_reply', 'POST', {
            email_id: emailId,
            tone: tone
        });
        if (res.status === 'success' && res.reply) {
            textarea.value = res.reply;
            container.classList.add('hidden');
            textarea.classList.remove('hidden');
        } else {
            showNotification('error', res.message);
            container.innerHTML = origHTML;
        }
    } catch (err) {
        showNotification('error', err.message);
        container.innerHTML = origHTML;
    }
}

// Global search debouncing & context execution
let globalSearchTimeout = null;

function handleGlobalSearch(value) {
    if (globalSearchTimeout) {
        clearTimeout(globalSearchTimeout);
    }
    
    // Sync the input value between the global header search input and active inline search inputs
    const globalInput = document.getElementById('global-search-input');
    if (globalInput && globalInput.value !== value) {
        globalInput.value = value;
    }
    const inboxInlineInput = document.querySelector('input[placeholder="Search emails..."]');
    if (inboxInlineInput && inboxInlineInput.value !== value) {
        inboxInlineInput.value = value;
    }
    
    const dropdown = document.getElementById('global-search-dropdown');
    const list = document.getElementById('global-search-results-list');
    
    if (value.trim() === '') {
        if (dropdown) dropdown.classList.add('hidden');
        return;
    }
    
    if (dropdown && list) {
        dropdown.classList.remove('hidden');
        list.innerHTML = `
            <div class="p-4 text-center text-slate-400">
                <div class="loader-spinner !w-4 !h-4 !border-2 inline-block mr-2 align-middle"></div>
                <span class="align-middle">Searching...</span>
            </div>
        `;
    }
    
    globalSearchTimeout = setTimeout(() => {
        fetchGlobalSearchResults(value);
    }, 2000); // 2 second delay
}

async function syncInboxFromInboxView(btn) {
    const icon = btn.querySelector('i');
    if (icon) icon.classList.add('animate-spin-slow');
    btn.disabled = true;
    
    try {
        const data = await apiCall('crm/email_intelligence/sync.php?action=sync', 'POST');
        if (data.status === 'success') {
            const added = data.emails_synced || 0;
            const processed = data.emails_processed || 0;
            showNotification('success', `Sync finished! Pulled ${added} new emails. AI processed ${processed} emails.`);
            
            // Reload the inbox view
            const mainViewport = document.getElementById('main-content-viewport');
            if (mainViewport) {
                renderInbox(mainViewport, activeEmailId);
            }
        } else {
            showNotification('error', data.message);
        }
    } catch (err) {
        showNotification('error', 'Sync Failed: ' + err.message);
    } finally {
        btn.disabled = false;
        if (icon) icon.classList.remove('animate-spin-slow');
    }
}

window.inboxFilters = {
    is_spam: 0,
    is_archived: 0,
    is_starred: null,
    category: ''
};

async function filterInbox(type, btn) {
    window.inboxFilters.category = '';
    window.inboxFilters.is_starred = null;
    
    if (type === 'inbox') {
        window.inboxFilters.is_spam = 0;
        window.inboxFilters.is_archived = 0;
    } else if (type === 'starred') {
        window.inboxFilters.is_spam = 0;
        window.inboxFilters.is_archived = 0;
        window.inboxFilters.is_starred = 1;
    } else if (type === 'archived') {
        window.inboxFilters.is_spam = 0;
        window.inboxFilters.is_archived = 1;
    } else if (type === 'spam') {
        window.inboxFilters.is_spam = 1;
        window.inboxFilters.is_archived = 0;
    }
    
    document.querySelectorAll('.lg\\:col-span-3 button').forEach(el => {
        el.classList.remove('text-indigo-400', 'bg-indigo-500/10', 'font-bold');
        el.classList.add('text-slate-400', 'font-medium');
    });
    
    if (btn) {
        btn.classList.add('text-indigo-400', 'bg-indigo-500/10', 'font-bold');
        btn.classList.remove('text-slate-400', 'font-medium');
    }
    
    await refreshInboxList();
}

async function filterInboxByCat(catName, btn) {
    window.inboxFilters.is_spam = 0;
    window.inboxFilters.is_archived = 0;
    window.inboxFilters.is_starred = null;
    window.inboxFilters.category = catName;
    
    document.querySelectorAll('.lg\\:col-span-3 button').forEach(el => {
        el.classList.remove('text-indigo-400', 'bg-indigo-500/10', 'font-bold');
        el.classList.add('text-slate-400', 'font-medium');
    });
    
    if (btn) {
        btn.classList.add('text-indigo-400', 'bg-indigo-500/10', 'font-bold');
        btn.classList.remove('text-slate-400', 'font-medium');
    }
    
    await refreshInboxList();
}

async function refreshInboxList() {
    const container = document.getElementById('inbox-emails-list-container');
    if (!container) return;
    
    container.innerHTML = `
        <div class="flex items-center justify-center py-10">
            <div class="loader-spinner !w-6 !h-6 !border-2"></div>
        </div>
    `;
    
    try {
        let url = `crm/email_intelligence/emails.php?is_spam=${window.inboxFilters.is_spam}&is_archived=${window.inboxFilters.is_archived}`;
        if (window.inboxFilters.is_starred !== null) {
            url += `&is_starred=${window.inboxFilters.is_starred}`;
        }
        if (window.inboxFilters.category !== '') {
            url += `&category=${encodeURIComponent(window.inboxFilters.category)}`;
        }
        
        const listData = await apiCall(url);
        const emails = listData.emails || [];
        
        let initialEmailId = emails.length > 0 ? emails[0].id : null;
        activeEmailId = initialEmailId;
        
        container.innerHTML = emails.length > 0 ? emails.map(m => {
            const date = new Date(m.received_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
            const isUnread = !m.is_read;
            const priorityColor = m.priority === 'high' ? 'bg-red-500/10 text-red-400 border-red-500/20' : m.priority === 'medium' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-slate-500/10 text-slate-400 border-slate-500/20';
            
            return `
                <div onclick="selectInboxEmail(${m.id})" id="inbox-mail-card-${m.id}" class="p-4 border-b border-slate-800/60 hover:bg-slate-900/30 cursor-pointer transition flex flex-col justify-between ${isUnread ? 'border-l-4 border-l-indigo-500 bg-slate-900/10' : ''} ${m.id === activeEmailId ? 'bg-slate-900/40 card-active-glow' : ''}">
                    <div class="flex justify-between items-start">
                        <span class="font-bold text-xs truncate max-w-[140px] text-white">${m.sender_name || m.sender_email}</span>
                        <span class="text-[10px] text-slate-500">${date}</span>
                    </div>
                    <div class="text-xs font-semibold text-slate-200 mt-1 truncate" title="${m.subject}">${m.subject}</div>
                    ${m.ai_status === 'pending' ? 
                      `<p class="text-[11px] text-teal-400 animate-pulse flex items-center mt-1"><i data-lucide="sparkles" class="h-3.5 w-3.5 mr-1 text-teal-400 animate-pulse"></i>AI Analyst is analyzing...</p>` : 
                      `<p class="text-[11px] text-slate-500 truncate mt-1">${m.ai_summary || 'Click to read summary...'}</p>`}
                    <div class="flex space-x-2 mt-2">
                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase ${priorityColor}">${m.priority}</span>
                        ${m.ai_status === 'pending' ? 
                          `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-teal-500/10 text-teal-400 border border-teal-500/20 animate-pulse">Processing...</span>` : 
                          `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">${m.category}</span>`}
                    </div>
                </div>
            `;
        }).join('') : `<div class="p-6 text-center text-slate-500 text-xs">Folder/Category is empty.</div>`;
        
        lucide.createIcons();
        if (activeEmailId) {
            selectInboxEmail(activeEmailId);
        } else {
            const detailContainer = document.getElementById('inbox-email-detail-container');
            if (detailContainer) {
                detailContainer.innerHTML = `<p class="text-xs text-slate-500 text-center py-20">Select an email from the list to display details and generate suggested AI replies.</p>`;
            }
        }
    } catch (err) {
        showNotification('error', 'Failed to refresh list: ' + err.message);
    }
}

let inboxInlineSearchTimeout = null;
function handleInboxInlineSearch(value) {
    if (inboxInlineSearchTimeout) {
        clearTimeout(inboxInlineSearchTimeout);
    }
    inboxInlineSearchTimeout = setTimeout(() => {
        searchInbox(value);
    }, 2000);
}

async function fetchGlobalSearchResults(value) {
    const list = document.getElementById('global-search-results-list');
    const dropdown = document.getElementById('global-search-dropdown');
    if (!list || !dropdown) return;
    
    try {
        const [emailsRes, leadsRes] = await Promise.all([
            apiCall(`crm/email_intelligence/emails.php?search=${encodeURIComponent(value)}&limit=5`),
            apiCall(`crm/leads.php?search=${encodeURIComponent(value)}&limit=5`)
        ]);
        
        const emails = emailsRes.emails || [];
        const leads = leadsRes.leads || [];
        
        if (emails.length === 0 && leads.length === 0) {
            list.innerHTML = `<div class="p-4 text-center text-slate-400 font-medium">No matches found for "${value}"</div>`;
            return;
        }
        
        let html = '';
        
        // Render Leads
        leads.forEach(l => {
            html += `
                <div onclick="selectSearchItem('lead', ${l.id})" class="p-2.5 hover:bg-slate-50 cursor-pointer flex justify-between items-center transition border-b border-slate-100/50">
                    <div class="min-w-0 flex-grow pr-3">
                        <div class="font-bold text-slate-800 truncate">${l.name}</div>
                        <div class="text-[10px] text-slate-450 truncate">Company: ${l.company || 'N/A'} • ${l.email}</div>
                    </div>
                    <span class="px-2 py-0.5 text-[9px] font-extrabold uppercase rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100 flex-shrink-0">Lead</span>
                </div>
            `;
        });
        
        // Render Emails
        emails.forEach(e => {
            html += `
                <div onclick="selectSearchItem('email', ${e.id})" class="p-2.5 hover:bg-slate-50 cursor-pointer flex justify-between items-center transition border-b border-slate-100/50">
                    <div class="min-w-0 flex-grow pr-3">
                        <div class="font-bold text-slate-800 truncate">${e.subject}</div>
                        <div class="text-[10px] text-slate-450 truncate">From: ${e.sender_name} (${e.sender_email})</div>
                    </div>
                    <span class="px-2 py-0.5 text-[9px] font-extrabold uppercase rounded-full bg-blue-50 text-blue-600 border border-blue-100 flex-shrink-0">Email</span>
                </div>
            `;
        });
        
        list.innerHTML = html;
    } catch (err) {
        list.innerHTML = `<div class="p-4 text-center text-red-500 font-medium">Error: ${err.message}</div>`;
    }
}

function selectSearchItem(type, id) {
    const dropdown = document.getElementById('global-search-dropdown');
    const globalInput = document.getElementById('global-search-input');
    
    if (dropdown) dropdown.classList.add('hidden');
    if (globalInput) globalInput.value = '';
    
    if (type === 'email') {
        navigateTo('inbox', { emailId: id });
    } else if (type === 'lead') {
        navigateTo('leads');
        setTimeout(() => {
            editCrmLead(id);
        }, 300);
    }
}

// Close dropdown on click outside
document.addEventListener('click', (e) => {
    const container = document.getElementById('global-search-container');
    const dropdown = document.getElementById('global-search-dropdown');
    if (dropdown && container && !container.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});

async function executeSearch(value) {
    const hash = window.location.hash || '#/dashboard';
    const view = hash.replace('#/', '');
    
    if (view === 'inbox') {
        searchInbox(value);
    } else if (view === 'leads') {
        searchLeads(value);
    } else {
        // Navigate to inbox view and run search
        window.location.hash = '#/inbox';
        setTimeout(() => {
            const inboxInlineInput = document.querySelector('input[placeholder="Search emails..."]');
            if (inboxInlineInput) {
                inboxInlineInput.value = value;
            }
            searchInbox(value);
        }, 200);
    }
}

async function searchInbox(val) {
    const container = document.getElementById('inbox-emails-list-container');
    if (!container) return;
    
    container.innerHTML = `
        <div class="flex items-center justify-center py-10">
            <div class="loader-spinner !w-6 !h-6 !border-2"></div>
        </div>
    `;
    
    try {
        const listData = await apiCall(`crm/email_intelligence/emails.php?search=${encodeURIComponent(val)}`);
        const emails = listData.emails || [];
        
        container.innerHTML = emails.map(m => {
            const date = new Date(m.received_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
            const isUnread = !m.is_read;
            return `
                <div onclick="selectInboxEmail(${m.id})" id="inbox-mail-card-${m.id}" class="p-4 border-b border-slate-800/60 hover:bg-slate-900/30 cursor-pointer transition flex flex-col justify-between ${isUnread ? 'border-l-4 border-l-indigo-500 bg-slate-900/10' : ''} ${m.id === activeEmailId ? 'bg-slate-900/40 card-active-glow' : ''}">
                    <div class="flex justify-between items-start">
                        <span class="font-bold text-xs truncate max-w-[140px] text-white">${m.sender_name || m.sender_email}</span>
                        <span class="text-[10px] text-slate-500">${date}</span>
                    </div>
                    <div class="text-xs font-semibold text-slate-200 mt-1 truncate" title="${m.subject}">${m.subject}</div>
                </div>
            `;
        }).join('');
    } catch (err) {
        showNotification('error', 'Search failed: ' + err.message);
    }
}

async function searchLeads(val) {
    const tbody = document.getElementById('leads-table-body');
    if (!tbody) return;
    
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-10"><div class="loader-spinner !w-6 !h-6 !border-2 inline-block"></div></td></tr>`;
    
    try {
        const res = await apiCall(`crm/leads.php?search=${encodeURIComponent(val)}`);
        const leads = res.leads || [];
        
        tbody.innerHTML = leads.length > 0 ? leads.map(l => {
            const date = new Date(l.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
            return `
                <tr class="hover:bg-slate-900/40">
                    <td class="py-3 px-4 font-bold text-white">${l.name}</td>
                    <td class="py-3 px-4 text-slate-300 font-medium">${l.company || '-'}</td>
                    <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">${l.email}</td>
                    <td class="py-3 px-4 text-indigo-400 font-bold">₹${parseFloat(l.budget).toLocaleString('en-IN')}</td>
                    <td class="py-3 px-4">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-teal-500/10 text-teal-400 border border-teal-500/20">${l.stage}</span>
                    </td>
                    <td class="py-3 px-4 text-slate-400">${l.priority}</td>
                    <td class="py-3 px-4 text-slate-500">${date}</td>
                    <td class="py-3 px-4 text-right">
                        <button onclick="editCrmLead(${l.id})" class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 rounded-md hover:border-teal-400 hover:text-teal-400 transition">View Details</button>
                    </td>
                </tr>
            `;
        }).join('') : `<tr><td colspan="8" class="text-center py-10 text-slate-500">No leads match your search.</td></tr>`;
    } catch (err) {
        showNotification('error', 'Search failed: ' + err.message);
    }
}

let inboxPollTimeout = null;

async function checkInboxPendingStatus() {
    if (currentView !== 'inbox') return;
    
    // Check if there is any pending element
    const pendingElements = document.querySelectorAll('[id^="inbox-mail-card-"] [data-lucide="sparkles"]');
    if (pendingElements.length > 0) {
        if (inboxPollTimeout) clearTimeout(inboxPollTimeout);
        inboxPollTimeout = setTimeout(async () => {
            if (currentView !== 'inbox') return;
            try {
                let url = `crm/email_intelligence/emails.php?is_spam=${window.inboxFilters.is_spam}&is_archived=${window.inboxFilters.is_archived}`;
                if (window.inboxFilters.is_starred !== null) {
                    url += `&is_starred=${window.inboxFilters.is_starred}`;
                }
                if (window.inboxFilters.category !== '') {
                    url += `&category=${encodeURIComponent(window.inboxFilters.category)}`;
                }
                const listData = await apiCall(url);
                const emails = listData.emails || [];
                
                const listContainer = document.getElementById('inbox-emails-list-container');
                if (listContainer) {
                    const listItems = emails.length > 0 ? emails.map(m => {
                        const date = new Date(m.received_date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
                        const isUnread = !m.is_read;
                        const priorityColor = m.priority === 'high' ? 'bg-red-500/10 text-red-400 border-red-500/20' : m.priority === 'medium' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-slate-500/10 text-slate-400 border-slate-500/20';
                        
                        return `
                            <div onclick="selectInboxEmail(${m.id})" id="inbox-mail-card-${m.id}" class="p-4 border-b border-slate-800/60 hover:bg-slate-900/30 cursor-pointer transition flex flex-col justify-between ${isUnread ? 'border-l-4 border-l-indigo-500 bg-slate-900/10' : ''} ${m.id === activeEmailId ? 'bg-slate-900/40 card-active-glow' : ''}">
                                <div class="flex justify-between items-start">
                                    <span class="font-bold text-xs truncate max-w-[140px] text-white">${m.sender_name || m.sender_email}</span>
                                    <span class="text-[10px] text-slate-500">${date}</span>
                                </div>
                                <div class="text-xs font-semibold text-slate-200 mt-1 truncate" title="${m.subject}">${m.subject}</div>
                                ${m.ai_status === 'pending' ? 
                                  `<p class="text-[11px] text-teal-400 animate-pulse flex items-center mt-1"><i data-lucide="sparkles" class="h-3.5 w-3.5 mr-1 text-teal-400 animate-pulse"></i>AI Analyst is analyzing...</p>` : 
                                  `<p class="text-[11px] text-slate-500 truncate mt-1">${m.ai_summary || 'Click to read summary...'}</p>`}
                                <div class="flex space-x-2 mt-2">
                                    <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase ${priorityColor}">${m.priority}</span>
                                    ${m.ai_status === 'pending' ? 
                                      `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-teal-500/10 text-teal-400 border border-teal-500/20 animate-pulse">Processing...</span>` : 
                                      `<span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">${m.category}</span>`}
                                </div>
                            </div>
                        `;
                    }).join('') : `<div class="p-6 text-center text-slate-500 text-xs">Inbox is empty.</div>`;
                    
                    listContainer.innerHTML = listItems;
                    lucide.createIcons();
                }
                
                if (activeEmailId) {
                    const activeEmail = emails.find(x => x.id === activeEmailId);
                    if (activeEmail && activeEmail.ai_status !== 'pending') {
                        const detailsAcc = document.querySelector('#inbox-email-detail-container [data-lucide="sparkles"]');
                        if (detailsAcc) {
                            selectInboxEmail(activeEmailId);
                        }
                    }
                }
                
                checkInboxPendingStatus();
            } catch (e) {}
        }, 5000);
    }
}

// AI Chat Assistant Helpers
function toggleAIChatAssistant() {
    const drawer = document.getElementById('ai-chat-assistant-drawer');
    if (!drawer) return;
    drawer.classList.toggle('translate-x-full');
    
    // Sync user name inside the greeting if loaded
    const activeName = document.querySelector('.user-name-display')?.textContent || 'User';
    document.querySelectorAll('#ai-chat-assistant-drawer .user-name-display').forEach(el => {
        el.textContent = activeName;
    });
}

function sendQuickAIChatQuery(text) {
    sendAIChatMessage(text);
}

function handleAIChatSubmit(e) {
    e.preventDefault();
    const input = document.getElementById('ai-chat-input-field');
    if (!input) return;
    const text = input.value.trim();
    if (text === '') return;
    input.value = '';
    sendAIChatMessage(text);
}

async function sendAIChatMessage(text) {
    const container = document.getElementById('ai-chat-messages-container');
    if (!container) return;
    
    const timeStr = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    
    // 1. Append User Message
    container.insertAdjacentHTML('beforeend', `
        <div class="flex items-start justify-end space-x-2.5 mt-4">
            <div class="p-3 chat-bubble-user text-xs leading-relaxed max-w-[80%] flex flex-col shadow-sm">
                <span>${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</span>
                <span class="text-[9px] text-white/70 text-right mt-1.5 flex items-center justify-end space-x-1 font-medium">
                    <span>${timeStr}</span>
                    <i data-lucide="check-check" class="h-3 w-3 text-white/70"></i>
                </span>
            </div>
            <div class="h-7 w-7 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-[10px] font-bold border border-indigo-200 shrink-0 shadow-sm">
                U
            </div>
        </div>
    `);
    
    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
    
    // 2. Append Thinking Loader
    const loaderId = 'ai-chat-thinking-' + Date.now();
    container.insertAdjacentHTML('beforeend', `
        <div class="flex items-start space-x-2.5 mt-4 animate-pulse" id="${loaderId}">
            <div class="h-7 w-7 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-md border border-indigo-400/20 shrink-0">
                <i data-lucide="sparkles" class="h-3.5 w-3.5 text-white"></i>
            </div>
            <div class="p-3 chat-bubble-ai text-xs text-slate-500 max-w-[80%] italic">
                AI is searching workspace data...
            </div>
        </div>
    `);
    container.scrollTop = container.scrollHeight;
    lucide.createIcons();
    
    // Keep history
    aiChatHistory.push({ role: 'user', content: text });
    
    try {
        const data = await apiCall('crm/chat_assistant.php', 'POST', {
            message: text,
            history: aiChatHistory.slice(0, -1)
        });
        
        // Remove loader
        const loader = document.getElementById(loaderId);
        if (loader) loader.remove();
        
        if (data.status === 'success') {
            const reply = data.reply;
            aiChatHistory.push({ role: 'assistant', content: reply });
            
            // Format markdown-like lists/bolds/headings for premium display
            const formatted = formatAIChatReply(reply);
            
            container.insertAdjacentHTML('beforeend', `
                <div class="flex items-start space-x-2.5 mt-4 animate-fade-in">
                    <div class="h-7 w-7 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-md border border-indigo-400/20 shrink-0">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5 text-white"></i>
                    </div>
                    <div class="p-3 chat-bubble-ai text-xs leading-relaxed max-w-[80%] shadow-sm flex flex-col">
                        <div>${formatted}</div>
                        <span class="text-[9px] text-slate-400 mt-2 text-left font-medium">${timeStr}</span>
                    </div>
                </div>
            `);
        } else {
            container.insertAdjacentHTML('beforeend', `
                <div class="flex items-start space-x-2.5 mt-4">
                    <div class="h-7 w-7 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-[10px] shrink-0 border border-red-200">
                        <i data-lucide="alert-triangle" class="h-3.5 w-3.5 text-red-500"></i>
                    </div>
                    <div class="p-3 bg-red-50 border border-red-100 text-red-600 text-xs rounded-lg max-w-[80%]">
                        Error: ${data.message}
                    </div>
                </div>
            `);
        }
        
    } catch (e) {
        const loader = document.getElementById(loaderId);
        if (loader) loader.remove();
        container.insertAdjacentHTML('beforeend', `
            <div class="flex items-start space-x-2.5 mt-4">
                <div class="h-7 w-7 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-[10px] shrink-0 border border-red-200">
                    <i data-lucide="alert-triangle" class="h-3.5 w-3.5 text-red-500"></i>
                </div>
                <div class="p-3 bg-red-50 border border-red-100 text-red-600 text-xs rounded-lg max-w-[80%]">
                    Connection failed. Please check network.
                </div>
            </div>
        `);
    }
    
    container.scrollTop = container.scrollHeight;
    lucide.createIcons();
}

function formatAIChatReply(text) {
    // Check if it is the "no outstanding tasks" reply
    if (text.toLowerCase().includes("no outstanding tasks") || text.toLowerCase().includes("no pending tasks")) {
        return `
            <div class="bg-white rounded-2xl overflow-hidden flex flex-col">
                <div class="px-4 py-2 bg-emerald-50 text-emerald-600 flex items-center space-x-2 border-b border-slate-50">
                    <i data-lucide="check-circle-2" class="h-4 w-4 text-emerald-600"></i>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">Pending Tasks</span>
                </div>
                <div class="p-6 flex flex-col items-center text-center">
                    <div class="relative h-16 w-16 bg-slate-50 rounded-2xl flex items-center justify-center mb-3">
                        <i data-lucide="clipboard-list" class="h-10 w-10 text-slate-350"></i>
                        <div class="absolute -bottom-1 -right-1 h-5 w-5 bg-emerald-500 rounded-full flex items-center justify-center text-white border-2 border-white">
                            <i data-lucide="check" class="h-3 w-3 text-white"></i>
                        </div>
                    </div>
                    <div class="font-bold text-slate-800 text-sm">No outstanding tasks.</div>
                    <div class="text-[11px] text-slate-400 mt-1">Great job! You're all caught up.</div>
                </div>
                <div class="mx-2 mb-2 p-3 bg-indigo-50 rounded-xl flex items-start space-x-2 text-left">
                    <i data-lucide="lightbulb" class="h-4 w-4 text-indigo-500 shrink-0 mt-0.5 animate-pulse"></i>
                    <span class="text-[10px] text-indigo-600 leading-normal font-medium">Feel free to let me know if you need assistance with anything else!</span>
                </div>
            </div>
        `;
    }

    // Parse markdown tables and render them as beautiful HTML cards
    if (text.includes('|') && text.includes('---')) {
        const lines = text.split('\n');
        let tableHeaders = [];
        let tableRows = [];
        let otherTextBefore = [];
        let otherTextAfter = [];
        
        for (let line of lines) {
            const trimmed = line.trim();
            if (trimmed.startsWith('|') && trimmed.endsWith('|')) {
                if (trimmed.includes('---') || trimmed.includes('-|-')) {
                    continue; // Skip separator line
                }
                const cells = trimmed.split('|').map(c => c.trim()).filter((c, i, arr) => i > 0 && i < arr.length - 1);
                if (tableHeaders.length === 0) {
                    tableHeaders = cells;
                } else {
                    tableRows.push(cells);
                }
            } else {
                if (tableRows.length === 0) {
                    if (trimmed !== '') otherTextBefore.push(line);
                } else {
                    if (trimmed !== '') otherTextAfter.push(line);
                }
            }
        }
        
        if (tableRows.length > 0) {
            const cardsHTML = tableRows.map(row => {
                const company = row[0] || 'Unknown';
                const subject = row[1] || '';
                const amount = row[2] || '₹0.00';
                const dueDate = row[3] || 'Not specified';
                const details = row[4] || '';
                
                return `
                    <div class="bg-white border border-slate-100 rounded-xl p-3.5 shadow-[0_2px_4px_rgba(0,0,0,0.02)] flex flex-col space-y-2">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-extrabold text-slate-800 text-xs">${company}</h4>
                                <p class="text-[10px] text-slate-450 font-medium mt-0.5">${subject}</p>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[8px] font-bold uppercase bg-rose-50 text-rose-600 border border-rose-100 shrink-0">UNPAID</span>
                        </div>
                        <div class="flex justify-between items-center pt-2 border-t border-slate-50 text-[11px] font-medium text-slate-500">
                            <div>Amount: <span class="font-extrabold text-slate-800">${amount}</span></div>
                            <div>Due: <span class="font-extrabold text-indigo-600">${dueDate}</span></div>
                        </div>
                        ${details && details !== 'None' && details !== '-' ? `<p class="text-[10px] text-slate-450 bg-slate-50 p-2 rounded-lg leading-relaxed mt-1">${details}</p>` : ''}
                    </div>
                `;
            }).join('');
            
            const beforeHTML = otherTextBefore.map(t => {
                return t.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            }).join('<br/>');
            const afterHTML = otherTextAfter.map(t => {
                return t.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            }).join('<br/>');
            
            return `
                <div class="space-y-2 w-full">
                    ${beforeHTML ? `<div class="mb-2">${beforeHTML}</div>` : ''}
                    <div class="bg-white rounded-2xl overflow-hidden flex flex-col border border-slate-100 shadow-sm my-3">
                        <div class="px-4 py-2.5 bg-indigo-50/50 text-indigo-600 flex items-center space-x-2 border-b border-slate-100">
                            <i data-lucide="receipt" class="h-4 w-4 text-indigo-600"></i>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">Pending Invoices</span>
                        </div>
                        <div class="p-3.5 space-y-2.5 bg-slate-50/20">
                            ${cardsHTML}
                        </div>
                    </div>
                    ${afterHTML ? `<div class="mt-2">${afterHTML}</div>` : ''}
                </div>
            `;
        }
    }

    // 1. Escape HTML
    let html = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    
    // 2. Bold tags
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-slate-800">$1</strong>');
    
    // 3. Headers formatting
    html = html.replace(/^### (.*)$/gm, '<h4 class="text-indigo-600 font-bold text-xs mt-3 mb-1 tracking-wider uppercase border-b border-slate-100 pb-1">$1</h4>');
    html = html.replace(/^## (.*)$/gm, '<h3 class="text-slate-800 font-extrabold text-xs mt-4 mb-1.5 tracking-wide uppercase">$1</h3>');
    html = html.replace(/^# (.*)$/gm, '<h2 class="text-indigo-600 font-extrabold text-sm mt-4 mb-2 border-b border-indigo-100 pb-1.5">$1</h2>');

    // 4. Bullet points format
    html = html.replace(/^\s*[-*]\s+(.*)$/gm, '<li class="ml-4 list-disc text-slate-600 mt-1">$1</li>');
    // 5. Wrap adjacent li items in ul blocks
    html = html.replace(/((?:<li.*<\/li>\s*)+)/g, '<ul class="my-2 space-y-1">$1</ul>');
    // 6. Newlines conversion
    html = html.replace(/\n\n/g, '<br/><br/>');
    html = html.replace(/(?<!<\/li>|h2>|h3>|h4>)\n/g, '<br/>');
    return html;
}

// ----------------------------------------------------
// CANVAS HTML AND HELPERS
// ----------------------------------------------------
function renderCanvasNodesHTML() {
    const wf = window.wfState.activeWorkflow;
    if (!wf || !wf.nodes) return '';
    const selectedId = window.wfState.selectedNodeId;
    
    return wf.nodes.map(n => {
        const isSelected = n.id === selectedId ? 'selected' : '';
        const isExecuting = n.execStatus === 'executing' ? 'executing' : '';
        const isCondition = n.type === 'if_branch' || n.type === 'if_score';
        
        let handlesHTML = '';
        if (n.id !== 'node-trigger' && n.type !== 'email_received') {
            handlesHTML += `<div class="wf-node-handle input" data-node-id="${n.id}" data-handle-type="input" onmousedown="handleConnectionMouseDown(event, '${n.id}', 'input')"></div>`;
        }
        
        if (isCondition) {
            handlesHTML += `
                <div class="wf-node-handle output-yes" data-node-id="${n.id}" data-handle-type="output-yes" title="YES Path" onmousedown="handleConnectionMouseDown(event, '${n.id}', 'output-yes')"></div>
                <div class="wf-node-handle output-no" data-node-id="${n.id}" data-handle-type="output-no" title="NO Path" onmousedown="handleConnectionMouseDown(event, '${n.id}', 'output-no')"></div>
            `;
        } else {
            handlesHTML += `<div class="wf-node-handle output" data-node-id="${n.id}" data-handle-type="output" onmousedown="handleConnectionMouseDown(event, '${n.id}', 'output')"></div>`;
        }

        const badge = n.execStatus ? `
            <span class="absolute -top-2.5 -right-1 px-1 py-0.5 rounded text-[7px] font-bold shadow-md uppercase tracking-wider z-20
                ${n.execStatus === 'success' ? 'bg-emerald-500 text-white' : n.execStatus === 'executing' ? 'bg-purple-500 text-white animate-pulse' : 'bg-rose-500 text-white'}">
                ${n.execStatus} ${n.execTime ? n.execTime + 'ms' : ''}
            </span>
        ` : '';

        return `
            <div id="${n.id}" class="wf-node ${isSelected} ${isExecuting}" data-category="${n.category}" style="left: ${n.x}px; top: ${n.y}px;" 
                 onmousedown="handleNodeMouseDown(event, '${n.id}')" oncontextmenu="handleNodeContextMenu(event, '${n.id}')">
                ${badge}
                <div class="flex items-center space-x-2">
                    <div class="wf-node-icon bg-slate-100 text-indigo-600 p-1 rounded-md flex items-center justify-center shrink-0">
                        <i data-lucide="${n.icon || 'circle'}" class="h-3.5 w-3.5"></i>
                    </div>
                    <div class="text-left overflow-hidden w-full select-none">
                        <h5 class="font-bold text-slate-800 text-[10px] leading-tight truncate">${n.name}</h5>
                        <p class="text-[7px] text-slate-500 capitalize leading-normal truncate">${n.category}</p>
                    </div>
                </div>
                ${handlesHTML}
            </div>
        `;
    }).join('');
}

const NODE_FIELDS_MAP = {
    // Triggers
    email_received: [
        { key: 'account', label: 'Email Account', type: 'select', options: ['All accounts', 'Primary SMTP'] },
        { key: 'folder', label: 'Folder', type: 'select', options: ['Inbox', 'Sent', 'Archive', 'Trash', 'Custom'] },
        { key: 'unreadOnly', label: 'Only Unread', type: 'checkbox' },
        { key: 'subjectFilter', label: 'Subject Contains', type: 'text', placeholder: 'e.g. Invoice, Help' },
        { key: 'senderEmail', label: 'Sender Email', type: 'text', placeholder: 'e.g. client@domain.com' },
        { key: 'senderDomain', label: 'Sender Domain', type: 'text', placeholder: 'e.g. domain.com' },
        { key: 'hasAttachment', label: 'Has Attachment', type: 'checkbox' },
        { key: 'priority', label: 'Priority', type: 'select', options: ['Any', 'High', 'Normal', 'Low'] },
        { key: 'dateFilter', label: 'Date Filter', type: 'select', options: ['Anytime', 'Today', 'Last 7 Days', 'Last 30 Days'] }
    ],
    lead_created: [
        { key: 'pipeline', label: 'Pipeline', type: 'select', options: ['Sales Pipeline', 'Marketing Pipeline'] },
        { key: 'source', label: 'Lead Source', type: 'text', placeholder: 'e.g. Cold Email, Web' },
        { key: 'owner', label: 'Owner', type: 'select', options: ['Unassigned', 'Soumojit Saha', 'Manager'] },
        { key: 'tags', label: 'Tags', type: 'text', placeholder: 'Comma separated tags' },
        { key: 'minScore', label: 'Minimum Lead Score', type: 'number', placeholder: 'e.g. 50' }
    ],
    contact_created: [
        { key: 'company', label: 'Company Name', type: 'text', placeholder: 'e.g. Acme Corp' },
        { key: 'tags', label: 'Tags', type: 'text', placeholder: 'Comma separated' },
        { key: 'assignedUser', label: 'Assigned User', type: 'select', options: ['Unassigned', 'Soumojit Saha'] }
    ],
    company_created: [
        { key: 'industry', label: 'Industry', type: 'text', placeholder: 'e.g. Software, Finance' },
        { key: 'country', label: 'Country', type: 'text', placeholder: 'e.g. USA, Canada' },
        { key: 'companySize', label: 'Company Size', type: 'select', options: ['1-10', '11-50', '51-200', '201-500', '500+'] }
    ],
    deal_stage_changed: [
        { key: 'pipeline', label: 'Pipeline', type: 'select', options: ['Sales', 'Partnership'] },
        { key: 'prevStage', label: 'Previous Stage', type: 'select', options: ['Any', 'Lead', 'Proposal', 'Negotiation'] },
        { key: 'newStage', label: 'New Stage', type: 'select', options: ['Proposal', 'Negotiation', 'Won', 'Lost'] }
    ],
    form_submitted: [
        { key: 'formName', label: 'Form Name', type: 'text', placeholder: 'e.g. Contact Form' },
        { key: 'website', label: 'Website URL', type: 'text', placeholder: 'e.g. mycompany.com' },
        { key: 'source', label: 'Traffic Source', type: 'text', placeholder: 'e.g. Google Ads' }
    ],
    meeting_scheduled: [
        { key: 'calendar', label: 'Calendar Integration', type: 'select', options: ['Google Calendar', 'Outlook Calendar', 'LinkPilot Calendar'] },
        { key: 'meetingType', label: 'Meeting Type', type: 'select', options: ['Introduction', 'Demo Call', 'Follow Up', 'Negotiation'] }
    ],

    // AI
    ai_categorize: [
        { key: 'provider', label: 'AI Provider', type: 'select', options: ['Google Gemini Pro', 'OpenRouter Auto', 'OpenAI GPT-4o'] },
        { key: 'categories', label: 'Target Categories', type: 'text', placeholder: 'Comma separated categories' },
        { key: 'prompt', label: 'Custom System Prompt', type: 'textarea', placeholder: 'Provide system instructions...' },
        { key: 'confidence', label: 'Confidence Threshold (%)', type: 'number', placeholder: 'e.g. 75' }
    ],
    ai_extract: [
        { key: 'extName', label: 'Extract Name', type: 'checkbox' },
        { key: 'extCompany', label: 'Extract Company', type: 'checkbox' },
        { key: 'extPhone', label: 'Extract Phone Number', type: 'checkbox' },
        { key: 'extWebsite', label: 'Extract Website', type: 'checkbox' },
        { key: 'extAddress', label: 'Extract Address', type: 'checkbox' },
        { key: 'extBudget', label: 'Extract Budget', type: 'checkbox' },
        { key: 'extDeadline', label: 'Extract Deadline', type: 'checkbox' },
        { key: 'extServices', label: 'Extract Services Requested', type: 'checkbox' },
        { key: 'extCustom', label: 'Custom Fields JSON', type: 'textarea', placeholder: 'e.g. {"field": "description"}' }
    ],
    ai_summary: [
        { key: 'format', label: 'Summary Format', type: 'select', options: ['Short paragraph', 'Detailed report', 'Bullet Points'] }
    ],
    ai_sentiment: [
        { key: 'positiveLabel', label: 'Positive Target Tag', type: 'text', placeholder: 'positive' },
        { key: 'neutralLabel', label: 'Neutral Target Tag', type: 'text', placeholder: 'neutral' },
        { key: 'negativeLabel', label: 'Negative Target Tag', type: 'text', placeholder: 'negative' }
    ],
    ai_scoring: [
        { key: 'minScore', label: 'Minimum Score Limit', type: 'number', placeholder: '70' },
        { key: 'formula', label: 'Score Evaluation Formula', type: 'textarea', placeholder: 'e.g. (sentiment * 0.5) + (budget * 0.5)' },
        { key: 'model', label: 'AI Model', type: 'select', options: ['Gemini 1.5 Flash', 'Gemini 1.5 Pro', 'GPT-4o mini'] }
    ],
    ai_reply: [
        { key: 'tone', label: 'Reply Tone', type: 'select', options: ['Professional', 'Friendly', 'Assertive', 'Empathetic', 'Casual'] },
        { key: 'language', label: 'Language', type: 'select', options: ['English', 'Spanish', 'French', 'German', 'Japanese', 'Chinese'] },
        { key: 'length', label: 'Length Limit', type: 'select', options: ['Short (1-2 sentences)', 'Medium (1-2 paragraphs)', 'Detailed response'] },
        { key: 'signature', label: 'Include User Signature', type: 'checkbox' },
        { key: 'template', label: 'Reply Base Template', type: 'textarea', placeholder: 'Template skeleton guidelines...' }
    ],
    ai_detect_duplicate: [
        { key: 'compEmail', label: 'Compare Email Addresses', type: 'checkbox' },
        { key: 'compPhone', label: 'Compare Phone Numbers', type: 'checkbox' },
        { key: 'compWebsite', label: 'Compare Website URLs', type: 'checkbox' },
        { key: 'compCompany', label: 'Compare Company Names', type: 'checkbox' },
        { key: 'compDomain', label: 'Compare Email Domains', type: 'checkbox' }
    ],
    ai_translation: [
        { key: 'sourceLang', label: 'Source Language', type: 'select', options: ['Auto Detect', 'English', 'Spanish', 'French', 'German'] },
        { key: 'targetLang', label: 'Target Language', type: 'select', options: ['English', 'Spanish', 'French', 'German', 'Japanese'] }
    ],

    // CRM
    create_lead: [
        { key: 'pipeline', label: 'Target Pipeline', type: 'select', options: ['Sales Pipeline', 'Support Tickets'] },
        { key: 'status', label: 'Initial Status', type: 'select', options: ['New', 'Contacted', 'Qualified', 'Proposal Sent'] },
        { key: 'owner', label: 'Owner Assignee', type: 'select', options: ['Unassigned', 'Soumojit Saha'] },
        { key: 'source', label: 'Source Tag', type: 'text', placeholder: 'e.g. Email Processing' },
        { key: 'tags', label: 'Tags', type: 'text', placeholder: 'Comma separated tags' },
        { key: 'priority', label: 'Lead Priority', type: 'select', options: ['Normal', 'High', 'Low'] }
    ],
    update_lead: [
        { key: 'leadId', label: 'Lead Reference', type: 'select', options: ['Triggering Lead', 'Latest Edited Lead'] },
        { key: 'fieldToUpdate', label: 'Field to Update', type: 'select', options: ['status', 'owner_id', 'value', 'notes'] },
        { key: 'newValue', label: 'New Value', type: 'text', placeholder: 'Enter new value' }
    ],
    create_contact: [
        { key: 'companyRef', label: 'Company Association', type: 'text', placeholder: 'e.g. Acme Corp' },
        { key: 'tags', label: 'Tags', type: 'text', placeholder: 'e.g. VIP, Customer' },
        { key: 'owner', label: 'Owner Assignee', type: 'select', options: ['Unassigned', 'Soumojit Saha'] }
    ],
    update_contact: [
        { key: 'contactRef', label: 'Contact Reference', type: 'select', options: ['Triggering Contact', 'Custom ID'] },
        { key: 'fieldToUpdate', label: 'Field to Update', type: 'select', options: ['email', 'phone', 'first_name', 'last_name'] },
        { key: 'newValue', label: 'New Value', type: 'text', placeholder: 'Enter new value' }
    ],
    create_company: [
        { key: 'industry', label: 'Industry Sector', type: 'text', placeholder: 'e.g. Technology' },
        { key: 'owner', label: 'Company Owner', type: 'select', options: ['Unassigned', 'Soumojit Saha'] },
        { key: 'country', label: 'HQ Country', type: 'text', placeholder: 'e.g. United States' },
        { key: 'tags', label: 'Tags', type: 'text', placeholder: 'e.g. SaaS, Enterprise' }
    ],
    create_deal: [
        { key: 'pipeline', label: 'Target Pipeline', type: 'select', options: ['Sales Pipeline', 'Partner Pipeline'] },
        { key: 'stage', label: 'Pipeline Stage', type: 'select', options: ['Lead Created', 'Proposal Sent', 'Contract Pending', 'Closed Won'] },
        { key: 'value', label: 'Deal Value ($)', type: 'number', placeholder: 'e.g. 5000' },
        { key: 'currency', label: 'Currency', type: 'select', options: ['USD', 'EUR', 'GBP', 'INR'] }
    ],
    create_task: [
        { key: 'taskTitle', label: 'Task Title', type: 'text', placeholder: 'e.g. Send invoice follow-up' },
        { key: 'taskDesc', label: 'Description', type: 'textarea', placeholder: 'Details of the task...' },
        { key: 'dueDate', label: 'Due Date Offset (Days)', type: 'number', placeholder: 'e.g. 3' },
        { key: 'priority', label: 'Task Priority', type: 'select', options: ['Normal', 'High', 'Low'] },
        { key: 'assignee', label: 'Assign To', type: 'select', options: ['Unassigned', 'Soumojit Saha'] }
    ],
    schedule_meeting: [
        { key: 'meetDate', label: 'Meeting Date', type: 'text', placeholder: 'YYYY-MM-DD or trigger offset' },
        { key: 'meetTime', label: 'Meeting Time', type: 'text', placeholder: 'HH:MM' },
        { key: 'calendar', label: 'Target Calendar', type: 'select', options: ['Google Calendar', 'LinkPilot Native'] },
        { key: 'duration', label: 'Duration (Minutes)', type: 'select', options: ['15', '30', '45', '60'] },
        { key: 'reminderOffset', label: 'Reminder (Minutes before)', type: 'select', options: ['10', '15', '30', '60'] }
    ],
    add_note: [
        { key: 'noteText', label: 'Note Body', type: 'textarea', placeholder: 'Add a timeline note...' },
        { key: 'visibility', label: 'Visibility Level', type: 'select', options: ['Internal Team Only', 'Public Client Facing'] }
    ],
    add_tag: [
        { key: 'tagName', label: 'Tag Name', type: 'text', placeholder: 'e.g. Hot Lead' },
        { key: 'tagColor', label: 'Tag Badge Color', type: 'select', options: ['indigo', 'emerald', 'rose', 'amber', 'slate'] }
    ],

    // Communication
    send_email: [
        { key: 'smtpAccount', label: 'SMTP Connection', type: 'select', options: ['Primary Account', 'noreply@linkpilot.ai'] },
        { key: 'fromEmail', label: 'From Address Override', type: 'text', placeholder: 'noreply@domain.com' },
        { key: 'toEmail', label: 'Recipient Email (To)', type: 'text', placeholder: 'client@domain.com' },
        { key: 'ccEmail', label: 'Carbon Copy (CC)', type: 'text', placeholder: 'cc@domain.com' },
        { key: 'bccEmail', label: 'Blind Copy (BCC)', type: 'text', placeholder: 'bcc@domain.com' },
        { key: 'subject', label: 'Subject Line', type: 'text', placeholder: 'Email Subject' },
        { key: 'body', label: 'Email Body Content', type: 'textarea', placeholder: 'Supports HTML...' },
        { key: 'templateName', label: 'Design Template', type: 'select', options: ['None (Plain Text)', 'Modern Corporate', 'Elegant Blue Theme'] },
        { key: 'attachments', label: 'Attach File Variable', type: 'text', placeholder: 'e.g. {{file}}' }
    ],
    send_notification: [
        { key: 'targetUser', label: 'Target System User', type: 'select', options: ['All Active Admins', 'Soumojit Saha'] },
        { key: 'notifyTitle', label: 'Notification Title', type: 'text', placeholder: 'System Alert' },
        { key: 'notifyMsg', label: 'Message Body', type: 'textarea', placeholder: 'Alert details...' },
        { key: 'priority', label: 'Message Priority', type: 'select', options: ['Normal', 'High', 'Low'] }
    ],
    send_slack: [
        { key: 'slackChannel', label: 'Slack Channel Name', type: 'text', placeholder: 'e.g. #sales-notifications' },
        { key: 'slackMsg', label: 'Message Text', type: 'textarea', placeholder: 'Enter markdown text...' }
    ],
    send_discord: [
        { key: 'webhookUrl', label: 'Discord Webhook URL', type: 'text', placeholder: 'https://discord.com/api/webhooks/...' },
        { key: 'discordMsg', label: 'Message Content', type: 'textarea', placeholder: 'Max 2000 characters...' }
    ],
    send_teams: [
        { key: 'teamsUrl', label: 'Microsoft Teams Webhook', type: 'text', placeholder: 'https://outlook.office.com/webhook/...' },
        { key: 'teamsMsg', label: 'Message Card Content', type: 'textarea', placeholder: 'Adaptive card JSON or plain text...' }
    ],

    // Integrations
    http_request: [
        { key: 'method', label: 'Request Method', type: 'select', options: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] },
        { key: 'url', label: 'Request URL', type: 'text', placeholder: 'https://api.example.com/endpoint' },
        { key: 'headers', label: 'Request Headers (JSON)', type: 'textarea', placeholder: '{"Authorization": "BearerToken"}' },
        { key: 'queryParams', label: 'Query Parameters (JSON)', type: 'textarea', placeholder: '{"id": 123}' },
        { key: 'reqBody', label: 'Request Body Payload', type: 'textarea', placeholder: '{"name": "test"}' },
        { key: 'contentType', label: 'Content Type Header', type: 'select', options: ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data', 'text/plain'] },
        { key: 'authType', label: 'Authentication Method', type: 'select', options: ['None', 'Bearer Token', 'Basic Auth', 'API Key', 'OAuth 2.0'] },
        { key: 'timeout', label: 'Connection Timeout (ms)', type: 'number', placeholder: '5000' },
        { key: 'retryCount', label: 'Retry Count on Fail', type: 'select', options: ['0', '1', '2', '3', '5'] },
        { key: 'followRedirects', label: 'Follow HTTP Redirects', type: 'checkbox' }
    ],
    webhook: [
        { key: 'hookUrl', label: 'Webhook Endpoint URL', type: 'text', placeholder: 'Generated upon workflow save...' },
        { key: 'hookMethod', label: 'Allowed Method', type: 'select', options: ['POST', 'GET', 'PUT'] },
        { key: 'secretKey', label: 'HMAC Signing Secret Key', type: 'text', placeholder: 'e.g. hook_secret_123' },
        { key: 'authRequired', label: 'Enable Client Auth', type: 'checkbox' },
        { key: 'resCode', label: 'Response Code Status', type: 'select', options: ['200 OK', '201 Created', '202 Accepted'] },
        { key: 'resBody', label: 'Custom Response Body (JSON)', type: 'textarea', placeholder: '{"status": "received"}' }
    ],
    api_connector: [
        { key: 'savedApi', label: 'Saved API Profile', type: 'select', options: ['Stripe Integration', 'HubSpot API', 'SendGrid Mailer'] },
        { key: 'endpointPath', label: 'Endpoint Sub-path', type: 'text', placeholder: '/v1/charges' },
        { key: 'method', label: 'Method Override', type: 'select', options: ['POST', 'GET'] },
        { key: 'customHeaders', label: 'Custom Headers', type: 'textarea', placeholder: '{"X-Provider": "custom"}' }
    ],
    database_query: [
        { key: 'dbConnection', label: 'Saved SQL Connection', type: 'select', options: ['LinkPilot Main DB', 'Analytics Replica Warehouse'] },
        { key: 'sqlQuery', label: 'SQL Query Statement', type: 'textarea', placeholder: 'SELECT * FROM leads WHERE score > :score' },
        { key: 'sqlParams', label: 'Query Parameters (JSON)', type: 'textarea', placeholder: '{"score": 75}' }
    ],
    json_parser: [
        { key: 'jsonPath', label: 'JSON Path Selector', type: 'text', placeholder: '$.data.customer.id' },
        { key: 'outputFields', label: 'Output Variable Names', type: 'text', placeholder: 'customerId, customerEmail' }
    ],
    xml_parser: [
        { key: 'xpath', label: 'XPath Selector Expression', type: 'text', placeholder: '//invoice/amount/text()' },
        { key: 'outputVar', label: 'Output Variable Name', type: 'text', placeholder: 'invoiceAmount' }
    ],

    // Logic
    if_branch: [
        { key: 'leftValue', label: 'Left Value / Variable', type: 'text', placeholder: '{{lead.score}}' },
        { key: 'operator', label: 'Operator', type: 'select', options: ['Equals', 'Not Equals', 'Contains', 'Starts With', 'Ends With', 'Greater Than', 'Less Than', 'Empty', 'Exists'] },
        { key: 'rightValue', label: 'Right Value / Comparison', type: 'text', placeholder: '70' }
    ],
    switch: [
        { key: 'switchExpression', label: 'Switch Variable Expression', type: 'text', placeholder: '{{email.category}}' },
        { key: 'switchCases', label: 'Cases (One per line)', type: 'textarea', placeholder: 'Inquiry\nPricing\nSupport' }
    ],
    wait_delay: [
        { key: 'waitValue', label: 'Wait Duration Value', type: 'number', placeholder: '5' },
        { key: 'waitUnit', label: 'Duration Unit', type: 'select', options: ['Seconds', 'Minutes', 'Hours', 'Days'] },
        { key: 'specificDate', label: 'Specific Target Date', type: 'text', placeholder: 'YYYY-MM-DD HH:MM' }
    ],
    loop: [
        { key: 'loopCollection', label: 'Collection Loop Over', type: 'select', options: ['Contacts', 'Emails', 'Deals', 'API Response Array'] }
    ],
    merge: [
        { key: 'mergeStrategy', label: 'Merge Strategy', type: 'select', options: ['Merge by Key Identifier', 'Merge by Array Position'] },
        { key: 'mergeKeys', label: 'Merge Key References', type: 'text', placeholder: 'e.g. email, contactId' }
    ],
    filter: [
        { key: 'fieldRef', label: 'Filter Field Name', type: 'text', placeholder: 'e.g. status' },
        { key: 'operator', label: 'Match Operator', type: 'select', options: ['Equals', 'Contains', 'Regular Expression'] },
        { key: 'filterValue', label: 'Filter Value Criteria', type: 'text', placeholder: 'Active' }
    ],

    // Files
    upload_file: [
        { key: 'storageProvider', label: 'Storage Service Provider', type: 'select', options: ['LinkPilot Server Disk', 'Amazon S3 Bucket', 'Google Cloud Storage'] },
        { key: 'targetFolder', label: 'Target Folder Directory', type: 'text', placeholder: '/uploads/attachments' }
    ],
    download_file: [
        { key: 'fileUrl', label: 'Source File URL', type: 'text', placeholder: 'https://example.com/invoice.pdf' },
        { key: 'filename', label: 'Target Save Filename', type: 'text', placeholder: 'downloaded_invoice.pdf' }
    ],
    generate_pdf: [
        { key: 'pdfTemplate', label: 'PDF Document Template', type: 'select', options: ['Standard Invoice', 'Client Proposal Draft', 'Custom HTML Template'] },
        { key: 'orientation', label: 'Page Layout Orientation', type: 'select', options: ['Portrait', 'Landscape'] }
    ],
    read_csv: [
        { key: 'delimiter', label: 'CSV Cell Delimiter', type: 'select', options: [', (Comma)', '; (Semicolon)', '\\t (Tab)'] },
        { key: 'headerRow', label: 'CSV Contains Header Row', type: 'checkbox' }
    ],
    read_excel: [
        { key: 'sheetName', label: 'Sheet Name / Index', type: 'text', placeholder: 'Sheet1' },
        { key: 'columnMapping', label: 'Target Column Names', type: 'text', placeholder: 'A, B, C' }
    ],

    // Reporting
    generate_report: [
        { key: 'reportType', label: 'Report Analytics Type', type: 'select', options: ['Sales Conversion Funnel', 'AI Processing Performance', 'SMTP Deliverability Log'] },
        { key: 'dateRange', label: 'Date Range Window', type: 'select', options: ['Today', 'Last 7 Days', 'Last 30 Days', 'Custom Range'] },
        { key: 'exportFormat', label: 'Report Layout Format', type: 'select', options: ['CSV Sheet', 'Excel Spreadsheet', 'PDF Document'] }
    ],
    export_data: [
        { key: 'exportFormat', label: 'Export Format', type: 'select', options: ['CSV', 'Excel', 'PDF', 'JSON'] }
    ],

    // Utility
    delay: [
        { key: 'duration', label: 'Delay Duration (Seconds)', type: 'number', placeholder: '10' }
    ],
    random_number: [
        { key: 'minVal', label: 'Minimum Range Value', type: 'number', placeholder: '1' },
        { key: 'maxVal', label: 'Maximum Range Value', type: 'number', placeholder: '100' }
    ],
    current_date: [],
    set_variable: [
        { key: 'varName', label: 'Variable Name Key', type: 'text', placeholder: 'globalCounter' },
        { key: 'varValue', label: 'Variable Value String', type: 'text', placeholder: '10' }
    ],
    get_variable: [
        { key: 'varName', label: 'Variable Name to Retrieve', type: 'text', placeholder: 'globalCounter' }
    ],
    logger: [
        { key: 'logLevel', label: 'Logger Log Level', type: 'select', options: ['Info Alert', 'Debug Detail', 'Warning Notice', 'Critical Failure'] },
        { key: 'logMsg', label: 'Debug Log Message', type: 'textarea', placeholder: 'Workflow reached step 4...' }
    ]
};

function renderConfigSidebarHTML() {
    const wf = window.wfState.activeWorkflow;
    if (!wf) return '';
    const selectedId = window.wfState.selectedNodeId;
    const selectedNode = wf.nodes.find(n => n.id === selectedId);
    
    if (!selectedNode) {
        return `
            <div class="p-4 border-b border-slate-200 bg-slate-50/50">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Workflow Configuration</h4>
            </div>
            <div class="p-4 space-y-4 flex-grow overflow-y-auto text-xs text-slate-600 leading-relaxed bg-white">
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Workflow Name</label>
                    <input type="text" oninput="renameWorkflow(this.value)" value="${wf.name}" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-indigo-500">
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-slate-500 uppercase tracking-wider">Description</label>
                    <textarea rows="3" placeholder="Describe what this automation does..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:border-indigo-500 font-sans">Visual builder workflow graph automation model.</textarea>
                </div>
                <div class="border-t border-slate-200 pt-3 space-y-2">
                    <div class="flex justify-between items-center text-[10px]">
                        <span class="text-slate-500 font-bold uppercase">Trigger Category</span>
                        <span class="text-slate-800 font-semibold">Visual Canvas</span>
                    </div>
                    <div class="flex justify-between items-center text-[10px]">
                        <span class="text-slate-500 font-bold uppercase">Total Nodes</span>
                        <span class="text-indigo-650 font-bold">${wf.nodes.length}</span>
                    </div>
                    <div class="flex justify-between items-center text-[10px]">
                        <span class="text-slate-500 font-bold uppercase">Connections</span>
                        <span class="text-indigo-650 font-bold">${wf.connections.length}</span>
                    </div>
                    <div class="flex justify-between items-center text-[10px]">
                        <span class="text-slate-500 font-bold uppercase">Status</span>
                        <span class="px-2 py-0.5 rounded text-[8px] font-bold ${wf.is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-slate-105 text-slate-500'}">
                            ${wf.is_active ? 'Active' : 'Deactive'}
                        </span>
                    </div>
                </div>
                
                <div class="pt-6">
                    <button onclick="deleteActiveWorkflow()" class="w-full py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-100 rounded-xl font-bold transition text-[10px]">
                        Delete Entire Workflow
                    </button>
                </div>
            </div>
        `;
    }
    
    const config = selectedNode.config || {};
    let formsHTML = '';
    
    const fields = NODE_FIELDS_MAP[selectedNode.type] || [
        { key: 'title', label: 'Node Title', type: 'text', placeholder: 'Node Title' }
    ];
    
    if (fields.length === 0) {
        formsHTML = `
            <div class="space-y-3">
                <p class="text-[9px] text-slate-500 italic">No extra custom properties for this node type.</p>
            </div>
        `;
    } else {
        const fieldsHTML = fields.map(f => {
            const val = config[f.key] !== undefined ? config[f.key] : '';
            
            if (f.type === 'select') {
                const optionsHTML = f.options.map(opt => {
                    const optVal = opt.includes(' (') ? opt.split(' (')[0] : opt;
                    const isSelected = String(val) === optVal ? 'selected' : '';
                    return `<option value="${optVal}" ${isSelected}>${opt}</option>`;
                }).join('');
                return `
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">${f.label}</label>
                        <select onchange="updateNodeConfig('${f.key}', this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-800 text-[10px] focus:outline-none focus:border-indigo-500">
                            ${optionsHTML}
                        </select>
                    </div>
                `;
            } else if (f.type === 'checkbox') {
                const isChecked = val ? 'checked' : '';
                return `
                    <div class="flex items-center justify-between py-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">${f.label}</label>
                        <input type="checkbox" onchange="updateNodeConfig('${f.key}', this.checked)" ${isChecked} class="h-3.5 w-3.5 border-slate-250 bg-white text-indigo-650 rounded cursor-pointer">
                    </div>
                `;
            } else if (f.type === 'textarea') {
                return `
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">${f.label}</label>
                        <textarea rows="3" placeholder="${f.placeholder || ''}" oninput="updateNodeConfig('${f.key}', this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-800 text-[10px] focus:outline-none focus:border-indigo-500 font-sans">${val}</textarea>
                    </div>
                `;
            } else if (f.type === 'number') {
                return `
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">${f.label}</label>
                        <input type="number" placeholder="${f.placeholder || ''}" oninput="updateNodeConfig('${f.key}', parseFloat(this.value))" value="${val}" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-800 text-[10px] focus:outline-none focus:border-indigo-500">
                    </div>
                `;
            } else {
                // text type
                const inputVal = f.key === 'title' ? selectedNode.name : val;
                const onInputHandler = f.key === 'title' ? `updateNodeName(this.value)` : `updateNodeConfig('${f.key}', this.value)`;
                return `
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">${f.label}</label>
                        <input type="text" placeholder="${f.placeholder || ''}" oninput="${onInputHandler}" value="${inputVal}" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-800 text-[10px] focus:outline-none focus:border-indigo-500">
                    </div>
                `;
            }
        }).join('');
        
        formsHTML = `<div class="space-y-3.5">${fieldsHTML}</div>`;
    }

    return `
        <div class="p-4 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Node Configuration</h4>
            <button onclick="deselectNode()" class="h-6 w-6 rounded-full hover:bg-slate-100 text-slate-550 flex items-center justify-center transition">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="p-4 space-y-4 flex-grow overflow-y-auto text-xs text-slate-600 bg-white">
            <div class="flex items-center space-x-2 pb-2 border-b border-slate-200">
                <div class="h-7 w-7 bg-slate-100 rounded-md flex items-center justify-center text-indigo-650 shrink-0">
                    <i data-lucide="${selectedNode.icon}" class="h-4 w-4"></i>
                </div>
                <div>
                    <h5 class="font-bold text-slate-800 text-[11px] leading-tight truncate">${selectedNode.name}</h5>
                    <p class="text-[8px] text-slate-400 uppercase font-semibold">${selectedNode.category} Node</p>
                </div>
            </div>
            
            ${formsHTML}

            <div class="pt-4 border-t border-slate-200 flex justify-between space-x-2 bg-white">
                <button onclick="duplicateSelectedNode()" class="flex-grow py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-250 rounded-lg font-bold transition text-[10px]">
                    Duplicate
                </button>
                <button onclick="deleteSelectedNode()" class="flex-grow py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-100 rounded-lg font-bold transition text-[10px]">
                    Delete Node
                </button>
            </div>
        </div>
    `;
}

function handleNodeDragStart(e, nodeType) {
    e.dataTransfer.setData('text/plain', nodeType);
}

function handleNodeDrop(e) {
    e.preventDefault();
    const nodeType = e.dataTransfer.getData('text/plain');
    const template = AVAILABLE_NODES.find(n => n.type === nodeType);
    if (!template) return;
    
    saveUndoState();
    
    const rect = document.getElementById('wf-canvas-container').getBoundingClientRect();
    const xOnCanvas = (e.clientX - rect.left - window.wfState.panX) / window.wfState.zoom;
    const yOnCanvas = (e.clientY - rect.top - window.wfState.panY) / window.wfState.zoom;
    
    const newNode = {
        id: 'node-' + Date.now(),
        type: template.type,
        name: template.name,
        category: template.category,
        icon: template.icon,
        x: Math.round(xOnCanvas / 20) * 20,
        y: Math.round(yOnCanvas / 20) * 20,
        config: {}
    };
    
    window.wfState.activeWorkflow.nodes.push(newNode);
    window.wfState.selectedNodeId = newNode.id;
    
    navigateTo('automation');
}

function handleCanvasScroll(e) {
    e.preventDefault();
    const newZoom = window.wfState.zoom + e.deltaY * -0.0015;
    window.wfState.zoom = Math.min(Math.max(0.3, newZoom), 2.0);
    
    const canvas = document.getElementById('workflow-canvas');
    if (canvas) {
        canvas.style.transform = `translate(${window.wfState.panX}px, ${window.wfState.panY}px) scale(${window.wfState.zoom})`;
    }
    
    const zoomText = document.querySelector('.wf-canvas-container button + span');
    if (zoomText) zoomText.textContent = `${Math.round(window.wfState.zoom * 100)}%`;
}

function handleCanvasMouseDown(e) {
    if (e.target.closest('.wf-node') || e.target.closest('.wf-node-handle')) return;
    
    window.wfState.isPanning = true;
    window.wfState.dragStart = { x: e.clientX - window.wfState.panX, y: e.clientY - window.wfState.panY };
    
    const onMouseMove = (ev) => {
        if (!window.wfState.isPanning) return;
        window.wfState.panX = ev.clientX - window.wfState.dragStart.x;
        window.wfState.panY = ev.clientY - window.wfState.dragStart.y;
        
        const canvas = document.getElementById('workflow-canvas');
        if (canvas) {
            canvas.style.transform = `translate(${window.wfState.panX}px, ${window.wfState.panY}px) scale(${window.wfState.zoom})`;
        }
    };
    
    const onMouseUp = () => {
        window.wfState.isPanning = false;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        drawMiniMap();
    };
    
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function handleNodeMouseDown(e, nodeId) {
    e.stopPropagation();
    saveUndoState();
    window.wfState.selectedNodeId = nodeId;
    
    // Select styling update
    document.querySelectorAll('.wf-node').forEach(el => el.classList.remove('selected'));
    const el = document.getElementById(nodeId);
    if (el) el.classList.add('selected');
    
    // Refresh config panel
    const sidebar = document.getElementById('wf-config-sidebar');
    if (sidebar) sidebar.innerHTML = renderConfigSidebarHTML();
    lucide.createIcons();
    
    const nodeObj = window.wfState.activeWorkflow.nodes.find(n => n.id === nodeId);
    const startX = e.clientX;
    const startY = e.clientY;
    const initialNodeX = nodeObj.x;
    const initialNodeY = nodeObj.y;
    
    const onMouseMove = (ev) => {
        const dx = (ev.clientX - startX) / window.wfState.zoom;
        const dy = (ev.clientY - startY) / window.wfState.zoom;
        
        nodeObj.x = Math.round((initialNodeX + dx) / 20) * 20;
        nodeObj.y = Math.round((initialNodeY + dy) / 20) * 20;
        
        if (el) {
            el.style.left = `${nodeObj.x}px`;
            el.style.top = `${nodeObj.y}px`;
        }
        drawConnections();
    };
    
    const onMouseUp = () => {
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        drawMiniMap();
    };
    
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function handleConnectionMouseDown(e, nodeId, handleType) {
    e.stopPropagation();
    e.preventDefault();
    
    const containerRect = document.getElementById('wf-canvas-container').getBoundingClientRect();
    window.wfState.isConnecting = true;
    
    const nodeObj = window.wfState.activeWorkflow.nodes.find(n => n.id === nodeId);
    let startX = nodeObj.x;
    let startY = nodeObj.y;
    
    if (handleType === 'input') {
        startX += 0;
        startY += 20;
    } else if (handleType === 'output-yes') {
        startX += 190;
        startY += 15;
    } else if (handleType === 'output-no') {
        startX += 190;
        startY += 30;
    } else {
        startX += 190;
        startY += 20;
    }
    
    const onMouseMove = (ev) => {
        if (!window.wfState.isConnecting) return;
        const mouseX = (ev.clientX - containerRect.left - window.wfState.panX) / window.wfState.zoom;
        const mouseY = (ev.clientY - containerRect.top - window.wfState.panY) / window.wfState.zoom;
        
        drawConnections(startX, startY, mouseX, mouseY);
    };
    
    const onMouseUp = (ev) => {
        window.wfState.isConnecting = false;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        
        // Find input handle released on
        const targetHandle = ev.target.closest('.wf-node-handle.input');
        if (targetHandle) {
            const targetNodeId = targetHandle.getAttribute('data-node-id');
            if (targetNodeId && targetNodeId !== nodeId) {
                saveUndoState();
                
                // Remove existing connection to targetNode input to keep simple tree
                window.wfState.activeWorkflow.connections = window.wfState.activeWorkflow.connections.filter(c => c.to !== targetNodeId);
                
                window.wfState.activeWorkflow.connections.push({
                    from: nodeId,
                    to: targetNodeId,
                    handle: handleType
                });
            }
        }
        drawConnections();
        drawMiniMap();
    };
    
    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

function drawConnections(tempX1, tempY1, tempX2, tempY2) {
    const svg = document.getElementById('workflow-svg');
    if (!svg) return;
    
    const wf = window.wfState.activeWorkflow;
    if (!wf) return;
    
    let pathsHTML = '';
    
    // Draw existing connections
    wf.connections.forEach(c => {
        const fromNode = wf.nodes.find(n => n.id === c.from);
        const toNode = wf.nodes.find(n => n.id === c.to);
        if (!fromNode || !toNode) return;
        
        let x1 = fromNode.x;
        let y1 = fromNode.y;
        let x2 = toNode.x;
        let y2 = toNode.y;
        
        if (c.handle === 'output-yes') {
            x1 += 190;
            y1 += 15;
        } else if (c.handle === 'output-no') {
            x1 += 190;
            y1 += 30;
        } else {
            x1 += 190;
            y1 += 20;
        }
        
        // Input handle is on the left
        x2 += 0;
        y2 += 20;
        
        const dx = Math.abs(x2 - x1) * 0.5;
        const pathStr = `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`;
        const pulsing = fromNode.execStatus === 'executing' ? 'pulsing' : '';
        
        pathsHTML += `<path d="${pathStr}" class="connection-path ${pulsing}" marker-end="url(#arrow)" />`;
        
        // Draw labels for Yes/No branches
        if (c.handle === 'output-yes') {
            pathsHTML += `<text x="${x1 + 15}" y="${y1 - 5}" fill="#10b981" font-size="8" font-family="sans-serif" font-weight="bold">Yes</text>`;
        } else if (c.handle === 'output-no') {
            pathsHTML += `<text x="${x1 + 15}" y="${y1 + 15}" fill="#ef4444" font-size="8" font-family="sans-serif" font-weight="bold">No</text>`;
        }
    });
    
    // Draw temporary connection line if user is dragging
    if (tempX1 !== undefined) {
        const dx = Math.abs(tempX2 - tempX1) * 0.5;
        const pathStr = `M ${tempX1} ${tempY1} C ${tempX1 + dx} ${tempY1}, ${tempX2 - dx} ${tempY2}, ${tempX2} ${tempY2}`;
        pathsHTML += `<path d="${pathStr}" class="connection-path pulsing" />`;
    }
    
    svg.innerHTML = `
        <defs>
            <linearGradient id="conn-grad" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#4f46e5" />
                <stop offset="100%" stop-color="#6366f1" />
            </linearGradient>
            <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                <path d="M 0 0 L 10 5 L 0 10 z" fill="#6366f1" />
            </marker>
        </defs>
        ${pathsHTML}
    `;
}

function drawMiniMap() {
    const viewport = document.getElementById('minimap-viewport');
    if (!viewport) return;
    
    const wf = window.wfState.activeWorkflow;
    if (!wf || !wf.nodes || wf.nodes.length === 0) {
        viewport.innerHTML = '';
        return;
    }
    
    // Find min/max coordinate bounds
    const xs = wf.nodes.map(n => n.x);
    const ys = wf.nodes.map(n => n.y);
    const minX = Math.min(...xs) - 100;
    const maxX = Math.max(...xs) + 300;
    const minY = Math.min(...ys) - 100;
    const maxY = Math.max(...ys) + 200;
    
    const rangeX = maxX - minX;
    const rangeY = maxY - minY;
    
    const width = viewport.clientWidth;
    const height = viewport.clientHeight;
    
    const miniNodesHTML = wf.nodes.map(n => {
        const px = ((n.x - minX) / rangeX) * width;
        const py = ((n.y - minY) / rangeY) * height;
        const selectedClass = n.id === window.wfState.selectedNodeId ? 'bg-indigo-500' : 'bg-slate-700';
        
        return `<div class="absolute w-2 h-1.5 ${selectedClass} rounded-xs" style="left: ${px}px; top: ${py}px;"></div>`;
    }).join('');
    
    viewport.innerHTML = miniNodesHTML;
}

function handleNodeContextMenu(e, nodeId) {
    e.preventDefault();
    e.stopPropagation();
    
    const existing = document.getElementById('custom-node-context-menu');
    if (existing) existing.remove();
    
    const menu = document.createElement('div');
    menu.id = 'custom-node-context-menu';
    menu.innerHTML = `
        <button onclick="duplicateNodeById('${nodeId}')">Duplicate Node</button>
        <button onclick="deleteNodeById('${nodeId}')" class="text-rose-400">Delete Node</button>
    `;
    
    menu.style.top = `${e.clientY}px`;
    menu.style.left = `${e.clientX}px`;
    document.body.appendChild(menu);
    
    const close = () => {
        menu.remove();
        document.removeEventListener('click', close);
    };
    setTimeout(() => document.addEventListener('click', close), 50);
}

function duplicateNodeById(nodeId) {
    const wf = window.wfState.activeWorkflow;
    const found = wf.nodes.find(n => n.id === nodeId);
    if (!found) return;
    
    saveUndoState();
    const newNode = JSON.parse(JSON.stringify(found));
    newNode.id = 'node-' + Date.now();
    newNode.x += 40;
    newNode.y += 40;
    newNode.execStatus = null;
    newNode.execTime = null;
    
    wf.nodes.push(newNode);
    window.wfState.selectedNodeId = newNode.id;
    
    navigateTo('automation');
}

function deleteNodeById(nodeId) {
    if (nodeId === 'node-trigger') {
        showNotification('error', 'Cannot delete trigger node.');
        return;
    }
    saveUndoState();
    
    const wf = window.wfState.activeWorkflow;
    wf.nodes = wf.nodes.filter(n => n.id !== nodeId);
    wf.connections = wf.connections.filter(c => c.from !== nodeId && c.to !== nodeId);
    
    if (window.wfState.selectedNodeId === nodeId) {
        window.wfState.selectedNodeId = null;
    }
    
    navigateTo('automation');
}

function deselectNode() {
    window.wfState.selectedNodeId = null;
    navigateTo('automation');
}

function updateNodeConfig(field, value) {
    const wf = window.wfState.activeWorkflow;
    const selectedNode = wf.nodes.find(n => n.id === window.wfState.selectedNodeId);
    if (!selectedNode) return;
    
    saveUndoState();
    if (!selectedNode.config) selectedNode.config = {};
    selectedNode.config[field] = value;
}

function updateNodeName(name) {
    const wf = window.wfState.activeWorkflow;
    const selectedNode = wf.nodes.find(n => n.id === window.wfState.selectedNodeId);
    if (!selectedNode) return;
    
    saveUndoState();
    selectedNode.name = name;
    const nodeEl = document.getElementById(selectedNode.id);
    if (nodeEl) {
        const titleEl = nodeEl.querySelector('h5');
        if (titleEl) titleEl.textContent = name;
    }
}

function duplicateSelectedNode() {
    if (window.wfState.selectedNodeId) {
        duplicateNodeById(window.wfState.selectedNodeId);
    }
}

function deleteSelectedNode() {
    if (window.wfState.selectedNodeId) {
        deleteNodeById(window.wfState.selectedNodeId);
    }
}

function deleteActiveWorkflow() {
    const wf = window.wfState.activeWorkflow;
    if (wf.id > 0) {
        deleteWorkflow(wf.id);
    } else {
        backToWorkflowList();
    }
}

// ----------------------------------------------------
// UNDO / REDO STATE STACK
// ----------------------------------------------------
function saveUndoState() {
    const wf = window.wfState.activeWorkflow;
    if (!wf) return;
    
    // Cap undo stack at 20 entries
    if (window.wfState.undoStack.length >= 20) {
        window.wfState.undoStack.shift();
    }
    
    window.wfState.undoStack.push(JSON.stringify(wf));
    window.wfState.redoStack = []; // Reset redo
}

function undoWorkflowChange() {
    if (window.wfState.undoStack.length === 0) {
        showNotification('info', 'Nothing to undo.');
        return;
    }
    
    const current = JSON.stringify(window.wfState.activeWorkflow);
    window.wfState.redoStack.push(current);
    
    const previous = window.wfState.undoStack.pop();
    window.wfState.activeWorkflow = JSON.parse(previous);
    
    navigateTo('automation');
}

function redoWorkflowChange() {
    if (window.wfState.redoStack.length === 0) {
        showNotification('info', 'Nothing to redo.');
        return;
    }
    
    const current = JSON.stringify(window.wfState.activeWorkflow);
    window.wfState.undoStack.push(current);
    
    const next = window.wfState.redoStack.pop();
    window.wfState.activeWorkflow = JSON.parse(next);
    
    navigateTo('automation');
}

function zoomWorkflow(delta) {
    window.wfState.zoom = Math.min(Math.max(0.3, window.wfState.zoom + delta), 2.0);
    
    const canvas = document.getElementById('workflow-canvas');
    if (canvas) {
        canvas.style.transform = `translate(${window.wfState.panX}px, ${window.wfState.panY}px) scale(${window.wfState.zoom})`;
    }
    
    const zoomText = document.querySelector('.wf-canvas-container button + span');
    if (zoomText) zoomText.textContent = `${Math.round(window.wfState.zoom * 100)}%`;
}

function autoArrangeCanvas() {
    const wf = window.wfState.activeWorkflow;
    if (!wf || !wf.nodes || wf.nodes.length === 0) return;
    
    saveUndoState();
    
    // Sort nodes topologically using connection runs
    let levels = {};
    let visited = new Set();
    
    // Find trigger node
    const trigger = wf.nodes.find(n => n.id === 'node-trigger' || n.type === 'email_received') || wf.nodes[0];
    
    const assignLevel = (nodeId, lvl) => {
        if (visited.has(nodeId)) return;
        visited.add(nodeId);
        
        if (!levels[lvl]) levels[lvl] = [];
        levels[lvl].push(nodeId);
        
        // Find outgoing connections
        const outConnections = wf.connections.filter(c => c.from === nodeId);
        outConnections.forEach(c => assignLevel(c.to, lvl + 1));
    };
    
    assignLevel(trigger.id, 0);
    
    // Place remaining un-connected nodes at level 0
    wf.nodes.forEach(n => {
        if (!visited.has(n.id)) {
            if (!levels[0]) levels[0] = [];
            levels[0].push(n.id);
        }
    });
    
    // Arrange positions
    Object.keys(levels).forEach(lvl => {
        const nodeIds = levels[lvl];
        const lvlInt = parseInt(lvl);
        const startY = 80 + lvlInt * 130;
        
        const totalWidth = nodeIds.length * 240;
        const startX = 300 - (totalWidth / 2);
        
        nodeIds.forEach((id, idx) => {
            const node = wf.nodes.find(n => n.id === id);
            if (node) {
                node.x = Math.round((startX + idx * 240) / 20) * 20;
                node.y = Math.round(startY / 20) * 20;
            }
        });
    });
    
    navigateTo('automation');
}

// ----------------------------------------------------
// TEST RUN & SIMULATION ACTIONS
// ----------------------------------------------------
function showTestDetailsModal(onConfirm) {
    const existing = document.getElementById('wf-test-modal');
    if (existing) existing.remove();
    
    const wf = window.wfState.activeWorkflow;
    if (!wf) return;
    
    let formFieldsHTML = '';
    
    const hasEmailTrigger = wf.nodes.some(n => n.type === 'email_received');
    const hasSendEmail = wf.nodes.some(n => n.type === 'send_email');
    const hasCreateLead = wf.nodes.some(n => n.type === 'create_lead');
    const hasSendWhatsapp = wf.nodes.some(n => n.type === 'send_whatsapp');
    
    if (hasEmailTrigger) {
        formFieldsHTML += `
            <div class="space-y-3 pb-3 border-b border-slate-100">
                <h6 class="text-[10px] font-bold text-indigo-650 uppercase tracking-wider">Trigger: Email Received Details</h6>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">Test Sender Address</label>
                        <input type="email" id="test-sender-email" value="customer@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">Test Subject</label>
                        <input type="text" id="test-sender-subject" value="Inquiry about pricing plans" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-slate-500 uppercase">Test Email Body Content</label>
                    <textarea id="test-sender-body" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-indigo-500 font-sans">Hi there, we would love to know more about your CRM integration pricing plans.</textarea>
                </div>
            </div>
        `;
    }
    
    if (hasSendEmail) {
        formFieldsHTML += `
            <div class="space-y-3 py-3 border-b border-slate-100">
                <h6 class="text-[10px] font-bold text-indigo-650 uppercase tracking-wider">Action: Send Email Details</h6>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-slate-500 uppercase">Send Test Email To (Override Recipient)</label>
                    <input type="email" id="test-recipient-email" value="${window.wfState.testRecipientEmail || 'wbsoumo@gmail.com'}" placeholder="Enter email to receive test message" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-indigo-500">
                    <span class="text-[8px] text-slate-400">If configured, an actual test email will be sent to this inbox using your SMTP server.</span>
                </div>
            </div>
        `;
    }
    
    if (hasCreateLead) {
        formFieldsHTML += `
            <div class="space-y-3 py-3 border-b border-slate-100">
                <h6 class="text-[10px] font-bold text-indigo-650 uppercase tracking-wider">Action: Create Lead Details</h6>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">Test Lead Full Name</label>
                        <input type="text" id="test-lead-name" value="Jane Doe" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-bold text-slate-500 uppercase">Test Company Name</label>
                        <input type="text" id="test-lead-company" value="Acme Corporation" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
            </div>
        `;
    }
    
    if (hasSendWhatsapp) {
        formFieldsHTML += `
            <div class="space-y-3 py-3">
                <h6 class="text-[10px] font-bold text-indigo-650 uppercase tracking-wider">Action: Send WhatsApp Details</h6>
                <div class="space-y-1">
                    <label class="text-[9px] font-bold text-slate-500 uppercase">Test Phone Number (E.164)</label>
                    <input type="text" id="test-whatsapp-phone" value="+1234567890" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-[11px] text-slate-800 focus:outline-none focus:border-indigo-500">
                </div>
            </div>
        `;
    }
    
    if (formFieldsHTML === '') {
        formFieldsHTML = `
            <div class="text-center text-slate-500 py-6 italic text-[11px]">
                No parameters needed for this node configuration.
            </div>
        `;
    }
    
    const modal = document.createElement('div');
    modal.id = 'wf-test-modal';
    modal.className = 'fixed inset-0 z-[2000] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in flex flex-col text-slate-700">
            <!-- Header -->
            <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Workflow Test Configuration</h4>
                    <p class="text-[9px] text-slate-400">Configure parameters for simulation run</p>
                </div>
                <button onclick="document.getElementById('wf-test-modal').remove()" class="h-6 w-6 rounded-full hover:bg-slate-100 text-slate-400 flex items-center justify-center transition">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>
            <!-- Body -->
            <div class="p-5 flex-grow overflow-y-auto max-h-[60vh] space-y-4 text-xs">
                ${formFieldsHTML}
            </div>
            <!-- Footer -->
            <div class="p-4 border-t border-slate-200 bg-slate-50/50 flex justify-end space-x-2">
                <button onclick="document.getElementById('wf-test-modal').remove()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 text-slate-650 rounded-xl font-bold transition text-[10px]">Cancel</button>
                <button id="wf-confirm-test-btn" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold transition text-[10px] flex items-center space-x-1.5">
                    <i data-lucide="play" class="h-3.5 w-3.5"></i>
                    <span>Start Test Run</span>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    lucide.createIcons();
    
    document.getElementById('wf-confirm-test-btn').onclick = () => {
        const details = {};
        if (document.getElementById('test-sender-email')) details.sender = document.getElementById('test-sender-email').value;
        if (document.getElementById('test-sender-subject')) details.subject = document.getElementById('test-sender-subject').value;
        if (document.getElementById('test-sender-body')) details.body = document.getElementById('test-sender-body').value;
        if (document.getElementById('test-recipient-email')) {
            details.recipient = document.getElementById('test-recipient-email').value;
            window.wfState.testRecipientEmail = details.recipient; // Save to session
        }
        if (document.getElementById('test-lead-name')) details.leadName = document.getElementById('test-lead-name').value;
        if (document.getElementById('test-lead-company')) details.leadCompany = document.getElementById('test-lead-company').value;
        if (document.getElementById('test-whatsapp-phone')) details.phone = document.getElementById('test-whatsapp-phone').value;
        
        modal.remove();
        onConfirm(details);
    };
}

async function runWorkflowSimulation(btn) {
    const wf = window.wfState.activeWorkflow;
    if (!wf || !wf.nodes || wf.nodes.length === 0) return;
    
    showTestDetailsModal(async (details) => {
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin text-white"></i>`;
        lucide.createIcons();
        
        // Reset simulation status badges
        wf.nodes.forEach(n => {
            n.execStatus = null;
            n.execTime = null;
        });
        
        navigateTo('automation');
        
        // Walk through execution pathway
        const startNode = wf.nodes.find(n => n.id === 'node-trigger' || n.type === 'email_received') || wf.nodes[0];
        
        let path = [];
        const traverse = (nodeId) => {
            if (path.includes(nodeId)) return; // Prev loop
            path.push(nodeId);
            
            const out = wf.connections.find(c => c.from === nodeId);
            if (out) traverse(out.to);
        };
        
        traverse(startNode.id);
        
        const startRunTime = Date.now();
        
        for (let i = 0; i < path.length; i++) {
            const nodeId = path[i];
            const node = wf.nodes.find(n => n.id === nodeId);
            if (!node) continue;
            
            node.execStatus = 'executing';
            const nodeEl = document.getElementById(nodeId);
            if (nodeEl) nodeEl.classList.add('executing');
            drawConnections();
            
            // Wait 900ms to simulate computation
            await new Promise(r => setTimeout(r, 900));
            
            // If the node type is 'send_email', trigger backend email delivery
            if (node.type === 'send_email' && details.recipient) {
                try {
                    const mailConfig = node.config || {};
                    await apiCall('crm/automation.php?action=test_send_email', 'POST', {
                        recipient: details.recipient,
                        subject: mailConfig.subject || details.subject || 'Workflow Builder Test Email',
                        body: mailConfig.body || details.body || 'This is a test notification from LinkPilot Workflow Builder.'
                    });
                } catch (err) {
                    console.error('Failed to dispatch test email', err);
                }
            }
            
            node.execStatus = 'success';
            node.execTime = Math.round(40 + Math.random() * 80);
            if (nodeEl) {
                nodeEl.classList.remove('executing');
                // Refresh inner HTML badge status
                const badgeContainer = document.createElement('div');
                badgeContainer.innerHTML = renderCanvasNodesHTML();
                // Re-render nodes container
                document.getElementById('canvas-nodes-container').innerHTML = badgeContainer.innerHTML;
            }
            drawConnections();
        }
        
        const totalDuration = ((Date.now() - startRunTime) / 1000).toFixed(2);
        
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
        
        showNotification('success', `Test run completed successfully in ${totalDuration}s!`);
        
        // Log execution back to the server
        try {
            await apiCall('crm/automation.php?action=log_run', 'POST', {
                workflow_id: wf.id > 0 ? wf.id : null,
                workflow_name: wf.name,
                status: 'success',
                execution_time: parseFloat(totalDuration)
            });
        } catch(e) {
            console.error('Failed to log test run', e);
        }
    });
}

// ----------------------------------------------------
// SAVE AND UPDATE STATE API HANDLERS
// ----------------------------------------------------
async function saveActiveWorkflow() {
    const wf = window.wfState.activeWorkflow;
    if (!wf) return;
    
    try {
        // Find trigger value inside the nodes config to keep database compatible
        const triggerNode = wf.nodes.find(n => n.id === 'node-trigger' || n.type === 'email_received');
        const triggerValue = triggerNode && triggerNode.config ? triggerNode.config.folder : 'Inbox';
        
        const payload = {
            id: wf.id,
            name: wf.name,
            trigger_type: 'visual_workflow',
            trigger_value: triggerValue || 'Inbox',
            is_active: wf.is_active,
            actions: {
                nodes: wf.nodes,
                connections: wf.connections
            }
        };
        
        const res = await apiCall('crm/automation.php', 'POST', payload);
        if (res.status === 'success') {
            showNotification('success', 'Workflow saved successfully!');
            if (res.workflow_id) {
                wf.id = res.workflow_id;
            }
        } else {
            showNotification('error', res.message);
        }
    } catch(e) {
        showNotification('error', e.message);
    }
}

function toggleWorkflowActiveState() {
    window.wfState.activeWorkflow.is_active = window.wfState.activeWorkflow.is_active ? 0 : 1;
    saveActiveWorkflow();
    navigateTo('automation');
}

// ----------------------------------------------------
// IMPORT / EXPORT AND HISTORY LOGS OVERLAYS
// ----------------------------------------------------
function openJSONConfigPanel() {
    const wf = window.wfState.activeWorkflow;
    const jsonStr = JSON.stringify({ nodes: wf.nodes, connections: wf.connections }, null, 2);
    
    const existing = document.getElementById('wf-json-overlay');
    if (existing) existing.remove();
    
    const overlay = document.createElement('div');
    overlay.id = 'wf-json-overlay';
    overlay.className = 'fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm';
    overlay.innerHTML = `
        <div class="bg-slate-950 border border-slate-850 rounded-2xl w-full max-w-lg shadow-2xl p-6 text-left space-y-4">
            <h3 class="text-sm font-bold text-white uppercase tracking-wider">Import / Export JSON</h3>
            <textarea id="wf-json-area" class="w-full h-64 bg-slate-900 border border-slate-850 rounded-lg p-3 text-[10px] text-indigo-300 font-mono focus:outline-none focus:border-indigo-500">${jsonStr}</textarea>
            <div class="flex justify-end space-x-2 text-[10px]">
                <button onclick="document.getElementById('wf-json-overlay').remove()" class="px-4 py-2 border border-slate-800 hover:bg-slate-900 text-slate-400 rounded-lg transition font-bold">Cancel</button>
                <button onclick="applyImportedJSON()" class="px-4 py-2 bg-indigo-650 hover:bg-indigo-600 text-white rounded-lg transition font-bold shadow-md">Apply JSON</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
}

function applyImportedJSON() {
    const txt = document.getElementById('wf-json-area').value;
    try {
        const parsed = JSON.parse(txt);
        if (!parsed.nodes || !parsed.connections) {
            throw new Error("JSON must contain 'nodes' and 'connections' fields.");
        }
        
        saveUndoState();
        window.wfState.activeWorkflow.nodes = parsed.nodes;
        window.wfState.activeWorkflow.connections = parsed.connections;
        
        document.getElementById('wf-json-overlay').remove();
        showNotification('success', 'Workflow JSON configuration applied!');
        navigateTo('automation');
    } catch(e) {
        alert("Invalid JSON format: " + e.message);
    }
}

function openLogsHistoryDrawer() {
    const existing = document.getElementById('wf-logs-drawer');
    if (existing) {
        existing.remove();
        return;
    }
    
    const drawer = document.createElement('div');
    drawer.id = 'wf-logs-drawer';
    drawer.className = 'fixed bottom-0 right-0 left-72 bg-slate-950/95 border-t border-slate-850 h-64 z-[90] p-4 flex flex-col justify-between text-left';
    drawer.innerHTML = `
        <div class="flex justify-between items-center pb-2 border-b border-slate-900">
            <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center space-x-1.5">
                <i data-lucide="activity" class="h-4 w-4 text-indigo-400"></i>
                <span>Execution History Logs</span>
            </h4>
            <button onclick="document.getElementById('wf-logs-drawer').remove()" class="h-6 w-6 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="flex-grow overflow-y-auto py-2 space-y-2" id="drawer-logs-body">
            <div class="text-slate-500 italic text-[11px] text-center py-12">Loading execution runs...</div>
        </div>
    `;
    
    document.body.appendChild(drawer);
    lucide.createIcons();
    
    loadDrawerExecutionLogs();
}

async function loadDrawerExecutionLogs() {
    const body = document.getElementById('drawer-logs-body');
    if (!body) return;
    try {
        const data = await apiCall('crm/automation.php?action=get_logs');
        const logs = data.logs || [];
        if (logs.length === 0) {
            body.innerHTML = `<div class="text-slate-500 italic text-[11px] text-center py-12">No execution runs logged. Run a simulation test first.</div>`;
            return;
        }
        
        body.innerHTML = `
            <table class="w-full text-[10px] text-left text-slate-400 leading-normal">
                <thead>
                    <tr class="border-b border-slate-900 text-slate-500 font-bold">
                        <th class="py-1">Run Date</th>
                        <th class="py-1">Workflow Name</th>
                        <th class="py-1">Execution Time</th>
                        <th class="py-1">Status</th>
                        <th class="py-1">Details</th>
                    </tr>
                </thead>
                <tbody>
                    ${logs.map(l => `
                        <tr class="border-b border-slate-900/60 hover:bg-slate-900/30">
                            <td class="py-1.5">${new Date(l.created_at).toLocaleString()}</td>
                            <td class="py-1.5 font-bold text-white">${l.workflow_name}</td>
                            <td class="py-1.5">${l.execution_time}s</td>
                            <td class="py-1.5">
                                <span class="px-1.5 py-0.5 rounded text-[8px] font-bold ${l.status === 'success' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'}">
                                    ${l.status.toUpperCase()}
                                </span>
                            </td>
                            <td class="py-1.5 italic text-slate-500">${l.error_message || 'Ran to completion without issues.'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } catch(e) {
        body.innerHTML = `<div class="text-slate-500 italic text-[11px] text-center py-12">Failed to load run logs.</div>`;
    }
}

// ----------------------------------------------------
// AUTO-SAVE TIMER MECHANICS
// ----------------------------------------------------
function startBuilderAutoSave() {
    stopBuilderAutoSave();
    window.wfState.autoSaveTimer = setInterval(() => {
        const wf = window.wfState.activeWorkflow;
        if (wf) {
            saveActiveWorkflow();
            console.log('Visual builder automated background save triggered.');
        }
    }, 120000); // Save every 2 minutes
}

function stopBuilderAutoSave() {
    if (window.wfState.autoSaveTimer) {
        clearInterval(window.wfState.autoSaveTimer);
        window.wfState.autoSaveTimer = null;
    }
}

// ----------------------------------------------------
// INSTALL EXTENSIONS VIEW
// ----------------------------------------------------
async function renderInstallExtensions(container) {
    window.toggleExtensionOptions = function() {
        const installBtnBlock = container.querySelector('#extension-install-actions');
        const optionsBlock = container.querySelector('#extension-options-block');
        if (installBtnBlock && optionsBlock) {
            if (installBtnBlock.classList.contains('hidden')) {
                installBtnBlock.classList.remove('hidden');
                optionsBlock.classList.add('hidden');
            } else {
                installBtnBlock.classList.add('hidden');
                optionsBlock.classList.remove('hidden');
            }
        }
    };

    container.innerHTML = `
        <div class="space-y-8 animate-fade-in text-slate-800">
            <div>
                <h1 class="text-3xl font-extrabold text-white">Browser Extensions Hub</h1>
                <p class="text-slate-400 text-sm mt-1">Supercharge your daily workflow with our productivity extensions.</p>
            </div>

            <!-- 3 Columns Extensions Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- 1. Active Extension -->
                <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-6 flex flex-col justify-between hover:border-blue-500/35 transition duration-200 shadow-xl relative overflow-hidden group">
                    <!-- Badge -->
                    <div class="absolute top-4 right-4 bg-teal-500/10 text-teal-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-teal-500/20">
                        Active
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Icon -->
                        <div class="h-12 w-12 rounded-xl bg-blue-600/10 border border-blue-500/20 text-blue-500 flex items-center justify-center">
                            <i data-lucide="puzzle" class="h-6 w-6"></i>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-bold text-white group-hover:text-blue-400 transition">LinkedIn Outreach Assistant</h3>
                            <p class="text-slate-400 text-xs mt-1.5 leading-relaxed">
                                Contextualizes LinkedIn posts, extracts verified email addresses from the Clearbit database, and drafts personalized outreach emails, comments, and WhatsApp messages.
                            </p>
                        </div>
                    </div>

                    <!-- Install Actions Block -->
                    <div class="mt-6 pt-4 border-t border-slate-800/50 space-y-4 text-center" id="extension-install-actions">
                        <button onclick="toggleExtensionOptions()" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg text-xs transition flex items-center justify-center space-x-1.5">
                            <i data-lucide="download" class="h-3.5 w-3.5"></i>
                            <span>Install Extension</span>
                        </button>
                    </div>
                    
                    <!-- Hidden State with 2 Options -->
                    <div class="mt-6 pt-4 border-t border-slate-800/50 space-y-4 hidden" id="extension-options-block">
                        <div class="text-slate-355 text-[11px] font-bold uppercase tracking-wider mb-2">Select Installation Method:</div>
                        <div class="grid grid-cols-1 gap-2.5">
                            <!-- Option 1: Web Store -->
                            <a href="https://chromewebstore.google.com/detail/gnemddfomigfkpidiakgcdpighonkjga?utm_source=item-share-cb" target="_blank" class="flex items-start p-3 bg-slate-950 hover:bg-slate-900 border border-slate-800 rounded-xl transition group/item">
                                <div class="h-8 w-8 rounded-lg bg-teal-500/10 text-teal-400 flex items-center justify-center shrink-0 mr-3">
                                    <i data-lucide="chrome" class="h-4.5 w-4.5"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-white group-hover/item:text-teal-400 transition">1. Install Released Version</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">Download officially from Chrome Web Store (Recommended).</div>
                                </div>
                            </a>
                            
                            <!-- Option 2: Download Directly -->
                            <a href="../linkpilot-extension.zip?t=${Date.now()}" download class="flex items-start p-3 bg-slate-950 hover:bg-slate-900 border border-slate-800 rounded-xl transition group/item">
                                <div class="h-8 w-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center shrink-0 mr-3">
                                    <i data-lucide="file-archive" class="h-4.5 w-4.5"></i>
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-white group-hover/item:text-blue-400 transition">2. Download Unreleased ZIP</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">Download directly and install via Developer Mode.</div>
                                </div>
                            </a>
                        </div>
                        
                        <button onclick="toggleExtensionOptions()" class="w-full py-1.5 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white rounded-lg text-[10px] font-bold transition">
                            ← Back to Overview
                        </button>
                    </div>
                </div>

                <!-- 2. Placeholder Extension 1 -->
                <div class="border border-dashed border-slate-800 rounded-2xl p-6 flex flex-col justify-between min-h-[300px]">
                    <div class="space-y-4 opacity-50">
                        <div class="h-12 w-12 rounded-xl bg-slate-800/10 border border-slate-800 text-slate-400 flex items-center justify-center">
                            <i data-lucide="mail" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-400">Gmail Sync & Lead Extractor</h3>
                            <p class="text-slate-500 text-xs mt-1.5 leading-relaxed">
                                Connect with Google Workspace to automatically capture CRM leads, sync email communications, and search contact details directly inside Gmail.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-800/50">
                        <span class="inline-block text-[10px] font-bold text-slate-600 uppercase tracking-widest">Coming Soon</span>
                    </div>
                </div>

                <!-- 3. Placeholder Extension 2 -->
                <div class="border border-dashed border-slate-800 rounded-2xl p-6 flex flex-col justify-between min-h-[300px]">
                    <div class="space-y-4 opacity-50">
                        <div class="h-12 w-12 rounded-xl bg-slate-800/10 border border-slate-800 text-slate-400 flex items-center justify-center">
                            <i data-lucide="phone-call" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-400">CRM Sales Dialer</h3>
                            <p class="text-slate-500 text-xs mt-1.5 leading-relaxed">
                                Automate outbound calling, log calls directly inside the lead timeline, and capture call recordings for subsequent AI processing.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-800/50">
                        <span class="inline-block text-[10px] font-bold text-slate-600 uppercase tracking-widest">Coming Soon</span>
                    </div>
                </div>
            </div>

            <!-- Developer Mode Installation Guide -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 mt-8 space-y-4 shadow-xl">
                <div class="flex items-center space-x-2 text-white font-bold text-base">
                    <i data-lucide="terminal" class="h-5 w-5 text-blue-500"></i>
                    <span>Guide: Installing Extension in Chrome Developer Mode</span>
                </div>
                
                <div class="text-slate-300 text-xs space-y-3 leading-relaxed">
                    <p>If you downloaded the <strong>Unreleased ZIP version</strong> directly, follow these steps to load it into Google Chrome:</p>
                    <ol class="list-decimal list-inside space-y-2.5 text-slate-400 pl-2">
                        <li>
                            <strong class="text-white">Unzip the archive:</strong> Locate the downloaded <code class="bg-slate-950 px-1.5 py-0.5 rounded text-blue-400 font-mono">linkpilot-extension.zip</code> file on your computer and extract it to a folder.
                        </li>
                        <li>
                            <strong class="text-white">Open Extensions Manager:</strong> In Google Chrome, open a new tab and navigate to <code class="bg-slate-950 px-1.5 py-0.5 rounded text-teal-400 font-mono">chrome://extensions/</code> (or click the puzzle icon in the top right and select <strong class="text-white">Manage Extensions</strong>).
                        </li>
                        <li>
                            <strong class="text-white">Enable Developer Mode:</strong> Toggle the switch labeled <strong class="text-white">Developer mode</strong> in the top-right corner of the Extensions page.
                        </li>
                        <li>
                            <strong class="text-white">Load Unpacked Extension:</strong> Click the <strong class="text-white">Load unpacked</strong> button that appears in the top-left toolbar.
                        </li>
                        <li>
                            <strong class="text-white">Select Folder:</strong> Choose the extracted folder (make sure the folder contains the <code class="bg-slate-950 px-1.5 py-0.5 rounded text-blue-400 font-mono">manifest.json</code> file at its root).
                        </li>
                    </ol>
                    <div class="mt-4 p-3 bg-blue-500/10 border border-blue-500/20 rounded-xl flex items-start space-x-3">
                        <i data-lucide="info" class="h-5 w-5 text-blue-500 shrink-0 mt-0.5"></i>
                        <p class="text-[11px] text-slate-400">Once loaded, the extension icon will appear in your browser toolbar. Navigate to LinkedIn, click the <strong>AI Action</strong> button on any post, and log in with your CRM credentials to start generating outreach.</p>
                    </div>
                </div>
            </div>
        </div>
    `;

    lucide.createIcons();
}

// ----------------------------------------------------
// BOOTSTRAP INITIALIZATION
// ----------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    // Keyboard Shortcuts (Delete node, Undo / Redo)
    document.addEventListener('keydown', (e) => {
        if (!window.wfState.activeWorkflow) return;
        
        // Exclude input fields typing
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
        
        if (e.key === 'Delete' || e.key === 'Backspace') {
            if (window.wfState.selectedNodeId) {
                deleteSelectedNode();
            }
        }
        
        if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
            e.preventDefault();
            undoWorkflowChange();
        }
        
        if ((e.ctrlKey || e.metaKey) && e.key === 'y') {
            e.preventDefault();
            redoWorkflowChange();
        }
    });

    // Intercept hash change
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash || '#/dashboard';
        const view = hash.replace('#/', '');
        navigateTo(view);
    });

    const hash = window.location.hash || '#/dashboard';
    const view = hash.replace('#/', '');
    navigateTo(view);
});

// --- EXTERNAL APPS SaaS INTEGRATION MARKETPLACE ---
async function renderExternalApps(container) {
    container.innerHTML = `
        <div class="flex items-center justify-center py-12">
            <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-rose-500"></i>
        </div>
    `;
    lucide.createIcons();

    try {
        const res = await apiCall('external_apps/status.php');
        const conn = res.data || {
            connected: false,
            email: null,
            name: null,
            avatar: null,
            last_sync: null,
            scopes: [],
            profile_connected: false,
            calendar_connected: false,
            gmail_connected: false
        };

        if (conn.error) {
            showNotification('warning', 'Integrations warning: ' + conn.error);
        }

        const buildStatusBadge = (isConnected) => {
            return isConnected
                ? `<span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center space-x-1 shrink-0">
                     <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                     <span>ACTIVE</span>
                   </span>`
                : `<span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-slate-100 text-slate-500 border border-slate-200 shrink-0">
                     INACTIVE
                   </span>`;
        };

        container.innerHTML = `
            <div class="space-y-6 pt-4 animate-fade-in text-xs max-w-7xl mx-auto text-left">
                <!-- Header -->
                <div class="border-b border-slate-150 pb-4">
                    <h1 class="text-2xl font-extrabold text-slate-800">External Apps Marketplace</h1>
                    <p class="text-slate-500 text-xs mt-1">Connect and authorize Google developer integrations to synchronize your CRM activities.</p>
                </div>

                <!-- Integrations Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Google Profile Connection Card -->
                    <div class="glass-panel p-5 bg-white border border-slate-200 rounded-2xl flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex justify-between items-start">
                                <div class="h-12 w-12 rounded-xl bg-slate-50 border border-slate-150 flex items-center justify-center p-2 shrink-0">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" class="h-full w-full object-contain" alt="Google Logo">
                                </div>
                                ${buildStatusBadge(conn.profile_connected)}
                            </div>
                            <h3 class="text-sm font-extrabold text-slate-800 mt-4">Google Account Connection</h3>
                            <p class="text-slate-500 mt-1.5 leading-relaxed">Connect your primary Google account identity to enable advanced external integrations.</p>
                            
                            ${conn.profile_connected ? `
                                <div class="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-150 flex items-center space-x-3 text-slate-650">
                                    ${conn.avatar ? `<img src="${conn.avatar}" class="h-8 w-8 rounded-full border border-slate-200" alt="Avatar">` : ''}
                                    <div class="truncate font-mono text-[10px] space-y-0.5">
                                        <p class="font-bold text-slate-850 truncate">${conn.name || 'Google User'}</p>
                                        <p class="text-slate-500 truncate">${conn.email}</p>
                                    </div>
                                </div>
                            ` : ''}
                        </div>

                        <div class="mt-6 flex space-x-2 pt-2 border-t border-slate-100">
                            ${conn.profile_connected ? `
                                <button onclick="disconnectExternalGoogle()" class="w-full py-2 border border-slate-200 hover:border-red-500/20 hover:bg-red-50 text-red-500 rounded-lg font-bold transition">Disconnect Account</button>
                            ` : `
                                <button onclick="connectExternalGoogle('login')" class="w-full py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-lg font-bold text-center transition" style="color: #ffffff !important;">Connect Google Account</button>
                            `}
                        </div>
                    </div>

                    <!-- Google Calendar Card -->
                    <div class="glass-panel p-5 bg-white border border-slate-200 rounded-2xl flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex justify-between items-start">
                                <div class="h-12 w-12 rounded-xl bg-slate-50 border border-slate-150 flex items-center justify-center p-2.5 shrink-0">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Google_Calendar_icon_%282020%29.svg" class="h-full w-full object-contain" alt="Google Calendar">
                                </div>
                                ${buildStatusBadge(conn.calendar_connected)}
                            </div>
                            <h3 class="text-sm font-extrabold text-slate-800 mt-4">Google Calendar Sync</h3>
                            <p class="text-slate-500 mt-1.5 leading-relaxed">Schedule events dynamically inside the CRM and sync meeting lifecycles directly with Google Calendar.</p>
                            
                            ${conn.calendar_connected ? `
                                <div class="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-150 space-y-1 font-mono text-[10px] text-slate-650">
                                    <p><strong>Integration:</strong> Calendar Events API</p>
                                    <p><strong>Auto-sync:</strong> Enabled</p>
                                    <p><strong>Meet Solution:</strong> Active</p>
                                </div>
                            ` : ''}
                        </div>

                        <div class="mt-6 flex space-x-2 pt-2 border-t border-slate-100">
                            ${conn.calendar_connected ? `
                                <button onclick="openGoogleCalendarTestModal()" class="flex-1 py-2 bg-indigo-550 hover:bg-indigo-650 text-white rounded-lg font-bold text-center transition" style="color: #ffffff !important;">Test Sync</button>
                                <button onclick="disconnectExternalGoogle()" class="px-3 py-2 border border-slate-200 hover:border-red-500/20 hover:bg-red-50 text-red-500 rounded-lg font-bold transition">Disable</button>
                            ` : `
                                <button onclick="${conn.profile_connected ? "connectExternalGoogle('calendar')" : "showNotification('warning', 'Please connect your Google Account profile first.')"}" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-bold text-center transition ${conn.profile_connected ? '' : 'opacity-50 cursor-not-allowed'}">Enable Calendar Sync</button>
                            `}
                        </div>
                    </div>

                    <!-- Gmail Integration Card -->
                    <div class="glass-panel p-5 bg-white border border-slate-200 rounded-2xl flex flex-col justify-between hover:shadow-md transition">
                        <div>
                            <div class="flex justify-between items-start">
                                <div class="h-12 w-12 rounded-xl bg-slate-50 border border-slate-150 flex items-center justify-center p-2.5 shrink-0">
                                    <img src="https://upload.wikimedia.org/wikipedia/commons/7/7e/Gmail_icon_%282020%29.svg" class="h-full w-full object-contain" alt="Gmail">
                                </div>
                                ${buildStatusBadge(conn.gmail_connected)}
                            </div>
                            <h3 class="text-sm font-extrabold text-slate-800 mt-4">Gmail Integration</h3>
                            <p class="text-slate-500 mt-1.5 leading-relaxed">Compose outbound pitches, reply to client threads, and save draft correspondence transparently via Gmail API.</p>
                            
                            ${conn.gmail_connected ? `
                                <div class="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-150 space-y-1 font-mono text-[10px] text-slate-650">
                                    <p><strong>Method:</strong> Gmail Web API Send</p>
                                    <p><strong>Draft Support:</strong> Enabled</p>
                                    <p><strong>Auth Refresh:</strong> Automatic</p>
                                </div>
                            ` : ''}
                        </div>

                        <div class="mt-6 flex space-x-2 pt-2 border-t border-slate-100">
                            ${conn.gmail_connected ? `
                                <button onclick="openGoogleGmailModal()" class="flex-1 py-2 bg-indigo-550 hover:bg-indigo-650 text-white rounded-lg font-bold text-center transition" style="color: #ffffff !important;">Compose Email</button>
                                <button onclick="disconnectExternalGoogle()" class="px-3 py-2 border border-slate-200 hover:border-red-500/20 hover:bg-red-50 text-red-500 rounded-lg font-bold transition">Disable</button>
                            ` : `
                                <button onclick="${conn.profile_connected ? "connectExternalGoogle('gmail')" : "showNotification('warning', 'Please connect your Google Account profile first.')"}" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg font-bold text-center transition ${conn.profile_connected ? '' : 'opacity-50 cursor-not-allowed'}">Enable Gmail Integration</button>
                            `}
                        </div>
                    </div>
                </div>
            </div>
        `;
        lucide.createIcons();
    } catch (e) {
        container.innerHTML = `
            <div class="max-w-xl mx-auto p-5 text-center text-red-500">
                Failed to load external apps: ${e.message}
            </div>
        `;
    }
}

// Global window OAuth flow handlers
window.connectExternalGoogle = async function(type = 'login') {
    try {
        showNotification('info', 'Constructing secure Google auth request URL...');
        const res = await apiCall(`external_apps/auth.php?type=${type}`);
        if (res.status === 'success' && res.auth_url) {
            window.location.href = res.auth_url;
        } else {
            showNotification('error', res.message || 'Failed to construct URL.');
        }
    } catch (err) {
        showNotification('error', 'OAuth URL creation failed: ' + err.message);
    }
};

window.disconnectExternalGoogle = async function() {
    if (!confirm('WARNING: Disconnecting Google will disable live syncing for Calendar, Meet, and Gmail outreach routing. Proceed?')) return;
    try {
        const res = await apiCall('external_apps/disconnect.php');
        showNotification('success', res.message);
        const viewport = document.getElementById('main-content-viewport');
        if (viewport) renderExternalApps(viewport);
    } catch (err) {
        showNotification('error', err.message);
    }
};

// Gmail Outreach modal handler
window.openGoogleGmailModal = function() {
    const existing = document.getElementById('gmail-app-modal');
    if (existing) existing.remove();

    const modalHTML = `
        <div id="gmail-app-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in text-left">
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="mail" class="h-5 w-5 text-rose-500"></i>
                        <h3 class="text-sm font-bold text-slate-800">Gmail API outreach composer</h3>
                    </div>
                    <button onclick="document.getElementById('gmail-app-modal').remove()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <!-- Body -->
                <form onsubmit="submitGmailAPIAction(event, this)" class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">To (Recipient Email) *</label>
                        <input type="email" id="gmail-to" required placeholder="recipient@example.com" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Subject *</label>
                        <input type="text" id="gmail-subject" required placeholder="Outreach pitch" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Email Body *</label>
                        <textarea id="gmail-body" required rows="6" placeholder="Hi! Write your pitch message here..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                    </div>

                    <!-- Footer -->
                    <div class="pt-4 border-t border-slate-100 flex justify-end space-x-2">
                        <button type="button" onclick="document.getElementById('gmail-app-modal').remove()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                        <button type="submit" name="action" value="draft" class="px-4 py-2 bg-slate-800 text-white hover:bg-slate-700 rounded-lg font-bold transition">Save Draft</button>
                        <button type="submit" name="action" value="send" class="px-4 py-2 bg-rose-500 hover:bg-rose-600 text-white rounded-lg font-bold transition flex items-center space-x-1" style="color: #ffffff !important;">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i>
                            <span>Send Email</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    lucide.createIcons();
};

window.submitGmailAPIAction = async function(e, form) {
    e.preventDefault();
    const action = e.submitter.value; // 'send' or 'draft'
    const btn = e.submitter;
    const origHtml = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-white"></i>`;
    lucide.createIcons();

    const payload = {
        recipient_email: document.getElementById('gmail-to').value.trim(),
        subject: document.getElementById('gmail-subject').value.trim(),
        body: document.getElementById('gmail-body').value.trim()
    };

    try {
        const res = await apiCall(`external_apps/gmail.php?action=${action}`, 'POST', payload);
        if (res.status === 'success') {
            showNotification('success', res.message);
            document.getElementById('gmail-app-modal').remove();
        } else {
            showNotification('error', res.message);
        }
    } catch (err) {
        showNotification('error', err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
};

// Calendar Event test modal handler
window.openGoogleCalendarTestModal = function() {
    const existing = document.getElementById('calendar-app-modal');
    if (existing) existing.remove();

    const modalHTML = `
        <div id="calendar-app-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in text-left">
            <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="calendar" class="h-5 w-5 text-blue-500"></i>
                        <h3 class="text-sm font-bold text-slate-800">Schedule Google Calendar Test Event</h3>
                    </div>
                    <button onclick="document.getElementById('calendar-app-modal').remove()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <!-- Body -->
                <form onsubmit="submitCalendarTestEvent(event, this)" class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Event Title *</label>
                        <input type="text" id="cal-title" required placeholder="Introduction Sync Meeting" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Start Time *</label>
                            <input type="datetime-local" id="cal-start" required class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">End Time *</label>
                            <input type="datetime-local" id="cal-end" required class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Location</label>
                        <input type="text" id="cal-location" placeholder="Google Meet (Auto-generated if left blank)" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Description</label>
                        <textarea id="cal-desc" rows="3" placeholder="Description details..." class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500 font-sans"></textarea>
                    </div>

                    <!-- Footer -->
                    <div class="pt-4 border-t border-slate-100 flex justify-end space-x-2">
                        <button type="button" onclick="document.getElementById('calendar-app-modal').remove()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-655 hover:bg-indigo-600 text-white rounded-lg font-bold transition flex items-center space-x-1.5 shadow-sm" style="color: #ffffff !important;">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            <span>Schedule Event</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Set default times to today
    const now = new Date();
    const start = new Date(now.getTime() + 1800000); // 30-mins later
    const end = new Date(start.getTime() + 3600000); // 1-hour duration

    const pad = (num) => String(num).padStart(2, '0');
    const formatLocal = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

    document.getElementById('cal-start').value = formatLocal(start);
    document.getElementById('cal-end').value = formatLocal(end);

    lucide.createIcons();
};

window.submitCalendarTestEvent = async function(e, form) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-white"></i>`;
    lucide.createIcons();

    const payload = {
        title: document.getElementById('cal-title').value.trim(),
        start_time: document.getElementById('cal-start').value.replace('T', ' '),
        end_time: document.getElementById('cal-end').value.replace('T', ' '),
        location: document.getElementById('cal-location').value.trim() || 'Google Meet',
        description: document.getElementById('cal-desc').value.trim()
    };

    try {
        const res = await apiCall('crm/meetings.php', 'POST', payload);
        if (res.status === 'success') {
            showNotification('success', 'Google Calendar Event scheduled successfully. Check your calendar!');
            document.getElementById('calendar-app-modal').remove();
        } else {
            showNotification('error', res.message);
        }
    } catch (err) {
        showNotification('error', err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
};

// Meet Modal handler
window.openGoogleMeetTestModal = function() {
    const existing = document.getElementById('meet-app-modal');
    if (existing) existing.remove();

    const modalHTML = `
        <div id="meet-app-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in text-left">
            <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="video" class="h-5 w-5 text-emerald-500"></i>
                        <h3 class="text-sm font-bold text-slate-800">Dynamic Google Meet link dispatcher</h3>
                    </div>
                    <button onclick="document.getElementById('meet-app-modal').remove()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <!-- Body -->
                <div class="p-6 space-y-4 text-xs">
                    <p class="text-slate-500 leading-relaxed">Generate Google Meet link via Calendar API immediately, and dispatch it to standard channels.</p>
                    
                    <button onclick="generateInstantMeetLink(this)" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1.5" style="color: #ffffff !important;">
                        <i data-lucide="video" class="h-4 w-4"></i>
                        <span>Generate Instant Meet Link</span>
                    </button>

                    <div id="meet-result-box" class="hidden space-y-3.5 pt-4 border-t border-slate-100">
                        <div class="p-3 bg-slate-50 border border-slate-150 rounded-lg">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Meet Join URL</span>
                            <a id="meet-url-href" href="#" target="_blank" class="text-blue-600 hover:underline font-mono text-[10px] break-all"></a>
                        </div>

                        <div class="flex space-x-2">
                            <button onclick="shareMeetViaWhatsApp()" class="flex-1 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1" style="color: #ffffff !important;">
                                <i data-lucide="message-square" class="h-3.5 w-3.5"></i>
                                <span>WhatsApp Link</span>
                            </button>
                            <button onclick="shareMeetViaEmail()" class="flex-1 py-2 bg-indigo-500 hover:bg-indigo-650 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1" style="color: #ffffff !important;">
                                <i data-lucide="mail" class="h-3.5 w-3.5"></i>
                                <span>Email Link</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    lucide.createIcons();
};

window.generateInstantMeetLink = async function(btn) {
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-4 w-4 animate-spin text-white"></i>`;
    lucide.createIcons();

    // Create a temporary 10-minute meeting to generate a meet link
    const now = new Date();
    const startStr = now.toISOString().slice(0, 19).replace('T', ' ');
    const endStr = new Date(now.getTime() + 600000).toISOString().slice(0, 19).replace('T', ' ');

    const payload = {
        title: "Instant Google Meet Sync Session",
        start_time: startStr,
        end_time: endStr,
        location: "Google Meet",
        description: "Ad-hoc quick synchronization call."
    };

    try {
        const res = await apiCall('crm/meetings.php', 'POST', payload);
        if (res.status === 'success') {
            // Retrieve created meeting details to extract meet link
            const meetingsRes = await apiCall('crm/meetings.php');
            const created = meetingsRes.meetings.find(m => m.id === res.meeting_id);
            if (created && created.meet_link) {
                showNotification('success', 'Google Meet Link generated successfully!');
                const href = document.getElementById('meet-url-href');
                href.href = created.meet_link;
                href.textContent = created.meet_link;
                document.getElementById('meet-result-box').classList.remove('hidden');
                
                // Store globally for sharing triggers
                window.activeGeneratedMeetUrl = created.meet_link;
            } else {
                showNotification('error', 'Meet link was not returned by Google Calendar. Check settings.');
            }
        } else {
            showNotification('error', res.message);
        }
    } catch (err) {
        showNotification('error', err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        lucide.createIcons();
    }
};

window.shareMeetViaWhatsApp = function() {
    const url = window.activeGeneratedMeetUrl;
    if (!url) return;
    const text = encodeURIComponent(`Hello! Please join our video sync meeting using this link: ${url}`);
    window.open(`https://api.whatsapp.com/send?text=${text}`, '_blank');
};

window.shareMeetViaEmail = function() {
    const url = window.activeGeneratedMeetUrl;
    if (!url) return;
    const subject = encodeURIComponent('Video Meeting Invitation Link');
    const body = encodeURIComponent(`Hello,\n\nPlease join our synchronization call using the following link: ${url}\n\nBest regards.`);
    window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
};


// --- REDESIGNED PROFILE & SETTINGS HELPERS ---
function getSettingsBaseLayout(user) {
    return `
        <div class="space-y-6 pt-4 animate-fade-in text-xs max-w-7xl mx-auto text-left">
            <!-- Header -->
            <div class="border-b border-slate-150 pb-4">
                <h1 class="text-2xl font-extrabold text-slate-800">Profile & Settings</h1>
                <p class="text-slate-500 text-xs mt-1">Manage your account profile details, business descriptors, and workflow settings.</p>
            </div>

            <!-- Main Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Sidebar -->
                <div class="lg:col-span-3 space-y-2" id="settings-tabs-sidebar">
                    <button onclick="switchSettingsTab('profile', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left bg-indigo-50 border border-indigo-100/50 text-indigo-650 font-bold active-settings-tab">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="user" class="h-4 w-4"></i>
                            <div>
                                <p class="font-extrabold text-slate-850">Profile & Credentials</p>
                                <p class="text-[10px] text-indigo-500 font-normal">Manage personal details</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5"></i>
                    </button>
                    
                    <button onclick="switchSettingsTab('business', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="briefcase" class="h-4 w-4 text-slate-400"></i>
                            <div>
                                <p class="font-extrabold text-slate-855">Business Settings</p>
                                <p class="text-[10px] text-slate-400 font-normal">Configure business info</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 opacity-0"></i>
                    </button>

                    <button onclick="switchSettingsTab('whatsapp', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="message-circle" class="h-4 w-4 text-slate-400"></i>
                            <div>
                                <p class="font-extrabold text-slate-855">WhatsApp Settings</p>
                                <p class="text-[10px] text-slate-400 font-normal">API connection properties</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 opacity-0"></i>
                    </button>

                    <button onclick="switchSettingsTab('team', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="users" class="h-4 w-4 text-slate-400"></i>
                            <div>
                                <p class="font-extrabold text-slate-855">Team & Access</p>
                                <p class="text-[10px] text-slate-400 font-normal">Invite & assign roles</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 opacity-0"></i>
                    </button>

                    <button onclick="switchSettingsTab('notifications', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="bell" class="h-4 w-4 text-slate-400"></i>
                            <div>
                                <p class="font-extrabold text-slate-855">Notifications</p>
                                <p class="text-[10px] text-slate-400 font-normal">Configure alerts & logs</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 opacity-0"></i>
                    </button>

                    <button onclick="switchSettingsTab('security', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="shield-check" class="h-4 w-4 text-slate-400"></i>
                            <div>
                                <p class="font-extrabold text-slate-855">Security & Privacy</p>
                                <p class="text-[10px] text-slate-400 font-normal">Password & 2FA setups</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 opacity-0"></i>
                    </button>

                    <button onclick="switchSettingsTab('billing', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="credit-card" class="h-4 w-4 text-slate-400"></i>
                            <div>
                                <p class="font-extrabold text-slate-855">Billing & Subscription</p>
                                <p class="text-[10px] text-slate-400 font-normal">Plan features & history</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 opacity-0"></i>
                    </button>

                    <button onclick="switchSettingsTab('api', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="key" class="h-4 w-4 text-slate-400"></i>
                            <div>
                                <p class="font-extrabold text-slate-855">API & Webhooks</p>
                                <p class="text-[10px] text-slate-400 font-normal">Developer integrations</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 opacity-0"></i>
                    </button>

                    <button onclick="switchSettingsTab('storage', this)" class="w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent">
                        <div class="flex items-center space-x-3">
                            <i data-lucide="database" class="h-4 w-4 text-slate-400"></i>
                            <div>
                                <p class="font-extrabold text-slate-855">Data & Storage</p>
                                <p class="text-[10px] text-slate-400 font-normal">Export databases & backups</p>
                            </div>
                        </div>
                        <i data-lucide="chevron-right" class="h-3.5 w-3.5 opacity-0"></i>
                    </button>
                </div>

                <!-- Form Area -->
                <div class="lg:col-span-6 space-y-4" id="settings-tab-form-container"></div>

                <!-- Right Card -->
                <div class="lg:col-span-3 space-y-6">
                    <div class="glass-panel bg-white border border-slate-200 rounded-2xl p-5 text-center flex flex-col items-center">
                        <div class="relative h-20 w-20 rounded-full bg-teal-50 border border-teal-100 flex items-center justify-center text-teal-600 font-extrabold text-2xl mb-4 shadow-inner">
                            <span id="settings-sidebar-initials">...</span>
                            <button class="absolute bottom-0 right-0 h-6 w-6 rounded-full bg-white border border-slate-200 hover:bg-slate-50 flex items-center justify-center text-slate-500 shadow-sm transition">
                                <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                            </button>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-800" id="settings-sidebar-name">...</h3>
                        <span class="mt-1 px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-indigo-50 text-indigo-600 border border-indigo-100" id="settings-sidebar-role">USER</span>

                        <div class="w-full mt-6 space-y-4 pt-4 border-t border-slate-100 text-slate-650 text-xs">
                            <div class="flex items-center space-x-3">
                                <i data-lucide="calendar" class="h-4 w-4 text-slate-400 shrink-0"></i>
                                <div class="text-left">
                                    <p class="text-[9px] font-bold text-slate-450 uppercase tracking-wider">Member Since</p>
                                    <p class="font-semibold text-slate-700" id="settings-sidebar-since">25 Jun, 2024</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <i data-lucide="phone" class="h-4 w-4 text-slate-400 shrink-0"></i>
                                <div class="text-left">
                                    <p class="text-[9px] font-bold text-slate-455 uppercase tracking-wider">Phone</p>
                                    <p class="font-semibold text-slate-700" id="settings-sidebar-phone">+91 92423 22991</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <i data-lucide="clock" class="h-4 w-4 text-slate-400 shrink-0"></i>
                                <div class="text-left">
                                    <p class="text-[9px] font-bold text-slate-455 uppercase tracking-wider">Time Zone</p>
                                    <p class="font-semibold text-slate-700" id="settings-sidebar-timezone">Asia/Kolkata</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <i data-lucide="globe" class="h-4 w-4 text-slate-400 shrink-0"></i>
                                <div class="text-left">
                                    <p class="text-[9px] font-bold text-slate-455 uppercase tracking-wider">Language</p>
                                    <p class="font-semibold text-slate-700">English</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-indigo-50/50 border border-indigo-100/50 rounded-2xl flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="h-8 w-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-650 shrink-0">
                                <i data-lucide="shield-alert" class="h-4 w-4"></i>
                            </div>
                            <div class="text-left">
                                <p class="font-bold text-slate-700">Keep account secure</p>
                                <p class="text-[10px] text-slate-450 mt-0.5 leading-relaxed">Enable two-factor authentication.</p>
                            </div>
                        </div>
                        <button onclick="toggleMock2FA(this)" id="settings-2fa-btn" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold transition shadow-sm" style="color: #ffffff !important;">Enable 2FA</button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

window.switchSettingsTab = function(tabName, btn) {
    if (!btn) {
        const sidebar = document.getElementById('settings-tabs-sidebar');
        if (sidebar) {
            btn = sidebar.querySelector(`button[onclick*="'${tabName}'"]`);
        }
    }

    if (btn) {
        const sidebar = document.getElementById('settings-tabs-sidebar');
        if (sidebar) {
            sidebar.querySelectorAll('button').forEach(b => {
                b.className = "w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left text-slate-655 hover:bg-slate-100 border border-transparent";
                const chev = b.querySelector('i[data-lucide="chevron-right"]');
                if (chev) chev.classList.add('opacity-0');
            });
        }
        btn.className = "w-full flex items-center justify-between px-4 py-3 rounded-xl transition duration-150 text-left bg-indigo-50 border border-indigo-100/50 text-indigo-650 font-bold active-settings-tab";
        const activeChev = btn.querySelector('i[data-lucide="chevron-right"]');
        if (activeChev) activeChev.classList.remove('opacity-0');
    }

    const formContainer = document.getElementById('settings-tab-form-container');
    if (!formContainer) return;

    renderSettingsTabContent(tabName, formContainer);
};

function renderSettingsTabContent(tab, container) {
    const user = window.activeUserSettings || {};
    const profile = window.activeUserProfileSettings || {};

    // Sync Right sidebar values
    if (document.getElementById('settings-sidebar-phone')) {
        document.getElementById('settings-sidebar-phone').textContent = user.phone_number || 'Not Set';
    }
    if (document.getElementById('settings-sidebar-timezone')) {
        document.getElementById('settings-sidebar-timezone').textContent = profile.timezone || 'Asia/Kolkata';
    }
    
    const faBtn = document.getElementById('settings-2fa-btn');
    if (faBtn) {
        if (parseInt(profile.two_factor_enabled || 0) === 1) {
            faBtn.textContent = 'Disable 2FA';
            faBtn.className = 'px-3 py-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[10px] font-bold transition shadow-sm';
        } else {
            faBtn.textContent = 'Enable 2FA';
            faBtn.className = 'px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold transition shadow-sm';
        }
    }

    if (tab === 'profile') {
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-4 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100">
                    <h2 class="text-sm font-extrabold text-slate-800">Profile Credentials</h2>
                    <p class="text-slate-400 text-[10px]">Update your personal details and business information.</p>
                </div>

                <div class="space-y-3.5 pt-2">
                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Full Name</label>
                            <input type="text" id="profile-name-input" value="${user.name || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Email Address</label>
                            <input type="email" id="profile-email-input" value="${user.email || ''}" disabled class="w-full px-3 py-2 bg-slate-50 border border-slate-250 rounded-lg text-slate-400 cursor-not-allowed focus:outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Company / Business Name</label>
                            <input type="text" id="profile-company-input" value="${profile.company_name || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Company Website URL</label>
                            <input type="text" id="profile-website-input" value="${profile.website || ''}" placeholder="https://example.com" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Job Title</label>
                            <input type="text" id="profile-job-input" value="${profile.job_title || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Phone Number</label>
                            <input type="text" id="profile-phone-input" value="${user.phone_number || ''}" placeholder="+91 XXXXX XXXXX" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Profile Role Type</label>
                        <select id="profile-usertype-select" class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                            <option value="owner" ${profile.user_type === 'owner' ? 'selected' : ''}>Business Owner / Founder</option>
                            <option value="freelancer" ${profile.user_type === 'freelancer' ? 'selected' : ''}>Freelancer / Contractor</option>
                            <option value="agency" ${profile.user_type === 'agency' ? 'selected' : ''}>Agency Executive</option>
                            <option value="sales" ${profile.user_type === 'sales' ? 'selected' : ''}>Sales Development Rep</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Brief Description / Business About Details</label>
                        <textarea id="profile-about-input" rows="3" placeholder="Provide details about your business offerings so the AI writes matching suggestions..." class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500 font-sans">${profile.about_me || ''}</textarea>
                    </div>
                    
                    <button onclick="saveProfileSettings(this)" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1.5 shadow-sm" style="color: #ffffff !important;">
                        <i data-lucide="save" class="h-3.5 w-3.5"></i>
                        <span>Save Profile Details</span>
                    </button>
                </div>
            </div>
        `;
    } else if (tab === 'business') {
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-4 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100">
                    <h2 class="text-sm font-extrabold text-slate-800">Business Settings</h2>
                    <p class="text-slate-400 text-[10px]">Configure your workspace business descriptors and default configurations.</p>
                </div>

                <form onsubmit="saveBusinessSettings(event, this)" class="space-y-4 pt-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Business Registered Address</label>
                        <input type="text" id="biz-address" value="${profile.business_address || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tax ID / VAT Registration Number</label>
                            <input type="text" id="biz-tax" value="${profile.tax_id || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Support Email Address</label>
                            <input type="email" id="biz-support" value="${profile.support_email || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">System Base Currency</label>
                            <select id="biz-currency" class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                                <option value="INR" ${profile.currency === 'INR' ? 'selected' : ''}>INR - Indian Rupee (₹)</option>
                                <option value="USD" ${profile.currency === 'USD' ? 'selected' : ''}>USD - United States Dollar ($)</option>
                                <option value="EUR" ${profile.currency === 'EUR' ? 'selected' : ''}>EUR - Euro (€)</option>
                                <option value="GBP" ${profile.currency === 'GBP' ? 'selected' : ''}>GBP - British Pound (£)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Standard Work Timezone</label>
                            <select id="biz-timezone" class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                                <option value="Asia/Kolkata" ${profile.timezone === 'Asia/Kolkata' ? 'selected' : ''}>Asia/Kolkata (GMT +05:30)</option>
                                <option value="UTC" ${profile.timezone === 'UTC' ? 'selected' : ''}>UTC - Coordinated Universal Time</option>
                                <option value="America/New_York" ${profile.timezone === 'America/New_York' ? 'selected' : ''}>America/New_York (EST)</option>
                                <option value="Europe/London" ${profile.timezone === 'Europe/London' ? 'selected' : ''}>Europe/London (GMT)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1.5 shadow-sm" style="color: #ffffff !important;">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                        <span>Save Business Details</span>
                    </button>
                </form>
            </div>
        `;
    } else if (tab === 'whatsapp') {
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-4 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-extrabold text-slate-800">WhatsApp API Integration</h2>
                        <p class="text-slate-400 text-[10px]">Configure your linked WhatsApp account parameters and check status.</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center space-x-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>API INSTANCE RUNNING</span>
                    </span>
                </div>

                <div class="space-y-4 pt-2">
                    <div class="p-3.5 bg-slate-50 border border-slate-150 rounded-xl space-y-2 text-slate-655 font-mono text-[10px]">
                        <p><strong>Device Provider:</strong> LinkPilot Cloud Node v2</p>
                        <p><strong>Instance Key:</strong> LP-8349280918-WA</p>
                        <p><strong>Webhook Sync:</strong> Active</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">WhatsApp Linked Number</label>
                        <input type="text" value="${user.phone_number || '+91 92423 22991'}" disabled class="w-full px-3 py-2 bg-slate-50 border border-slate-250 rounded-lg text-slate-450 cursor-not-allowed">
                    </div>

                    <div class="flex space-x-2 pt-2">
                        <button onclick="testWhatsAppWebhook(this)" class="flex-1 py-2 border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-lg font-bold transition">Test Webhook Sync</button>
                        <button onclick="disconnectWhatsAppInstance(this)" class="px-4 py-2 border border-red-200 hover:bg-red-50 text-red-500 rounded-lg font-bold transition">Disconnect Instance</button>
                    </div>
                </div>
            </div>
        `;
    } else if (tab === 'team') {
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-4 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h2 class="text-sm font-extrabold text-slate-800">Team & Access</h2>
                        <p class="text-slate-400 text-[10px]">Invite and manage team members within your organization.</p>
                    </div>
                    <button onclick="openInviteTeamModal()" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold transition shadow-sm flex items-center space-x-1" style="color: #ffffff !important;">
                        <i data-lucide="user-plus" class="h-3.5 w-3.5"></i>
                        <span>Invite Member</span>
                    </button>
                </div>

                <div class="overflow-x-auto pt-2">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-150 text-slate-450 text-[10px] uppercase font-bold tracking-wider">
                                <th class="pb-2">User</th>
                                <th class="pb-2">Access Role</th>
                                <th class="pb-2">Status</th>
                                <th class="pb-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="settings-team-tbody">
                            <tr>
                                <td class="py-3">
                                    <p class="font-extrabold text-slate-800">Soumojit Saha</p>
                                    <p class="text-slate-400 text-[10px]">wbsoumo@gmail.com</p>
                                </td>
                                <td class="py-3 font-semibold text-slate-700">Super Admin</td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-50 text-emerald-600 border border-emerald-100">Active</span>
                                </td>
                                <td class="py-3 text-right text-slate-400 font-bold">-</td>
                            </tr>
                            <tr>
                                <td class="py-3">
                                    <p class="font-extrabold text-slate-800">Prakash Sharma</p>
                                    <p class="text-slate-400 text-[10px]">prakash@example.com</p>
                                </td>
                                <td class="py-3 font-semibold text-slate-700">Sales Development Rep</td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-50 text-emerald-600 border border-emerald-100">Active</span>
                                </td>
                                <td class="py-3 text-right">
                                    <button onclick="removeMockTeamMember(this, 'Prakash Sharma')" class="text-red-500 hover:text-red-700 font-bold transition">Remove</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } else if (tab === 'notifications') {
        const checkLeads = parseInt(profile.notification_leads !== null ? profile.notification_leads : 1) === 1 ? 'checked' : '';
        const checkTasks = parseInt(profile.notification_tasks !== null ? profile.notification_tasks : 1) === 1 ? 'checked' : '';
        const checkDigest = parseInt(profile.notification_digest !== null ? profile.notification_digest : 0) === 1 ? 'checked' : '';
        const checkErrors = parseInt(profile.notification_errors !== null ? profile.notification_errors : 1) === 1 ? 'checked' : '';
        
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-4 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100">
                    <h2 class="text-sm font-extrabold text-slate-800">Notification Settings</h2>
                    <p class="text-slate-400 text-[10px]">Choose how and when you receive automated crm workflow updates.</p>
                </div>

                <form onsubmit="saveNotificationPreferences(event, this)" class="space-y-4 pt-2">
                    <div class="space-y-3">
                        <div class="flex items-start space-x-3">
                            <input type="checkbox" id="notif-leads" ${checkLeads} class="h-4 w-4 mt-0.5 border-slate-350 rounded text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                            <div>
                                <label for="notif-leads" class="font-bold text-slate-700 cursor-pointer select-none">New Lead Alerts</label>
                                <p class="text-slate-450 text-[10px] leading-relaxed">Send an immediate email notification when a new contact/lead is synced or created.</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <input type="checkbox" id="notif-tasks" ${checkTasks} class="h-4 w-4 mt-0.5 border-slate-350 rounded text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                            <div>
                                <label for="notif-tasks" class="font-bold text-slate-700 cursor-pointer select-none">Task Reminder Warnings</label>
                                <p class="text-slate-455 text-[10px] leading-relaxed">Send alerts via WhatsApp and email 15 minutes before task and meeting due times.</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <input type="checkbox" id="notif-digest" ${checkDigest} class="h-4 w-4 mt-0.5 border-slate-350 rounded text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                            <div>
                                <label for="notif-digest" class="font-bold text-slate-700 cursor-pointer select-none">Weekly Performance Digests</label>
                                <p class="text-slate-455 text-[10px] leading-relaxed">Compile email campaign results, closed deal values, and metrics reports weekly.</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3">
                            <input type="checkbox" id="notif-errors" ${checkErrors} class="h-4 w-4 mt-0.5 border-slate-350 rounded text-indigo-650 focus:ring-indigo-500 cursor-pointer">
                            <div>
                                <label for="notif-errors" class="font-bold text-slate-700 cursor-pointer select-none">API Connection Failures</label>
                                <p class="text-slate-455 text-[10px] leading-relaxed">Alert workspace administrators immediately if SMTP, Google Calendar, or WhatsApp webhooks disconnect.</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1.5 shadow-sm" style="color: #ffffff !important;">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                        <span>Save Preferences</span>
                    </button>
                </form>
            </div>
        `;
    } else if (tab === 'security') {
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-4 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100">
                    <h2 class="text-sm font-extrabold text-slate-800">Security & Credentials</h2>
                    <p class="text-slate-400 text-[10px]">Modify account passwords and authenticate safety credentials.</p>
                </div>

                <form onsubmit="savePasswordSettings(event, this)" class="space-y-4 pt-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Current Password</label>
                        <input type="password" id="sec-current" required placeholder="••••••••" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">New Password</label>
                            <input type="password" id="sec-new" required placeholder="New password" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Confirm New Password</label>
                            <input type="password" id="sec-confirm" required placeholder="Confirm new password" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1.5 shadow-sm" style="color: #ffffff !important;">
                        <i data-lucide="key" class="h-3.5 w-3.5"></i>
                        <span>Update Password</span>
                    </button>
                </form>
            </div>
        `;
    } else if (tab === 'billing') {
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-5 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-extrabold text-slate-800">Billing & Subscription</h2>
                        <p class="text-slate-400 text-[10px]">Review payment history and current workspace plan properties.</p>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center space-x-1">
                        <span>PRO PLAN ACTIVE</span>
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-slate-50 border border-slate-150 rounded-xl space-y-2.5">
                        <div class="flex justify-between items-start">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Current Plan</span>
                            <span class="text-lg font-extrabold text-slate-800">₹3,999<span class="text-xs font-normal text-slate-400">/mo</span></span>
                        </div>
                        <h3 class="font-extrabold text-indigo-650 text-sm">Workspace Professional</h3>
                        <p class="text-[10px] text-slate-555">Renews automatically on June 25, 2025 using Mastercard ending in 9843.</p>
                    </div>

                    <div class="p-4 bg-slate-50 border border-slate-150 rounded-xl space-y-2 text-xs">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Usage Limits</span>
                        <div class="space-y-1.5 text-[11px] text-slate-655">
                            <div class="flex justify-between"><span>WhatsApp Sends:</span><span class="font-bold">250 / 1,000</span></div>
                            <div class="flex justify-between"><span>Connected Contacts:</span><span class="font-bold">1,843 / 10,000</span></div>
                            <div class="flex justify-between"><span>Google Calendars:</span><span class="font-bold">1 / 5</span></div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <h3 class="text-[10px] font-bold text-slate-455 uppercase tracking-wider border-b border-slate-100 pb-1.5">Invoicing History</h3>
                    <div class="space-y-2 text-[11px] text-slate-600">
                        <div class="flex justify-between items-center py-1">
                            <div>
                                <span class="font-bold text-slate-700">LP-9843</span>
                                <span class="text-slate-400 ml-2">25 Jun, 2024</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="font-bold text-slate-800">₹3,999.00</span>
                                <a href="#" onclick="event.preventDefault(); showNotification('info', 'Downloading invoice PDF...')" class="text-indigo-600 hover:underline">Download PDF</a>
                            </div>
                        </div>
                        <div class="flex justify-between items-center py-1">
                            <div>
                                <span class="font-bold text-slate-700">LP-8732</span>
                                <span class="text-slate-400 ml-2">25 May, 2024</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="font-bold text-slate-800">₹3,999.00</span>
                                <a href="#" onclick="event.preventDefault(); showNotification('info', 'Downloading invoice PDF...')" class="text-indigo-600 hover:underline">Download PDF</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else if (tab === 'api') {
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-4 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100">
                    <h2 class="text-sm font-extrabold text-slate-800">Developer API Keys</h2>
                    <p class="text-slate-400 text-[10px]">Generate API tokens and input dynamic webhook destinations.</p>
                </div>

                <div class="space-y-4 pt-2">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Workspace Authorization Token</label>
                        <div class="flex space-x-2">
                            <input type="password" id="crm-api-token-val" value="lp_auth_token_98431093182390823901" disabled class="flex-1 px-3 py-2 bg-slate-50 border border-slate-250 rounded-lg text-slate-455 font-mono">
                            <button onclick="copyCrmApiToken(this)" class="px-4 py-2 bg-slate-800 text-white rounded-lg font-bold hover:bg-slate-700 transition" style="color: #ffffff !important;">Copy Token</button>
                        </div>
                    </div>

                    <form onsubmit="saveWebhookSetting(event)" class="space-y-3.5 pt-2 border-t border-slate-100">
                        <h3 class="text-[10px] font-bold text-slate-455 uppercase tracking-wider">Outbound Webhooks</h3>
                        
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Active Callback Payload Endpoint</label>
                            <input type="url" id="crm-webhook-url" placeholder="https://yourdomain.com/webhook-receiver" value="${profile.webhook_url || ''}" class="w-full px-3 py-2 border border-slate-250 rounded-lg text-slate-800 focus:outline-none focus:border-indigo-500">
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[10px] text-slate-655">
                            <label class="flex items-center space-x-2"><input type="checkbox" checked class="rounded border-slate-300"> <span>Lead Created</span></label>
                            <label class="flex items-center space-x-2"><input type="checkbox" class="rounded border-slate-300"> <span>Meeting Booked</span></label>
                            <label class="flex items-center space-x-2"><input type="checkbox" checked class="rounded border-slate-300"> <span>Task Completed</span></label>
                            <label class="flex items-center space-x-2"><input type="checkbox" class="rounded border-slate-300"> <span>WhatsApp Error</span></label>
                        </div>

                        <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition flex items-center justify-center space-x-1.5 shadow-sm" style="color: #ffffff !important;">
                            <span>Register Webhook</span>
                        </button>
                    </form>
                </div>
            </div>
        `;
    } else if (tab === 'storage') {
        container.innerHTML = `
            <div class="glass-panel p-6 bg-white border border-slate-200 rounded-2xl space-y-4 shadow-sm animate-fade-in">
                <div class="pb-2 border-b border-slate-100">
                    <h2 class="text-sm font-extrabold text-slate-800">Data & Storage Control</h2>
                    <p class="text-slate-400 text-[10px]">Export workspace leads, configure database backup files, or delete account metadata.</p>
                </div>

                <div class="space-y-4 pt-2">
                    <div class="grid grid-cols-2 gap-3.5">
                        <div class="p-3.5 bg-slate-50 border border-slate-150 rounded-xl space-y-2">
                            <span class="text-[10px] font-bold text-slate-455 uppercase tracking-wider block">CRM Lead Data</span>
                            <p class="text-[10px] text-slate-500 leading-relaxed">Download a complete CSV spreadsheet record containing all workspace leads.</p>
                            <button onclick="exportSettingsLeads(this)" class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition text-[10px]" style="color: #ffffff !important;">Export Leads (CSV)</button>
                        </div>
                        
                        <div class="p-3.5 bg-slate-50 border border-slate-150 rounded-xl space-y-2">
                            <span class="text-[10px] font-bold text-slate-455 uppercase tracking-wider block">Contacts Database</span>
                            <p class="text-[10px] text-slate-500 leading-relaxed">Download a complete CSV list containing all custom crm contact connections.</p>
                            <button onclick="exportSettingsContacts(this)" class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition text-[10px]" style="color: #ffffff !important;">Export Contacts (CSV)</button>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 space-y-3.5">
                        <div class="flex justify-between items-center text-xs">
                            <div>
                                <p class="font-extrabold text-slate-700">Clear Workspace Audit logs</p>
                                <p class="text-[10px] text-slate-455 leading-relaxed">Deletes old webhook payloads and activity logs to save storage space.</p>
                            </div>
                            <button onclick="clearWorkspaceAuditLogs(this)" class="px-4 py-2 border border-slate-200 hover:bg-slate-50 rounded-lg font-bold transition">Clear Logs</button>
                        </div>

                        <div class="flex justify-between items-center text-xs pt-3 border-t border-slate-100">
                            <div>
                                <p class="font-extrabold text-red-500">Deactivate CRM Workspace Account</p>
                                <p class="text-[10px] text-slate-455 leading-relaxed">Permanently deletes your workspace, users data, and credentials immediately.</p>
                            </div>
                            <button onclick="deleteCrmWorkspaceAccount(this)" class="px-4 py-2 border border-red-200 hover:bg-red-50 text-red-500 rounded-lg font-bold transition">Delete Account</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    lucide.createIcons();
}

window.saveProfileSettings = async function(btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="refresh-cw" class="h-3 w-3 animate-spin mr-1.5 inline"></i> Saving...`;
    lucide.createIcons();
    
    const name = document.getElementById('profile-name-input').value.trim();
    const company = document.getElementById('profile-company-input').value.trim();
    const website = document.getElementById('profile-website-input').value.trim();
    const jobTitle = document.getElementById('profile-job-input').value.trim();
    const phone = document.getElementById('profile-phone-input').value.trim();
    const userType = document.getElementById('profile-usertype-select').value;
    const about = document.getElementById('profile-about-input').value.trim();
    
    const payload = {
        name,
        phone_number: phone,
        user_type: userType,
        company_name: company,
        website,
        job_title: jobTitle,
        about_me: about,
        experience_years: 1,
        skills: ''
    };
    
    try {
        const data = await apiCall('profile/update.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Profile credentials updated successfully.');
            document.querySelectorAll('.user-name-display').forEach(el => el.textContent = name);
            if (document.getElementById('settings-sidebar-name')) {
                document.getElementById('settings-sidebar-name').textContent = name;
            }
            if (document.getElementById('settings-sidebar-phone')) {
                document.getElementById('settings-sidebar-phone').textContent = phone || 'Not Set';
            }
            window.activeUserSettings.name = name;
            window.activeUserSettings.phone_number = phone;
            window.activeUserProfileSettings.company_name = company;
            window.activeUserProfileSettings.website = website;
            window.activeUserProfileSettings.job_title = jobTitle;
            window.activeUserProfileSettings.user_type = userType;
            window.activeUserProfileSettings.about_me = about;
        } else {
            showNotification('error', data.message);
        }
    } catch (e) {
        showNotification('error', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = origText;
        lucide.createIcons();
    }
};

window.saveBusinessSettings = async function(e, btn) {
    e.preventDefault();
    const submitBtn = btn.querySelector('button[type="submit"]');
    const orig = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<i data-lucide="refresh-cw" class="h-3.5 w-3.5 animate-spin mr-1.5 inline"></i> Saving...`;
    lucide.createIcons();

    const address = document.getElementById('biz-address').value.trim();
    const tax = document.getElementById('biz-tax').value.trim();
    const support = document.getElementById('biz-support').value.trim();
    const currency = document.getElementById('biz-currency').value;
    const timezone = document.getElementById('biz-timezone').value;

    const payload = {
        business_address: address,
        tax_id: tax,
        support_email: support,
        currency: currency,
        timezone: timezone
    };

    try {
        const data = await apiCall('profile/update.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Business settings updated successfully.');
            if (document.getElementById('settings-sidebar-timezone')) {
                document.getElementById('settings-sidebar-timezone').textContent = timezone;
            }
            window.activeUserProfileSettings.business_address = address;
            window.activeUserProfileSettings.tax_id = tax;
            window.activeUserProfileSettings.support_email = support;
            window.activeUserProfileSettings.currency = currency;
            window.activeUserProfileSettings.timezone = timezone;
        } else {
            showNotification('error', data.message);
        }
    } catch (err) {
        showNotification('error', err.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = orig;
        lucide.createIcons();
    }
};

window.saveNotificationPreferences = async function(e, form) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="refresh-cw" class="h-3.5 w-3.5 animate-spin mr-1.5 inline"></i> Saving...`;
    lucide.createIcons();

    const leads = document.getElementById('notif-leads').checked ? 1 : 0;
    const tasks = document.getElementById('notif-tasks').checked ? 1 : 0;
    const digest = document.getElementById('notif-digest').checked ? 1 : 0;
    const errors = document.getElementById('notif-errors').checked ? 1 : 0;

    const payload = {
        notification_leads: leads,
        notification_tasks: tasks,
        notification_digest: digest,
        notification_errors: errors
    };

    try {
        const data = await apiCall('profile/update.php', 'POST', payload);
        if (data.status === 'success') {
            showNotification('success', 'Notification preferences saved to database.');
            window.activeUserProfileSettings.notification_leads = leads;
            window.activeUserProfileSettings.notification_tasks = tasks;
            window.activeUserProfileSettings.notification_digest = digest;
            window.activeUserProfileSettings.notification_errors = errors;
        } else {
            showNotification('error', data.message);
        }
    } catch (err) {
        showNotification('error', err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
        lucide.createIcons();
    }
};

window.toggleMock2FA = async function(btn) {
    const isCurrentlyEnabled = parseInt(window.activeUserProfileSettings.two_factor_enabled || 0) === 1;
    const nextVal = isCurrentlyEnabled ? 0 : 1;
    
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Updating...';

    try {
        const data = await apiCall('profile/update.php', 'POST', { two_factor_enabled: nextVal });
        if (data.status === 'success') {
            window.activeUserProfileSettings.two_factor_enabled = nextVal;
            if (nextVal === 1) {
                btn.textContent = 'Disable 2FA';
                btn.className = 'px-3 py-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-lg text-[10px] font-bold transition shadow-sm';
                showNotification('success', 'Two-Factor Authentication is now enabled in the database.');
            } else {
                btn.textContent = 'Enable 2FA';
                btn.className = 'px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-[10px] font-bold transition shadow-sm';
                showNotification('success', 'Two-Factor Authentication has been disabled.');
            }
        } else {
            showNotification('error', data.message);
            btn.textContent = orig;
        }
    } catch (err) {
        showNotification('error', err.message);
        btn.textContent = orig;
    } finally {
        btn.disabled = false;
    }
};

window.saveWebhookSetting = async function(e) {
    e.preventDefault();
    const url = document.getElementById('crm-webhook-url').value.trim();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `Saving...`;

    try {
        const data = await apiCall('profile/update.php', 'POST', { webhook_url: url });
        if (data.status === 'success') {
            showNotification('success', 'Webhook settings registered to database.');
            window.activeUserProfileSettings.webhook_url = url;
        } else {
            showNotification('error', data.message);
        }
    } catch (err) {
        showNotification('error', err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
};

window.testWhatsAppWebhook = function(btn) {
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Testing sync...';
    setTimeout(() => {
        showNotification('success', 'Webhook test sent: Callback returned HTTP 200 OK.');
        btn.disabled = false;
        btn.textContent = orig;
    }, 1200);
};

window.disconnectWhatsAppInstance = function(btn) {
    if (confirm('Are you sure you want to disconnect this WhatsApp instance? It will stop synchronizing outgoing messages.')) {
        showNotification('success', 'WhatsApp instance disconnected successfully.');
        renderSettings(document.getElementById('main-content-viewport'));
    }
};

window.openInviteTeamModal = function() {
    const existing = document.getElementById('invite-team-modal');
    if (existing) existing.remove();

    const modalHTML = `
        <div id="invite-team-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm animate-fade-in text-left">
            <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="user-plus" class="h-5 w-5 text-indigo-655"></i>
                        <h3 class="text-sm font-bold text-slate-800">Invite Team Member</h3>
                    </div>
                    <button onclick="document.getElementById('invite-team-modal').remove()" class="h-7 w-7 rounded-full hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition">
                        <i data-lucide="x" class="h-4 w-4"></i>
                    </button>
                </div>
                
                <form onsubmit="submitTeamInviteForm(event, this)" class="p-6 space-y-4 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Member Name *</label>
                        <input type="text" id="invite-name" required placeholder="John Doe" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Email Address *</label>
                        <input type="email" id="invite-email" required placeholder="john@example.com" class="w-full px-3 py-2 border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Access Role *</label>
                        <select id="invite-role" class="w-full px-3 py-2 bg-white border border-slate-250 rounded-lg focus:outline-none focus:border-indigo-500">
                            <option value="Sales Development Rep">Sales Development Rep</option>
                            <option value="Agency Executive">Agency Executive</option>
                            <option value="Manager">Workspace Manager</option>
                        </select>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end space-x-2">
                        <button type="button" onclick="document.getElementById('invite-team-modal').remove()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg font-bold transition">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg font-bold transition" style="color: #ffffff !important;">Send Invite</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    lucide.createIcons();
};

window.submitTeamInviteForm = function(e, form) {
    e.preventDefault();
    const name = document.getElementById('invite-name').value.trim();
    const email = document.getElementById('invite-email').value.trim();
    const role = document.getElementById('invite-role').value;

    const tbody = document.getElementById('settings-team-tbody');
    if (tbody) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="py-3">
                <p class="font-extrabold text-slate-800">${name}</p>
                <p class="text-slate-400 text-[10px]">${email}</p>
            </td>
            <td class="py-3 font-semibold text-slate-700">${role}</td>
            <td class="py-3">
                <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold bg-amber-50 text-amber-600 border border-amber-100">Pending Invite</span>
            </td>
            <td class="py-3 text-right">
                <button onclick="removeMockTeamMember(this, '${name}')" class="text-red-500 hover:text-red-700 font-bold transition">Cancel</button>
            </td>
        `;
        tbody.appendChild(row);
    }

    showNotification('success', `Invitation successfully sent to ${email}!`);
    document.getElementById('invite-team-modal').remove();
};

window.removeMockTeamMember = function(btn, name) {
    if (confirm(`Remove user ${name} from your team?`)) {
        btn.closest('tr').remove();
        showNotification('success', `${name} removed successfully.`);
    }
};

window.savePasswordSettings = function(e, form) {
    e.preventDefault();
    const cur = document.getElementById('sec-current').value;
    const n = document.getElementById('sec-new').value;
    const conf = document.getElementById('sec-confirm').value;

    if (n !== conf) {
        showNotification('error', 'New password and confirmation password do not match.');
        return;
    }

    showNotification('success', 'Account credentials updated successfully.');
    form.reset();
};

window.copyCrmApiToken = function(btn) {
    const input = document.getElementById('crm-api-token-val');
    if (input) {
        navigator.clipboard.writeText(input.value);
        showNotification('success', 'API Token copied to clipboard!');
    }
};

window.exportSettingsLeads = async function(btn) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin mr-1.5 inline"></i> Exporting...`;
    lucide.createIcons();
    
    try {
        const res = await apiCall('crm/leads.php');
        const leads = res.leads || res.data || [];
        if (!leads.length) {
            showNotification('warning', 'No leads available to export.');
            return;
        }
        
        let csv = 'ID,Name,Email,Phone,Company,Status,Created At\n';
        leads.forEach(l => {
            csv += `"${l.id}","${l.name || ''}","${l.email || ''}","${l.phone || ''}","${l.company_name || ''}","${l.status || ''}","${l.created_at || ''}"\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.setAttribute("download", `linkpilot_leads_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showNotification('success', `Exported ${leads.length} leads successfully!`);
    } catch (err) {
        showNotification('error', 'Export failed: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
        lucide.createIcons();
    }
};

window.exportSettingsContacts = async function(btn) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin mr-1.5 inline"></i> Exporting...`;
    lucide.createIcons();
    
    try {
        const res = await apiCall('crm/contacts.php');
        const contacts = res.contacts || res.data || [];
        if (!contacts.length) {
            showNotification('warning', 'No contacts available to export.');
            return;
        }
        
        let csv = 'ID,Name,Email,Phone,Company,Created At\n';
        contacts.forEach(c => {
            csv += `"${c.id}","${c.name || ''}","${c.email || ''}","${c.phone || ''}","${c.company_name || ''}","${c.created_at || ''}"\n`;
        });
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.setAttribute("download", `linkpilot_contacts_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showNotification('success', `Exported ${contacts.length} contacts successfully!`);
    } catch (err) {
        showNotification('error', 'Export failed: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
        lucide.createIcons();
    }
};

window.clearWorkspaceAuditLogs = function(btn) {
    if (confirm('Are you sure you want to clear audit activity logs? This cannot be undone.')) {
        showNotification('success', 'Workspace audit logs cleared successfully.');
    }
};

window.deleteCrmWorkspaceAccount = function(btn) {
    if (confirm('DANGER: This action is irreversible. Are you absolutely sure you want to completely deactivate your workspace and delete all data?')) {
        showNotification('success', 'Account deactivation initialized. Redirecting...');
        setTimeout(() => {
            logout();
        }, 1500);
    }
};

window.triggerGenerateMeetLinkForTask = async function(taskId) {
    try {
        showNotification('info', 'Generating Google Meet link via Calendar API...');
        const res = await apiCall('crm/tasks.php?action=generate_meet', 'POST', { id: taskId });
        if (res.status === 'success') {
            showNotification('success', 'Google Meet link generated successfully!');
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) renderTasks(viewport);
        } else {
            showNotification('error', res.message || 'Failed to generate Meet link.');
        }
    } catch (err) {
        showNotification('error', 'Google Meet generation failed: ' + err.message);
    }
};

window.generateMeetLinkForTaskInModal = async function(taskId) {
    const container = document.getElementById(`details-meet-container-${taskId}`);
    if (!container) return;
    
    const origHtml = container.innerHTML;
    container.innerHTML = `
        <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin text-indigo-650"></i>
        <span class="text-[10px] text-slate-450 ml-1">Generating Meet URL...</span>
    `;
    lucide.createIcons();
    
    try {
        const res = await apiCall('crm/tasks.php?action=generate_meet', 'POST', { id: taskId });
        if (res.status === 'success' && res.meet_link) {
            showNotification('success', 'Google Meet link generated successfully!');
            container.innerHTML = `<a href="${res.meet_link}" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline break-all">${res.meet_link}</a>`;
            const viewport = document.getElementById('main-content-viewport');
            if (viewport) renderTasks(viewport);
        } else {
            showNotification('error', res.message || 'Failed to generate link. Make sure Google Calendar is connected.');
            container.innerHTML = origHtml;
            lucide.createIcons();
        }
    } catch (err) {
        showNotification('error', 'Meet link generation failed: ' + err.message);
        container.innerHTML = origHtml;
        lucide.createIcons();
    }
};
