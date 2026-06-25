// dashboard/assets/js/app.js

const API_BASE_URL = 'https://linkpilot.work/backend/api';

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
}

function removeAuthToken() {
    localStorage.removeItem('linkpilot_token');
    localStorage.removeItem('linkpilot_user');
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

// Populate user navigation and menus
function setupNavigation() {
    const user = getCurrentUser();
    if (!user) return;

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
    const navLinks = document.querySelectorAll('.sidebar-nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentFile) {
            link.classList.add('bg-teal-500', 'text-white');
            link.classList.remove('text-slate-300', 'hover:bg-slate-800');
        } else {
            link.classList.remove('bg-teal-500', 'text-white');
            link.classList.add('text-slate-300', 'hover:bg-slate-800');
        }
    });

    // Programmatically inject "Install Extension" button in navigation links bar
    if (navLinks.length > 0) {
        const navContainer = navLinks[0].parentNode;
        if (navContainer && !document.getElementById('download-ext-nav-btn')) {
            const extBtn = document.createElement('button');
            extBtn.id = 'download-ext-nav-btn';
            extBtn.className = 'ml-4 px-3 py-1.5 bg-teal-500/10 text-teal-400 border border-teal-500/20 rounded-lg text-xs font-semibold hover:bg-teal-500 hover:text-slate-950 transition duration-150';
            extBtn.innerHTML = '✨ Install Extension';
            extBtn.addEventListener('click', openExtensionGuide);
            navContainer.appendChild(extBtn);
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
    
    const downloadUrl = `${API_BASE_URL}/extension/download.php?token=${getAuthToken()}`;
    
    modal.innerHTML = `
        <div class="glass-panel p-6 max-w-lg w-full bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl relative max-h-[90vh] overflow-y-auto text-left">
            <button onclick="document.getElementById('ext-guide-modal').remove()" class="absolute top-4 right-4 text-slate-400 hover:text-white text-xl font-bold">&times;</button>
            
            <h2 class="text-xl font-extrabold text-teal-400 mb-2 flex items-center">
                <span>✨ Install Chrome Extension</span>
            </h2>
            <p class="text-xs text-slate-400 mb-6">Setup the LinkPilot AI extension to inject action buttons and automate your LinkedIn outreach.</p>
            
            <div class="space-y-4">
                <!-- Step 1 -->
                <div class="flex items-start space-x-3">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 font-bold text-xs flex items-center justify-center">1</span>
                    <div>
                        <h4 class="text-sm font-semibold text-white">Download the Extension Package</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Click the link below to download the latest extension files directly as a ZIP archive.</p>
                        <a href="${downloadUrl}" target="_blank" class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-teal-400 text-slate-950 text-xs font-bold rounded-lg mt-2 hover:bg-teal-300 transition">
                            <span>Download ZIP Package</span>
                        </a>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="flex items-start space-x-3">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 font-bold text-xs flex items-center justify-center">2</span>
                    <div>
                        <h4 class="text-sm font-semibold text-white">Extract the Folder</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Unzip the downloaded <code>linkpilot-chrome-extension.zip</code> file onto a dedicated folder on your computer.</p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="flex items-start space-x-3">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 font-bold text-xs flex items-center justify-center">3</span>
                    <div>
                        <h4 class="text-sm font-semibold text-white">Open Chrome Extensions Manager</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Open Google Chrome, navigate to <code>chrome://extensions/</code> in your URL bar, and press Enter.</p>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="flex items-start space-x-3">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 font-bold text-xs flex items-center justify-center">4</span>
                    <div>
                        <h4 class="text-sm font-semibold text-white">Enable Developer Mode</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Toggle the <strong>Developer mode</strong> switch in the top-right corner of the Extensions tab to active.</p>
                    </div>
                </div>

                <!-- Step 5 -->
                <div class="flex items-start space-x-3">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 font-bold text-xs flex items-center justify-center">5</span>
                    <div>
                        <h4 class="text-sm font-semibold text-white">Load Unpacked Extension</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Click the <strong>Load unpacked</strong> button in the top-left corner, and select the extracted extension folder.</p>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-slate-800 flex justify-end">
                <button onclick="document.getElementById('ext-guide-modal').remove()" class="px-4 py-2 bg-slate-800 border border-slate-700 hover:border-slate-600 rounded-lg text-xs font-semibold text-white transition">Close Guide</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
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
});
