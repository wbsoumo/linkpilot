/**
 * @fileoverview Prioritized DOM selectors for parsing LinkedIn feed content.
 */

(() => {
    window.LinkPilotSelectors = {
        // Selector chains to locate feed post elements
        post: [
            '.feed-shared-update-v2',
            '.occludable-update',
            'article',
            '[class*="feed-shared-update"]',
            '[class*="occludable-update"]'
        ],

        // Selectors to extract the author/actor name
        author: [
            '.update-components-actor__title',
            '.feed-shared-actor__title',
            '.comments-post-meta__name-text',
            '[class*="actor__title"]',
            '[class*="actor-title"]',
            'span[dir="ltr"]'
        ],

        // Selectors to extract the author headline (job title/company)
        headline: [
            '.update-components-actor__description',
            '.feed-shared-actor__description',
            '.comments-post-meta__headline',
            '[class*="actor__description"]',
            '[class*="actor-description"]',
            '[class*="headline"]',
            '[class*="sub-title"]'
        ],

        // Selectors to extract the post description text body
        text: [
            '.feed-shared-inline-show-more-text',
            '[class*="inline-show-more-text"]',
            '[class*="show-more-text"]',
            '.feed-shared-update-v2__description',
            '.feed-shared-update-v2__commentary',
            '.update-components-text',
            '.update-components-update-v2__commentary',
            '.feed-shared-text',
            '[class*="update-v2__description"]',
            '[class*="update-v2__commentary"]',
            '[class*="update-components-text"]',
            '[class*="feed-shared-text"]',
            '[class*="commentary"]',
            '[class*="description"]',
            '[class*="break-words"]'
        ],

        // Selectors for key profile and post links
        links: {
            profile: 'a[href*="/in/"]',
            post: 'a[href*="/feed/update/"]'
        }
    };
})();
