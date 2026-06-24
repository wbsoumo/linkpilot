/**
 * @fileoverview Safe DOM and runtime utility functions for LinkPilot AI.
 */

(() => {
    window.LinkPilotUtils = {
        /**
         * Securely creates an HTML element to prevent XSS.
         * @param {string} tag
         * @param {Object} [attrs={}]
         * @param {Array<HTMLElement|string>} [children=[]]
         * @returns {HTMLElement}
         */
        safeCreate: (tag, attrs = {}, children = []) => {
            const el = document.createElement(tag);
            
            // Assign attributes safely
            Object.entries(attrs).forEach(([key, val]) => {
                if (key === 'style' && typeof val === 'object') {
                    Object.assign(el.style, val);
                } else if (key.startsWith('on') && typeof val === 'function') {
                    el.addEventListener(key.substring(2).toLowerCase(), val);
                } else {
                    el.setAttribute(key, val);
                }
            });

            // Append children safely
            children.forEach(child => {
                if (child instanceof HTMLElement) {
                    el.appendChild(child);
                } else if (typeof child === 'string') {
                    el.appendChild(document.createTextNode(child));
                }
            });

            return el;
        },

        /**
         * Debounces a function execution.
         * @param {Function} fn
         * @param {number} delay
         * @returns {Function}
         */
        debounce: (fn, delay) => {
            let timeoutId;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn(...args), delay);
            };
        },

        /**
         * Safely sends a runtime message checking for active errors.
         * @param {Object} payload
         * @param {Function} callback
         */
        safeSendMessage: (payload, callback) => {
            try {
                chrome.runtime.sendMessage(payload, (response) => {
                    const error = chrome.runtime.lastError;
                    if (error) {
                        window.LinkPilotLogger.error('Runtime message error:', error.message);
                        callback({ status: 'error', message: error.message });
                        return;
                    }
                    callback(response);
                });
            } catch (err) {
                window.LinkPilotLogger.error('Sync runtime message throw:', err);
                callback({ status: 'error', message: err.message });
            }
        },

        /**
         * Checks if an element is a main post social action bar.
         * @param {HTMLElement} el
         * @returns {boolean}
         */
        isMainActionBar: (el) => {
            if (!el || (el.tagName !== 'DIV' && el.tagName !== 'UL')) {
                return false;
            }
            
            // Ensure it is not inside comments, replies, or comment text boxes
            if (el.closest('[class*="comments-"], [class*="comment-"], [class*="reply-"], .comment-social-bar, .comments-shared-social-action-bar')) {
                return false;
            }
            
            // Check for presence of key post action buttons by walking children
            let hasLike = false;
            let hasComment = false;
            let hasSendOrRepost = false;
            
            const interactiveElements = el.querySelectorAll('button, a, [role="button"]');
            interactiveElements.forEach(item => {
                const label = (item.getAttribute('aria-label') || '').toLowerCase();
                const text = (item.textContent || item.innerText || '').toLowerCase().trim();
                const className = (item.className || '').toLowerCase();
                
                if (label.includes('like') || label.includes('react') || className.includes('react') || text === 'like') {
                    hasLike = true;
                }
                if (label.includes('comment') || className.includes('comment') || text === 'comment') {
                    hasComment = true;
                }
                if (label.includes('send') || label.includes('repost') || label.includes('share') || text === 'share' || text === 'repost' || text === 'send') {
                    hasSendOrRepost = true;
                }
            });
            
            let matchCount = 0;
            if (hasLike) matchCount++;
            if (hasComment) matchCount++;
            if (hasSendOrRepost) matchCount++;
            
            return matchCount >= 2;
        }
    };
    
    // Global alias exposure
    window.isMainActionBar = window.LinkPilotUtils.isMainActionBar;
})();
