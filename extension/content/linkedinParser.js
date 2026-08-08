/**
 * @fileoverview Resilient scraping and parsing module for LinkedIn posts.
 */

(() => {
    /**
     * Checks if a string line represents metadata rather than actor info.
     * @param {string} line
     * @returns {boolean}
     */
    const isMetadataLine = (line) => {
        const lower = line.toLowerCase().trim();
        const isTime = !!lower.match(/^\d+[hmdyw]$/);
        const isConnection = /^(1st|2nd|3rd|\+)+$/i.test(lower) || ['1st', '2nd', '3rd'].some(degree => lower.includes(degree));
        const isOther = lower.includes('•') ||
                        lower.includes('edited') ||
                        lower === 'following' ||
                        lower === 'follow' ||
                        lower === 'commented' ||
                        lower === 'reposted' ||
                        lower === 'liked';
        return isTime || isConnection || isOther;
    };

    /**
     * Validates if a post element is an actual feed post and not inside comments,
     * sidebars, chat overlay bubbles, messaging panels, or ads.
     * @param {HTMLElement} el
     * @returns {boolean}
     */
    const isValidFeedPost = (el) => {
        if (!el) return false;
        
        const blacklistedAncestors = [
            '[class*="comment"]',
            '[class*="reply"]',
            '[class*="msg-"]',
            '.msg-overlay-bubble-header',
            '.msg-overlay-conversation-bubble',
            '.msg-thread',
            '.nt-card',
            '.artdeco-card--feed-ad',
            '.right-rail',
            '.scaffold-layout__aside'
        ];

        return !el.closest(blacklistedAncestors.join(', '));
    };

    /**
     * Determines if a DOM element should be excluded from main post parsing
     * (e.g. if it belongs to suggesting header, comment list, or actions block).
     * @param {HTMLElement} el
     * @param {HTMLElement} postRoot
     * @returns {boolean}
     */
    const isExcludedNode = (el, postRoot) => {
        // 1. Exclude if inside comment sections
        if (el.closest('[class*="commentList"], [class*="comment-item"], [class*="comments-Section"], [class*="commentsSection"], .comments-post-meta')) {
            return true;
        }

        // 2. Exclude if parent header has suggesting classes / text
        const suggesters = el.closest('[class*="suggest"], [class*="header--suggestion"]');
        if (suggesters) return true;

        // 3. Exclude top suggestion headers but preserve elements nested inside the actor/author card
        if (el.closest('.update-components-header') && !el.closest('.update-components-actor')) {
            return true;
        }
        if (el.closest('.feed-shared-update-v2__header') && !el.closest('.feed-shared-actor')) {
            return true;
        }

        // 4. Exclude all elements in the notification/activity header (which is separated by a <hr>)
        const hr = postRoot.querySelector('hr');
        if (hr && (el.compareDocumentPosition(hr) & Node.DOCUMENT_POSITION_FOLLOWING)) {
            // Check if any sibling before the <hr> contains action keywords (commented, reposted, likes, etc.)
            let sibling = hr.previousElementSibling;
            while (sibling) {
                const text = (sibling.innerText || '').toLowerCase();
                if (text.includes('commented') || 
                    text.includes('reposted') || 
                    text.includes('likes') || 
                    text.includes('reacted') || 
                    text.includes('shared') || 
                    text.includes('loves') || 
                    text.includes('celebrates') || 
                    text.includes('supports')) {
                    return true;
                }
                sibling = sibling.previousElementSibling;
            }
        }

        return false;
    };

    /**
     * Helper to find the main social action bar by walking up from a button/link.
     * @param {HTMLElement} btn
     * @returns {HTMLElement|null}
     */
    const findMainActionBar = (btn) => {
        let current = btn.parentElement;
        while (current && current !== document.body) {
            if (window.isMainActionBar(current)) {
                return current;
            }
            current = current.parentElement;
        }
        return null;
    };

    /**
     * Helper to find the main post container from the action bar.
     * @param {HTMLElement} bar
     * @returns {HTMLElement}
     */
    const findPostContainer = (bar) => {
        const closestPost = bar.closest('div[data-urn], [data-id], article, .feed-shared-update-v2, .occludable-update, [class*="feed-shared-update"], [class*="occludable-update"], [class*="update-v2"]');
        if (closestPost) {
            return closestPost;
        }
        
        let current = bar.parentElement;
        while (current && current !== document.body) {
            const hasActor = current.querySelector('.update-components-actor, .feed-shared-actor, [class*="actor"], a[href*="/in/"]');
            if (hasActor && (current.tagName === 'DIV' || current.tagName === 'ARTICLE')) {
                return current;
            }
            current = current.parentElement;
        }
        return bar.parentElement || bar;
    };

    window.LinkPilotParser = {
        /**
         * Find all valid feed posts inside a given root node.
         * @param {HTMLElement|Document} root
         * @returns {Array<HTMLElement>}
         */
        findFeedPosts: (root = document) => {
            const posts = new Set();
            
            // Gather elements that look like Comment/Like buttons
            const interactiveElements = root.querySelectorAll('button, [role="button"], a');
            interactiveElements.forEach(btn => {
                const label = (btn.getAttribute('aria-label') || '').toLowerCase();
                const text = (btn.textContent || btn.innerText || '').toLowerCase().trim();
                const className = (btn.className || '').toLowerCase();
                
                const isCommentOrLike = 
                    label.includes('comment') || 
                    label.includes('like') || 
                    label.includes('react') || 
                    className.includes('comment') || 
                    className.includes('react') || 
                    text === 'comment' || 
                    text === 'like';
                    
                if (isCommentOrLike) {
                    const bar = findMainActionBar(btn);
                    if (bar) {
                        const post = findPostContainer(bar);
                        if (post && isValidFeedPost(post)) {
                            posts.add(post);
                        }
                    }
                }
            });

            return Array.from(posts);
        },

        /**
         * Extract the author name of the post.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractAuthor: (postElement) => {
            for (const sel of window.LinkPilotSelectors.author) {
                const elements = postElement.querySelectorAll(sel);
                for (const el of elements) {
                    if (isExcludedNode(el, postElement)) continue;
                    const text = el.innerText.split('\n')[0].trim();
                    if (text && !isMetadataLine(text)) return text;
                }
            }

            const profileLinks = postElement.querySelectorAll(window.LinkPilotSelectors.links.profile);
            for (const profileLink of profileLinks) {
                if (isExcludedNode(profileLink, postElement)) continue;

                const ariaLabelEl = profileLink.querySelector('[aria-label]');
                if (ariaLabelEl) {
                    const label = ariaLabelEl.getAttribute('aria-label');
                    if (label) {
                        let cleanLabel = label.replace(/\s*(1st|2nd|3rd|following|follow|\+|•).*$/i, '').trim();
                        cleanLabel = cleanLabel
                            .replace(/^view\s+/i, '')
                            .replace(/’s\s+profile/i, '')
                            .replace(/'s\s+profile/i, '')
                            .replace(/photo\s+of\s+/i, '')
                            .trim();
                        if (cleanLabel) return cleanLabel;
                    }
                }

                const text = profileLink.innerText.trim();
                if (text && !isMetadataLine(text)) {
                    const firstLine = text.split('\n')[0].trim();
                    const cleanText = firstLine.replace(/\s*(•|1st|2nd|3rd|\+).*$/g, '').trim();
                    if (cleanText) return cleanText;
                }
            }

            const images = postElement.querySelectorAll('a[href*="/in/"] img');
            for (const img of images) {
                const link = img.closest('a');
                if (link && isExcludedNode(link, postElement)) continue;

                const alt = (img.getAttribute('alt') || '').trim();
                if (alt && (alt.toLowerCase().includes('profile') || alt.toLowerCase().includes('photo'))) {
                    const cleanAlt = alt
                        .replace(/view\s+/i, '')
                        .replace(/’s\s+profile/i, '')
                        .replace(/'s\s+profile/i, '')
                        .replace(/photo\s+of\s+/i, '')
                        .trim();
                    if (cleanAlt) return cleanAlt;
                }
            }

            return 'Post Author';
        },

        /**
         * Extract the headline of the author.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractHeadline: (postElement) => {
            for (const sel of window.LinkPilotSelectors.headline) {
                const elements = postElement.querySelectorAll(sel);
                for (const el of elements) {
                    if (isExcludedNode(el, postElement)) continue;
                    const text = el.innerText.trim();
                    if (text && !isMetadataLine(text)) return text;
                }
            }

            const profileLinks = postElement.querySelectorAll(window.LinkPilotSelectors.links.profile);
            let nameLink = null;
            for (const link of profileLinks) {
                if (isExcludedNode(link, postElement)) continue;
                const text = link.innerText.trim();
                if (text && !isMetadataLine(text)) {
                    nameLink = link;
                    break;
                }
            }

            if (nameLink) {
                let current = nameLink.parentElement;
                for (let i = 0; i < 4 && current; i++) {
                    const paragraphs = current.querySelectorAll('p, span, div');
                    for (const cand of paragraphs) {
                        if (nameLink.contains(cand)) continue;

                        const text = cand.innerText.trim();
                        if (text &&
                            text.length > 2 &&
                            !text.includes(nameLink.innerText.split('\n')[0].trim()) &&
                            !isMetadataLine(text)) {
                            return text;
                        }
                    }
                    current = current.parentElement;
                }
            }

            const actorContainers = postElement.querySelectorAll('.update-components-actor, .feed-shared-actor, [class*="actor"]');
            for (const actorContainer of actorContainers) {
                if (isExcludedNode(actorContainer, postElement)) continue;
                const textLines = actorContainer.innerText.split('\n').map(l => l.trim()).filter(Boolean);
                const cleanLines = textLines.filter(line => !isMetadataLine(line));
                if (cleanLines.length > 1) {
                    return cleanLines[1];
                }
            }
            return 'LinkedIn Member';
        },

        /**
         * Extract the company from the headline if possible.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractCompany: (postElement) => {
            const headline = window.LinkPilotParser.extractHeadline(postElement);
            if (headline.includes(' at ')) {
                return headline.split(' at ')[1].trim();
            }
            if (headline.includes(' @ ')) {
                return headline.split(' @ ')[1].trim();
            }
            return headline;
        },

        /**
         * Extract the description body text of the post.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractText: (postElement) => {
            for (const sel of window.LinkPilotSelectors.text) {
                const elements = postElement.querySelectorAll(sel);
                for (const el of elements) {
                    if (isExcludedNode(el, postElement)) continue;
                    const cloned = el.cloneNode(true);
                    const seeMore = cloned.querySelector('button, .see-more, [class*="see-more"]');
                    if (seeMore) seeMore.remove();
                    const text = cloned.innerText.trim();
                    if (text) return text;
                }
            }
            return 'No text content detected.';
        },

        /**
         * Extract the unique post URN or feed URL.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractPostURL: (postElement) => {
            const urn = postElement.getAttribute('data-urn');
            if (urn) {
                return `https://www.linkedin.com/feed/update/${urn}`;
            }
            const link = postElement.querySelector(window.LinkPilotSelectors.links.post);
            if (link) {
                return link.href.split('?')[0];
            }
            return '';
        },

        /**
         * Extract the profile URL of the author.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractProfileURL: (postElement) => {
            const link = postElement.querySelector(window.LinkPilotSelectors.links.profile);
            if (link) {
                return link.href.split('?')[0];
            }
            return '';
        },

        /**
         * Scan post body text to extract any email addresses.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractEmail: (postElement) => {
            const text = window.LinkPilotParser.extractText(postElement);
            const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
            const matches = text.match(emailRegex);
            return matches ? matches[0] : '';
        },

        /**
         * Scan post body text to extract any phone numbers.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractPhone: (postElement) => {
            const text = window.LinkPilotParser.extractText(postElement);
            const phoneRegex = /(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/g;
            const matches = text.match(phoneRegex);
            return matches ? matches[0].trim() : '';
        },

        /**
         * Scan post body text to extract hashtags.
         * @param {HTMLElement} postElement
         * @returns {Array<string>}
         */
        extractHashtags: (postElement) => {
            const text = window.LinkPilotParser.extractText(postElement);
            const hashtagRegex = /#\w+/g;
            const matches = text.match(hashtagRegex);
            return matches ? matches : [];
        },

        /**
         * Extract relative post timestamp (e.g. 9h, 2d).
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractTimestamp: (postElement) => {
            const timeSelectors = [
                '.update-components-actor__sub-text',
                '.feed-shared-actor__sub-text',
                '.comments-post-meta__time-ago',
                '[class*="sub-text"]',
                '[class*="time-ago"]'
            ];
            for (const sel of timeSelectors) {
                const el = postElement.querySelector(sel);
                if (el) {
                    const textLines = el.innerText.split('•').map(l => l.trim()).filter(Boolean);
                    if (textLines.length > 0) return textLines[0];
                }
            }
            return '';
        },

        /**
         * Performs full parsing of a post card element.
         * @param {HTMLElement} postElement
         * @returns {Object}
         */
        parsePost: (postElement) => {
            const parser = window.LinkPilotParser;
            return {
                postId: postElement.getAttribute('data-urn') || postElement.getAttribute('data-id') || Math.random().toString(36).substr(2, 9),
                author: parser.extractAuthor(postElement),
                headline: parser.extractHeadline(postElement),
                company: parser.extractCompany(postElement),
                profileUrl: parser.extractProfileURL(postElement),
                postUrl: parser.extractPostURL(postElement),
                postText: parser.extractText(postElement),
                email: parser.extractEmail(postElement),
                phone: parser.extractPhone(postElement),
                hashtags: parser.extractHashtags(postElement),
                time: parser.extractTimestamp(postElement),
                domReference: postElement
            };
        },

        /**
         * Scrapes the active LinkedIn profile page header info.
         * @returns {Object}
         */
        parseProfile: () => {
            let name = 'LinkedIn Member';
            const nameEl = document.querySelector('h1.text-heading-xlarge, main.scaffold-layout__main section h1, h1');
            if (nameEl) {
                name = nameEl.innerText.trim();
            }

            let jobTitle = '';
            const titleEl = document.querySelector('div.text-body-medium, [class*="text-body-medium"]');
            if (titleEl) {
                jobTitle = titleEl.innerText.trim();
            }

            let company = '';
            let companyUrn = '';
            
            // 1. Check top-card right panel link (Current Company)
            const topCardLink = document.querySelector('ul.pv-text-details__right-panel a[href*="/company/"], [data-field="experience_company_logo"] a[href*="/company/"]');
            if (topCardLink) {
                const href = topCardLink.getAttribute('href');
                const match = href.match(/\/company\/([a-zA-Z0-9\-]+)/);
                if (match && match[1]) {
                    companyUrn = match[1];
                    company = topCardLink.innerText.trim();
                }
            }
            
            // 2. Fallback to Experience section (First experience item logo link)
            if (!companyUrn) {
                const expLink = document.querySelector('div#experience ~ div.pvs-list__outer-container > ul > li a[href*="/company/"], #experience-section a[href*="/company/"]');
                if (expLink) {
                    const href = expLink.getAttribute('href');
                    const match = href.match(/\/company\/([a-zA-Z0-9\-]+)/);
                    if (match && match[1]) {
                        companyUrn = match[1];
                        const li = expLink.closest('li');
                        if (li) {
                            const nameTextEl = li.querySelector('span[aria-hidden="true"], [class*="title"]');
                            if (nameTextEl) {
                                company = nameTextEl.innerText.trim();
                            }
                        }
                    }
                }
            }
            
            // 3. Fallback to parsing company name from headline
            if (!company) {
                if (jobTitle.includes(' at ')) {
                    company = jobTitle.split(' at ')[1].trim().split('·')[0].trim();
                } else if (jobTitle.includes(' @ ')) {
                    company = jobTitle.split(' @ ')[1].trim().split('·')[0].trim();
                } else {
                    company = 'LinkedIn Member';
                }
            }

            const linkedinUrl = window.location.href.split('?')[0];

            return {
                name,
                company,
                job_title: jobTitle,
                linkedin_url: linkedinUrl,
                company_urn: companyUrn
            };
        }
    };
})();
