// extension/content.js

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
    
    /* Loading spinner */
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

// Global variable tracking the active post DOM container
let activePostElement = null;

// Start Injection Observer
function initButtonInjection() {
    console.log("[LinkPilot AI] Initializing button injection observer...");
    // Run injection once immediately
    injectActionButtons();
    
    // Set up observer for dynamically loaded feed cards
    const observer = new MutationObserver((mutations) => {
        let shouldInject = false;
        for (const mutation of mutations) {
            if (mutation.addedNodes.length > 0) {
                shouldInject = true;
                break;
            }
        }
        if (shouldInject) {
            injectActionButtons();
        }
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
}

// Helper to check if an element is a main post social action bar
function isMainActionBar(el) {
    if (!el || (el.tagName !== 'DIV' && el.tagName !== 'UL')) {
        return false;
    }
    
    // Ensure it is not inside comments, replies, or comment text boxes
    if (el.closest('[class*="comments-"], [class*="comment-"], [class*="reply-"], .comment-social-bar, .comments-shared-social-action-bar')) {
        return false;
    }
    
    // Check for presence of key post action buttons
    const hasLike = el.querySelector('[aria-label*="Reaction" i], [aria-label*="Like" i]');
    const hasComment = el.querySelector('[aria-label*="Comment" i]');
    const hasSendOrRepost = el.querySelector('[aria-label*="Send" i], [aria-label*="Repost" i], [aria-label*="Share" i]');
    
    let matchCount = 0;
    if (hasLike) matchCount++;
    if (hasComment) matchCount++;
    if (hasSendOrRepost) matchCount++;
    
    return matchCount >= 2;
}

// Helper to find the main social action bar by walking up the tree from any matched button/link
function findMainActionBar(btn) {
    let current = btn.parentElement;
    while (current && current !== document.body) {
        if (isMainActionBar(current)) {
            return current;
        }
        current = current.parentElement;
    }
    return null;
}

// Scans page and injects button into feed cards
function injectActionButtons() {
    // 1. Collect all candidate containers using class selectors (excluding comment box buttons)
    const classSelectors = [
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
    
    const candidateContainers = new Set();
    document.querySelectorAll(classSelectors.join(', ')).forEach(el => {
        if (isMainActionBar(el)) {
            candidateContainers.add(el);
        }
    });
    
    // 2. Collect candidates by looking for comment/like/repost buttons and finding their parent containers
    document.querySelectorAll('button, [role="button"], a').forEach(btn => {
        const label = (btn.getAttribute('aria-label') || '').toLowerCase();
        const text = (btn.textContent || btn.innerText || '').toLowerCase().trim();
        const className = (btn.className || '').toLowerCase();
        
        // Match buttons that look like Like, Comment, Repost, Share, or Send
        const matchesButton = 
            className.includes('comment-button') || 
            className.includes('react-button') ||
            className.includes('share-button') ||
            className.includes('repost-button') ||
            label.includes('comment') || 
            label.includes('like') || 
            label.includes('react') || 
            label.includes('repost') ||
            label.includes('share') ||
            text === 'comment' || 
            text === 'like' || 
            text === 'repost' || 
            text === 'share';
            
        if (matchesButton) {
            const mainBar = findMainActionBar(btn);
            if (mainBar) {
                candidateContainers.add(mainBar);
            }
        }
    });
    
    candidateContainers.forEach((bar) => {
        // Prevent duplicate injections
        if (bar.querySelector('.linkpilot-btn')) {
            return;
        }
        
        console.log(`[LinkPilot AI] Injecting AI button into:`, bar);
        
        // Create Action Button
        const button = document.createElement('button');
        button.className = 'linkpilot-btn artdeco-button artdeco-button--muted artdeco-button--4 artdeco-button--tertiary';
        button.type = 'button';
        button.style.color = '#14B8A6';
        button.style.fontWeight = 'bold';
        button.style.display = 'inline-flex';
        button.style.alignItems = 'center';
        button.style.justifyContent = 'center';
        button.style.gap = '6px';
        button.style.transition = 'background-color 0.2s';
        button.style.marginLeft = '8px'; // Add separation
        button.style.marginRight = '8px';
        button.style.border = '1px solid rgba(140, 140, 140, 0.4)';
        button.style.borderRadius = '4px';
        button.style.padding = '4px 8px';
        button.style.cursor = 'pointer';
        
        const iconUrl = chrome.runtime.getURL('gemini-color.png');
        button.innerHTML = `
            <img src="${iconUrl}" alt="AI" style="width: 16px; height: 16px; object-fit: contain; display: inline-block;" />
            <span style="font-size: 13px; font-weight: 600;">AI</span>
        `;
        
        // Determine if we need to wrap the button in a sibling tag type (like LI or SPAN)
        let wrapper = null;
        const sister = bar.firstElementChild;
        if (sister && (sister.tagName === 'LI' || sister.tagName === 'SPAN' || sister.tagName === 'DIV')) {
            wrapper = document.createElement(sister.tagName);
            wrapper.className = sister.className;
            wrapper.style.display = 'inline-flex';
            wrapper.style.alignItems = 'center';
        }
        
        // Append the button to the end of the action bar (after the Share/Send icon)
        if (wrapper) {
            wrapper.appendChild(button);
            bar.appendChild(wrapper);
        } else {
            bar.appendChild(button);
        }
        
        // Bind Click Action
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            console.log("[LinkPilot AI] Sparkles Action button clicked.");
            openActionModal(bar);
        });
    });
}

// Scrape post contents by walking up from the action bar container
function scrapePostDetails(bar) {
    if (!bar) return { text: 'No text content detected.', author: 'Post Author', company: 'LinkedIn Member', postUrl: '', authorUrl: '', postElement: null };
    
    let current = bar.parentElement;
    let author = '';
    let company = '';
    let text = '';
    let authorUrl = '';
    let postUrl = '';
    let postContainer = null;
    
    while (current && current !== document.body) {
        // Exclude containers inside comment sections
        if (current.matches && current.matches('[class*="comments-"], [class*="comment-"], [class*="reply-"], .comment-social-bar')) {
            return { text: 'No text content detected.', author: 'Post Author', company: 'LinkedIn Member', postUrl: '', authorUrl: '', postElement: null };
        }
        
        // 1. Try to find the profile link and name
        if (!author) {
            const profileLink = current.querySelector('a[href*="/in/"]');
            if (profileLink) {
                author = profileLink.innerText.split('\n')[0].trim();
                authorUrl = profileLink.href.split('?')[0];
            }
        }
        
        // 2. Try to find the actor/author headline
        if (!company && author) {
            const descEl = current.querySelector('.update-components-actor__description, .feed-shared-actor__description, [class*="actor-description"], [class*="actor__description"], [class*="headline"], [class*="sub-title"]');
            if (descEl && descEl.innerText.trim() !== author) {
                company = descEl.innerText.trim();
            } else {
                // Sibling/Parent text line fallback
                const profileLink = current.querySelector('a[href*="/in/"]');
                if (profileLink) {
                    const actorParent = profileLink.closest('[class*="actor"], [class*="header"], [class*="profile-info"]') || profileLink.parentElement;
                    if (actorParent) {
                        const textLines = actorParent.innerText.split('\n').map(l => l.trim()).filter(Boolean);
                        const cleanLines = textLines.filter(line => {
                            const lower = line.toLowerCase();
                            return !lower.includes('•') && 
                                   !lower.match(/^\d+[hmdy]$/) && 
                                   !lower.includes('edited') &&
                                   lower !== '1st' && 
                                   lower !== '2nd' && 
                                   lower !== '3rd' &&
                                   lower !== 'following' &&
                                   lower !== 'follow' &&
                                   !lower.includes(author.toLowerCase());
                        });
                        if (cleanLines.length > 0) {
                            company = cleanLines[0];
                        }
                    }
                }
            }
        }
        
        // 3. Try to find the post description text
        if (!text) {
            const textSelectors = [
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
                '[class*="description"]'
            ];
            for (const selector of textSelectors) {
                const el = current.querySelector(selector);
                if (el && el !== bar) {
                    const cloned = el.cloneNode(true);
                    const seeMore = cloned.querySelector('button, .see-more, [class*="see-more"]');
                    if (seeMore) seeMore.remove();
                    text = cloned.innerText.trim();
                    if (text) {
                        postContainer = current;
                        break;
                    }
                }
            }
            
            // Sibling break-words search fallback
            if (!text) {
                const breakWords = current.querySelector('[class*="break-words"]');
                if (breakWords && breakWords !== bar && !breakWords.closest('a[href*="/in/"]')) {
                    text = breakWords.innerText.trim();
                    if (text) {
                        postContainer = current;
                    }
                }
            }
        }
        
        // 4. Try to find URN for post URL
        if (!postUrl) {
            const urn = current.getAttribute('data-urn');
            if (urn) {
                postUrl = `https://www.linkedin.com/feed/update/${urn}`;
            } else {
                const postLink = current.querySelector('a[href*="/feed/update/"]');
                if (postLink) postUrl = postLink.href.split('?')[0];
            }
        }
        
        // Break early if we successfully found both author and post text
        if (author && text) {
            postContainer = current;
            break;
        }
        
        current = current.parentElement;
    }
    
    return {
        text: text || 'No text content detected.',
        author: author || 'Post Author',
        company: company || 'LinkedIn Member',
        postUrl,
        authorUrl,
        postElement: postContainer || bar.parentElement || bar
    };
}

// Build and open modal dialog using Shadow DOM
function openActionModal(postElement) {
    // Avoid double overlay
    const existing = document.getElementById('linkpilot-modal-container');
    if (existing) existing.remove();
    
    const details = scrapePostDetails(postElement);
    activePostElement = details.postElement;
    
    // Check session first
    chrome.runtime.sendMessage({ action: 'getSession' }, (session) => {
        const isAuth = session && session.loggedIn;
        
        // Create root wrapper div
        const container = document.createElement('div');
        container.id = 'linkpilot-modal-container';
        container.style.position = 'relative';
        container.style.zIndex = '999999';
        
        // Create Shadow Root
        const shadow = container.attachShadow({ mode: 'open' });
        
        // Inject styles
        const styleBlock = document.createElement('style');
        styleBlock.textContent = MODAL_STYLES;
        shadow.appendChild(styleBlock);
        
        // Create Overlay
        const overlay = document.createElement('div');
        overlay.className = 'overlay';
        shadow.appendChild(overlay);
        
        // Create Modal
        const modal = document.createElement('div');
        modal.className = 'modal';
        overlay.appendChild(modal);
        
        // Click overlay to close
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                container.remove();
            }
        });
        
        // Header
        const header = document.createElement('div');
        header.className = 'header';
        header.innerHTML = `
            <div class="author-info">
                <h3>${details.author || 'Post Author'}</h3>
                <p>${details.company || 'LinkedIn Member'}</p>
                <div class="post-summary">${details.text ? details.text.substring(0, 150) + '...' : 'No text content detected.'}</div>
            </div>
            <button class="close-btn">&times;</button>
        `;
        modal.appendChild(header);
        
        header.querySelector('.close-btn').addEventListener('click', () => container.remove());
        
        if (!isAuth) {
            // Unauthenticated view
            const unauthBody = document.createElement('div');
            unauthBody.className = 'unauth-container';
            unauthBody.innerHTML = `
                <div style="font-size: 32px; margin-bottom: 8px;">✨</div>
                <h3 style="margin: 0; font-size: 16px;">Authentication Required</h3>
                <p style="margin: 4px 0 16px 0; font-size: 13px; color: #94A3B8;">Please log in to your LinkPilot AI extension first to generate outreach responses.</p>
                <button class="btn btn-primary" id="go-to-popup-btn">Open Extension Login</button>
            `;
            modal.appendChild(unauthBody);
            
            unauthBody.querySelector('#go-to-popup-btn').addEventListener('click', () => {
                alert('Please click the LinkPilot AI extension icon in your browser toolbar to log in.');
                container.remove();
            });
            
            document.body.appendChild(container);
            return;
        }
        
        // Tabs Navigation
        const tabsBar = document.createElement('div');
        tabsBar.className = 'tabs-bar';
        tabsBar.innerHTML = `
            <button class="tab-btn active" data-tab="email">📧 Outreach Email</button>
            <button class="tab-btn" data-tab="whatsapp">💬 WhatsApp Message</button>
            <button class="tab-btn" data-tab="comment">📝 LinkedIn Comment</button>
        `;
        modal.appendChild(tabsBar);
        
        // Body Content
        const contentBody = document.createElement('div');
        contentBody.className = 'content-body';
        modal.appendChild(contentBody);
        
        // Tab Content: Email
        const emailContent = document.createElement('div');
        emailContent.className = 'tab-content active';
        emailContent.id = 'tab-content-email';
        emailContent.innerHTML = `
            <div class="status-toast" id="email-toast"></div>
            <div class="form-group">
                <label>Recipient Email Address</label>
                <input type="email" id="email-recipient" placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label>Email Subject</label>
                <input type="text" id="email-subject" placeholder="Outreach Subject...">
            </div>
            <div class="form-group">
                <label>Email Body</label>
                <textarea id="email-body" rows="8" placeholder="Outreach email content will appear here..."></textarea>
            </div>
            <div class="action-row">
                <button class="btn btn-primary" id="email-generate-btn">✨ Generate Outreach</button>
                <button class="btn btn-secondary" id="email-copy-btn" disabled>📋 Copy</button>
                <button class="btn btn-secondary" id="email-send-btn" disabled>✉ Send Email</button>
            </div>
        `;
        contentBody.appendChild(emailContent);
        
        // Tab Content: WhatsApp
        const whatsappContent = document.createElement('div');
        whatsappContent.className = 'tab-content';
        whatsappContent.id = 'tab-content-whatsapp';
        whatsappContent.innerHTML = `
            <div class="status-toast" id="whatsapp-toast"></div>
            <div class="form-group">
                <label>Phone Number (with Country Code)</label>
                <input type="text" id="whatsapp-phone" placeholder="+919876543210">
            </div>
            <div class="form-group">
                <label>Message Content</label>
                <textarea id="whatsapp-body" rows="6" placeholder="WhatsApp message will appear here..."></textarea>
            </div>
            <div class="action-row">
                <button class="btn btn-primary" id="whatsapp-generate-btn">✨ Generate Message</button>
                <button class="btn btn-secondary" id="whatsapp-copy-btn" disabled>📋 Copy</button>
                <button class="btn btn-secondary" id="whatsapp-open-btn" disabled>💬 Open WhatsApp</button>
            </div>
        `;
        contentBody.appendChild(whatsappContent);
        
        // Tab Content: Comment
        const commentContent = document.createElement('div');
        commentContent.className = 'tab-content';
        commentContent.id = 'tab-content-comment';
        commentContent.innerHTML = `
            <div class="status-toast" id="comment-toast"></div>
            <div class="form-group">
                <label>Generated LinkedIn Comment</label>
                <textarea id="comment-body" rows="5" placeholder="LinkedIn comment will appear here..."></textarea>
            </div>
            <div class="action-row">
                <button class="btn btn-primary" id="comment-generate-btn">✨ Generate Comment</button>
                <button class="btn btn-secondary" id="comment-copy-btn" disabled>📋 Copy</button>
                <button class="btn btn-secondary" id="comment-paste-btn" disabled>✍ Paste to Comment Box</button>
            </div>
        `;
        contentBody.appendChild(commentContent);
        
        // Bind Tab Switching
        const tabBtns = tabsBar.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                const tabName = btn.dataset.tab;
                contentBody.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                shadow.getElementById(`tab-content-${tabName}`).classList.add('active');
            });
        });
        
        // Bind Business Logic Handlers
        bindEmailHandlers(shadow, details);
        bindWhatsAppHandlers(shadow, details);
        bindCommentHandlers(shadow, details);
        
        // Track Popup opened
        chrome.runtime.sendMessage({
            action: 'trackAction',
            payload: { event_type: 'popup_opened', details: `Opened popup for post URL: ${details.postUrl}` }
        });
        
        document.body.appendChild(container);
    });
}

// ----------------------------------------------------
// Business Logic: Email Tab
// ----------------------------------------------------
function bindEmailHandlers(shadow, postDetails) {
    const genBtn = shadow.getElementById('email-generate-btn');
    const copyBtn = shadow.getElementById('email-copy-btn');
    const sendBtn = shadow.getElementById('email-send-btn');
    const recipientInput = shadow.getElementById('email-recipient');
    const subjectInput = shadow.getElementById('email-subject');
    const bodyInput = shadow.getElementById('email-body');
    const toast = shadow.getElementById('email-toast');
    
    // Pre-populate recipient if we parsed it from DOM, otherwise check empty
    // LinkedIn doesn't always show emails, so default is empty
    
    genBtn.addEventListener('click', () => {
        genBtn.disabled = true;
        genBtn.innerHTML = `<span class="spinner"></span> Generating...`;
        toast.className = 'status-toast';
        toast.style.display = 'none';
        
        const payload = {
            post_content: postDetails.text,
            post_url: postDetails.postUrl,
            author_name: postDetails.author,
            company_name: postDetails.company,
            author_profile_url: postDetails.authorUrl,
            email: recipientInput.value
        };
        
        chrome.runtime.sendMessage({ action: 'generateEmail', payload }, (res) => {
            genBtn.disabled = false;
            genBtn.innerHTML = `✨ Generate Outreach`;
            
            if (res && res.status === 'success') {
                subjectInput.value = res.subject;
                // Replace HTML br and p tags with plain text lines for editable textareas
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
            
            chrome.runtime.sendMessage({
                action: 'trackAction',
                payload: { event_type: 'email_copied', details: `Copied email for author: ${postDetails.author}` }
            });
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
        sendBtn.innerHTML = `<span class="spinner"></span> Sending...`;
        
        const payload = {
            recipient_email: recipient,
            subject: subjectInput.value,
            // Wrap plain text lines in HTML before dispatching
            body: bodyInput.value.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>').replace(/^/, '<p>').replace(/$/, '</p>')
        };
        
        chrome.runtime.sendMessage({ action: 'sendEmail', payload }, (res) => {
            sendBtn.disabled = false;
            sendBtn.innerHTML = `✉ Send Email`;
            
            if (res && res.status === 'success') {
                toast.className = 'status-toast success';
                toast.textContent = 'Email outreach sent successfully via SMTP!';
            } else {
                toast.className = 'status-toast error';
                toast.textContent = (res && res.message) ? res.message : 'Mailer transmission failed.';
            }
        });
    });
}

// ----------------------------------------------------
// Business Logic: WhatsApp Tab
// ----------------------------------------------------
function bindWhatsAppHandlers(shadow, postDetails) {
    const genBtn = shadow.getElementById('whatsapp-generate-btn');
    const copyBtn = shadow.getElementById('whatsapp-copy-btn');
    const openBtn = shadow.getElementById('whatsapp-open-btn');
    const phoneInput = shadow.getElementById('whatsapp-phone');
    const bodyInput = shadow.getElementById('whatsapp-body');
    const toast = shadow.getElementById('whatsapp-toast');
    
    genBtn.addEventListener('click', () => {
        genBtn.disabled = true;
        genBtn.innerHTML = `<span class="spinner"></span> Generating...`;
        toast.className = 'status-toast';
        toast.style.display = 'none';
        
        const payload = {
            post_content: postDetails.text,
            post_url: postDetails.postUrl,
            author_name: postDetails.author,
            company_name: postDetails.company,
            phone: phoneInput.value
        };
        
        chrome.runtime.sendMessage({ action: 'generateWhatsApp', payload }, (res) => {
            genBtn.disabled = false;
            genBtn.innerHTML = `✨ Generate Message`;
            
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
            
            chrome.runtime.sendMessage({
                action: 'trackAction',
                payload: { event_type: 'whatsapp_copied', details: `Copied WhatsApp message for ${postDetails.author}` }
            });
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
        
        // Track the click event
        chrome.runtime.sendMessage({
            action: 'trackAction',
            payload: { event_type: 'whatsapp_opened', details: `Opened WhatsApp chat for: ${phone}` }
        }, () => {
            window.open(url, '_blank');
        });
    });
}

// ----------------------------------------------------
// Business Logic: Comment Tab
// ----------------------------------------------------
function bindCommentHandlers(shadow, postDetails) {
    const genBtn = shadow.getElementById('comment-generate-btn');
    const copyBtn = shadow.getElementById('comment-copy-btn');
    const pasteBtn = shadow.getElementById('comment-paste-btn');
    const bodyInput = shadow.getElementById('comment-body');
    const toast = shadow.getElementById('comment-toast');
    
    genBtn.addEventListener('click', () => {
        genBtn.disabled = true;
        genBtn.innerHTML = `<span class="spinner"></span> Generating...`;
        toast.className = 'status-toast';
        toast.style.display = 'none';
        
        const payload = {
            post_content: postDetails.text,
            author_name: postDetails.author
        };
        
        chrome.runtime.sendMessage({ action: 'generateComment', payload }, (res) => {
            genBtn.disabled = false;
            genBtn.innerHTML = `✨ Generate Comment`;
            
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
            
            chrome.runtime.sendMessage({
                action: 'trackAction',
                payload: { event_type: 'comment_copied', details: `Copied comment for: ${postDetails.author}` }
            });
        });
    });
    
    pasteBtn.addEventListener('click', () => {
        if (!activePostElement) return;
        
        // Find comment boxes inside the active post card
        // LinkedIn comment textareas are usually divs with contenteditable="true" and class like "ql-editor" or textareas
        const commentBox = activePostElement.querySelector('.ql-editor[contenteditable="true"], textarea.comments-comment-box__textarea');
        
        if (commentBox) {
            if (commentBox.tagName === 'DIV') {
                // Draft comment in contenteditable div
                commentBox.focus();
                
                // Set innerText and trigger an input/change event so LinkedIn registers the keyboard changes
                commentBox.innerHTML = `<p>${bodyInput.value}</p>`;
                commentBox.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                // Set standard textarea value
                commentBox.focus();
                commentBox.value = bodyInput.value;
                commentBox.dispatchEvent(new Event('input', { bubbles: true }));
            }
            
            toast.className = 'status-toast success';
            toast.textContent = 'Comment pasted successfully! Please review it and click Post manually.';
            
            chrome.runtime.sendMessage({
                action: 'trackAction',
                payload: { event_type: 'comment_inserted', details: `Pasted comment on post by ${postDetails.author}` }
            });
        } else {
            // If comment section is closed, alert user to open it
            toast.className = 'status-toast error';
            toast.textContent = 'Comment box not found. Please click LinkedIn\'s "Comment" button first to open it.';
        }
    });
}

// Start Injection Observer
initButtonInjection();
// Track extension injection telemetry
chrome.runtime.sendMessage({
    action: 'trackAction',
    payload: { event_type: 'extension_opened', details: 'Extension content script loaded on LinkedIn' }
});
console.log("LinkPilot AI Extension loaded successfully.");
