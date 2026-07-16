// tracker.js - LinkPilot Enterprise Analytics, Performance & Tracking Engine
(function() {
    // 1. Generate or retrieve session token
    let sessionToken = localStorage.getItem('linkpilot_session_token');
    if (!sessionToken) {
        sessionToken = 'sess_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('linkpilot_session_token', sessionToken);
    }

    const isDashboard = window.location.pathname.includes('/dashboard/');
    const apiPath = isDashboard ? '../backend/api/analytics/track.php' : 'backend/api/analytics/track.php';

    // Configure whether to write telemetry to local Database or offload to Google Tag Manager / GA4
    const OFFLOAD_TO_GTM = true; // Set to true to disable local database writes and use GTM/GA4 dataLayer instead

    // Helper to send payloads to tracking API
    async function sendTrackingPayload(type, payload) {
        if (OFFLOAD_TO_GTM) {
            // Push to GTM/GA4 dataLayer
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'linkpilot_telemetry',
                telemetry_type: type,
                session_token: sessionToken,
                payload: payload
            });
            return;
        }

        try {
            const token = localStorage.getItem('linkpilot_token');
            const headers = { 'Content-Type': 'application/json' };
            if (token) headers['Authorization'] = 'Bearer ' + token;

            const body = {
                session_token: sessionToken,
                type: type,
                payload: payload
            };

            fetch(apiPath, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(body),
                keepalive: true
            });
        } catch (e) {
            // Fail silently to keep application running smoothly
        }
    }

    // Expose public emitter API
    window.LinkPilotTracker = {
        trackEvent: function(eventName, eventCategory = 'general', eventAction = null, metadata = {}) {
            if (OFFLOAD_TO_GTM) {
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    event: eventName,
                    eventCategory: eventCategory,
                    eventAction: eventAction,
                    page_url: window.location.href,
                    page_title: document.title,
                    referrer: document.referrer || null,
                    metadata: metadata
                });
                return;
            }

            sendTrackingPayload('event', {
                event_name: eventName,
                event_category: eventCategory,
                event_action: eventAction,
                page_url: window.location.href,
                page_title: document.title,
                referrer: document.referrer || null,
                metadata: metadata
            });
        }
    };

    // 2. Initialize Session and capture Device Footprints
    function initSession() {
        // Detect OS, OS version, Browser and Browser version
        const ua = navigator.userAgent;
        let browserName = "Unknown Browser";
        let browserVersion = "Unknown";
        let osName = "Unknown OS";
        let osVersion = "Unknown";
        let deviceType = "Desktop";

        // Browser Detection
        if (ua.includes("Chrome") && !ua.includes("Edg")) {
            browserName = "Chrome";
            const match = ua.match(/Chrome\/([0-9\.]+)/);
            if (match) browserVersion = match[1];
        } else if (ua.includes("Firefox")) {
            browserName = "Firefox";
            const match = ua.match(/Firefox\/([0-9\.]+)/);
            if (match) browserVersion = match[1];
        } else if (ua.includes("Safari") && !ua.includes("Chrome")) {
            browserName = "Safari";
            const match = ua.match(/Version\/([0-9\.]+)/);
            if (match) browserVersion = match[1];
        } else if (ua.includes("Edg")) {
            browserName = "Microsoft Edge";
            const match = ua.match(/Edg\/([0-9\.]+)/);
            if (match) browserVersion = match[1];
        }

        // OS Detection
        if (ua.includes("Windows")) {
            osName = "Windows";
            const match = ua.match(/Windows NT ([0-9\.]+)/);
            if (match) osVersion = match[1];
        } else if (ua.includes("Macintosh")) {
            osName = "Mac OS X";
            const match = ua.match(/Mac OS X ([0-9_]+)/);
            if (match) osVersion = match[1].replace(/_/g, '.');
        } else if (ua.includes("Android")) {
            osName = "Android";
            deviceType = "Mobile";
        } else if (ua.includes("iPhone") || ua.includes("iPad")) {
            osName = "iOS";
            deviceType = "Mobile";
        }

        if (/mobile|tablet|phone/i.test(ua)) {
            deviceType = "Mobile";
        }

        const devicePayload = {
            device: {
                brand: navigator.vendor || 'Unknown Brand',
                model: navigator.platform || 'Unknown Model',
                screen_width: window.screen.width,
                screen_height: window.screen.height,
                viewport_width: window.innerWidth,
                viewport_height: window.innerHeight,
                pixel_ratio: window.devicePixelRatio || 1,
                touch_supported: ('ontouchstart' in window || navigator.maxTouchPoints > 0) ? 1 : 0,
                dark_mode_enabled: (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 1 : 0
            },
            browser: {
                name: browserName,
                version: browserVersion,
                os: osName,
                os_version: osVersion,
                device_type: deviceType
            }
        };

        sendTrackingPayload('session_init', devicePayload);
    }

    // 3. Track active/idle time duration
    let activeTime = 0;
    let idleTime = 0;
    let lastActiveEvent = Date.now();

    function resetActivityTimer() {
        lastActiveEvent = Date.now();
    }

    // Register activity events
    ['mousemove', 'keydown', 'scroll', 'click'].forEach(evt => {
        window.addEventListener(evt, resetActivityTimer, { passive: true });
    });

    // Run interval pings every second
    setInterval(() => {
        // If user made movements in last 10 seconds, count as active
        if (Date.now() - lastActiveEvent < 10000) {
            activeTime++;
        } else {
            idleTime++;
        }
    }, 1000);

    // Send session durations logs every 15 seconds
    setInterval(() => {
        if (activeTime > 0 || idleTime > 0) {
            sendTrackingPayload('session_ping', {
                active_duration_increment: activeTime,
                idle_duration_increment: idleTime
            });
            activeTime = 0;
            idleTime = 0;
        }
    }, 15000);

    // 4. Rage Click & Dead Click Detection
    let clickHistory = [];
    document.addEventListener('click', (e) => {
        const target = e.target;
        const now = Date.now();

        // 4.1 Check Rage Clicks: 3+ clicks on same element in 1.5 seconds
        clickHistory = clickHistory.filter(c => now - c.time < 1500);
        clickHistory.push({ time: now, element: target });

        const matches = clickHistory.filter(c => c.element === target);
        if (matches.length >= 3) {
            window.LinkPilotTracker.trackEvent('rage_clicks', 'mouse', 'rage_click', {
                element_tag: target.tagName,
                element_id: target.id || null,
                element_text: (target.innerText || '').substring(0, 50)
            });
            clickHistory = []; // Reset history after trigger
            return;
        }

        // 4.2 Check Dead Clicks: clicks on static non-interactive elements
        const interactive = target.closest('button, a, input, select, textarea, [role="button"], [onclick]');
        if (!interactive) {
            // Check if element has cursor pointer or hover styles (heuristic)
            const style = window.getComputedStyle(target);
            if (style.cursor !== 'pointer') {
                window.LinkPilotTracker.trackEvent('dead_clicks', 'mouse', 'dead_click', {
                    element_tag: target.tagName,
                    element_id: target.id || null,
                    page_y: e.pageY,
                    page_x: e.pageX
                });
            }
        }
    });

    // 5. Scroll Depth Tracking
    let maxScroll = 0;
    window.addEventListener('scroll', () => {
        const docH = document.documentElement.scrollHeight - window.innerHeight;
        if (docH <= 0) return;
        const scrollPct = Math.round((window.scrollY / docH) * 100);
        if (scrollPct > maxScroll) {
            maxScroll = scrollPct;
        }
    }, { passive: true });

    // Send final scroll depth on page close
    window.addEventListener('beforeunload', () => {
        if (maxScroll > 10) {
            window.LinkPilotTracker.trackEvent('scroll_depth', 'navigation', 'scrolled', {
                max_scroll_percent: maxScroll
            });
        }
    });

    // 6. Keyboard Shortcut Listeners
    window.addEventListener('keydown', (e) => {
        const isModifier = e.ctrlKey || e.metaKey;
        if (isModifier) {
            let key = e.key.toLowerCase();
            const trackedKeys = ['c', 'v', 'f', 'z', 'y', 'p', 'k'];
            if (trackedKeys.includes(key)) {
                window.LinkPilotTracker.trackEvent('shortcut_used', 'keyboard', 'shortcut', {
                    key_combination: (e.ctrlKey ? 'Ctrl+' : 'Cmd+') + key.toUpperCase()
                });
            }
        }
    });

    // 7. Performance Loading timers
    window.addEventListener('load', () => {
        setTimeout(() => {
            if (window.performance && window.performance.timing) {
                const t = window.performance.timing;
                const loadTime = t.loadEventEnd - t.navigationStart;
                if (loadTime > 0) {
                    sendTrackingPayload('performance', {
                        page_load_ms: loadTime,
                        api_latency_ms: t.responseEnd - t.requestStart,
                        db_time_ms: t.responseStart - t.requestStart
                    });
                }
            }
        }, 100);
    });

    // 8. Capture Global JavaScript Exceptions (Sentry-like)
    window.addEventListener('error', (e) => {
        sendTrackingPayload('error', {
            error_type: 'JavaScript Error',
            error_code: e.message || 'Uncaught Exception',
            page_url: window.location.href,
            stack_trace: e.error ? e.error.stack : (e.filename + ' Line: ' + e.lineno + ' Col: ' + e.colno)
        });
    });

    // Capture Unhandled Promise Rejections
    window.addEventListener('unhandledrejection', (e) => {
        sendTrackingPayload('error', {
            error_type: 'Promise Rejection',
            error_code: 'Unhandled Rejection',
            page_url: window.location.href,
            stack_trace: e.reason ? (e.reason.stack || String(e.reason)) : 'Promise rejected without reason'
        });
    });

    // Start Tracker
    if (document.readyState === 'complete') {
        initSession();
    } else {
        window.addEventListener('load', initSession);
    }
})();
