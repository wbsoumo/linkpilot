/**
 * @fileoverview High-performance MutationObserver wrapper for feed posts.
 */

(() => {
    let mutationObserver = null;

    /**
     * Scrapes and injects newly added nodes.
     * @param {HTMLElement|Document} root
     */
    const processNodes = (root) => {
        // 1. Process feed updates
        const posts = window.LinkPilotParser.findFeedPosts(root);
        posts.forEach(postEl => {
            // Never rescan/re-inject existing cached posts
            if (window.LinkPilotStorage.hasPost(postEl)) {
                return;
            }

            // Immediately parse the post and store it in the WeakMap cache
            try {
                const postData = window.LinkPilotParser.parsePost(postEl);
                window.LinkPilotStorage.setPost(postEl, postData);
                
                // Inject the action button passing the pre-scraped data
                window.LinkPilotInjector.injectButton(postEl, postData);
            } catch (err) {
                window.LinkPilotLogger.error('Failed to parse or inject post:', err);
            }
        });

        // 2. Process profile actions
        if (window.location.pathname.includes('/in/')) {
            try {
                window.LinkPilotInjector.injectProfileButton();
            } catch (err) {
                window.LinkPilotLogger.error('Failed to inject profile button:', err);
            }
        }
    };

    // Debounced runner to bundle multiple rapid mutation batches
    const debouncedProcess = window.LinkPilotUtils.debounce(() => {
        if (!chrome.runtime || !chrome.runtime.id) {
            if (mutationObserver) {
                mutationObserver.disconnect();
                mutationObserver = null;
            }
            return;
        }
        window.LinkPilotLogger.debug('Running debounced DOM processing sweep...');
        processNodes(document.body);
    }, 150);

    window.LinkPilotObserver = {
        /**
         * Initializes the mutation observer.
         */
        start: () => {
            if (mutationObserver) {
                mutationObserver.disconnect();
            }

            window.LinkPilotLogger.info('Initializing optimized MutationObserver...');
            
            // Run once immediately on loaded content
            processNodes(document.body);

            mutationObserver = new MutationObserver((mutations) => {
                let shouldProcess = false;
                for (const mutation of mutations) {
                    // Only trigger if children are added
                    if (mutation.addedNodes.length > 0) {
                        shouldProcess = true;
                        break;
                    }
                }
                if (shouldProcess) {
                    debouncedProcess();
                }
            });

            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        /**
         * Stops the mutation observer to prevent memory leaks.
         */
        stop: () => {
            if (mutationObserver) {
                mutationObserver.disconnect();
                mutationObserver = null;
                window.LinkPilotLogger.info('MutationObserver disconnected.');
            }
        }
    };
})();
