// dashboard/assets/js/app.js

// Resolve API URL dynamically depending on hosting environment
const API_BASE_URL = window.location.origin + (window.location.pathname.includes('/LinkPilot%20AI/') ? '/LinkPilot%20AI/backend/api' : '/backend/api');

// central notification system
function showNotification(type, message) {
    const existing = document.getElementById('toast-container');
    if (existing) existing.remove();

    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'fixed bottom-5 right-5 z-50 animate-fade-in';

    const color = type === 'success' ? 'bg-teal-500' : type === 'warning' ? 'bg-amber-500' : 'bg-red-500';
    const icon = type === 'success' ? '✓' : type === 'warning' ? '⚠' : '✗';

    container.innerHTML = `
        <div class="${color} text-white px-5 py-3 rounded-lg shadow-lg flex items-center space-x-3 max-w-sm">
            <span class="font-bold text-lg">${icon}</span>
            <span class="text-sm font-medium">${message}</span>
        </div>
    `;

    document.body.appendChild(container);
    setTimeout(() => {
        container.style.opacity = '0';
        container.style.transition = 'opacity 0.5s ease';
        setTimeout(() => container.remove(), 500);
    }, 4000);
}

// Check Authentication Status
function getAuthToken() {
    return localStorage.getItem('linkpilot_token');
}

function setAuthToken(token) {
    localStorage.setItem('linkpilot_token', token);
    localStorage.removeItem('linkpilot_session_token');
}

function removeAuthToken() {
    localStorage.removeItem('linkpilot_token');
    localStorage.removeItem('linkpilot_user');
    localStorage.removeItem('linkpilot_session_token');
}

function getCurrentUser() {
    const userJson = localStorage.getItem('linkpilot_user');
    return userJson ? JSON.parse(userJson) : null;
}

function requireAuth() {
    const token = getAuthToken();
    const isAuthPage = window.location.pathname.includes('login.html') || window.location.pathname.includes('register.html');
    
    if (!token && !isAuthPage) {
        window.location.href = 'login.html';
    } else if (token && isAuthPage) {
        window.location.href = 'index.html';
    }
}

// Global fetch wrapper with JWT auth
async function apiCall(endpoint, method = 'GET', body = null, isUrlEncoded = false) {

    const token = getAuthToken();
    const headers = {
        'Accept': 'application/json'
    };

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    let requestBody = body;
    if (body && !isUrlEncoded) {
        headers['Content-Type'] = 'application/json';
        requestBody = JSON.stringify(body);
    }

    try {
        const response = await fetch(`${API_BASE_URL}/${endpoint}`, {
            method,
            headers,
            body: requestBody
        });

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Non-JSON output from backend:", text);
            throw new Error("Invalid backend server response.");
        }

        if (!response.ok) {
            if (response.status === 401) {
                removeAuthToken();
                const isAuthPage = window.location.pathname.includes('login.html') || window.location.pathname.includes('register.html');
                if (!isAuthPage) {
                    window.location.href = 'login.html';
                }
            }
            throw new Error(data.message || 'API request failed.');
        }

        return data;
    } catch (error) {
        console.error(`API Call failed (${endpoint}):`, error);
        throw error;
    }
}

// Log out user
function logout() {
    removeAuthToken();
    window.location.href = 'login.html';
}

// Dynamic unread count badge loader
function refreshUnreadBadgeCount() {
    const badge = document.getElementById('sidebar-conversations-unread-badge');
    const headerBadge = document.getElementById('header-inbox-badge');
    if ((!badge && !headerBadge) || !getAuthToken()) return;
    
    apiCall('crm/email_intelligence/emails.php?limit=1')
        .then(res => {
            if (res && res.status === 'success' && typeof res.unread_count !== 'undefined') {
                if (badge) {
                    badge.textContent = res.unread_count;
                    if (res.unread_count > 0) {
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                }
                if (headerBadge) {
                    headerBadge.textContent = res.unread_count;
                    if (res.unread_count > 0) {
                        headerBadge.classList.remove('hidden');
                    } else {
                        headerBadge.classList.add('hidden');
                    }
                }
            }
        })
        .catch(err => console.warn('Failed to load unread count:', err));
}

// Populate user navigation and menus
function setupNavigation() {
    const user = getCurrentUser();
    if (!user) return;
    
    refreshUnreadBadgeCount();

    // Set User Name in UI
    const userNameElements = document.querySelectorAll('.user-name-display');
    userNameElements.forEach(el => el.textContent = user.name);

    // Set User Email
    const userEmailElements = document.querySelectorAll('.user-email-display');
    userEmailElements.forEach(el => el.textContent = user.email);

    // Set User Avatar Initials
    const initials = user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    const avatar = document.getElementById('user-avatar-initials');
    if (avatar) avatar.textContent = initials;

    // Header Initials & Role representation
    const headerAvatar = document.getElementById('header-user-avatar-initials');
    if (headerAvatar) headerAvatar.textContent = initials;
    const roleDisplays = document.querySelectorAll('.user-role-display');
    roleDisplays.forEach(el => el.textContent = (user.role === 'admin' ? 'Super Admin' : 'User'));

    // Show Admin Link if user is an admin
    const adminLink = document.getElementById('admin-nav-link');
    if (adminLink) {
        if (user.role === 'admin') {
            adminLink.classList.remove('hidden');
        } else {
            adminLink.classList.add('hidden');
        }
    }

    // Set Active State on nav links based on current file name
    const currentFile = window.location.pathname.split('/').pop() || 'index.html';
    const isSPAPage = currentFile === 'index.html' || currentFile === 'admin.html' || currentFile === '';
    
    if (!isSPAPage) {
        const navLinks = document.querySelectorAll('.sidebar-nav-link');
        
        // Inject Email Finder and Recharge links programmatically if not present
        if (navLinks.length > 0) {
            const navContainer = navLinks[0].parentNode;
            if (navContainer && !document.getElementById('email-finder-nav-link')) {
                const finderLink = document.createElement('a');
                finderLink.id = 'email-finder-nav-link';
                finderLink.href = 'email_finder.html';
                finderLink.className = 'sidebar-nav-link px-4 py-2 rounded-lg text-sm font-semibold transition duration-150';
                finderLink.textContent = 'Email Finder';
                
                const rechargeLink = document.createElement('a');
                rechargeLink.id = 'recharge-nav-link';
                rechargeLink.href = 'index.html#/recharge';
                rechargeLink.className = 'sidebar-nav-link px-4 py-2 rounded-lg text-sm font-semibold transition duration-150';
                rechargeLink.textContent = 'Recharge';
    
                const adminLink = document.getElementById('admin-nav-link');
                if (adminLink) {
                    navContainer.insertBefore(finderLink, adminLink);
                    navContainer.insertBefore(rechargeLink, adminLink);
                } else {
                    navContainer.appendChild(finderLink);
                    navContainer.appendChild(rechargeLink);
                }
            }
        }
    
        // Apply active classes to all nav links (including programmatically added ones)
        const updatedNavLinks = document.querySelectorAll('.sidebar-nav-link');
        updatedNavLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentFile) {
                link.className = 'sidebar-nav-link px-4 py-2 rounded-lg text-sm font-semibold transition duration-150 bg-teal-500 text-white';
            } else {
                link.className = 'sidebar-nav-link px-4 py-2 rounded-lg text-sm font-semibold transition duration-150 text-slate-300 hover:bg-slate-800';
            }
        });
    }

    // Programmatically inject "Install Extension" button in navigation links bar
    const navLinksForExt = document.querySelectorAll('.sidebar-nav-link');
    if (navLinksForExt.length > 0) {
        const navContainer = navLinksForExt[0].parentNode;
        if (navContainer && !document.getElementById('download-ext-nav-btn')) {
            const extBtn = document.createElement('button');
            extBtn.id = 'download-ext-nav-btn';
            extBtn.className = 'ml-4 px-3 py-1.5 bg-teal-500/10 text-teal-400 border border-teal-500/20 rounded-lg text-xs font-semibold hover:bg-teal-500 hover:text-slate-950 transition duration-150';
            extBtn.innerHTML = '✨ Install Extension';
            extBtn.addEventListener('click', openExtensionGuide);
            navContainer.appendChild(extBtn);
        }
    }

    // Load Wallet balance if element exists (both pre-rendered in SPA shell and custom sub-pages)
    const balanceVal = document.getElementById('nav-credit-balance-value');
    if (balanceVal) {
        apiCall('profile/get_credits.php')
            .then(res => {
                if (res && res.status === 'success' && res.wallet) {
                    balanceVal.textContent = res.wallet.remaining;
                }
            })
            .catch(err => console.warn('Failed to load nav credits:', err));
    }

    // Load Auto-Reply status badge
    if (typeof loadHeaderAutoReplyStatus === 'function') {
        loadHeaderAutoReplyStatus();
    }

    // Header Profile Dropdown Trigger Binding
    const headerTrigger = document.getElementById('header-profile-trigger');
    const headerDropdown = document.getElementById('header-profile-dropdown');
    if (headerTrigger && headerDropdown && !headerTrigger.dataset.dropdownInitialized) {
        headerTrigger.dataset.dropdownInitialized = "true";
        headerTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            headerDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', () => {
            headerDropdown.classList.add('hidden');
        });

        headerDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // Global navigation helper for header menu options
    window.headerNavigateToSettings = function(tabName, editMode = false) {
        if (headerDropdown) {
            headerDropdown.classList.add('hidden');
        }
        window.activeSettingsTabOverride = tabName;
        if (editMode) {
            window.activeSettingsEditFocusOverride = true;
        }
        
        // If we are already on #/settings, switch directly
        if (window.location.hash === '#/settings') {
            window.switchSettingsTab(tabName, null);
            if (editMode) {
                setTimeout(() => {
                    const input = document.getElementById('profile-name-input');
                    if (input) {
                        input.focus();
                        input.select();
                    }
                }, 100);
            }
        } else {
            navigateTo('settings');
        }
    };

    // Add interactive profile dropdown to avatar initials bubble
    if (avatar && !avatar.dataset.dropdownInitialized) {
        avatar.dataset.dropdownInitialized = "true";
        avatar.classList.add('cursor-pointer', 'hover:ring-2', 'hover:ring-teal-400', 'transition-all');

        // Wrap avatar in relative container to anchor the dropdown menu
        const wrapper = document.createElement('div');
        wrapper.className = 'relative flex items-center';
        avatar.parentNode.insertBefore(wrapper, avatar);
        wrapper.appendChild(avatar);

        // Create dropdown menu element
        const dropdown = document.createElement('div');
        dropdown.id = 'user-profile-dropdown';
        dropdown.className = 'hidden absolute right-0 top-full mt-2 w-48 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50 animate-fade-in glass-panel-dark';
        dropdown.style.marginTop = '12px';

        dropdown.innerHTML = `
            <a href="profile.html" class="flex items-center space-x-2 px-4 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition duration-150 rounded-t-lg">
                <i data-lucide="user" class="h-4 w-4 text-teal-400"></i>
                <span>View Profile</span>
            </a>
            <a href="profile.html?edit=true" class="flex items-center space-x-2 px-4 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition duration-150">
                <i data-lucide="edit-3" class="h-4 w-4 text-teal-400"></i>
                <span>Edit Profile</span>
            </a>
            <a href="../pricing.html" class="flex items-center space-x-2 px-4 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition duration-150">
                <i data-lucide="zap" class="h-4 w-4 text-amber-400"></i>
                <span>Upgrade Plans</span>
            </a>
            <button id="nav-view-guidance-btn" class="w-full flex items-center space-x-2 px-4 py-2.5 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition duration-150 text-left border-t border-slate-800 mt-1">
                <i data-lucide="help-circle" class="h-4 w-4 text-teal-400"></i>
                <span>View Guidance</span>
            </button>
        `;
        wrapper.appendChild(dropdown);

        // Toggle dropdown on click
        avatar.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', () => {
            dropdown.classList.add('hidden');
        });

        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Hook up View Guidance button click
        const guidanceBtn = dropdown.querySelector('#nav-view-guidance-btn');
        if (guidanceBtn) {
            guidanceBtn.addEventListener('click', (e) => {
                e.preventDefault();
                dropdown.classList.add('hidden');
                openExtensionGuide();
            });
        }

        // Refresh icons inside dropdown
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }
}

// Open Step-by-Step Extension Installation Modal
function openExtensionGuide() {
    const existing = document.getElementById('ext-guide-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'ext-guide-modal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm animate-fade-in p-4';
    
    modal.innerHTML = `
        <div class="glass-panel p-6 max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl relative max-h-[90vh] overflow-y-auto text-left">
            <button onclick="document.getElementById('ext-guide-modal').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-xl font-bold">&times;</button>
            
            <h2 class="text-xl font-extrabold text-teal-400 mb-2 flex items-center">
                <span>✨ Install Chrome Extension</span>
            </h2>
            <p class="text-xs text-slate-400 mb-6">Install the official LinkPilot AI Chrome extension to inject action buttons and automate your LinkedIn outreach.</p>
            
            <div class="space-y-4">
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-850 flex flex-col items-center text-center">
                    <img src="assets/img/logo.png" class="h-16 w-16 mb-3 object-contain rounded-[22%] overflow-hidden" alt="LinkPilot Logo" onerror="this.onerror=null; this.src='../dashboard/assets/img/logo.png';">
                    <h3 class="text-sm font-semibold text-white">LinkPilot AI - LinkedIn Outreach</h3>
                    <p class="text-[11px] text-slate-400 mt-1 max-w-xs">Available on the official Chrome Web Store. Clean, secure, and receives automated updates.</p>
                    
                    <a href="https://chromewebstore.google.com/detail/gnemddfomigfkpidiakgcdpighonkjga?utm_source=item-share-cb" target="_blank" class="w-full inline-flex items-center justify-center space-x-2 px-4 py-2.5 bg-teal-400 text-slate-950 text-xs font-bold rounded-lg mt-4 hover:bg-teal-300 transition duration-150">
                        <i data-lucide="external-link" class="h-4 w-4"></i>
                        <span>Add to Chrome</span>
                    </a>
                </div>

                <div class="text-[11px] text-slate-400 space-y-2">
                    <p class="font-semibold text-slate-300">Why use the Extension?</p>
                    <ul class="list-disc pl-4 space-y-1 text-slate-400">
                        <li>Inject smart email, comment draft, and WhatsApp buttons on LinkedIn.</li>
                        <li>Context-aware writing powered by your custom AI configuration.</li>
                        <li>100% secure, standard extensions sandbox compliance.</li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-slate-800 flex justify-end">
                <button onclick="document.getElementById('ext-guide-modal').remove()" class="px-4 py-2 bg-slate-800 border border-slate-700 hover:border-slate-600 rounded-lg text-xs font-semibold text-white transition">Close Guide</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    if (window.lucide) {
        window.lucide.createIcons();
    }
}

// Toggle loading state on forms
function setFormLoading(formId, isLoading) {
    const form = document.getElementById(formId);
    if (!form) return;

    const submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) return;

    if (isLoading) {
        submitBtn.disabled = true;
        submitBtn.dataset.originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = `
            <div class="flex items-center justify-center space-x-2">
                <div class="loader-spinner"></div>
                <span>Processing...</span>
            </div>
        `;
    } else {
        submitBtn.disabled = false;
        if (submitBtn.dataset.originalText) {
            submitBtn.innerHTML = submitBtn.dataset.originalText;
        }
    }
}

// Document load events
document.addEventListener('DOMContentLoaded', () => {
    requireAuth();
    setupNavigation();
    
    // Check if redirected because SMTP needs setup
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('setup_smtp') === 'true') {
        showNotification('warning', 'Please configure your SMTP server first to access action pages.');
    }
    if (urlParams.get('google_connected') === 'true') {
        showNotification('success', 'Google Workspace Account connected successfully via Secure OAuth!');
    }
    if (urlParams.get('google_error')) {
        showNotification('error', decodeURIComponent(urlParams.get('google_error')));
    }
    
    // Refresh user profile details asynchronously to sync roles securely if token is present
    const token = getAuthToken();
    if (token) {
        apiCall('profile/get.php').then(data => {
            if (data.user) {
                const localUser = getCurrentUser();
                if (localUser) {
                    localUser.role = data.user.role;
                    localUser.name = data.user.name;
                    localUser.email = data.user.email;
                    localStorage.setItem('linkpilot_user', JSON.stringify(localUser));
                    
                    // Re-setup navigation to reflect correct role
                    const adminLink = document.getElementById('admin-nav-link');
                    if (adminLink) {
                        if (data.user.role === 'admin') {
                            adminLink.classList.remove('hidden');
                        } else {
                            adminLink.classList.add('hidden');
                        }
                    }
                }
            }
        }).catch(err => {
            console.error("Failed to sync user role from server:", err);
        });
    }
    
    // Register Logout button handler
    const logoutBtn = document.getElementById('logout-action-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            logout();
        });
    }

    // Keyboard shortcut Ctrl+/ / Cmd+/ or Ctrl+K / Cmd+K to focus search input
    document.addEventListener('keydown', (e) => {
        const isK = e.key.toLowerCase() === 'k';
        const isSlash = e.key === '/';
        
        if ((e.ctrlKey || e.metaKey) && (isK || isSlash)) {
            e.preventDefault();
            const searchInput = document.getElementById('global-search-input');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
    });
});

// Auto-Reply Global Status Checking & Modals
window.loadHeaderAutoReplyStatus = function() {
    const container = document.getElementById('header-autoreply-status-container');
    if (!container) return;

    apiCall('whatsapp/autoreply_status.php')
        .then(res => {
            if (res && res.status === 'success') {
                container.classList.remove('hidden');
                container.classList.add('flex');
                
                // Set click handler
                container.onclick = () => showAutoReplyStatusModal(res);
                
                if (res.live) {
                    container.className = "flex items-center justify-center space-x-1.5 h-8 w-8 md:w-auto md:h-auto md:px-3 md:py-1.5 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-full md:rounded-lg text-[10px] font-extrabold hover:bg-emerald-600 hover:text-white transition duration-150 cursor-pointer shadow-sm";
                    container.innerHTML = `
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                        <span class="hidden md:inline">Auto-Reply Live</span>
                    `;
                } else {
                    container.className = "flex items-center justify-center space-x-1.5 h-8 w-8 md:w-auto md:h-auto md:px-3 md:py-1.5 bg-rose-50 text-rose-600 border border-rose-100 rounded-full md:rounded-lg text-[10px] font-extrabold hover:bg-rose-600 hover:text-white transition duration-150 cursor-pointer shadow-sm";
                    container.innerHTML = `
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500 shrink-0"></span>
                        <span class="hidden md:inline">Auto-Reply Inactive</span>
                    `;
                }
            }
        })
        .catch(err => console.warn('Failed to load auto-reply status:', err));
};

window.showAutoReplyStatusModal = function(res) {
    const existing = document.getElementById('autoreply-status-modal');
    if (existing) existing.remove();
    
    let fixBtnHtml = '';
    let fixActionText = '';
    let fixActionFunction = '';
    
    if (!res.live && res.fix_action) {
        if (res.fix_action === 'enable_autopilot') {
            fixActionText = 'Enable AI Autopilot';
            fixActionFunction = "window.location.hash = '#/settings'; setTimeout(() => { if (typeof window.switchSettingsTab === 'function') window.switchSettingsTab('whatsapp', null); }, 100); closeAutoReplyModal();";
        } else if (res.fix_action === 'connect_whatsapp') {
            fixActionText = 'Connect WhatsApp Account';
            fixActionFunction = "window.location.hash = '#/settings'; setTimeout(() => { if (typeof window.switchSettingsTab === 'function') window.switchSettingsTab('whatsapp', null); }, 100); closeAutoReplyModal();";
        } else if (res.fix_action === 'recharge_wallet') {
            fixActionText = 'Recharge Credit Balance';
            fixActionFunction = "window.location.hash = '#/settings'; setTimeout(() => { if (typeof window.switchSettingsTab === 'function') window.switchSettingsTab('billing', null); }, 100); closeAutoReplyModal();";
        } else if (res.fix_action === 'configure_keys') {
            fixActionText = 'Configure AI Keys';
            fixActionFunction = "window.location.hash = '#/settings'; setTimeout(() => { if (typeof window.switchSettingsTab === 'function') window.switchSettingsTab('profile', null); }, 100); closeAutoReplyModal();";
        }
        
        if (fixActionText) {
            fixBtnHtml = `
                <button onclick="${fixActionFunction}" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold transition flex items-center justify-center space-x-1.5 shadow-sm text-xs" style="color: #ffffff !important;">
                    <i data-lucide="wrench" class="h-4 w-4 text-white"></i>
                    <span>${fixActionText}</span>
                </button>
            `;
        }
    }
    
    const modalHtml = `
        <div id="autoreply-status-modal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300">
            <div class="bg-white rounded-3xl w-full max-w-sm p-6 shadow-2xl border border-slate-100 transform transition-all scale-100 duration-300 mx-4 flex flex-col space-y-4 animate-fade-in">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center space-x-2">
                        <div class="h-8 w-8 rounded-lg ${res.live ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-rose-50 text-rose-600 border border-rose-100'} flex items-center justify-center shadow-sm shrink-0">
                            <i data-lucide="${res.live ? 'sparkles' : 'alert-circle'}" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800">Auto-Reply Autopilot</h3>
                            <p class="text-[9px] text-slate-400">Real-time status check</p>
                        </div>
                    </div>
                    <button onclick="closeAutoReplyModal()" class="h-6 w-6 rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition border border-slate-100">
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
                
                <!-- Status & Reason -->
                <div class="space-y-2.5 text-center py-2">
                    <div class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold ${res.live ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-rose-50 text-rose-600 border border-rose-100'}">
                        <span class="h-1.5 w-1.5 rounded-full ${res.live ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'}"></span>
                        <span>${res.live ? 'LIVE & RUNNING' : 'SYSTEM INACTIVE'}</span>
                    </div>
                    <p class="text-slate-600 text-xs font-semibold px-2 leading-relaxed">${res.reason}</p>
                </div>
                
                <!-- Action Buttons -->
                <div class="space-y-2">
                    ${fixBtnHtml}
                    <button onclick="closeAutoReplyModal()" class="w-full py-2 bg-slate-50 hover:bg-slate-100 text-slate-655 rounded-xl font-bold border border-slate-200 transition text-xs">
                        Dismiss
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    lucide.createIcons();
    
    window.closeAutoReplyModal = function() {
        const modal = document.getElementById('autoreply-status-modal');
        if (modal) modal.remove();
    };
};

