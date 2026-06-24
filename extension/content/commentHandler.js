/**
 * @fileoverview Safe comment insertion simulator for LinkedIn editors.
 */

(() => {
    window.LinkPilotCommentHandler = {
        /**
         * Safely inputs text into a LinkedIn comment editor.
         * Supports contenteditable divs (Quill) and standard textareas.
         * 
         * @param {HTMLElement} postElement - Root post card element containing the comment box.
         * @param {string} text - The generated comment text.
         * @returns {boolean} - True if editor was found and populated, false otherwise.
         */
        pasteComment: (postElement, text) => {
            if (!postElement) return false;

            // Target the rich editor contenteditable block or standard textareas
            const editor = postElement.querySelector('.ql-editor[contenteditable="true"], textarea.comments-comment-box__textarea, [role="textbox"]');
            if (!editor) {
                return false;
            }

            // Focus the editor element
            editor.focus();

            if (editor.tagName === 'DIV' || editor.getAttribute('contenteditable') === 'true') {
                // Clear existing editor nodes securely
                while (editor.firstChild) {
                    editor.removeChild(editor.firstChild);
                }

                // Create paragraph structure safely using text nodes to prevent XSS
                const p = document.createElement('p');
                p.appendChild(document.createTextNode(text));
                editor.appendChild(p);
            } else {
                // Set textarea values directly
                editor.value = text;
            }

            // Dispatch events to trigger framework React state updates
            editor.dispatchEvent(new Event('focus', { bubbles: true }));
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            editor.dispatchEvent(new Event('change', { bubbles: true }));
            editor.dispatchEvent(new Event('blur', { bubbles: true }));

            // Dispatch keyboard event fallback to resolve internal editor models
            const keyEvent = new KeyboardEvent('keydown', {
                key: 'Process',
                code: 'Process',
                keyCode: 229,
                which: 229,
                bubbles: true,
                cancelable: true
            });
            editor.dispatchEvent(keyEvent);

            return true;
        }
    };
})();
