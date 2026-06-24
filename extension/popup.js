// extension/popup.js

const DASHBOARD_URL = 'https://linkpilot.work/dashboard';

document.addEventListener('DOMContentLoaded', () => {
    checkSession();
    
    // Login Button
    document.getElementById('login-btn').addEventListener('click', () => {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
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

    // Logout Button
    document.getElementById('logout-btn').addEventListener('click', () => {
        chrome.runtime.sendMessage({ action: 'logout' }, (res) => {
            if (res && res.status === 'success') {
                showLoginView();
            }
        });
    });

    // Navigation Links
    document.getElementById('open-dashboard-btn').addEventListener('click', (e) => {
        e.preventDefault();
        chrome.tabs.create({ url: `${DASHBOARD_URL}/index.html` });
    });

    document.getElementById('open-smtp-btn').addEventListener('click', (e) => {
        e.preventDefault();
        chrome.tabs.create({ url: `${DASHBOARD_URL}/smtp.html` });
    });

    document.getElementById('open-leads-btn').addEventListener('click', (e) => {
        e.preventDefault();
        chrome.tabs.create({ url: `${DASHBOARD_URL}/leads.html` });
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
    document.getElementById('user-name').textContent = user.name;
}

function showLoginView() {
    document.getElementById('dashboard-view').classList.add('hidden');
    document.getElementById('login-view').classList.remove('hidden');
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('login-error').textContent = '';
}
