/**
 * @fileoverview Safe DOM injector for the LinkPilot AI button.
 */

(() => {
    window.LinkPilotInjector = {
        /**
         * Injects the AI button into a valid post container.
         * @param {HTMLElement} postElement
         * @param {Object} postData
         */
        injectButton: (postElement, postData) => {
            if (!postElement) return;

            const barSelectors = [
                '.feed-shared-social-action-bar',
                '.social-details-social-actions',
                '.social-actions-button-bar',
                '.social-actions',
                '.social-action-bar',
                'ul[class*="social-actions"]',
                'ul[class*="social-action-bar"]',
                'div[class*="social-actions"]',
                'div[class*="social-action-bar"]',
                'div[class*="social-actions-button-bar"]',
                'div[class*="social-details-social-actions"]'
            ];
            
            let bar = null;
            for (const selector of barSelectors) {
                const el = postElement.querySelector(selector);
                if (el && window.isMainActionBar(el)) {
                    bar = el;
                    break;
                }
            }

            // Fallback: search for any div/ul container that matches isMainActionBar
            if (!bar) {
                const containers = postElement.querySelectorAll('div, ul');
                for (const el of containers) {
                    if (window.isMainActionBar(el)) {
                        bar = el;
                        break;
                    }
                }
            }

            if (!bar) {
                window.LinkPilotLogger.warn('Could not locate valid social action bar for post:', postData.postId);
                return;
            }

            // Prevent duplicate injections
            if (bar.querySelector('.linkpilot-btn')) {
                return;
            }

            const iconUrl = chrome.runtime.getURL('gemini-color.png');

            // Programmatically construct the button elements securely (XSS-safe)
            const icon = window.LinkPilotUtils.safeCreate('img', {
                src: iconUrl,
                alt: 'AI',
                style: 'width: 16px; height: 16px; object-fit: contain; display: inline-block;'
            });

            const textSpan = window.LinkPilotUtils.safeCreate('span', {
                style: 'font-size: 13px; font-weight: 600;'
            }, ['AI']);

            const button = window.LinkPilotUtils.safeCreate('button', {
                class: 'linkpilot-btn artdeco-button artdeco-button--muted artdeco-button--4 artdeco-button--tertiary',
                type: 'button',
                style: {
                    color: '#14B8A6',
                    fontWeight: 'bold',
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                    transition: 'background-color 0.2s',
                    marginLeft: '8px',
                    marginRight: '8px',
                    border: '1px solid rgba(140, 140, 140, 0.4)',
                    borderRadius: '4px',
                    padding: '4px 8px',
                    cursor: 'pointer'
                },
                onclick: (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    window.LinkPilotLogger.debug('AI action button clicked for cached post:', postData.postId);
                    
                    // Update global active element tracking for comment pasting
                    window.activePostElement = postData.domReference;
                    
                    // Directly call openActionModal with cached postData (no DOM querying)
                    window.openActionModal(postData);
                }
            }, [icon, textSpan]);

            // Check if we need to wrap the button to match sibling tag structures (e.g. li or span wrappers)
            let wrapper = null;
            const sister = bar.firstElementChild;
            if (sister && (sister.tagName === 'LI' || sister.tagName === 'SPAN' || sister.tagName === 'DIV')) {
                wrapper = window.LinkPilotUtils.safeCreate(sister.tagName, {
                    class: sister.className,
                    style: {
                        display: 'inline-flex',
                        alignItems: 'center'
                    }
                }, [button]);
            }

            // Append to the end of the action bar
            if (wrapper) {
                bar.appendChild(wrapper);
            } else {
                bar.appendChild(button);
            }

            window.LinkPilotLogger.debug('Successfully injected AI action button for post:', postData.postId);
        },

        /**
         * Injects the Find Email button next to LinkedIn profile action controls.
         */
        injectProfileButton: () => {
            const selectors = [
                'div.pvs-profile-actions',
                '.pv-top-card-v2-ctas',
                'div.ph5.pb5 div.display-flex.mt4',
                'main.scaffold-layout__main section.artdeco-card div.display-flex.justify-flex-start',
                '[class*="profile-actions"]'
            ];

            let container = null;
            for (const selector of selectors) {
                const el = document.querySelector(selector);
                if (el) {
                    container = el;
                    break;
                }
            }

            if (!container) return;

            // Prevent duplicate injections
            if (container.querySelector('.linkpilot-find-email-btn')) {
                return;
            }

            const button = window.LinkPilotUtils.safeCreate('button', {
                class: 'linkpilot-find-email-btn artdeco-button artdeco-button--2 artdeco-button--primary ml2',
                type: 'button',
                style: {
                    backgroundColor: '#14B8A6',
                    color: '#0F172A',
                    fontWeight: 'bold',
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                    borderRadius: '16px',
                    padding: '6px 16px',
                    cursor: 'pointer',
                    border: 'none',
                    marginLeft: '8px'
                },
                onclick: (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const profileData = window.LinkPilotParser.parseProfile();
                    window.openFinderModal(profileData);
                }
            }, ['🔍 Find Email']);

            // Insert into the action panel
            container.appendChild(button);
            window.LinkPilotLogger.debug('Successfully injected Find Email button to profile actions.');
        }
    };
})();
