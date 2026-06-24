// extension/background.js

const API_BASE_URL = 'https://linkpilot.work/backend/api';

// Helper to make API calls with stored token
async function apiFetch(endpoint, method = 'GET', body = null) {
    const storage = await chrome.storage.local.get(['linkpilot_token']);
    const token = storage.linkpilot_token;
    
    const headers = {
        'Accept': 'application/json'
    };
    
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }
    
    const config = {
        method,
        headers
    };
    
    if (body) {
        headers['Content-Type'] = 'application/json';
        config.body = JSON.stringify(body);
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/${endpoint}`, config);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || `API request failed with status ${response.status}`);
        }
        return data;
    } catch (err) {
        console.error(`Background API error (${endpoint}):`, err);
        return { status: 'error', message: err.message };
    }
}

// Listen for messages from popup or content script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    
    if (message.action === 'login') {
        fetch(`${API_BASE_URL}/auth/login.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ email: message.email, password: message.password })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                chrome.storage.local.set({ 
                    linkpilot_token: data.token,
                    linkpilot_user: data.user
                }, () => {
                    sendResponse({ status: 'success', user: data.user });
                });
            } else {
                sendResponse({ status: 'error', message: data.message });
            }
        })
        .catch(err => sendResponse({ status: 'error', message: err.message }));
        return true; // Keep message channel open for async response
    }
    
    if (message.action === 'logout') {
        chrome.storage.local.remove(['linkpilot_token', 'linkpilot_user'], () => {
            sendResponse({ status: 'success' });
        });
        return true;
    }
    
    if (message.action === 'getSession') {
        chrome.storage.local.get(['linkpilot_token', 'linkpilot_user'], (data) => {
            sendResponse({ 
                loggedIn: !!data.linkpilot_token, 
                user: data.linkpilot_user || null 
            });
        });
        return true;
    }
    
    // API Proxies
    if (message.action === 'generateEmail') {
        apiFetch('generate/email.php', 'POST', message.payload)
            .then(data => sendResponse(data));
        return true;
    }
    
    if (message.action === 'generateWhatsApp') {
        apiFetch('generate/whatsapp.php', 'POST', message.payload)
            .then(data => sendResponse(data));
        return true;
    }
    
    if (message.action === 'generateComment') {
        apiFetch('generate/comment.php', 'POST', message.payload)
            .then(data => sendResponse(data));
        return true;
    }
    
    if (message.action === 'sendEmail') {
        apiFetch('generate/send_email.php', 'POST', message.payload)
            .then(data => sendResponse(data));
        return true;
    }
    
    if (message.action === 'trackAction') {
        apiFetch('generate/track_action.php', 'POST', message.payload)
            .then(data => sendResponse(data));
        return true;
    }
});
