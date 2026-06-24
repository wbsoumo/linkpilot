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
        const lower = line.toLowerCase();
        return lower.includes('•') ||
               !!lower.match(/^\d+[hmdy]$/) ||
               lower.includes('edited') ||
               ['1st', '2nd', '3rd', 'following', 'follow', 'commented', 'reposted', 'liked'].includes(lower);
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

    window.LinkPilotParser = {
        /**
         * Find all valid feed posts inside a given root node.
         * @param {HTMLElement|Document} root
         * @returns {Array<HTMLElement>}
         */
        findFeedPosts: (root = document) => {
            const posts = [];
            const selectors = window.LinkPilotSelectors.post;

            root.querySelectorAll(selectors.join(', ')).forEach(el => {
                if (el instanceof HTMLElement && isValidFeedPost(el)) {
                    posts.push(el);
                }
            });

            return posts;
        },

        /**
         * Extract the author name of the post.
         * @param {HTMLElement} postElement
         * @returns {string}
         */
        extractAuthor: (postElement) => {
            for (const sel of window.LinkPilotSelectors.author) {
                const el = postElement.querySelector(sel);
                if (el) {
                    const text = el.innerText.split('\n')[0].trim();
                    if (text) return text;
                }
            }
            const profileLink = postElement.querySelector(window.LinkPilotSelectors.links.profile);
            if (profileLink) {
                const text = profileLink.innerText.split('\n')[0].trim();
                if (text) return text;
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
                const el = postElement.querySelector(sel);
                if (el) {
                    const text = el.innerText.trim();
                    if (text) return text;
                }
            }
            const actorContainer = postElement.querySelector('.update-components-actor, .feed-shared-actor, [class*="actor"]');
            if (actorContainer) {
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
                const el = postElement.querySelector(sel);
                if (el) {
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
        }
    };
})();
