// extension/popup.js

const DASHBOARD_URL = 'https://linkpilot.work/dashboard';

document.addEventListener('DOMContentLoaded', () => {
    checkSession();
    
    // Set Version Dynamically
    try {
        const manifestVersion = chrome.runtime.getManifest().version;
        document.getElementById('extension-version').textContent = 'v' + manifestVersion;
    } catch (e) {
        console.warn('Could not read manifest version:', e);
    }

    // Toggle Password Visibility
    const togglePw = document.getElementById('toggle-pw-visibility');
    const passwordInput = document.getElementById('password');
    if (togglePw && passwordInput) {
        togglePw.addEventListener('click', () => {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                togglePw.style.color = '#10B981';
            } else {
                passwordInput.type = 'password';
                togglePw.style.color = '#94A3B8';
            }
        });
    }

    // Close Login button
    const closeBtn = document.getElementById('close-login-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            window.close();
        });
    }

    // Dark Mode switch dummy tracking
    const dmToggle = document.getElementById('dark-mode-toggle');
    if (dmToggle) {
        chrome.storage.local.get(['dark_mode'], (data) => {
            dmToggle.checked = data.dark_mode !== false;
        });
        dmToggle.addEventListener('change', () => {
            chrome.storage.local.set({ dark_mode: dmToggle.checked });
        });
    }

    // Login Action
    document.getElementById('login-btn').addEventListener('click', () => {
        const email = document.getElementById('email').value;
        const password = passwordInput.value;
        const errorDiv = document.getElementById('login-error');
        
        if (!email || !password) {
            errorDiv.textContent = 'Please fill out all credentials.';
            return;
        }
        
        errorDiv.textContent = '';
        
        chrome.runtime.sendMessage({ action: 'login', email, password }, (res) => {
            if (res && res.status === 'success') {
                showDashboardView(res.user);
            } else {
                errorDiv.textContent = (res && res.message) ? res.message : 'Invalid credentials or backend offline.';
            }
        });
    });

    // Logout Action
    document.getElementById('logout-btn').addEventListener('click', () => {
        chrome.runtime.sendMessage({ action: 'logout' }, (res) => {
            if (res && res.status === 'success') {
                showLoginView();
            }
        });
    });

    // Navigation Mapping Rows
    const navs = [
        { id: 'open-dashboard-btn', path: '/index.html#/dashboard' },
        { id: 'open-credits-btn', path: '/index.html#/dashboard' },
        { id: 'open-smtp-btn', path: '/index.html#/smtp' },
        { id: 'open-integrations-btn', path: '/index.html#/integrations' },
        { id: 'open-leads-btn', path: '/index.html#/leads' },
        { id: 'open-settings-btn', path: '/index.html#/settings' },
        { id: 'open-templates-btn', path: '/index.html#/templates' },
        { id: 'open-help-btn', path: '/index.html#/settings', external: 'https://linkpilot.work/support' },
        { id: 'manage-plan-btn', path: '/index.html#/settings' }
    ];

    navs.forEach(nav => {
        const el = document.getElementById(nav.id);
        if (el) {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                const targetUrl = nav.external || `${DASHBOARD_URL}${nav.path}`;
                chrome.tabs.create({ url: targetUrl });
            });
        }
    });
});

function checkSession() {
    chrome.runtime.sendMessage({ action: 'getSession' }, (res) => {
        if (res && res.loggedIn) {
            showDashboardView(res.user);
        } else {
            showLoginView();
        }
    });
}

function showDashboardView(user) {
    document.getElementById('login-view').classList.add('hidden');
    document.getElementById('dashboard-view').classList.remove('hidden');
    document.getElementById('user-name').textContent = user.name || 'User';
}

function showLoginView() {
    document.getElementById('dashboard-view').classList.add('hidden');
    document.getElementById('login-view').classList.remove('hidden');
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('login-error').textContent = '';
}
