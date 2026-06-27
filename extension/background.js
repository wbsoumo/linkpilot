// extension/background.js

const API_BASE_URL = 'https://linkpilot.work/backend/api';

// Helper to make API calls with stored token
async function apiFetch(endpoint, method = 'GET', body = null) {
    const storage = await chrome.storage.local.get(['linkpilot_token']);
    const token = storage.linkpilot_token;
    
    if (!token) {
        return { status: 'error', message: 'No authentication token found. Please log in.' };
    }
    
    const headers = {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`
    };
    
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
            if (response.status === 401) {
                // Token is invalid or expired - clear session state from storage
                chrome.storage.local.remove(['linkpilot_token', 'linkpilot_user']);
            }
            throw new Error(data.message || `API request failed with status ${response.status}`);
        }
        return data;
    } catch (err) {
        console.warn(`Background API call failed (${endpoint}):`, err.message);
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
    
    if (message.action === 'findEmail') {
        (async () => {
            const payload = message.payload || {};
            let companyUrn = payload.company_urn || '';
            const profileUrl = payload.linkedin_url || '';
            
            if (!companyUrn && profileUrl) {
                companyUrn = await fetchCompanyUrnFromProfile(profileUrl);
            }
            
            if (companyUrn) {
                const domain = await fetchCompanyDomain(companyUrn);
                if (domain) {
                    payload.domain = domain;
                }
            }
            
            const response = await apiFetch('extension/find_email.php', 'POST', payload);
            sendResponse(response);
        })();
        return true;
    }
    
    if (message.action === 'saveLead') {
        apiFetch('leads/index.php', 'POST', message.payload)
            .then(data => sendResponse(data));
        return true;
    }

    if (message.action === 'getCredits') {
        apiFetch('profile/get_credits.php', 'GET')
            .then(data => sendResponse(data));
        return true;
    }

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

/**
 * Fetch a profile page in the background and scrape its current company link URN.
 * @param {string} profileUrl
 * @returns {Promise<string>}
 */
async function fetchCompanyUrnFromProfile(profileUrl) {
    try {
        const res = await fetch(profileUrl);
        const html = await res.text();
        
        const match = html.match(/\/company\/([a-zA-Z0-9\-]+)/);
        const fsdMatch = html.match(/urn:li:fsd_company:(\d+)/);
        const compMatch = html.match(/urn:li:company:(\d+)/);

        await fetch('https://linkpilot.work/backend/api/debug_log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'fetchCompanyUrnFromProfile',
                profileUrl: profileUrl,
                match: match,
                fsdMatch: fsdMatch,
                compMatch: compMatch
            })
        }).catch(() => {});

        if (match && match[1]) {
            return match[1];
        }
        if (fsdMatch && fsdMatch[1]) {
            return fsdMatch[1];
        }
        if (compMatch && compMatch[1]) {
            return compMatch[1];
        }
    } catch (err) {
        console.warn('Failed to parse company URN from profile HTML:', err.message);
    }
    return '';
}

/**
 * Fetch a company's LinkedIn about page and resolve its website domain.
 * @param {string} companyUrn
 * @returns {Promise<string>}
 */
async function fetchCompanyDomain(companyUrn) {
    try {
        const url = `https://www.linkedin.com/company/${companyUrn}/about/`;
        const res = await fetch(url);
        const html = await res.text();
        
        let match = html.match(/"website":"(http[s]?:\/\/[^"]+)"/);
        let matchUrl = html.match(/"websiteUrl":"(http[s]?:\/\/[^"]+)"/);
        
        await fetch('https://linkpilot.work/backend/api/debug_log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'fetchCompanyDomain',
                companyUrn: companyUrn,
                match: match,
                matchUrl: matchUrl
            })
        }).catch(() => {});

        if (match && match[1]) {
            let cleanUrl = match[1].replace(/\\/g, '');
            return new URL(cleanUrl).hostname.replace('www.', '');
        }
        if (matchUrl && matchUrl[1]) {
            let cleanUrl = matchUrl[1].replace(/\\/g, '');
            return new URL(cleanUrl).hostname.replace('www.', '');
        }

        const redirRegex = /redir\/redirect\?url=([^&"]+)/g;
        let redirMatches = [...html.matchAll(redirRegex)];
        for (const m of redirMatches) {
            if (m[1]) {
                let decoded = decodeURIComponent(m[1]);
                if (!decoded.includes('linkedin.com')) {
                    return new URL(decoded).hostname.replace('www.', '');
                }
            }
        }

        const hrefRegex = /href="(http[s]?:\/\/(?!www\.linkedin\.com)[^"]+)"/g;
        let hrefMatches = [...html.matchAll(hrefRegex)];
        for (const m of hrefMatches) {
            if (m[1]) {
                const host = new URL(m[1]).hostname.replace('www.', '');
                if (host && !host.includes('linkedin.com')) {
                    return host;
                }
            }
        }
    } catch (err) {
        console.warn('Failed to resolve company website domain:', err.message);
    }
    return '';
}
