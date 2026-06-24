/**
 * @fileoverview WeakMap-based cache storage to store parsed post metadata.
 * 
 * DESIGN DECISION: Why WeakMap is better than HTML datasets:
 * 1. Memory Safety: WeakMaps hold weak references to their key elements. When a post element is removed
 *    from the DOM (due to infinite scroll, page changes, etc.), the browser's Garbage Collector automatically
 *    frees the associated metadata object, preventing memory leaks.
 * 2. Performance: Storing raw objects natively in a WeakMap avoids JSON serialization/deserialization
 *    overhead (JSON.stringify/parse) required when storing attributes in datasets.
 * 3. Security: Keeps the post metadata private from third-party scripts running in the page context.
 */

(() => {
    /** @type {WeakMap<HTMLElement, Object>} */
    const cache = new WeakMap();

    window.LinkPilotStorage = {
        /**
         * Set metadata for a specific post element.
         * @param {HTMLElement} element
         * @param {Object} data
         */
        setPost: (element, data) => {
            if (element instanceof HTMLElement) {
                cache.set(element, data);
            }
        },

        /**
         * Get metadata for a specific post element.
         * @param {HTMLElement} element
         * @returns {Object|undefined}
         */
        getPost: (element) => {
            if (element instanceof HTMLElement) {
                return cache.get(element);
            }
            return undefined;
        },

        /**
         * Check if metadata exists for a specific post element.
         * @param {HTMLElement} element
         * @returns {boolean}
         */
        hasPost: (element) => {
            if (element instanceof HTMLElement) {
                return cache.has(element);
            }
            return false;
        }
    };
})();
