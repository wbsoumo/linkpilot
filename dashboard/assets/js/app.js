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
                window.location.href = 'login.html';
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
    
    // Register Logout button handler
    const logoutBtn = document.getElementById('logout-action-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            logout();
        });
    }
});
