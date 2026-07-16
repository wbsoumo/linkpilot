// tracker.js - Web Analytics & Click Tracking for LinkPilot AI
(function() {
    // Generate or retrieve session token
    let sessionToken = localStorage.getItem('linkpilot_session_token');
    if (!sessionToken) {
        sessionToken = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('linkpilot_session_token', sessionToken);
    }

    // Helper to send tracking data
    async function sendEvent(eventType, details = {}) {
        try {
            const token = localStorage.getItem('linkpilot_token');
            const headers = {
                'Content-Type': 'application/json'
            };
            if (token) {
                headers['Authorization'] = 'Bearer ' + token;
            }

            // Determine page URL & title
            const pageUrl = window.location.href;
            const pageTitle = document.title;

            const payload = {
                session_token: sessionToken,
                activity_type: eventType,
                page_url: pageUrl,
                page_title: pageTitle,
                referrer: document.referrer || null,
                ...details
            };

            // Determine relative path based on location
            const isDashboard = window.location.pathname.includes('/dashboard/');
            const apiPath = isDashboard ? '../backend/api/analytics/track.php' : 'backend/api/analytics/track.php';

            fetch(apiPath, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(payload),
                keepalive: true
            });
        } catch (e) {
            // Fail silently
        }
    }

    // Track Page View on load
    window.addEventListener('load', () => {
        sendEvent('page_view');
    });

    // Track click interactions on buttons, links, inputs, and custom interactive roles
    document.addEventListener('click', (e) => {
        const target = e.target.closest('button, a, input[type="submit"], [role="button"]');
        if (!target) return;

        // Get element details
        const elementTag = target.tagName.toLowerCase();
        const elementId = target.id || null;
        const elementClass = target.className || null;
        const elementText = (target.innerText || target.value || '').trim().substring(0, 100);

        sendEvent('click', {
            element_tag: elementTag,
            element_id: elementId,
            element_class: elementClass,
            element_text: elementText
        });
    });
})();
