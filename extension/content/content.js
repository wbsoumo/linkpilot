/**
 * @fileoverview Main orchestrator and modal popup manager for LinkPilot AI.
 */

(() => {
    // Global variable tracking the active post DOM container for pasting comments
    window.activePostElement = null;

    // CSS styling for the modal, scoped inside Shadow DOM
    const MODAL_STYLES = `
        :host {
            --primary: #1D222F;
            --secondary: #161A24;
            --card-bg: #282E3C;
            --border: rgba(255, 255, 255, 0.12);
            --accent: #10B981;
            --accent-hover: #059669;
            --text-main: #F9FAFB;
            --text-muted: #9CA3AF;
            --success: #10B981;
            --radius-card: 16px;
            --radius-input: 8px;
            --font-family: -apple-system, BlinkMacSystemFont, "Inter", "Segoe UI", Roboto, sans-serif;
        }
        
        * {
            box-sizing: border-box;
            font-family: var(--font-family);
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(11, 15, 25, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal {
            width: 960px;
            max-width: 95vw;
            background: var(--primary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-card);
            color: var(--text-main);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            display: flex;
            flex-direction: column;
            height: 640px;
            max-height: 92vh;
            position: relative;
            animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            padding: 14px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .profile-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .profile-avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.1);
            border: 1.5px solid rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #10B981;
        }
        
        .avatar-svg {
            width: 20px;
            height: 20px;
        }
        
        .profile-title-area {
            display: flex;
            flex-direction: column;
        }
        
        .profile-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .profile-name {
            color: #10B981;
        }
        
        .profile-subtitle {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 1px;
        }
        
        .li-in-logo {
            background: #0A66C2;
            color: white;
            font-weight: bold;
            font-size: 9px;
            padding: 1px 3px;
            border-radius: 2px;
            font-family: sans-serif;
        }
        
        .close-btn-round {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-muted);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .close-btn-round:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .close-svg {
            width: 14px;
            height: 14px;
        }
        
        .post-preview-card {
            margin: 12px 24px 0 24px;
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }
        
        .spark-icon-wrapper {
            background: rgba(16, 185, 129, 0.1);
            padding: 6px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .spark-icon {
            font-size: 12px;
        }
        
        .post-preview-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .post-preview-text {
            margin: 0;
            font-size: 12px;
            color: #D1D5DB;
            line-height: 1.4;
            max-height: 3.4em;
            overflow-y: auto;
        }
        
        .view-post-link {
            align-self: flex-end;
            font-size: 11px;
            color: #10B981;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s;
        }
        
        .view-post-link:hover {
            color: #34D399;
        }
        
        .ext-link-svg {
            width: 10px;
            height: 10px;
        }
        
        .tabs-bar {
            display: flex;
            background: rgba(0, 0, 0, 0.15);
            border-bottom: 1px solid var(--border);
            padding: 0 24px;
            margin-top: 8px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            color: var(--text-main);
        }
        
        .tab-btn.active {
            color: #10B981;
            border-bottom-color: #10B981;
        }

        .tab-icon {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
        
        .content-body {
            padding: 16px 24px;
            overflow-y: auto;
            flex-grow: 1;
        }
        
        .tab-content {
            display: none;
            grid-template-columns: 1.15fr 1fr;
            gap: 24px;
            height: 100%;
        }
        
        .tab-content.active {
            display: grid;
        }
        
        .left-pane {
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-right: 1px solid var(--border);
            padding-right: 24px;
        }
        
        .right-pane {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-left: 8px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            position: relative;
        }
        
        .form-group label {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-icon {
            position: absolute;
            left: 12px;
            font-size: 13px;
            color: var(--text-muted);
            pointer-events: none;
        }
        
        .input-with-icon input, .input-with-icon select {
            padding-left: 36px !important;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            background: var(--secondary);
            border: 1px solid #334155;
            border-radius: var(--radius-input);
            color: white;
            padding: 8px 12px;
            font-size: 13px;
            outline: none;
            width: 100%;
            transition: all 0.2s;
        }
        
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #10B981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
        }
        
        .char-counter {
            align-self: flex-end;
            font-size: 9px;
            color: #6B7280;
            margin-top: -2px;
        }
        
        .pro-tip-box {
            background: rgba(16, 185, 129, 0.04);
            border: 1px dashed rgba(16, 185, 129, 0.2);
            border-radius: 10px;
            padding: 10px;
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin-top: auto;
        }
        
        .pro-tip-icon {
            font-size: 14px;
        }
        
        .pro-tip-text strong {
            font-size: 11px;
            color: #10B981;
            display: block;
            margin-bottom: 2px;
        }
        
        .pro-tip-text p {
            margin: 0;
            font-size: 10px;
            color: var(--text-muted);
            line-height: 1.4;
        }
        
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }
        
        .preview-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .reset-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .reset-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .output-box-wrapper {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            overflow: hidden;
            min-height: 200px;
        }
        
        .output-textarea {
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            resize: none;
            flex-grow: 1;
            padding: 12px !important;
            font-size: 13px !important;
            line-height: 1.5;
            color: #E5E7EB !important;
            outline: none !important;
            box-shadow: none !important;
            font-family: var(--font-family) !important;
        }
        
        .output-box-footer {
            padding: 8px 12px;
            border-top: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: var(--text-muted);
        }
        
        .generated-with {
            font-weight: 500;
        }
        
        .modal-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--border);
            padding: 12px 24px;
            background: rgba(0, 0, 0, 0.15);
            margin-top: auto;
        }
        
        .footer-left-buttons, .footer-right-buttons {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-primary {
            background-color: #10B981;
            color: #0B0F19;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }
        
        .btn-primary:hover {
            background-color: #34D399;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background-color: transparent;
            color: #E5E7EB;
            border: 1px solid #374151;
        }
        
        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.03);
            border-color: #4B5563;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* WhatsApp Theme Styles */
        .whatsapp-bubble-wrapper {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            flex-grow: 1;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            min-height: 200px;
        }
        
        .whatsapp-bubble {
            background: #005c4b;
            border-radius: 12px;
            border-top-left-radius: 0;
            padding: 10px 12px;
            max-width: 90%;
            color: #e9edef;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        
        .whatsapp-textarea {
            background: transparent !important;
            border: none !important;
            resize: none;
            width: 100%;
            height: 110px;
            outline: none !important;
            color: #e9edef !important;
            font-size: 13px !important;
            line-height: 1.5;
            padding: 0 !important;
            margin-bottom: 4px;
            font-family: var(--font-family) !important;
        }
        
        .whatsapp-bubble-footer {
            align-self: flex-end;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .whatsapp-time {
            font-size: 10px;
            color: rgba(233, 237, 239, 0.6);
        }
        
        .whatsapp-check {
            font-size: 12px;
            color: #53bdeb;
        }
        
        .phone-input-wrapper {
            display: flex;
            background: var(--secondary);
            border: 1px solid #334155;
            border-radius: var(--radius-input);
            overflow: hidden;
        }
        
        .phone-input-wrapper input {
            border: none !important;
            background: transparent !important;
            flex-grow: 1;
        }
        
        .country-code-select {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 10px;
            border-right: 1px solid #334155;
            background: rgba(255, 255, 255, 0.02);
            font-size: 12px;
        }
        
        .code-val {
            font-weight: 600;
        }
        
        .caret-down {
            font-size: 9px;
            color: var(--text-muted);
        }
        
        .contact-search-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 0 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border-left: 1px solid #334155;
            transition: color 0.2s;
        }
        
        .contact-search-btn:hover {
            color: white;
        }
        
        .contact-search-btn svg {
            width: 14px;
            height: 14px;
        }
        
        /* Comment Tab Theme Styles */
        .comment-style-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-top: 4px;
        }
        
        .style-card {
            background: var(--secondary);
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .style-card:hover {
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.02);
        }
        
        .style-card.active {
            border-color: #10B981;
            background: rgba(16, 185, 129, 0.05);
            box-shadow: 0 0 0 1px #10B981;
        }
        
        .style-card-icon {
            font-size: 14px;
            margin-bottom: 2px;
        }
        
        .style-card-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .style-card-desc {
            font-size: 9px;
            color: var(--text-muted);
            margin-top: 1px;
            text-align: left;
        }
        
        .segmented-control {
            display: flex;
            background: var(--secondary);
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 2px;
            gap: 2px;
        }
        
        .seg-btn {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.2s;
        }
        
        .seg-btn:hover {
            color: white;
        }
        
        .seg-btn.active {
            background: #1E293B;
            color: #10B981;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .seg-btn strong {
            font-size: 11px;
            font-weight: 700;
        }
        
        .seg-btn span {
            font-size: 9px;
            opacity: 0.8;
            margin-top: 1px;
        }
        
        .linkedin-comment-wrapper {
            background: var(--secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 200px;
        }
        
        .comment-feed-item {
            display: flex;
            gap: 10px;
        }
        
        .comment-author-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #0A66C2;
            color: white;
            font-weight: 900;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: sans-serif;
            flex-shrink: 0;
        }
        
        .comment-bubble {
            background: #1E293B;
            border-radius: 8px;
            padding: 8px 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .comment-author-meta {
            display: flex;
            flex-direction: column;
            margin-bottom: 4px;
        }
        
        .comment-author-name {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .comment-author-headline {
            font-size: 10px;
            color: var(--text-muted);
        }
        
        .linkedin-comment-textarea {
            background: transparent !important;
            border: none !important;
            resize: none;
            width: 100%;
            height: 80px;
            outline: none !important;
            color: #e9edef !important;
            font-size: 12px !important;
            line-height: 1.5;
            padding: 0 !important;
            font-family: var(--font-family) !important;
        }
        
        .comment-why-accordion {
            background: rgba(16, 185, 129, 0.03);
            border: 1px solid rgba(16, 185, 129, 0.15);
            border-radius: 8px;
            overflow: hidden;
            margin-top: auto;
        }
        
        .accordion-header {
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 700;
            color: #10B981;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            background: rgba(16, 185, 129, 0.05);
        }
        
        .accordion-body {
            padding: 8px 12px;
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.4;
            border-top: 1px solid rgba(16, 185, 129, 0.1);
        }
        
        /* Success Toast/Banner styles */
        .success-banner-animate {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #10B981;
            color: #0B0F19;
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.35);
            z-index: 1000000;
            animation: bounceInDown 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transition: all 0.4s ease;
        }
        
        .success-banner-animate.hide {
            opacity: 0;
            transform: translate(-50%, -20px) scale(0.9);
        }
        
        .banner-icon {
            background: white;
            color: #10B981;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 900;
        }

        @keyframes bounceInDown {
            0% {
                opacity: 0;
                transform: translate(-50%, -100px) scale(0.8);
            }
            60% {
                opacity: 1;
                transform: translate(-50%, 10px) scale(1.05);
            }
            80% {
                transform: translate(-50%, -5px) scale(0.98);
            }
            100% {
                transform: translate(-50%, 0) scale(1);
            }
        }
    `;

    /**
     * Bind outreach email tab actions.
     * @param {ShadowRoot} shadow
     * @param {Object} postDetails
     * @param {Function} showSuccessNotification
     */
    const bindEmailHandlers = (shadow, postDetails, showSuccessNotification) => {
        const genBtn = shadow.getElementById('email-generate-btn');
        const copyBtn = shadow.getElementById('email-copy-btn');
        const sendBtn = shadow.getElementById('email-send-btn');
        const recipientInput = shadow.getElementById('email-recipient');
        const subjectInput = shadow.getElementById('email-subject');
        const bodyInput = shadow.getElementById('email-body');
        const toast = shadow.getElementById('email-toast');
        const toneSelect = shadow.getElementById('email-tone');
        const notesInput = shadow.getElementById('email-notes');
        const notesCounter = shadow.getElementById('email-notes-counter');
        const resetBtn = shadow.getElementById('email-reset-btn');
        const charCountEl = shadow.getElementById('email-char-count');

        const creditsBadge = shadow.getElementById('finder-modal-credits');
        const headerCreditsBadge = shadow.getElementById('header-credits-badge');
        
        const updateCreditsBadge = () => {
            window.LinkPilotUtils.safeSendMessage({ action: 'getCredits' }, (res) => {
                if (res && res.status === 'success') {
                    const rem = (res.wallet && res.wallet.remaining !== undefined) ? res.wallet.remaining : 0;
                    if (creditsBadge) {
                        creditsBadge.textContent = `Credits: ${rem}`;
                    }
                    if (headerCreditsBadge) {
                        headerCreditsBadge.textContent = `Credits: ${rem}`;
                    }
                }
            });
        };
        
        updateCreditsBadge();

        if (postDetails.email) {
            recipientInput.value = postDetails.email;
        }

        if (notesInput && notesCounter) {
            notesInput.addEventListener('input', () => {
                notesCounter.textContent = `${notesInput.value.length}/300`;
            });
        }

        const updateEmailCharCount = () => {
            if (charCountEl && bodyInput) {
                charCountEl.textContent = `${bodyInput.value.length} characters`;
            }
        };
        if (bodyInput) {
            bodyInput.addEventListener('input', updateEmailCharCount);
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                subjectInput.value = '';
                bodyInput.value = '';
                if (notesInput) {
                    notesInput.value = '';
                    notesCounter.textContent = '0/300';
                }
                copyBtn.disabled = true;
                sendBtn.disabled = true;
                updateEmailCharCount();
                showSuccessNotification('Email panel reset successfully!');
            });
        }

        const findEmailBtn = shadow.getElementById('modal-find-email-btn');
        const emailStatusBox = shadow.getElementById('modal-find-email-status');

        if (findEmailBtn) {
            findEmailBtn.addEventListener('click', () => {
                const creditsText = (creditsBadge && creditsBadge.textContent) || '';
                const creditsMatch = creditsText.match(/\d+/);
                const currentCredits = creditsMatch ? parseInt(creditsMatch[0]) : 0;

                if (creditsText && currentCredits <= 0) {
                    toast.className = 'status-toast error';
                    toast.textContent = 'Insufficient credits. Please recharge your wallet.';
                    toast.style.display = 'block';
                    return;
                }

                findEmailBtn.disabled = true;
                emailStatusBox.innerHTML = '';
                emailStatusBox.style.display = 'flex';

                const addStatusRow = (msg, isDone = false, isError = false) => {
                    const dot = isDone ? '✓' : (isError ? '✗' : '⏳');
                    const color = isError ? '#EF4444' : (isDone ? '#10B981' : '#34D399');
                    const row = window.LinkPilotUtils.safeCreate('div', {
                        style: `display: flex; gap: 6px; align-items: center; color: ${color}; font-weight: 500;`
                    }, [
                        window.LinkPilotUtils.safeCreate('span', {}, [dot]),
                        window.LinkPilotUtils.safeCreate('span', {}, [msg])
                    ]);
                    emailStatusBox.appendChild(row);
                };

                addStatusRow('Connecting secure database tunnel...');
                
                setTimeout(async () => {
                    addStatusRow('Parsing profile credentials...');
                    
                    const result = await scrapeDomainFromProfile(postDetails.profileUrl);
                    
                    if (result.company) {
                        addStatusRow(`Scraped current company: ${result.company}`, true);
                        const subtitle = shadow.getElementById('modal-header-company');
                        if (subtitle) {
                            subtitle.textContent = result.company;
                        }
                    } else {
                        addStatusRow('Scraped company: No official company page found (skipping URN)', false, false);
                    }
                    
                    if (result.domain) {
                        addStatusRow(`Scraped company domain: ${result.domain}`, true);
                    } else {
                        addStatusRow('Scraped domain: No website domain located on page', false, false);
                    }
                    
                    setTimeout(() => {
                        addStatusRow('Scanning local cached hits...');
                        
                        const payload = {
                            linkedin_url: postDetails.profileUrl,
                            name: postDetails.author,
                            company: result.company || postDetails.company,
                            job_title: postDetails.headline,
                            domain: result.domain
                        };

                        window.LinkPilotUtils.safeSendMessage({ action: 'findEmail', payload }, (res) => {
                            if (res && res.status === 'success' && res.email) {
                                addStatusRow('Resolving Clearbit domain autocomplete...', true);
                                setTimeout(() => {
                                    addStatusRow(`Contact resolved successfully!`, true);
                                    recipientInput.value = res.email;
                                    updateCreditsBadge();
                                    findEmailBtn.disabled = false;
                                    showSuccessNotification('Prospect email resolved successfully!');
                                    setTimeout(() => {
                                        emailStatusBox.style.display = 'none';
                                    }, 3000);
                                }, 600);
                            } else {
                                addStatusRow('Querying remote databases...', false, true);
                                setTimeout(() => {
                                    addStatusRow('No verified email address could be resolved.', false, true);
                                    findEmailBtn.disabled = false;
                                    updateCreditsBadge();
                                    setTimeout(() => {
                                        emailStatusBox.style.display = 'none';
                                    }, 3000);
                                }, 800);
                            }
                        });
                    }, 500);
                }, 500);
            });
        }

        genBtn.addEventListener('click', () => {
            genBtn.disabled = true;
            genBtn.innerHTML = '<span class="spinner"></span> Generating...';
            toast.style.display = 'none';

            const payload = {
                post_content: postDetails.postText,
                post_url: postDetails.postUrl,
                author_name: postDetails.author,
                company_name: postDetails.company,
                author_profile_url: postDetails.profileUrl,
                email: recipientInput.value,
                tone: toneSelect ? toneSelect.value : 'Professional',
                notes: notesInput ? notesInput.value : ''
            };

            window.LinkPilotUtils.safeSendMessage({ action: 'generateEmail', payload }, (res) => {
                genBtn.disabled = false;
                genBtn.textContent = '✨ Generate Outreach';

                if (res && res.status === 'success') {
                    subjectInput.value = res.subject;
                    bodyInput.value = res.body.replace(/<br\s*\/?>/gi, '\n').replace(/<\/p><p>/gi, '\n\n').replace(/<p>/gi, '').replace(/<\/p>/gi, '');
                    copyBtn.disabled = false;
                    sendBtn.disabled = false;
                    updateEmailCharCount();
                    showSuccessNotification('Outreach email generated successfully!');
                } else {
                    toast.className = 'status-toast error';
                    toast.textContent = (res && res.message) ? res.message : 'Failed to connect to backend server.';
                    toast.style.display = 'block';
                }
            });
        });

        copyBtn.addEventListener('click', () => {
            const fullText = `Subject: ${subjectInput.value}\n\n${bodyInput.value}`;
            navigator.clipboard.writeText(fullText).then(() => {
                showSuccessNotification('Outreach content copied to clipboard!');
                window.LinkPilotUtils.safeSendMessage({
                    action: 'trackAction',
                    payload: { event_type: 'email_copied', details: `Copied email for author: ${postDetails.author}` }
                }, () => {});
            });
        });

        sendBtn.addEventListener('click', () => {
            const recipient = recipientInput.value;
            if (!recipient) {
                toast.className = 'status-toast error';
                toast.textContent = 'Please enter a recipient email address.';
                toast.style.display = 'block';
                return;
            }

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner"></span> Sending...';

            const payload = {
                recipient_email: recipient,
                subject: subjectInput.value,
                body: bodyInput.value.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>').replace(/^/, '<p>').replace(/$/, '</p>')
            };

            window.LinkPilotUtils.safeSendMessage({ action: 'sendEmail', payload }, (res) => {
                sendBtn.disabled = false;
                sendBtn.textContent = '✉ Send Email';

                if (res && res.status === 'success') {
                    showSuccessNotification('Email outreach sent successfully via SMTP!');
                } else {
                    toast.className = 'status-toast error';
                    toast.textContent = (res && res.message) ? res.message : 'Mailer transmission failed.';
                    toast.style.display = 'block';
                }
            });
        });
    };

    /**
     * Bind WhatsApp messaging actions.
     * @param {ShadowRoot} shadow
     * @param {Object} postDetails
     * @param {Function} showSuccessNotification
     */
    const bindWhatsAppHandlers = (shadow, postDetails, showSuccessNotification) => {
        const genBtn = shadow.getElementById('whatsapp-generate-btn');
        const copyBtn = shadow.getElementById('whatsapp-copy-btn');
        const openBtn = shadow.getElementById('whatsapp-open-btn');
        const phoneInput = shadow.getElementById('whatsapp-phone');
        const bodyInput = shadow.getElementById('whatsapp-body');
        const toast = shadow.getElementById('whatsapp-toast');
        const toneSelect = shadow.getElementById('whatsapp-tone');
        const lengthSelect = shadow.getElementById('whatsapp-length');
        const notesInput = shadow.getElementById('whatsapp-notes');
        const notesCounter = shadow.getElementById('whatsapp-notes-counter');
        const resetBtn = shadow.getElementById('whatsapp-reset-btn');

        if (postDetails.phone) {
            phoneInput.value = postDetails.phone;
        }

        if (notesInput && notesCounter) {
            notesInput.addEventListener('input', () => {
                notesCounter.textContent = `${notesInput.value.length}/200`;
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                bodyInput.value = '';
                if (notesInput) {
                    notesInput.value = '';
                    notesCounter.textContent = '0/200';
                }
                copyBtn.disabled = true;
                openBtn.disabled = true;
                showSuccessNotification('WhatsApp panel reset successfully!');
            });
        }

        genBtn.addEventListener('click', () => {
            genBtn.disabled = true;
            genBtn.innerHTML = '<span class="spinner"></span> Generating...';
            toast.style.display = 'none';

            const payload = {
                post_content: postDetails.postText,
                post_url: postDetails.postUrl,
                author_name: postDetails.author,
                company_name: postDetails.company,
                phone: phoneInput.value,
                tone: toneSelect ? toneSelect.value : 'Professional',
                length: lengthSelect ? lengthSelect.value : 'Medium',
                notes: notesInput ? notesInput.value : ''
            };

            window.LinkPilotUtils.safeSendMessage({ action: 'generateWhatsApp', payload }, (res) => {
                genBtn.disabled = false;
                genBtn.textContent = '✨ Generate Message';

                if (res && res.status === 'success') {
                    bodyInput.value = res.message;
                    copyBtn.disabled = false;
                    openBtn.disabled = false;
                    showSuccessNotification('WhatsApp message generated successfully!');
                } else {
                    toast.className = 'status-toast error';
                    toast.textContent = (res && res.message) ? res.message : 'Failed to connect to backend server.';
                    toast.style.display = 'block';
                }
            });
        });

        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(bodyInput.value).then(() => {
                showSuccessNotification('WhatsApp message copied to clipboard!');
                window.LinkPilotUtils.safeSendMessage({
                    action: 'trackAction',
                    payload: { event_type: 'whatsapp_copied', details: `Copied WhatsApp message for ${postDetails.author}` }
                }, () => {});
            });
        });

        openBtn.addEventListener('click', () => {
            let phone = phoneInput.value.replace(/[^0-9]/g, '');
            if (!phone) {
                toast.className = 'status-toast error';
                toast.textContent = 'Please enter a valid phone number (digits only).';
                toast.style.display = 'block';
                return;
            }

            const text = encodeURIComponent(bodyInput.value);
            const url = `https://wa.me/${phone}?text=${text}`;

            window.LinkPilotUtils.safeSendMessage({
                action: 'trackAction',
                payload: { event_type: 'whatsapp_opened', details: `Opened WhatsApp chat for: ${phone}` }
            }, () => {
                showSuccessNotification('WhatsApp outreach chat opened!');
                window.open(url, '_blank');
            });
        });
    };

    /**
     * Bind LinkedIn comment actions.
     * @param {ShadowRoot} shadow
     * @param {Object} postDetails
     * @param {Function} showSuccessNotification
     */
    const bindCommentHandlers = (shadow, postDetails, showSuccessNotification) => {
        const genBtn = shadow.getElementById('comment-generate-btn');
        const copyBtn = shadow.getElementById('comment-copy-btn');
        const pasteBtn = shadow.getElementById('comment-paste-btn');
        const saveTemplateBtn = shadow.getElementById('comment-save-template-btn');
        const bodyInput = shadow.getElementById('comment-body');
        const toast = shadow.getElementById('comment-toast');
        const toneSelect = shadow.getElementById('comment-tone');
        const notesInput = shadow.getElementById('comment-notes');
        const notesCounter = shadow.getElementById('comment-notes-counter');
        const resetBtn = shadow.getElementById('comment-reset-btn');

        let selectedStyle = 'Professional';
        const styleCards = shadow.querySelectorAll('.style-card');
        styleCards.forEach(card => {
            card.addEventListener('click', () => {
                styleCards.forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                selectedStyle = card.getAttribute('data-style');
            });
        });

        let selectedLength = 'Medium';
        const segBtns = shadow.querySelectorAll('.segmented-control .seg-btn');
        segBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                segBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedLength = btn.getAttribute('data-length');
            });
        });

        if (notesInput && notesCounter) {
            notesInput.addEventListener('input', () => {
                notesCounter.textContent = `${notesInput.value.length}/200`;
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                bodyInput.value = '';
                if (notesInput) {
                    notesInput.value = '';
                    notesCounter.textContent = '0/200';
                }
                copyBtn.disabled = true;
                pasteBtn.disabled = true;
                saveTemplateBtn.disabled = true;
                showSuccessNotification('LinkedIn comment panel reset successfully!');
            });
        }

        const accordionTrigger = shadow.getElementById('comment-accordion-trigger');
        const accordionBody = shadow.getElementById('comment-accordion-body');
        if (accordionTrigger && accordionBody) {
            accordionTrigger.addEventListener('click', () => {
                const isHidden = accordionBody.style.display === 'none';
                accordionBody.style.display = isHidden ? 'block' : 'none';
                accordionTrigger.querySelector('.accordion-caret').textContent = isHidden ? '▲' : '▼';
            });
        }

        genBtn.addEventListener('click', () => {
            genBtn.disabled = true;
            genBtn.innerHTML = '<span class="spinner"></span> Generating...';
            toast.style.display = 'none';

            const payload = {
                post_content: postDetails.postText,
                author_name: postDetails.author,
                style: selectedStyle,
                tone: toneSelect ? toneSelect.value : 'Professional',
                length: selectedLength,
                notes: notesInput ? notesInput.value : ''
            };

            window.LinkPilotUtils.safeSendMessage({ action: 'generateComment', payload }, (res) => {
                genBtn.disabled = false;
                genBtn.textContent = '✨ Generate Comment';

                if (res && res.status === 'success') {
                    bodyInput.value = res.comment;
                    copyBtn.disabled = false;
                    pasteBtn.disabled = false;
                    saveTemplateBtn.disabled = false;
                    showSuccessNotification('LinkedIn comment generated successfully!');
                } else {
                    toast.className = 'status-toast error';
                    toast.textContent = (res && res.message) ? res.message : 'Failed to connect to backend server.';
                    toast.style.display = 'block';
                }
            });
        });

        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(bodyInput.value).then(() => {
                showSuccessNotification('Comment content copied to clipboard!');
                window.LinkPilotUtils.safeSendMessage({
                    action: 'trackAction',
                    payload: { event_type: 'comment_copied', details: `Copied comment for: ${postDetails.author}` }
                }, () => {});
            });
        });

        pasteBtn.addEventListener('click', () => {
            if (!window.activePostElement) return;

            const success = window.LinkPilotCommentHandler.pasteComment(window.activePostElement, bodyInput.value);
            if (success) {
                showSuccessNotification('Comment pasted successfully! Click Post manually.');
                window.LinkPilotUtils.safeSendMessage({
                    action: 'trackAction',
                    payload: { event_type: 'comment_inserted', details: `Pasted comment on post by ${postDetails.author}` }
                }, () => {});
            } else {
                toast.className = 'status-toast error';
                toast.textContent = 'Comment box not found. Please click LinkedIn\'s "Comment" button first.';
                toast.style.display = 'block';
            }
        });

        if (saveTemplateBtn) {
            saveTemplateBtn.addEventListener('click', () => {
                showSuccessNotification('Comment outreach saved as template successfully!');
            });
        }
    };

    /**
     * Build and open modal dialog using Shadow DOM.
     * @param {Object} details - Pre-parsed post data object.
     */
    window.openActionModal = (details) => {
        const existing = document.getElementById('linkpilot-modal-container');
        if (existing) existing.remove();

        window.LinkPilotUtils.safeSendMessage({ action: 'getSession' }, (session) => {
            const isAuth = session && session.loggedIn;

            const container = window.LinkPilotUtils.safeCreate('div', {
                id: 'linkpilot-modal-container',
                style: { position: 'relative', zIndex: '999999' }
            });

            const shadow = container.attachShadow({ mode: 'open' });

            const styleBlock = window.LinkPilotUtils.safeCreate('style');
            styleBlock.textContent = MODAL_STYLES;
            shadow.appendChild(styleBlock);

            const modal = window.LinkPilotUtils.safeCreate('div', { class: 'modal' });

            const showSuccessNotification = (msg) => {
                let notification = modal.querySelector('#linkpilot-success-banner');
                if (notification) notification.remove();

                notification = window.LinkPilotUtils.safeCreate('div', {
                    id: 'linkpilot-success-banner',
                    class: 'success-banner-animate'
                }, [
                    window.LinkPilotUtils.safeCreate('span', { class: 'banner-icon' }, ['✓']),
                    window.LinkPilotUtils.safeCreate('span', { class: 'banner-text' }, [msg])
                ]);

                modal.appendChild(notification);

                setTimeout(() => {
                    notification.classList.add('hide');
                    setTimeout(() => notification.remove(), 400);
                }, 3000);
            };
            const overlay = window.LinkPilotUtils.safeCreate('div', {
                class: 'overlay',
                onclick: (e) => { if (e.target === overlay) container.remove(); }
            }, [modal]);

            shadow.appendChild(overlay);

            if (!isAuth) {
                // Render Login Form
                const emailInput = window.LinkPilotUtils.safeCreate('input', {
                    type: 'email',
                    placeholder: 'Enter your email...',
                    style: 'width: 100%; padding: 10px 12px; background: #1E293B; border: 1px solid #334155; border-radius: 8px; color: white; outline: none; margin-bottom: 12px; box-sizing: border-box;'
                });

                const passwordInput = window.LinkPilotUtils.safeCreate('input', {
                    type: 'password',
                    placeholder: 'Enter your password...',
                    style: 'width: 100%; padding: 10px 12px; background: #1E293B; border: 1px solid #334155; border-radius: 8px; color: white; outline: none; margin-bottom: 16px; box-sizing: border-box;'
                });

                const loginErrorDiv = window.LinkPilotUtils.safeCreate('div', {
                    style: 'color: #EF4444; font-size: 12px; margin-bottom: 12px; display: none; text-align: center; font-weight: 500;'
                });

                const loginBtn = window.LinkPilotUtils.safeCreate('button', {
                    class: 'btn btn-primary',
                    style: 'width: 100%; justify-content: center; height: 38px; font-weight: 600;',
                    onclick: (e) => {
                        e.preventDefault();
                        const email = emailInput.value.trim();
                        const password = passwordInput.value;

                        if (!email || !password) {
                            loginErrorDiv.textContent = 'Please enter both email and password.';
                            loginErrorDiv.style.display = 'block';
                            return;
                        }

                        loginErrorDiv.style.display = 'none';
                        loginBtn.disabled = true;
                        loginBtn.innerHTML = '<span class="spinner"></span> Logging in...';

                        window.LinkPilotUtils.safeSendMessage({
                            action: 'login',
                            email: email,
                            password: password
                        }, (res) => {
                            if (res && res.status === 'success') {
                                container.remove();
                                window.openActionModal(details);
                            } else {
                                loginBtn.disabled = false;
                                loginBtn.textContent = 'Log In';
                                loginErrorDiv.textContent = (res && res.message) ? res.message : 'Invalid credentials or connection error.';
                                loginErrorDiv.style.display = 'block';
                            }
                        });
                    }
                }, ['Log In']);

                const loginForm = window.LinkPilotUtils.safeCreate('form', {
                    style: 'width: 100%; text-align: left; margin-top: 8px; box-sizing: border-box;'
                }, [
                    window.LinkPilotUtils.safeCreate('label', {
                        style: 'display: block; font-size: 10px; font-weight: 600; color: #94A3B8; text-transform: uppercase; margin-bottom: 6px;'
                    }, ['Email Address']),
                    emailInput,
                    window.LinkPilotUtils.safeCreate('label', {
                        style: 'display: block; font-size: 10px; font-weight: 600; color: #94A3B8; text-transform: uppercase; margin-bottom: 6px;'
                    }, ['Password']),
                    passwordInput,
                    loginErrorDiv,
                    loginBtn
                ]);

                const unauthBody = window.LinkPilotUtils.safeCreate('div', {
                    class: 'unauth-container',
                    style: 'max-width: 380px; margin: 0 auto; padding: 30px 20px;'
                }, [
                    window.LinkPilotUtils.safeCreate('div', { style: 'font-size: 36px; margin-bottom: 4px;' }, ['✨']),
                    window.LinkPilotUtils.safeCreate('h3', { style: 'margin: 0; font-size: 18px; font-weight: 800; color: white;' }, ['LinkPilot AI Login']),
                    window.LinkPilotUtils.safeCreate('p', { style: 'margin: 6px 0 16px 0; font-size: 13px; color: #94A3B8; text-align: center; line-height: 1.4;' }, [
                        'Log in to activate your outreach assistant directly on this page.'
                    ]),
                    loginForm
                ]);
                modal.appendChild(unauthBody);
                document.body.appendChild(container);
                return;
            }

            // Set Logged In Active Layout
            modal.innerHTML = `
                <!-- Header -->
                <div class="header">
                    <div class="profile-header-left">
                        <div class="profile-avatar-circle">
                            <svg class="avatar-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="profile-title-area">
                            <div class="profile-title">View <span class="profile-name">${details.author}</span>'s profile</div>
                            <div class="profile-subtitle">
                                <span>LinkedIn Member</span>
                                <span class="li-in-logo">in</span>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div id="header-credits-wrapper" style="display: flex; align-items: center; gap: 8px;">
                            <span id="header-credits-badge" style="font-size: 11px; color: #10B981; font-weight: bold; background: rgba(16, 185, 129, 0.1); padding: 3px 10px; border-radius: 12px; border: 1px solid rgba(16, 185, 129, 0.2);">Credits: --</span>
                            <a href="https://linkpilot.work/dashboard/recharge.html" target="_blank" style="font-size: 10px; color: #F59E0B; text-decoration: none; font-weight: bold; background: rgba(245, 158, 11, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.2); transition: background-color 0.2s;">Recharge +</a>
                        </div>
                        <button class="close-btn-round" id="modal-close-btn">
                            <svg class="close-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Post Preview Card -->
                <div class="post-preview-card">
                    <div class="spark-icon-wrapper">
                        <span class="spark-icon">✨</span>
                    </div>
                    <div class="post-preview-content">
                        <p class="post-preview-text">${details.postText || 'No text content detected.'}</p>
                        <a href="${details.postUrl}" target="_blank" class="view-post-link">
                            <span>View Post</span>
                            <svg class="ext-link-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Tabs Bar -->
                <div class="tabs-bar">
                    <button class="tab-btn active" data-tab="email">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="tab-icon" style="color: #3B82F6;">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <span>Outreach Email</span>
                    </button>
                    <button class="tab-btn" data-tab="whatsapp">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="tab-icon" style="color: #25D366;">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.514 2.266 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.5-5.739-1.453L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.42 9.864-9.858.002-2.635-1.023-5.11-2.885-6.974C16.526 1.909 14.058.882 11.43.882c-5.449 0-9.873 4.42-9.877 9.861-.001 1.772.475 3.502 1.374 5.027L1.81 20.625l5.09-1.334z"/>
                        </svg>
                        <span>WhatsApp Message</span>
                    </button>
                    <button class="tab-btn" data-tab="comment">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="tab-icon" style="color: #0A66C2;">
                            <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.779-1.75-1.75s.784-1.75 1.75-1.75 1.75.779 1.75 1.75-.784 1.75-1.75 1.75zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                        </svg>
                        <span>LinkedIn Comment</span>
                    </button>
                </div>

                <!-- Content Body -->
                <div class="content-body" id="modal-content-body">
                    <!-- 1. Email Tab Content -->
                    <div class="tab-content active" id="tab-content-email">
                        <div class="left-pane">
                            <div class="status-toast" id="email-toast"></div>
                            <div class="form-group">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px;">
                                    <label style="margin: 0;">Recipient Email Address</label>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span id="finder-modal-credits" style="font-size: 11px; color: #10B981; font-weight: bold; background: rgba(16, 185, 129, 0.1); padding: 2px 8px; border-radius: 12px; display: inline-block;">Credits: --</span>
                                        <a href="https://linkpilot.work/dashboard/recharge.html" target="_blank" style="font-size: 10px; color: #F59E0B; text-decoration: none; font-weight: bold; background: rgba(245, 158, 11, 0.1); padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.2);">Add +</a>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <div class="input-with-icon" style="flex-grow: 1;">
                                        <span class="input-icon">✉️</span>
                                        <input type="email" id="email-recipient" placeholder="john@example.com">
                                    </div>
                                    <button type="button" class="btn btn-secondary" id="modal-find-email-btn" style="height: 41px; white-space: nowrap; font-size: 12px; font-weight: bold; color: #10B981; border-color: rgba(16, 185, 129, 0.3);">Find Email 🔍</button>
                                    <button type="button" id="modal-find-email-info" style="background: none; border: none; color: #64748B; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0 4px; font-family: monospace; font-size: 14px; font-weight: bold;" title="Extracting verified email address costs 1 credit. Free if cached or not found.">ⓘ</button>
                                </div>
                                <div id="modal-find-email-status" style="display: none; flex-direction: column; gap: 4px; font-size: 11px; padding: 10px; border-radius: 6px; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); text-align: left; margin-top: 4px; line-height: 1.4;"></div>
                            </div>
                            <div class="form-group">
                                <label>Email Subject</label>
                                <input type="text" id="email-subject" placeholder="Outreach Subject...">
                            </div>
                            <div class="form-group">
                                <label>Email Tone</label>
                                <div class="input-with-icon">
                                    <span class="input-icon">😊</span>
                                    <select id="email-tone">
                                        <option value="Professional" selected>Professional</option>
                                        <option value="Casual">Casual</option>
                                        <option value="Friendly">Friendly</option>
                                        <option value="Direct">Direct</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Additional Notes (Optional)</label>
                                <textarea id="email-notes" placeholder="Add any specific details you'd like to include..." maxlength="300" style="height: 45px; resize: none;"></textarea>
                                <span class="char-counter" id="email-notes-counter">0/300</span>
                            </div>
                            <div class="pro-tip-box">
                                <span class="pro-tip-icon">💡</span>
                                <div class="pro-tip-text">
                                    <strong>Pro Tip</strong>
                                    <p>Personalized emails get 3x more responses!</p>
                                </div>
                            </div>
                        </div>
                        <div class="right-pane">
                            <div class="preview-header">
                                <div class="preview-title">
                                    <span class="spark-icon">✨</span> Generated Outreach Email
                                </div>
                                <button class="reset-btn" id="email-reset-btn">🔄 Reset</button>
                            </div>
                            <div class="output-box-wrapper">
                                <textarea id="email-body" class="output-textarea" placeholder="Outreach email content will appear here..."></textarea>
                                <div class="output-box-footer">
                                    <span class="generated-with">Generated with ✨ LinkPilot AI</span>
                                    <span class="char-count" id="email-char-count">0 characters</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. WhatsApp Tab Content -->
                    <div class="tab-content" id="tab-content-whatsapp">
                        <div class="left-pane">
                            <div class="status-toast" id="whatsapp-toast"></div>
                            <div class="form-group">
                                <label>Recipient Phone Number</label>
                                <div class="phone-input-wrapper">
                                    <div class="country-code-select">
                                        <span class="flag-icon">🇮🇳</span>
                                        <span class="code-val">+91</span>
                                        <span class="caret-down">▼</span>
                                    </div>
                                    <input type="text" id="whatsapp-phone" placeholder="9876543210">
                                    <button class="contact-search-btn">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Message Tone</label>
                                <div class="input-with-icon">
                                    <span class="input-icon">😊</span>
                                    <select id="whatsapp-tone">
                                        <option value="Professional" selected>Professional</option>
                                        <option value="Casual">Casual</option>
                                        <option value="Friendly">Friendly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Message Length</label>
                                <div class="input-with-icon">
                                    <span class="input-icon">📏</span>
                                    <select id="whatsapp-length">
                                        <option value="Short">Short</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="Long">Long</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Additional Notes (Optional)</label>
                                <textarea id="whatsapp-notes" placeholder="Add any specific details you'd like to include..." maxlength="200" style="height: 45px; resize: none;"></textarea>
                                <span class="char-counter" id="whatsapp-notes-counter">0/200</span>
                            </div>
                        </div>
                        <div class="right-pane">
                            <div class="preview-header">
                                <div class="preview-title">
                                    <span class="spark-icon">💬</span> Generated WhatsApp Message
                                </div>
                                <button class="reset-btn" id="whatsapp-reset-btn">🔄 Reset</button>
                            </div>
                            <div class="whatsapp-bubble-wrapper">
                                <div class="whatsapp-bubble" style="width: 100%;">
                                    <textarea id="whatsapp-body" class="whatsapp-textarea" placeholder="WhatsApp message will appear here..."></textarea>
                                    <div class="whatsapp-bubble-footer">
                                        <span class="whatsapp-time" id="whatsapp-bubble-time">10:30 AM</span>
                                        <span class="whatsapp-check">✓✓</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. LinkedIn Comment Tab Content -->
                    <div class="tab-content" id="tab-content-comment">
                        <div class="left-pane">
                            <div class="status-toast" id="comment-toast"></div>
                            <div class="form-group">
                                <label>Comment Style</label>
                                <div class="comment-style-grid">
                                    <div class="style-card active" data-style="Professional">
                                        <span class="style-card-icon">💼</span>
                                        <span class="style-card-title">Professional</span>
                                        <span class="style-card-desc">Polite & respectful</span>
                                    </div>
                                    <div class="style-card" data-style="Supportive">
                                        <span class="style-card-icon">🤝</span>
                                        <span class="style-card-title">Supportive</span>
                                        <span class="style-card-desc">Show appreciation</span>
                                    </div>
                                    <div class="style-card" data-style="Insightful">
                                        <span class="style-card-icon">💡</span>
                                        <span class="style-card-title">Insightful</span>
                                        <span class="style-card-desc">Add value</span>
                                    </div>
                                    <div class="style-card" data-style="Question">
                                        <span class="style-card-icon">❓</span>
                                        <span class="style-card-title">Question</span>
                                        <span class="style-card-desc">Ask engaging Q</span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Tone</label>
                                <div class="input-with-icon">
                                    <span class="input-icon">😊</span>
                                    <select id="comment-tone">
                                        <option value="Professional" selected>Professional</option>
                                        <option value="Casual">Casual</option>
                                        <option value="Friendly">Friendly</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Comment Length</label>
                                <div class="segmented-control">
                                    <button class="seg-btn" data-length="Short">
                                        <strong>Short</strong>
                                        <span>1-2 lines</span>
                                    </button>
                                    <button class="seg-btn active" data-length="Medium">
                                        <strong>Medium</strong>
                                        <span>3-4 lines</span>
                                    </button>
                                    <button class="seg-btn" data-length="Long">
                                        <strong>Long</strong>
                                        <span>5+ lines</span>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Additional Notes (Optional)</label>
                                <textarea id="comment-notes" placeholder="Add any specific details you'd like to include..." maxlength="200" style="height: 45px; resize: none;"></textarea>
                                <span class="char-counter" id="comment-notes-counter">0/200</span>
                            </div>
                        </div>
                        <div class="right-pane">
                            <div class="preview-header">
                                <div class="preview-title">
                                    <span class="spark-icon">🔗</span> Generated LinkedIn Comment
                                </div>
                                <button class="reset-btn" id="comment-reset-btn">🔄 Reset</button>
                            </div>
                            <div class="linkedin-comment-wrapper">
                                <div class="comment-feed-item">
                                    <div class="comment-author-avatar">in</div>
                                    <div class="comment-bubble">
                                        <div class="comment-author-meta">
                                            <span class="comment-author-name">${details.author}</span>
                                            <span class="comment-author-headline">LinkedIn Member</span>
                                        </div>
                                        <textarea id="comment-body" class="linkedin-comment-textarea" placeholder="LinkedIn comment will appear here..."></textarea>
                                    </div>
                                </div>
                                <div class="comment-why-accordion">
                                    <div class="accordion-header" id="comment-accordion-trigger">
                                        <span>✨ Why this comment?</span>
                                        <span class="accordion-caret">▼</span>
                                    </div>
                                    <div class="accordion-body" id="comment-accordion-body">
                                        This comment shows genuine interest, highlights your skills, and keeps the door open for a conversation.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Rows -->
                <div class="modal-footer-row" id="email-footer-actions">
                    <div class="footer-left-buttons">
                        <button class="btn btn-secondary" id="email-copy-btn" disabled>
                            <span>📋</span> Copy to Clipboard
                        </button>
                        <button class="btn btn-secondary" id="email-send-btn" disabled>
                            <span>✉️</span> Send Email via SMTP
                        </button>
                    </div>
                    <button class="btn btn-primary" id="email-generate-btn">
                        <span>✨</span> Generate Outreach
                    </button>
                </div>

                <div class="modal-footer-row" id="whatsapp-footer-actions" style="display: none;">
                    <button class="btn btn-primary" id="whatsapp-generate-btn">
                        <span>✨</span> Generate WhatsApp Message
                    </button>
                    <div class="footer-right-buttons">
                        <button class="btn btn-secondary" id="whatsapp-copy-btn" disabled>
                            <span>📋</span> Copy
                        </button>
                        <button class="btn btn-secondary" id="whatsapp-open-btn" disabled>
                            <span>💬</span> Send on WhatsApp
                        </button>
                    </div>
                </div>

                <div class="modal-footer-row" id="comment-footer-actions" style="display: none;">
                    <button class="btn btn-primary" id="comment-generate-btn">
                        <span>✨</span> Generate LinkedIn Comment
                    </button>
                    <div class="footer-right-buttons">
                        <button class="btn btn-secondary" id="comment-copy-btn" disabled>
                            <span>📋</span> Copy
                        </button>
                        <button class="btn btn-secondary" id="comment-paste-btn" disabled>
                            <span>🔗</span> Preview on LinkedIn
                        </button>
                        <button class="btn btn-secondary" id="comment-save-template-btn" disabled>
                            <span>💾</span> Save as Template
                        </button>
                    </div>
                </div>
            `;

            // Set current time on WhatsApp bubble
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            const timeEl = shadow.getElementById('whatsapp-bubble-time');
            if (timeEl) {
                timeEl.textContent = timeStr;
            }

            // Tab Switching Logic
            const tabBtns = shadow.querySelectorAll('.tab-btn');
            const contentBody = shadow.getElementById('modal-content-body');
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    const tabName = btn.getAttribute('data-tab');
                    contentBody.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    shadow.getElementById(`tab-content-${tabName}`).classList.add('active');

                    // Show corresponding footer row
                    shadow.getElementById('email-footer-actions').style.display = tabName === 'email' ? 'flex' : 'none';
                    shadow.getElementById('whatsapp-footer-actions').style.display = tabName === 'whatsapp' ? 'flex' : 'none';
                    shadow.getElementById('comment-footer-actions').style.display = tabName === 'comment' ? 'flex' : 'none';
                });
            });

            // Close button listener
            const closeBtn = shadow.getElementById('modal-close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => container.remove());
            }

            // Overlay click listener
            const overlayEl = shadow.querySelector('.overlay');
            if (overlayEl) {
                overlayEl.addEventListener('click', (e) => {
                    if (e.target === overlayEl) container.remove();
                });
            }

            // Bind business actions
            bindEmailHandlers(shadow, details, showSuccessNotification);
            bindWhatsAppHandlers(shadow, details, showSuccessNotification);
            bindCommentHandlers(shadow, details, showSuccessNotification);

            // Track popup opened telemetry event
            window.LinkPilotUtils.safeSendMessage({
                action: 'trackAction',
                payload: { event_type: 'popup_opened', details: `Opened popup for post URL: ${details.postUrl}` }
            }, () => {});

            document.body.appendChild(container);
        });
    };

    /**
     * Build and open the email finder modal dialog using Shadow DOM.
     * @param {Object} details - Prospect details parsed from profile.
     */
    window.openFinderModal = (details) => {
        const existing = document.getElementById('linkpilot-modal-container');
        if (existing) existing.remove();

        window.LinkPilotUtils.safeSendMessage({ action: 'getSession' }, (session) => {
            const isAuth = session && session.loggedIn;

            const container = window.LinkPilotUtils.safeCreate('div', {
                id: 'linkpilot-modal-container',
                style: { position: 'relative', zIndex: '999999' }
            });

            const shadow = container.attachShadow({ mode: 'open' });

            const styleBlock = window.LinkPilotUtils.safeCreate('style');
            styleBlock.textContent = MODAL_STYLES;
            shadow.appendChild(styleBlock);

            const closeBtn = window.LinkPilotUtils.safeCreate('button', {
                class: 'close-btn',
                onclick: () => container.remove()
            }, ['\u00D7']);

            const authorInfo = window.LinkPilotUtils.safeCreate('div', { class: 'author-info' }, [
                window.LinkPilotUtils.safeCreate('h3', {}, [details.name]),
                window.LinkPilotUtils.safeCreate('p', { id: 'modal-header-company' }, [details.company || 'LinkedIn Member']),
                window.LinkPilotUtils.safeCreate('p', { style: 'font-size: 11px; color: #64748B;' }, [details.job_title || ''])
            ]);

            const header = window.LinkPilotUtils.safeCreate('div', { class: 'header' }, [authorInfo, closeBtn]);
            const modal = window.LinkPilotUtils.safeCreate('div', { class: 'modal', style: 'width: 480px;' }, [header]);
            const overlay = window.LinkPilotUtils.safeCreate('div', {
                class: 'overlay',
                onclick: (e) => { if (e.target === overlay) container.remove(); }
            }, [modal]);

            shadow.appendChild(overlay);

            if (!isAuth) {
                // Render Login Form
                const emailInput = window.LinkPilotUtils.safeCreate('input', {
                    type: 'email',
                    placeholder: 'Enter your email...',
                    style: 'width: 100%; padding: 10px 12px; background: #1E293B; border: 1px solid #334155; border-radius: 8px; color: white; outline: none; margin-bottom: 12px; box-sizing: border-box;'
                });

                const passwordInput = window.LinkPilotUtils.safeCreate('input', {
                    type: 'password',
                    placeholder: 'Enter your password...',
                    style: 'width: 100%; padding: 10px 12px; background: #1E293B; border: 1px solid #334155; border-radius: 8px; color: white; outline: none; margin-bottom: 16px; box-sizing: border-box;'
                });

                const loginErrorDiv = window.LinkPilotUtils.safeCreate('div', {
                    style: 'color: #EF4444; font-size: 12px; margin-bottom: 12px; display: none; text-align: center; font-weight: 500;'
                });

                const loginBtn = window.LinkPilotUtils.safeCreate('button', {
                    class: 'btn btn-primary',
                    style: 'width: 100%; justify-content: center; height: 38px; font-weight: 600;',
                    onclick: (e) => {
                        e.preventDefault();
                        const email = emailInput.value.trim();
                        const password = passwordInput.value;

                        if (!email || !password) {
                            loginErrorDiv.textContent = 'Please enter both email and password.';
                            loginErrorDiv.style.display = 'block';
                            return;
                        }

                        loginErrorDiv.style.display = 'none';
                        loginBtn.disabled = true;
                        loginBtn.innerHTML = '<span class="spinner"></span> Logging in...';

                        window.LinkPilotUtils.safeSendMessage({
                            action: 'login',
                            email: email,
                            password: password
                        }, (res) => {
                            if (res && res.status === 'success') {
                                container.remove();
                                window.openFinderModal(details);
                            } else {
                                loginBtn.disabled = false;
                                loginBtn.textContent = 'Log In';
                                loginErrorDiv.textContent = (res && res.message) ? res.message : 'Invalid credentials or connection error.';
                                loginErrorDiv.style.display = 'block';
                            }
                        });
                    }
                }, ['Log In']);

                const loginForm = window.LinkPilotUtils.safeCreate('form', {
                    style: 'width: 100%; text-align: left; margin-top: 8px; box-sizing: border-box;'
                }, [
                    window.LinkPilotUtils.safeCreate('label', {
                        style: 'display: block; font-size: 10px; font-weight: 600; color: #94A3B8; text-transform: uppercase; margin-bottom: 6px;'
                    }, ['Email Address']),
                    emailInput,
                    window.LinkPilotUtils.safeCreate('label', {
                        style: 'display: block; font-size: 10px; font-weight: 600; color: #94A3B8; text-transform: uppercase; margin-bottom: 6px;'
                    }, ['Password']),
                    passwordInput,
                    loginErrorDiv,
                    loginBtn
                ]);

                const unauthBody = window.LinkPilotUtils.safeCreate('div', {
                    class: 'unauth-container',
                    style: 'max-width: 380px; margin: 0 auto; padding: 30px 20px;'
                }, [
                    window.LinkPilotUtils.safeCreate('div', { style: 'font-size: 36px; margin-bottom: 4px;' }, ['✨']),
                    window.LinkPilotUtils.safeCreate('h3', { style: 'margin: 0; font-size: 18px; font-weight: 800; color: white;' }, ['LinkPilot Email Finder']),
                    window.LinkPilotUtils.safeCreate('p', { style: 'margin: 6px 0 16px 0; font-size: 13px; color: #94A3B8; text-align: center; line-height: 1.4;' }, [
                        'Log in to activate your Email Finder assistant.'
                    ]),
                    loginForm
                ]);
                modal.appendChild(unauthBody);
                document.body.appendChild(container);
                return;
            }

            // Create loading/result body container
            const finderBody = window.LinkPilotUtils.safeCreate('div', {
                class: 'content-body',
                style: 'display: flex; flex-direction: column; gap: 16px; align-items: center; justify-content: center; padding: 30px; text-align: center;'
            });
            modal.appendChild(finderBody);

            const loaderWrapper = window.LinkPilotUtils.safeCreate('div', { style: 'display: block;' }, [
                window.LinkPilotUtils.safeCreate('div', { class: 'spinner', style: 'width: 32px; height: 32px; border-width: 3px; border-top-width: 3px;' }),
                window.LinkPilotUtils.safeCreate('h3', { style: 'margin: 16px 0 6px 0; font-size: 15px; color: #14B8A6; font-weight: 700;' }, ['Resolving Profile Contact...']),
                window.LinkPilotUtils.safeCreate('p', { style: 'margin: 0; font-size: 12px; color: #94A3B8;' }, ['Checking remote databases...'])
            ]);
            finderBody.appendChild(loaderWrapper);

            document.body.appendChild(container);

            (async () => {
                const scrapedDomain = await scrapeDomainFromProfile(details.linkedin_url);

                // Execute finder request
                window.LinkPilotUtils.safeSendMessage({
                    action: 'findEmail',
                    payload: {
                        linkedin_url: details.linkedin_url,
                        name: details.name,
                        company: details.company,
                        job_title: details.job_title,
                        company_urn: details.company_urn,
                        domain: scrapedDomain
                    }
                }, (res) => {
                // Clear loader
                finderBody.innerHTML = '';

                if (res && res.status === 'success' && res.email) {
                    const emailInput = window.LinkPilotUtils.safeCreate('input', {
                        type: 'text',
                        value: res.email,
                        readonly: true,
                        style: 'width: 100%; text-align: center; padding: 12px; background: #1E293B; border: 1px solid #14B8A6; border-radius: 8px; color: #14B8A6; font-family: monospace; font-size: 15px; font-weight: bold; outline: none; margin-bottom: 4px; box-sizing: border-box;'
                    });

                    const statsRow = window.LinkPilotUtils.safeCreate('div', {
                        style: 'display: flex; justify-content: space-around; width: 100%; font-size: 11px; color: #94A3B8; margin-bottom: 20px;'
                    }, [
                        window.LinkPilotUtils.safeCreate('span', {}, [`Source: Verified Database`]),
                        window.LinkPilotUtils.safeCreate('span', {}, [`Confidence: ${res.confidence_score}%`])
                    ]);

                    const copyBtn = window.LinkPilotUtils.safeCreate('button', {
                        class: 'btn btn-secondary',
                        style: 'flex: 1; height: 38px; justify-content: center;',
                        onclick: () => {
                            navigator.clipboard.writeText(res.email).then(() => {
                                copyBtn.textContent = '✓ Copied!';
                                setTimeout(() => { copyBtn.textContent = '📋 Copy Email'; }, 2000);
                            });
                        }
                    }, ['📋 Copy Email']);

                    const saveBtn = window.LinkPilotUtils.safeCreate('button', {
                        class: 'btn btn-primary',
                        style: 'flex: 1; height: 38px; justify-content: center;',
                        onclick: () => {
                            saveBtn.disabled = true;
                            saveBtn.innerHTML = '<span class="spinner"></span> Saving...';
                            
                            window.LinkPilotUtils.safeSendMessage({
                                action: 'saveLead',
                                payload: {
                                    name: details.name,
                                    company_name: details.company,
                                    linkedin_url: details.linkedin_url,
                                    email: res.email,
                                    source: 'LinkedIn Extension'
                                }
                            }, (saveRes) => {
                                if (saveRes && saveRes.status === 'success') {
                                    saveBtn.className = 'btn';
                                    saveBtn.style.cssText = 'flex: 1; height: 38px; justify-content: center; background: rgba(34, 197, 94, 0.1); color: #22C55E; border: 1px solid rgba(34, 197, 94, 0.2); cursor: default;';
                                    saveBtn.innerHTML = '✓ Saved to Vault';
                                } else {
                                    saveBtn.disabled = false;
                                    saveBtn.textContent = 'Save to Vault';
                                }
                            });
                        }
                    }, ['📥 Save to Vault']);

                    const btnsRow = window.LinkPilotUtils.safeCreate('div', {
                        style: 'display: flex; gap: 12px; width: 100%;'
                    }, [copyBtn, saveBtn]);

                    const successBlock = window.LinkPilotUtils.safeCreate('div', {
                        style: 'width: 100%; display: flex; flex-direction: column; align-items: center;'
                    }, [
                        window.LinkPilotUtils.safeCreate('div', { style: 'font-size: 32px; margin-bottom: 12px;' }, ['🎉']),
                        window.LinkPilotUtils.safeCreate('h4', { style: 'margin: 0 0 16px 0; font-size: 15px; font-weight: bold; color: white;' }, ['Prospect Contact Located!']),
                        emailInput,
                        statsRow,
                        btnsRow
                    ]);

                    finderBody.appendChild(successBlock);

                } else if (res && res.status === 'error' && res.message.toLowerCase().includes('credits')) {
                    // Insufficient credits
                    const creditBlock = window.LinkPilotUtils.safeCreate('div', {}, [
                        window.LinkPilotUtils.safeCreate('div', { style: 'font-size: 32px; margin-bottom: 12px;' }, ['⚡']),
                        window.LinkPilotUtils.safeCreate('h4', { style: 'margin: 0 0 8px 0; font-size: 15px; font-weight: bold; color: #F59E0B;' }, ['Insufficient Wallet Balance']),
                        window.LinkPilotUtils.safeCreate('p', { style: 'margin: 0 0 20px 0; font-size: 12px; color: #94A3B8; line-height: 1.4;' }, [
                            'You do not have enough Email Finder credits. Recharge your wallet from the dashboard to continue.'
                        ]),
                        window.LinkPilotUtils.safeCreate('a', {
                            href: 'https://linkpilot.work/dashboard/recharge.html',
                            target: '_blank',
                            class: 'btn btn-primary',
                            style: 'text-decoration: none; display: inline-flex; justify-content: center; width: 100%; box-sizing: border-box; height: 38px; align-items: center;'
                        }, ['Buy Finder Credits'])
                    ]);
                    finderBody.appendChild(creditBlock);
                } else {
                    // Contact not found or timeout
                    const failBlock = window.LinkPilotUtils.safeCreate('div', {}, [
                        window.LinkPilotUtils.safeCreate('div', { style: 'font-size: 32px; margin-bottom: 12px;' }, ['🔍']),
                        window.LinkPilotUtils.safeCreate('h4', { style: 'margin: 0 0 8px 0; font-size: 15px; font-weight: bold; color: #EF4444;' }, ['Contact Email Not Found']),
                        window.LinkPilotUtils.safeCreate('p', { style: 'margin: 0; font-size: 12px; color: #94A3B8; line-height: 1.4;' }, [
                            'No verified email addresses could be resolved for this profile. No wallet credits were deducted.'
                        ])
                    ]);
                    finderBody.appendChild(failBlock);
                }
            });
          })();
        });
    };

    // Initialize extension content observer orchestrator
    window.LinkPilotLogger.info('Starting LinkPilot AI Observer module...');
    window.LinkPilotObserver.start();

    // Track extension loaded telemetry event
    window.LinkPilotUtils.safeSendMessage({
        action: 'trackAction',
        payload: { event_type: 'extension_opened', details: 'Extension modular content script loaded on LinkedIn' }
    }, () => {});

    /**
     * Scrapes the company name and website domain by fetching the profile and company page.
     * @param {string} profileUrl
     * @returns {Promise<{domain: string, company: string}>}
     */
    async function scrapeDomainFromProfile(profileUrl) {
        let resolvedCompany = '';
        let resolvedDomain = '';
        try {
            if (!profileUrl) return { domain: '', company: '' };
            
            // 1. Fetch profile HTML
            const profileRes = await fetch(profileUrl);
            const profileHtml = await profileRes.text();
            
            // 2. Extract company URN and company name
            let companyUrn = '';

            // Try to match URN and name directly from JSON strings in the HTML first!
            const companyNameMatch = profileHtml.match(/"companyName"\s*:\s*"([^"]+)"/);
            if (companyNameMatch && companyNameMatch[1]) {
                resolvedCompany = companyNameMatch[1].replace(/\\"/g, '"').trim();
            }

            const companyUrnMatch = profileHtml.match(/urn:li:fsd_company:(\d+)/);
            if (companyUrnMatch && companyUrnMatch[1] && companyUrnMatch[1] !== '96420083') {
                companyUrn = companyUrnMatch[1];
            }

            // Search for URN handles (supporting both escaped and unescaped company URLs)
            if (!companyUrn) {
                const matches = [...profileHtml.matchAll(/\\?\/company\\?\/([a-zA-Z0-9\-_]+)/g)];
                for (const m of matches) {
                    if (m[1]) {
                        const u = m[1].toLowerCase();
                        if (u !== 'linkedin' && u !== '96420083' && u !== 'invalid' && u !== 'setup') {
                            companyUrn = m[1];
                            break;
                        }
                    }
                }
            }
            
            if (!companyUrn) {
                const fsdMatches = [...profileHtml.matchAll(/urn:li:fsd_company:(\d+)/g)];
                for (const m of fsdMatches) {
                    if (m[1] && m[1] !== '96420083') {
                        companyUrn = m[1];
                        break;
                    }
                }
            }

            if (!companyUrn) {
                const compMatches = [...profileHtml.matchAll(/urn:li:company:(\d+)/g)];
                for (const m of compMatches) {
                    if (m[1] && m[1] !== '96420083') {
                        companyUrn = m[1];
                        break;
                    }
                }
            }

            // Fallback: Parse Profile HTML using DOMParser
            if (!companyUrn || !resolvedCompany) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(profileHtml, 'text/html');
                
                const items = doc.querySelectorAll('[componentkey^="entity-collection-item"]');
                if (items && items.length > 0) {
                    const firstItem = items[0];
                    if (!companyUrn) {
                        const companyLink = firstItem.querySelector('a[href*="/company/"]');
                        if (companyLink) {
                            const href = companyLink.getAttribute('href');
                            const match = href.match(/\/company\/([a-zA-Z0-9\-]+)/);
                            if (match && match[1]) {
                                companyUrn = match[1];
                            }
                        }
                    }
                    if (!resolvedCompany) {
                        const paragraphs = firstItem.querySelectorAll('p');
                        for (const p of paragraphs) {
                            const text = p.textContent || '';
                            if (text.includes('·') || text.includes(' \u00B7 ')) {
                                resolvedCompany = text.split(/[·\u00B7]/)[0].trim();
                                break;
                            }
                        }
                    }
                }
            }

            // Fallback for company name
            if (!resolvedCompany) {
                const paragraphs = doc.querySelectorAll('p, span');
                for (const p of paragraphs) {
                    const text = p.textContent || '';
                    if (text.includes('·') || text.includes(' \u00B7 ')) {
                        const parts = text.split(/[·\u00B7]/);
                        const nameCandidate = parts[0].trim();
                        if (nameCandidate && nameCandidate.length > 1 && nameCandidate.length < 100 && !nameCandidate.includes('years') && !nameCandidate.includes('mos') && !nameCandidate.includes('ago')) {
                            resolvedCompany = nameCandidate;
                            break;
                        }
                    }
                }
            }
            
            // 3. Fetch company page to get the domain
            if (companyUrn) {
                const companyRes = await fetch(`https://www.linkedin.com/company/${companyUrn}/`);
                const companyHtml = await companyRes.text();
                const companyDoc = parser.parseFromString(companyHtml, 'text/html');

                // Try to find Visit Website link on main company card
                const visitLink = companyDoc.querySelector('a.org-top-card-primary-actions__action, .org-top-card-primary-actions__inner a');
                if (visitLink) {
                    let href = visitLink.getAttribute('href') || '';
                    if (href.includes('redir/redirect')) {
                        const urlParam = new URL(href, window.location.origin).searchParams.get('url');
                        if (urlParam) href = urlParam;
                    }
                    if (href.startsWith('http') && !href.includes('linkedin.com')) {
                        try {
                            resolvedDomain = new URL(href).hostname.replace('www.', '');
                        } catch (e) {}
                    }
                }

                // Check all anchors for "Visit website" text
                if (!resolvedDomain) {
                    const anchors = companyDoc.querySelectorAll('a');
                    for (const a of anchors) {
                        const text = a.textContent || '';
                        let href = a.getAttribute('href') || '';
                        if (text.toLowerCase().includes('visit website') || href.includes('org-top-card-primary-actions')) {
                            if (href.includes('redir/redirect')) {
                                const urlParam = new URL(href, window.location.origin).searchParams.get('url');
                                if (urlParam) href = urlParam;
                            }
                            if (href.startsWith('http') && !href.includes('linkedin.com')) {
                                try {
                                    resolvedDomain = new URL(href).hostname.replace('www.', '');
                                    break;
                                } catch (e) {}
                            }
                        }
                    }
                }

                // Check JSON website metadata
                if (!resolvedDomain) {
                    let jsonMatch = companyHtml.match(/"website":"(http[s]?:\/\/[^"]+)"/);
                    let jsonMatchUrl = companyHtml.match(/"websiteUrl":"(http[s]?:\/\/[^"]+)"/);
                    if (jsonMatch && jsonMatch[1]) {
                        let cleanUrl = jsonMatch[1].replace(/\\/g, '');
                        resolvedDomain = new URL(cleanUrl).hostname.replace('www.', '');
                    } else if (jsonMatchUrl && jsonMatchUrl[1]) {
                        let cleanUrl = jsonMatchUrl[1].replace(/\\/g, '');
                        resolvedDomain = new URL(cleanUrl).hostname.replace('www.', '');
                    }
                }
            }

            // 4. Fallback to Clearbit if we got the company name but no website domain
            if (!resolvedDomain && resolvedCompany) {
                try {
                    const clearbitRes = await fetch(`https://autocomplete.clearbit.com/v1/companies/suggest?query=${encodeURIComponent(resolvedCompany)}`);
                    const data = await clearbitRes.json();
                    if (data && data[0] && data[0].domain) {
                        resolvedDomain = data[0].domain;
                    }
                } catch (e) {}
            }
        } catch (err) {
            console.warn('Scraping domain from profile failed:', err.message);
        }
        return { domain: resolvedDomain, company: resolvedCompany };
    }

    window.LinkPilotLogger.info('LinkPilot AI modules initialized successfully.');
})();
