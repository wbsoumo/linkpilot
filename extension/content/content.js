/**
 * @fileoverview Main orchestrator and modal popup manager for LinkPilot AI.
 */

(() => {
    // Global variable tracking the active post DOM container for pasting comments
    window.activePostElement = null;

    // CSS styling for the modal, scoped inside Shadow DOM
    const MODAL_STYLES = `
        :host {
            --primary: #0F172A;
            --secondary: #1E293B;
            --accent: #14B8A6;
            --success: #22C55E;
            --warning: #F59E0B;
            --danger: #EF4444;
            --background: #F8FAFC;
            --radius-card: 16px;
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
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
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal {
            width: 700px;
            max-width: 90%;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-card);
            color: #F8FAFC;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
            display: flex;
            flex-direction: column;
            max-height: 85vh;
            animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .author-info h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: white;
        }
        
        .author-info p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #94A3B8;
        }
        
        .post-summary {
            margin: 8px 0 0 0;
            font-size: 12px;
            color: #64748B;
            background: rgba(255, 255, 255, 0.03);
            padding: 6px 10px;
            border-radius: 6px;
            max-height: 60px;
            overflow-y: auto;
        }
        
        .close-btn {
            background: transparent;
            border: none;
            color: #64748B;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close-btn:hover {
            color: white;
        }
        
        .tabs-bar {
            display: flex;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 0 12px;
        }
        
        .tab-btn {
            padding: 12px 16px;
            background: transparent;
            border: none;
            color: #94A3B8;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .tab-btn:hover {
            color: white;
        }
        
        .tab-btn.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }
        
        .content-body {
            padding: 20px;
            overflow-y: auto;
            flex-grow: 1;
        }
        
        .tab-content {
            display: none;
            flex-direction: column;
            gap: 16px;
        }
        
        .tab-content.active {
            display: flex;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .form-group label {
            font-size: 11px;
            font-weight: 600;
            color: #94A3B8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .form-group input, .form-group textarea {
            background: #1E293B;
            border: 1px solid #334155;
            border-radius: 8px;
            color: white;
            padding: 10px 12px;
            font-size: 13px;
            outline: none;
            width: 100%;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            border-color: var(--accent);
        }
        
        .action-row {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: var(--accent);
            color: #0F172A;
        }
        
        .btn-primary:hover {
            background-color: #0D9488;
        }
        
        .btn-secondary {
            background-color: transparent;
            color: #E2E8F0;
            border: 1px solid #334155;
        }
        
        .btn-secondary:hover {
            background-color: #1E293B;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .status-toast {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            display: none;
            animation: fadeIn 0.2s ease;
        }
        
        .status-toast.success {
            display: block;
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .status-toast.error {
            display: block;
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .unauth-container {
            padding: 40px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        
        .unauth-container i {
            font-size: 40px;
            color: var(--warning);
        }
        
        .spinner {
            border: 2px solid rgba(20, 184, 166, 0.1);
            border-radius: 50%;
            border-top: 2px solid var(--accent);
            width: 14px;
            height: 14px;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;

    /**
     * Bind outreach email tab actions.
     * @param {ShadowRoot} shadow
     * @param {Object} postDetails
     */
    const bindEmailHandlers = (shadow, postDetails) => {
        const genBtn = shadow.getElementById('email-generate-btn');
        const copyBtn = shadow.getElementById('email-copy-btn');
        const sendBtn = shadow.getElementById('email-send-btn');
        const recipientInput = shadow.getElementById('email-recipient');
        const subjectInput = shadow.getElementById('email-subject');
        const bodyInput = shadow.getElementById('email-body');
        const toast = shadow.getElementById('email-toast');

        const creditsBadge = shadow.getElementById('finder-modal-credits');
        const headerCreditsBadge = shadow.getElementById('header-credits-badge');
        
        const updateCreditsBadge = () => {
            window.LinkPilotUtils.safeSendMessage({ action: 'getCredits' }, (res) => {
                if (res && res.status === 'success') {
                    const rem = (res.wallet && res.wallet.remaining !== undefined) ? res.wallet.remaining : 0;
                    if (creditsBadge) {
                        creditsBadge.textContent = `Credits: ${rem}`;
                        creditsBadge.style.display = 'inline-block';
                    }
                    if (headerCreditsBadge) {
                        headerCreditsBadge.textContent = `Credits: ${rem}`;
                    }
                }
            });
        };
        
        updateCreditsBadge();

        // Prepopulate email parsed from description if available
        if (postDetails.email) {
            recipientInput.value = postDetails.email;
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
                    const color = isError ? '#EF4444' : (isDone ? '#22C55E' : '#14B8A6');
                    const row = window.LinkPilotUtils.safeCreate('div', {
                        style: `display: flex; gap: 6px; align-items: center; color: ${color}; font-weight: 500;`
                    }, [
                        window.LinkPilotUtils.safeCreate('span', {}, [dot]),
                        window.LinkPilotUtils.safeCreate('span', {}, [msg])
                    ]);
                    emailStatusBox.appendChild(row);
                };

                addStatusRow('Connecting secure database tunnel...');
                
                setTimeout(() => {
                    addStatusRow('Parsing profile credentials...');
                    
                    setTimeout(() => {
                        addStatusRow('Scanning local cached hits...');
                        
                        const payload = {
                            linkedin_url: postDetails.profileUrl,
                            name: postDetails.author,
                            company: postDetails.company,
                            job_title: postDetails.headline
                        };

                        window.LinkPilotUtils.safeSendMessage({ action: 'findEmail', payload }, (res) => {
                            if (res && res.status === 'success' && res.email) {
                                addStatusRow('Resolving Clearbit domain autocomplete...', true);
                                setTimeout(() => {
                                    addStatusRow(`Contact resolved using provider: ${res.provider.toUpperCase()}!`, true);
                                    recipientInput.value = res.email;
                                    updateCreditsBadge();
                                    findEmailBtn.disabled = false;
                                    setTimeout(() => {
                                        emailStatusBox.style.display = 'none';
                                    }, 3000);
                                }, 600);
                            } else {
                                addStatusRow('Querying remote providers Hunter, Prospeo, Apollo...', false, true);
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
                email: recipientInput.value
            };

            window.LinkPilotUtils.safeSendMessage({ action: 'generateEmail', payload }, (res) => {
                genBtn.disabled = false;
                genBtn.textContent = '✨ Generate Outreach';

                if (res && res.status === 'success') {
                    subjectInput.value = res.subject;
                    bodyInput.value = res.body.replace(/<br\s*\/?>/gi, '\n').replace(/<\/p><p>/gi, '\n\n').replace(/<p>/gi, '').replace(/<\/p>/gi, '');
                    copyBtn.disabled = false;
                    sendBtn.disabled = false;
                } else {
                    toast.className = 'status-toast error';
                    toast.textContent = (res && res.message) ? res.message : 'Failed to connect to backend server.';
                }
            });
        });

        copyBtn.addEventListener('click', () => {
            const fullText = `Subject: ${subjectInput.value}\n\n${bodyInput.value}`;
            navigator.clipboard.writeText(fullText).then(() => {
                toast.className = 'status-toast success';
                toast.textContent = 'Outreach content copied to clipboard!';

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
                    toast.className = 'status-toast success';
                    toast.textContent = 'Email outreach sent successfully via SMTP!';
                } else {
                    toast.className = 'status-toast error';
                    toast.textContent = (res && res.message) ? res.message : 'Mailer transmission failed.';
                }
            });
        });
    };

    /**
     * Bind WhatsApp messaging actions.
     * @param {ShadowRoot} shadow
     * @param {Object} postDetails
     */
    const bindWhatsAppHandlers = (shadow, postDetails) => {
        const genBtn = shadow.getElementById('whatsapp-generate-btn');
        const copyBtn = shadow.getElementById('whatsapp-copy-btn');
        const openBtn = shadow.getElementById('whatsapp-open-btn');
        const phoneInput = shadow.getElementById('whatsapp-phone');
        const bodyInput = shadow.getElementById('whatsapp-body');
        const toast = shadow.getElementById('whatsapp-toast');

        if (postDetails.phone) {
            phoneInput.value = postDetails.phone;
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
                phone: phoneInput.value
            };

            window.LinkPilotUtils.safeSendMessage({ action: 'generateWhatsApp', payload }, (res) => {
                genBtn.disabled = false;
                genBtn.textContent = '✨ Generate Message';

                if (res && res.status === 'success') {
                    bodyInput.value = res.message;
                    copyBtn.disabled = false;
                    openBtn.disabled = false;
                } else {
                    toast.className = 'status-toast error';
                    toast.textContent = (res && res.message) ? res.message : 'Failed to connect to backend server.';
                }
            });
        });

        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(bodyInput.value).then(() => {
                toast.className = 'status-toast success';
                toast.textContent = 'WhatsApp message copied!';

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
                return;
            }

            const text = encodeURIComponent(bodyInput.value);
            const url = `https://wa.me/${phone}?text=${text}`;

            window.LinkPilotUtils.safeSendMessage({
                action: 'trackAction',
                payload: { event_type: 'whatsapp_opened', details: `Opened WhatsApp chat for: ${phone}` }
            }, () => {
                window.open(url, '_blank');
            });
        });
    };

    /**
     * Bind LinkedIn comment actions.
     * @param {ShadowRoot} shadow
     * @param {Object} postDetails
     */
    const bindCommentHandlers = (shadow, postDetails) => {
        const genBtn = shadow.getElementById('comment-generate-btn');
        const copyBtn = shadow.getElementById('comment-copy-btn');
        const pasteBtn = shadow.getElementById('comment-paste-btn');
        const bodyInput = shadow.getElementById('comment-body');
        const toast = shadow.getElementById('comment-toast');

        genBtn.addEventListener('click', () => {
            genBtn.disabled = true;
            genBtn.innerHTML = '<span class="spinner"></span> Generating...';
            toast.style.display = 'none';

            const payload = {
                post_content: postDetails.postText,
                author_name: postDetails.author
            };

            window.LinkPilotUtils.safeSendMessage({ action: 'generateComment', payload }, (res) => {
                genBtn.disabled = false;
                genBtn.textContent = '✨ Generate Comment';

                if (res && res.status === 'success') {
                    bodyInput.value = res.comment;
                    copyBtn.disabled = false;
                    pasteBtn.disabled = false;
                } else {
                    toast.className = 'status-toast error';
                    toast.textContent = (res && res.message) ? res.message : 'Failed to connect to backend server.';
                }
            });
        });

        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(bodyInput.value).then(() => {
                toast.className = 'status-toast success';
                toast.textContent = 'Comment copied!';

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
                toast.className = 'status-toast success';
                toast.textContent = 'Comment pasted successfully! Please review and click Post manually.';

                window.LinkPilotUtils.safeSendMessage({
                    action: 'trackAction',
                    payload: { event_type: 'comment_inserted', details: `Pasted comment on post by ${postDetails.author}` }
                }, () => {});
            } else {
                toast.className = 'status-toast error';
                toast.textContent = 'Comment box not found. Please click LinkedIn\'s "Comment" button first to open it.';
            }
        });
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

            // Secure programmatic container creation (XSS-safe)
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

            const textSummary = details.postText ? (details.postText.substring(0, 150) + '...') : 'No text content detected.';
            const summaryDiv = window.LinkPilotUtils.safeCreate('div', { class: 'post-summary' }, [textSummary]);

            const authorInfo = window.LinkPilotUtils.safeCreate('div', { class: 'author-info' }, [
                window.LinkPilotUtils.safeCreate('h3', {}, [details.author]),
                window.LinkPilotUtils.safeCreate('p', {}, [details.company]),
                summaryDiv
            ]);

            const headerCredits = window.LinkPilotUtils.safeCreate('div', {
                id: 'header-credits-wrapper',
                style: 'display: flex; align-items: center; gap: 8px;'
            }, [
                window.LinkPilotUtils.safeCreate('span', {
                    id: 'header-credits-badge',
                    style: 'font-size: 11px; color: #14B8A6; font-weight: bold; background: rgba(20, 184, 166, 0.1); padding: 3px 10px; border-radius: 12px; border: 1px solid rgba(20, 184, 166, 0.2);'
                }, ['Credits: --']),
                window.LinkPilotUtils.safeCreate('a', {
                    href: 'https://linkpilot.work/dashboard/recharge.html',
                    target: '_blank',
                    style: 'font-size: 10px; color: #F59E0B; text-decoration: none; font-weight: bold; background: rgba(245, 158, 11, 0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.2); transition: background-color 0.2s;'
                }, ['Recharge +'])
            ]);
            
            if (!isAuth) {
                headerCredits.style.display = 'none';
            }

            const headerActions = window.LinkPilotUtils.safeCreate('div', {
                style: 'display: flex; align-items: center; gap: 12px;'
            }, [headerCredits, closeBtn]);

            const header = window.LinkPilotUtils.safeCreate('div', { class: 'header' }, [authorInfo, headerActions]);
            const modal = window.LinkPilotUtils.safeCreate('div', { class: 'modal' }, [header]);
            const overlay = window.LinkPilotUtils.safeCreate('div', {
                class: 'overlay',
                onclick: (e) => { if (e.target === overlay) container.remove(); }
            }, [modal]);

            shadow.appendChild(overlay);

            if (!isAuth) {
                // Render a professional, inline login form directly in the middle of the screen
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
                                // Successful login! Re-open the modal with details to transition to full active view
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
                    loginForm,
                    window.LinkPilotUtils.safeCreate('div', {
                        style: 'margin-top: 20px; font-size: 11px; color: #64748B; text-align: center;'
                    }, [
                        'Need an account? ',
                        window.LinkPilotUtils.safeCreate('a', {
                            href: 'https://linkpilot.work/dashboard/register.html',
                            target: '_blank',
                            style: 'color: #14B8A6; text-decoration: none; font-weight: 600;'
                        }, ['Register Here'])
                    ])
                ]);
                modal.appendChild(unauthBody);
                document.body.appendChild(container);
                return;
            }

            // Tab Buttons Creation
            const tabEmail = window.LinkPilotUtils.safeCreate('button', { class: 'tab-btn active', 'data-tab': 'email' }, ['\uD83D\uDCE7 Outreach Email']);
            const tabWA = window.LinkPilotUtils.safeCreate('button', { class: 'tab-btn', 'data-tab': 'whatsapp' }, ['\uD83D\uDCAC WhatsApp Message']);
            const tabComment = window.LinkPilotUtils.safeCreate('button', { class: 'tab-btn', 'data-tab': 'comment' }, ['\uD83D\uDCDD LinkedIn Comment']);
            const tabsBar = window.LinkPilotUtils.safeCreate('div', { class: 'tabs-bar' }, [tabEmail, tabWA, tabComment]);
            modal.appendChild(tabsBar);

            // Tab Panels Creation
            const contentBody = window.LinkPilotUtils.safeCreate('div', { class: 'content-body' });
            modal.appendChild(contentBody);

            // Email Tab Panel
            const emailPanel = window.LinkPilotUtils.safeCreate('div', { class: 'tab-content active', id: 'tab-content-email' }, [
                window.LinkPilotUtils.safeCreate('div', { class: 'status-toast', id: 'email-toast' }),
                window.LinkPilotUtils.safeCreate('div', { class: 'form-group' }, [
                    window.LinkPilotUtils.safeCreate('div', { style: 'display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px;' }, [
                        window.LinkPilotUtils.safeCreate('label', { style: 'margin: 0;' }, ['Recipient Email Address']),
                        window.LinkPilotUtils.safeCreate('div', { style: 'display: flex; align-items: center; gap: 6px;' }, [
                            window.LinkPilotUtils.safeCreate('span', {
                                id: 'finder-modal-credits',
                                style: 'font-size: 11px; color: #14B8A6; font-weight: bold; background: rgba(20, 184, 166, 0.1); padding: 2px 8px; border-radius: 12px; display: inline-block;'
                            }, ['Credits: --']),
                            window.LinkPilotUtils.safeCreate('a', {
                                href: 'https://linkpilot.work/dashboard/recharge.html',
                                target: '_blank',
                                style: 'font-size: 10px; color: #F59E0B; text-decoration: none; font-weight: bold; background: rgba(245, 158, 11, 0.1); padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.2);'
                            }, ['Add +'])
                        ])
                    ]),
                    window.LinkPilotUtils.safeCreate('div', { style: 'display: flex; gap: 8px;' }, [
                        window.LinkPilotUtils.safeCreate('input', { type: 'email', id: 'email-recipient', placeholder: 'john@example.com', style: 'flex-grow: 1;' }),
                        window.LinkPilotUtils.safeCreate('button', {
                            type: 'button',
                            class: 'btn btn-secondary',
                            id: 'modal-find-email-btn',
                            style: 'height: 38px; white-space: nowrap; font-size: 12px; font-weight: bold; color: #14B8A6; border-color: rgba(20, 184, 166, 0.3);'
                        }, ['Find Email 🔍']),
                        window.LinkPilotUtils.safeCreate('button', {
                            type: 'button',
                            id: 'modal-find-email-info',
                            style: 'background: none; border: none; color: #64748B; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0 4px; font-family: monospace; font-size: 14px; font-weight: bold;',
                            title: 'Extracting verified email address costs 1 credit. Free if cached or not found.'
                        }, ['ⓘ'])
                    ]),
                    window.LinkPilotUtils.safeCreate('div', {
                        id: 'modal-find-email-status',
                        style: 'display: none; flex-direction: column; gap: 4px; font-size: 11px; padding: 10px; border-radius: 6px; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); text-align: left; margin-top: 4px; line-height: 1.4;'
                    })
                ]),
                window.LinkPilotUtils.safeCreate('div', { class: 'form-group' }, [
                    window.LinkPilotUtils.safeCreate('label', {}, ['Email Subject']),
                    window.LinkPilotUtils.safeCreate('input', { type: 'text', id: 'email-subject', placeholder: 'Outreach Subject...' })
                ]),
                window.LinkPilotUtils.safeCreate('div', { class: 'form-group' }, [
                    window.LinkPilotUtils.safeCreate('label', {}, ['Email Body']),
                    window.LinkPilotUtils.safeCreate('textarea', { id: 'email-body', rows: '8', placeholder: 'Outreach email content will appear here...' })
                ]),
                window.LinkPilotUtils.safeCreate('div', { class: 'action-row' }, [
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-primary', id: 'email-generate-btn' }, ['\u2728 Generate Outreach']),
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-secondary', id: 'email-copy-btn', disabled: 'true' }, ['\uD83D\uDCCB Copy']),
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-secondary', id: 'email-send-btn', disabled: 'true' }, ['\u2709 Send Email'])
                ])
            ]);
            contentBody.appendChild(emailPanel);

            // WhatsApp Tab Panel
            const waPanel = window.LinkPilotUtils.safeCreate('div', { class: 'tab-content', id: 'tab-content-whatsapp' }, [
                window.LinkPilotUtils.safeCreate('div', { class: 'status-toast', id: 'whatsapp-toast' }),
                window.LinkPilotUtils.safeCreate('div', { class: 'form-group' }, [
                    window.LinkPilotUtils.safeCreate('label', {}, ['Phone Number (with Country Code)']),
                    window.LinkPilotUtils.safeCreate('input', { type: 'text', id: 'whatsapp-phone', placeholder: '+919876543210' })
                ]),
                window.LinkPilotUtils.safeCreate('div', { class: 'form-group' }, [
                    window.LinkPilotUtils.safeCreate('label', {}, ['Message Content']),
                    window.LinkPilotUtils.safeCreate('textarea', { id: 'whatsapp-body', rows: '6', placeholder: 'WhatsApp message will appear here...' })
                ]),
                window.LinkPilotUtils.safeCreate('div', { class: 'action-row' }, [
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-primary', id: 'whatsapp-generate-btn' }, ['\u2728 Generate Message']),
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-secondary', id: 'whatsapp-copy-btn', disabled: 'true' }, ['\uD83D\uDCCB Copy']),
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-secondary', id: 'whatsapp-open-btn', disabled: 'true' }, ['\uD83D\uDCAC Open WhatsApp'])
                ])
            ]);
            contentBody.appendChild(waPanel);

            // Comment Tab Panel
            const commentPanel = window.LinkPilotUtils.safeCreate('div', { class: 'tab-content', id: 'tab-content-comment' }, [
                window.LinkPilotUtils.safeCreate('div', { class: 'status-toast', id: 'comment-toast' }),
                window.LinkPilotUtils.safeCreate('div', { class: 'form-group' }, [
                    window.LinkPilotUtils.safeCreate('label', {}, ['Generated LinkedIn Comment']),
                    window.LinkPilotUtils.safeCreate('textarea', { id: 'comment-body', rows: '5', placeholder: 'LinkedIn comment will appear here...' })
                ]),
                window.LinkPilotUtils.safeCreate('div', { class: 'action-row' }, [
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-primary', id: 'comment-generate-btn' }, ['\u2728 Generate Comment']),
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-secondary', id: 'comment-copy-btn', disabled: 'true' }, ['\uD83D\uDCCB Copy']),
                    window.LinkPilotUtils.safeCreate('button', { class: 'btn btn-secondary', id: 'comment-paste-btn', disabled: 'true' }, ['\u270D Paste to Comment Box'])
                ])
            ]);
            contentBody.appendChild(commentPanel);

            // Tab Switching Logic
            const tabBtns = [tabEmail, tabWA, tabComment];
            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    const tabName = btn.getAttribute('data-tab');
                    contentBody.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    shadow.getElementById(`tab-content-${tabName}`).classList.add('active');
                });
            });

            // Bind business actions
            bindEmailHandlers(shadow, details);
            bindWhatsAppHandlers(shadow, details);
            bindCommentHandlers(shadow, details);

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
                window.LinkPilotUtils.safeCreate('p', {}, [details.company || 'LinkedIn Member']),
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
                window.LinkPilotUtils.safeCreate('p', { style: 'margin: 0; font-size: 12px; color: #94A3B8;' }, ['Checking providers Hunter, Prospeo, Apollo...'])
            ]);
            finderBody.appendChild(loaderWrapper);

            document.body.appendChild(container);

            // Execute finder request
            window.LinkPilotUtils.safeSendMessage({
                action: 'findEmail',
                payload: {
                    linkedin_url: details.linkedin_url,
                    name: details.name,
                    company: details.company,
                    job_title: details.job_title
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
                        window.LinkPilotUtils.safeCreate('span', {}, [`Provider: ${res.provider.toUpperCase()}`]),
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

    window.LinkPilotLogger.info('LinkPilot AI modules initialized successfully.');
})();
