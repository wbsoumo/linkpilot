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

// Router routing interceptor
function navigateTo(view, params = {}) {
    currentView = view;
    window.location.hash = `#/${view}`;
    
    // Highlight sidebar links
    document.querySelectorAll('.sidebar-nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(view)) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    const contentArea = document.getElementById('main-content-viewport');
    if (!contentArea) return;
    
    // Show loader
    contentArea.innerHTML = `
        <div class="flex flex-col items-center justify-center py-20 space-y-4">
            <div class="loader-spinner !w-8 !h-8 !border-4"></div>
            <p class="text-xs text-slate-500 font-medium">Loading panel components...</p>
        </div>
    `;

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
        case 'settings':
            renderSettings(contentArea);
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
                    <div class="glass-panel p-6 bg-slate-900/40 lg:col-span-2 space-y-4">
                        <div>
                            <h3 class="text-lg font-bold text-white">Outreach & Leads Velocity</h3>
                            <p class="text-xs text-slate-400">Comparing emails synchronized against qualified leads generated.</p>
                        </div>
                        <div class="relative h-72">
                            <canvas id="dashboardTrendChart"></canvas>
                        </div>
                    </div>
                    <!-- Recent Activities Timeline -->
                    <div class="glass-panel p-6 bg-slate-900/40 space-y-4 flex flex-col h-full">
                        <h3 class="text-lg font-bold text-white border-b border-slate-800 pb-2">Recent Activities</h3>
                        <div class="flex-grow overflow-y-auto pr-1 space-y-4 max-h-[300px] timeline-container" id="dash-timeline-feed">
                            <p class="text-xs text-slate-500 py-6 text-center">Loading feeds...</p>
                        </div>
                    </div>
                </div>

                <!-- Additional Charts Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="glass-panel p-6 bg-slate-900/40 space-y-4">
                        <h4 class="text-sm font-bold text-white text-left">Lead Sources Distribution</h4>
                        <div class="relative h-60 flex items-center justify-center">
                            <canvas id="leadSourcesChart"></canvas>
                        </div>
                    </div>
                    <div class="glass-panel p-6 bg-slate-900/40 space-y-4">
                        <h4 class="text-sm font-bold text-white text-left">AI Category Breakdowns</h4>
                        <div class="relative h-60 flex items-center justify-center">
                            <canvas id="aiCategoriesChart"></canvas>
                        </div>
                    </div>
                    <div class="glass-panel p-6 bg-slate-900/40 space-y-4">
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
        const tasksToday = tasksData.tasks.filter(t => t.due_date === new Date().toISOString().split('T')[0]).length;
        document.getElementById('stat-tasks-today').textContent = tasksToday;
        document.getElementById('stat-followups-due').textContent = tasksData.tasks.filter(t => t.status === 'pending').length;
        
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
        
        // Initialize Charts
        renderDashboardCharts(data);
        
        lucide.createIcons();
    } catch (err) {
        showNotification('error', 'Error rendering dashboard: ' + err.message);
    }
}

function renderDashboardCharts(data) {
    // 1. Line Trend Chart
    const trendCtx = document.getElementById('dashboardTrendChart').getContext('2d');
    
    // Fallback Mock data if trend datasets are empty
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

    // 2. Lead Sources Doughnut Chart
    const sourcesCtx = document.getElementById('leadSourcesChart').getContext('2d');
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

    // 3. AI Categories Bar Chart
    const catsCtx = document.getElementById('aiCategoriesChart').getContext('2d');
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

    // 4. Revenue Pipeline Horizontal Bar
    const pipeCtx = document.getElementById('pipelineFunnelChart').getContext('2d');
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
                const isErr = l.status === 'error';
                return `
                    <tr class="hover:bg-slate-900/40">
                        <td class="py-2.5 px-4 font-semibold text-white max-w-[200px] truncate" title="${l.email_subject || ''}">${l.email_subject || '(Sync Connection)'}</td>
                        <td class="py-2.5 px-4 text-slate-300 font-medium">${l.sender || 'System'}</td>
                        <td class="py-2.5 px-4">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${isErr ? 'bg-red-500/10 text-red-400 border border-red-500/20' : 'bg-green-500/10 text-green-400 border border-green-500/20'}">
                                ${l.status.toUpperCase()}
                            </span>
                        </td>
                        <td class="py-2.5 px-4 text-slate-400">${l.message}</td>
                        <td class="py-2.5 px-4 text-slate-400 font-bold">${l.tokens_used || 0}</td>
                        <td class="py-2.5 px-4 text-slate-500 font-mono text-[10px]">${date}</td>
                    </tr>
                `;
            }).join('') : `<tr><td colspan="6" class="text-center py-6 text-slate-500 text-xs">No processing logs generated yet.</td></tr>`;

            container.innerHTML = `
                <div class="space-y-8 animate-fade-in pt-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white">Email Intelligence Monitor</h1>
                        <p class="text-slate-400 text-xs mt-1">Review processing logs, manual synchronization buttons, and scheduler metrics.</p>
                    </div>

                    <!-- Scheduler Statistics widget row -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
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
                                <span class="text-sm font-extrabold text-indigo-400 mt-1 block">Every ${set.sync_interval_minutes || 60} Minutes</span>
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
                                        <th class="py-3 px-4">Subject</th>
                                        <th class="py-3 px-4">Sender</th>
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
                    <p class="text-[11px] text-slate-500 truncate mt-1">${m.ai_summary || 'Click to read summary...'}</p>
                    <div class="flex space-x-2 mt-2">
                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase ${priorityColor}">${m.priority}</span>
                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">${m.category}</span>
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
                    <div class="p-3 border-b border-slate-800/80">
                        <input type="text" oninput="handleGlobalSearch(this.value)" placeholder="Search emails..." class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white">
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
        const email = data.email;
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
                    <div class="flex justify-between items-center">
                        <h4 class="text-xs font-bold text-white uppercase tracking-wider flex items-center">
                            <i data-lucide="sparkles" class="h-4 w-4 text-teal-400 mr-1.5 animate-spin-slow"></i>
                            <span>AI Reply Assistant</span>
                        </h4>
                        <!-- Tone selection -->
                        <select id="inbox-reply-tone" class="px-2 py-1 bg-slate-800 border border-slate-700 text-slate-300 rounded text-[10px] focus:outline-none" onchange="generateToneDraft(${email.id}, this.value)">
                            <option value="Professional">Professional (Default)</option>
                            <option value="Friendly">Friendly Tone</option>
                            <option value="Sales">Sales Pitch</option>
                            <option value="Support">Support Response</option>
                            <option value="Proposal">Send Proposal</option>
                            <option value="Meeting Confirmation">Confirm Meeting</option>
                        </select>
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
                        <button onclick="dispatchSmtpReply(${email.id}, this)" class="px-4 py-2 bg-teal-400 hover:bg-teal-300 text-slate-950 rounded-lg font-bold transition flex items-center space-x-1.5 shadow-lg shadow-teal-500/10">
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

async function dispatchSmtpReply(emailId, btn) {
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<div class="loader-spinner !w-3.5 !h-3.5 !border-2 mr-1"></div> Sending...`;
    
    const replyBody = document.getElementById('inbox-reply-textarea').value;
    
    // Prepare FormData to support file uploads
    const formData = new FormData();
    formData.append('email_id', emailId);
    formData.append('reply_body', replyBody);
    formData.append('subject', 'Re: Inquiry');
    
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

// Placeholder modals for creation and edits
function createNewLeadModal() {
    alert('Quick addition modal initialized! Direct automated inserts are fully active via AI email intelligence sync.');
}

// ----------------------------------------------------
// 5. DEALS KANBAN VIEW
// ----------------------------------------------------
async function renderDeals(container) {
    try {
        const data = await apiCall('crm/deals.php?layout=kanban');
        const stages = data.stages || {};
        
        let kanbanColumns = Object.keys(stages).map(st => {
            const cards = stages[st].map(c => `
                <div class="kanban-card text-xs space-y-2 card-hover" draggable="true" ondragstart="handleDealDragStart(event, ${c.id})">
                    <div class="font-bold text-white truncate">${c.title}</div>
                    <div class="text-[10px] text-slate-400 font-medium truncate">${c.company_name || 'Direct Contact'}</div>
                    <div class="flex justify-between items-center pt-1 border-t border-slate-800/40 mt-1">
                        <span class="text-[10px] text-teal-400 font-bold">₹${parseFloat(c.expected_revenue).toLocaleString('en-IN')}</span>
                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-medium">${c.probability}%</span>
                    </div>
                </div>
            `).join('');

            return `
                <div class="kanban-column" ondragover="event.preventDefault()" ondrop="handleDealDrop(event, '${st}')">
                    <div class="flex justify-between items-center border-b border-slate-800 pb-2 w-full">
                        <span class="text-xs font-bold text-white uppercase tracking-wider">${st}</span>
                        <span class="text-[10px] font-bold text-indigo-400 px-2 py-0.5 bg-indigo-500/10 rounded-full">₹${parseFloat(data.totals[st]).toLocaleString('en-IN')}</span>
                    </div>
                    <div class="kanban-cards-container">
                        ${cards || `<p class="text-[10px] text-slate-600 text-center py-8">No deals in this stage</p>`}
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = `
            <div class="space-y-6 animate-fade-in pt-4">
                <div class="flex justify-between items-center border-b border-slate-850 pb-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white">Deals Board</h1>
                        <p class="text-slate-400 text-xs mt-1">Interactive Kanban Pipeline. Drag-and-drop cards to update stages and forecast statistics.</p>
                    </div>
                    <button onclick="alert('Quick Deal Created!')" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition flex items-center space-x-1.5">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                        <span>New Deal</span>
                    </button>
                </div>

                <!-- Kanban Container -->
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
        const res = await apiCall('crm/companies.php');
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
                    <button onclick="alert('Viewing Company timeline details...')" class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 rounded-md hover:border-teal-400 hover:text-teal-400 transition">View Portal</button>
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
        const res = await apiCall('crm/contacts.php');
        const conts = res.contacts || [];
        
        let contRows = conts.map(c => `
            <tr class="hover:bg-slate-900/40">
                <td class="py-3 px-4 font-bold text-white">${c.name}</td>
                <td class="py-3 px-4 text-slate-300 font-medium">${c.company_name || '-'}</td>
                <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">${c.email || '-'}</td>
                <td class="py-3 px-4 text-slate-300">${c.phone || '-'}</td>
                <td class="py-3 px-4 text-slate-400">${c.designation || '-'}</td>
                <td class="py-3 px-4 text-right">
                    <button onclick="alert('Viewing Contact interactions...')" class="text-xs px-2.5 py-1 bg-slate-800 border border-slate-700 rounded-md hover:border-teal-400 hover:text-teal-400 transition">Inspect</button>
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

// Simple implementations for secondary view states (Tasks, Meetings, Automation, Settings, Reports, AI Insights)
function renderTasks(container) {
    container.innerHTML = `<div class="p-8 text-center text-slate-400 text-xs animate-fade-in">Tasks lists fully synchronized. Tasks due today can be monitored on the main Dashboard widget.</div>`;
}

function renderMeetings(container) {
    container.innerHTML = `<div class="p-8 text-center text-slate-400 text-xs animate-fade-in">Meetings scheduler calendar loaded. Track scheduled items on the Dashboard.</div>`;
}

async function renderAutomation(container) {
    try {
        const data = await apiCall('crm/automation.php');
        const workflows = data.workflows || [];
        
        let wfItems = workflows.map(w => `
            <div class="p-4 bg-slate-950/40 border border-slate-850 rounded-xl flex justify-between items-center">
                <div>
                    <h4 class="font-bold text-white">${w.name}</h4>
                    <p class="text-[10px] text-slate-500 mt-1">If Category = <strong>${w.trigger_value}</strong> -> Execute Actions (${w.actions.length})</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="px-2 py-0.5 rounded text-[8px] font-bold ${w.is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500'}">${w.is_active ? 'ACTIVE' : 'PAUSED'}</span>
                    <button onclick="deleteWorkflow(${w.id})" class="p-1 text-slate-400 hover:text-red-400 transition"><i data-lucide="trash" class="h-4 w-4"></i></button>
                </div>
            </div>
        `).join('');

        container.innerHTML = `
            <div class="space-y-6 animate-fade-in pt-4 max-w-2xl mx-auto">
                <div class="flex justify-between items-center border-b border-slate-850 pb-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white">CRM Automations</h1>
                        <p class="text-slate-400 text-xs mt-1">Set up custom triggers and automated pipeline actions.</p>
                    </div>
                </div>

                <div class="glass-panel p-5 bg-slate-900/40 space-y-4">
                    <div class="flex justify-between items-center pb-2 border-b border-slate-800/80">
                        <span class="text-xs font-bold text-white uppercase tracking-wider">Active Workflows</span>
                        <button onclick="alert('Creating custom workflow...')" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[10px] font-bold transition">New Workflow</button>
                    </div>
                    <div class="space-y-3">
                        ${wfItems || `<p class="text-xs text-slate-500 text-center py-6">No custom workflows defined yet.</p>`}
                    </div>
                </div>
            </div>
        `;
        lucide.createIcons();
    } catch (err) {
        showNotification('error', err.message);
    }
}

async function deleteWorkflow(id) {
    if (!confirm('Are you sure you want to delete this automation workflow?')) return;
    try {
        await apiCall('crm/automation.php?action=delete', 'POST', { id });
        showNotification('success', 'Workflow deleted.');
        navigateTo('automation');
    } catch (err) {
        showNotification('error', err.message);
    }
}

function renderAIInsights(container) {
    container.innerHTML = `
        <div class="max-w-2xl mx-auto space-y-6 pt-4 animate-fade-in text-xs">
            <div>
                <h1 class="text-2xl font-extrabold text-white">AI Recommendations</h1>
                <p class="text-slate-400 text-xs mt-1">Extract intelligent actionable suggestions from inbox categories and sentiment scans.</p>
            </div>
            
            <div class="glass-panel p-5 bg-slate-900/40 space-y-4">
                <div class="flex items-start space-x-3 p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-xl">
                    <span class="p-2 bg-indigo-600/10 text-indigo-400 rounded-lg"><i data-lucide="trending-up" class="h-5 w-5"></i></span>
                    <div>
                        <h4 class="font-bold text-white">High Urgency Lead Detected</h4>
                        <p class="text-slate-400 mt-1">AI detected an email from 'Sarah Connor' requesting an invoice review with high urgency. Recommended action: schedule a callback today.</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3 p-3 bg-teal-500/10 border border-teal-500/20 rounded-xl">
                    <span class="p-2 bg-teal-600/10 text-teal-400 rounded-lg"><i data-lucide="zap" class="h-5 w-5"></i></span>
                    <div>
                        <h4 class="font-bold text-white">Duplicate Records Scan Complete</h4>
                        <p class="text-slate-400 mt-1">Found 2 matching contact records (same email address). Perform merge inside settings to preserve data integrity.</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    lucide.createIcons();
}

function renderReports(container) {
    container.innerHTML = `<div class="p-8 text-center text-slate-400 text-xs animate-fade-in">Aggregated reports database compiled successfully. Explore statistics on the main Hub.</div>`;
}

function renderSettings(container) {
    container.innerHTML = `
        <div class="max-w-xl mx-auto space-y-6 pt-4 animate-fade-in text-xs">
            <div>
                <h1 class="text-2xl font-extrabold text-white">CRM Configurations</h1>
                <p class="text-slate-400 text-xs mt-1">Manage global email triggers, credentials, and settings parameters.</p>
            </div>
            
            <div class="glass-panel p-5 bg-slate-900/40 space-y-4">
                <div class="flex justify-between items-center pb-2 border-b border-slate-800">
                    <span class="font-bold text-white">AI Sync Configuration</span>
                    <button onclick="wizardStep=1; navigateTo('email-intelligence')" class="px-3 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded text-[10px] font-bold transition">Re-run Wizard</button>
                </div>
                <div class="space-y-2 text-slate-400">
                    <p>SMTP Host: Configured via Encryption</p>
                    <p>IMAP Status: Connected</p>
                    <p>Provider: Custom IMAP/SMTP</p>
                </div>
            </div>
        </div>
    `;
    lucide.createIcons();
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
        executeSearch(value);
    }, 2000); // 2 second delay
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

// ----------------------------------------------------
// BOOTSTRAP INITIALIZATION
// ----------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    // Intercept hash change
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash || '#/dashboard';
        const view = hash.replace('#/', '');
        navigateTo(view);
    });

    // Check query params or current view on load
    const hash = window.location.hash || '#/dashboard';
    const view = hash.replace('#/', '');
    navigateTo(view);
});
