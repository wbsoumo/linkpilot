/**
 * @fileoverview Central logging system for the LinkPilot AI extension.
 */

(() => {
    const DEBUG_MODE = true;
    const PREFIX = '[LinkPilot AI]';

    window.LinkPilotLogger = {
        /**
         * Log debug messages.
         * @param {...*} args
         */
        debug: (...args) => {
            if (DEBUG_MODE) {
                console.debug(PREFIX, '[DEBUG]', ...args);
            }
        },

        /**
         * Log informational messages.
         * @param {...*} args
         */
        info: (...args) => {
            console.log(PREFIX, '[INFO]', ...args);
        },

        /**
         * Log warning messages.
         * @param {...*} args
         */
        warn: (...args) => {
            console.warn(PREFIX, '[WARN]', ...args);
        },

        /**
         * Log error messages.
         * @param {...*} args
         */
        error: (...args) => {
            console.error(PREFIX, '[ERROR]', ...args);
        }
    };
})();
