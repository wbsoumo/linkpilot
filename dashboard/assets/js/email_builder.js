// dashboard/assets/js/email_builder.js

(function() {
    let activeTemplateId = null;
    let templateName = "Untitled Template";
    let templateSubject = "";
    let templateCategory = "Sales";
    let templateTag = "Outreach";
    let activeDevice = "desktop"; // desktop, tablet, mobile
    let activeSidebarTab = "elements"; // elements, sections, ai, brand
    let selectedElementId = null;
    let selectedSectionId = null;
    let undoStack = [];
    let redoStack = [];
    let isSaving = false;
    let autoSaveInterval = null;

    // Core Canvas JSON Tree
    let canvasData = [
        {
            id: "sec_1",
            type: "section",
            settings: {
                paddingTop: "30px",
                paddingBottom: "30px",
                backgroundColor: "#ffffff",
                borderRadius: "8px"
            },
            elements: [
                {
                    id: "el_logo",
                    type: "logo",
                    settings: {
                        logoUrl: "https://img.icons8.com/color/96/000000/send.png",
                        height: "48px",
                        align: "center",
                        paddingBottom: "15px"
                    }
                },
                {
                    id: "el_hero",
                    type: "hero",
                    settings: {
                        title: "Introducing LinkPilot Email AI",
                        subtitle: "Generate high-converting personalized email sequences in seconds.",
                        ctaText: "Start Building Now",
                        ctaLink: "https://linkpilot.ai",
                        backgroundColor: "#F8FAFC",
                        padding: "24px",
                        titleColor: "#0f172a",
                        subtitleColor: "#475569"
                    }
                },
                {
                    id: "el_text_1",
                    type: "text",
                    settings: {
                        content: "Dear {{First Name}},<br><br>We are thrilled to give you early access to our Drag & Drop Email Template Builder. Now you can design premium marketing templates that look perfect on Outlook, Gmail, and mobile screens.",
                        fontSize: "15px",
                        color: "#334155",
                        lineHeight: "1.6",
                        paddingTop: "20px",
                        paddingBottom: "10px"
                    }
                },
                {
                    id: "el_btn_1",
                    type: "button",
                    settings: {
                        text: "Explore Dashboard & Templates",
                        link: "https://linkpilot.ai/dashboard",
                        backgroundColor: "#4F46E5",
                        textColor: "#ffffff",
                        paddingTop: "12px",
                        paddingBottom: "12px",
                        paddingLeft: "24px",
                        paddingRight: "24px",
                        borderRadius: "8px",
                        align: "center"
                    }
                },
                {
                    id: "el_spacer",
                    type: "spacer",
                    settings: {
                        height: "20px"
                    }
                },
                {
                    id: "el_footer",
                    type: "footer",
                    settings: {
                        content: "© 2026 LinkPilot AI. All rights reserved.<br>You received this because you are an active LinkPilot subscriber.<br><a href='#' style='color:#4F46E5;text-decoration:none;'>Unsubscribe</a> | <a href='#' style='color:#4F46E5;text-decoration:none;'>Preferences</a>",
                        fontSize: "11px",
                        color: "#94a3b8",
                        align: "center",
                        paddingTop: "20px"
                    }
                }
            ]
        }
    ];

    // Global Brand Assets
    let brandStyles = {
        primaryColor: "#4F46E5",
        secondaryColor: "#0F172A",
        backgroundColor: "#f1f5f9",
        fontFamily: "Inter, Arial, sans-serif"
    };

    // Component Definition List
    const draggableElements = [
        { type: "heading", label: "Heading", icon: "type", desc: "Main title text" },
        { type: "text", label: "Paragraph", icon: "align-left", desc: "Longform message block" },
        { type: "image", label: "Image Block", icon: "image", desc: "Custom image upload / URL" },
        { type: "button", label: "CTA Button", icon: "mouse-pointer", desc: "Action conversion link" },
        { type: "divider", label: "Divider Line", icon: "minus", desc: "Horizontal section break" },
        { type: "spacer", label: "Spacer Gap", icon: "move", desc: "Vertical spacing height" },
        { type: "social", label: "Social Links", icon: "share-2", desc: "Social media sharing handles" },
        { type: "coupon", label: "Coupon Card", icon: "tag", desc: "Promotional discount block" },
        { type: "countdown", label: "Countdown Timer", icon: "clock", desc: "Scarcity counter block" }
    ];

    // Draggable Sections Definition List
    const readySections = [
        {
            name: "Hero Banner",
            elements: [
                {
                    type: "hero",
                    settings: {
                        title: "Season Clearance Sale Starts Now",
                        subtitle: "Get up to 70% off across all summer collection categories.",
                        ctaText: "Shop the Sale",
                        ctaLink: "#",
                        backgroundColor: "#ECFDF5",
                        padding: "30px",
                        titleColor: "#065f46",
                        subtitleColor: "#047857"
                    }
                }
            ]
        },
        {
            name: "Coupon Card",
            elements: [
                {
                    type: "coupon",
                    settings: {
                        code: "WELCOME50",
                        discount: "50% OFF",
                        desc: "Apply this coupon code at checkout for your first order.",
                        backgroundColor: "#EEF2F6",
                        borderColor: "#4F46E5"
                    }
                }
            ]
        },
        {
            name: "Call to Action",
            elements: [
                {
                    type: "heading",
                    settings: { content: "Ready to accelerate outreach?", fontSize: "20px", color: "#0f172a", align: "center", paddingTop: "15px" }
                },
                {
                    type: "button",
                    settings: { text: "Upgrade Plan Now", link: "#", backgroundColor: "#10B981", textColor: "#ffffff", borderRadius: "6px", align: "center", paddingTop: "10px" }
                }
            ]
        }
    ];

    // Push current state to undo history
    function recordState() {
        undoStack.push(JSON.stringify(canvasData));
        redoStack = []; // Clear redo stack on new action
        updateAutoSaveStatus();
    }

    // Undo action
    window.builderUndo = function() {
        if (undoStack.length === 0) return;
        redoStack.push(JSON.stringify(canvasData));
        canvasData = JSON.parse(undoStack.pop());
        renderCanvas();
        selectedElementId = null;
        renderPropertiesPanel();
    };

    // Redo action
    window.builderRedo = function() {
        if (redoStack.length === 0) return;
        undoStack.push(JSON.stringify(canvasData));
        canvasData = JSON.parse(redoStack.pop());
        renderCanvas();
        selectedElementId = null;
        renderPropertiesPanel();
    };

    // Main entry point
    window.renderEmailBuilder = async function(container, templateId = null) {
        activeTemplateId = templateId;
        selectedElementId = null;
        
        // Hide global chat assist button to avoid overlapping controls
        const globalChat = document.getElementById('ai-chat-trigger-btn');
        if (globalChat) globalChat.classList.add('hidden');

        // Check if editing existing template
        if (templateId) {
            try {
                const res = await apiCall('crm/get_custom_templates.php?id=' + templateId);
                if (res.status === 'success' && res.data && res.data.template) {
                    const t = res.data.template;
                    templateName = t.name || "Untitled Template";
                    templateSubject = t.subject || "";
                    templateCategory = t.category || "Sales";
                    templateTag = t.tag || "Outreach";
                    if (t.json_data) {
                        canvasData = JSON.parse(t.json_data);
                    }
                }
            } catch (err) {
                console.error("Failed to load existing template, using fallback", err);
            }
        } else {
            templateName = "New Campaign Layout";
            templateSubject = "Exclusive updates from our team";
            // Initialize with default standard layout tree
        }
        // Draw builder interface structure
        container.innerHTML = `
            <style>
                /* Premium Drag & Drop Builder UI Light Color Overrides */
                #builder-left-sidebar input,
                #builder-left-sidebar select,
                #builder-left-sidebar textarea,
                #builder-right-sidebar input:not([type="color"]),
                #builder-right-sidebar select,
                #builder-right-sidebar textarea,
                #test-email-modal input,
                #test-email-modal select,
                #test-email-modal textarea {
                    background-color: #ffffff !important;
                    color: #0f172a !important;
                    border: 1px solid #cbd5e1 !important;
                    border-radius: 8px !important;
                    outline: none !important;
                }
                #builder-left-sidebar input:focus,
                #builder-left-sidebar select:focus,
                #builder-left-sidebar textarea:focus,
                #builder-right-sidebar input:focus,
                #builder-right-sidebar select:focus,
                #builder-right-sidebar textarea:focus {
                    border-color: #4F46E5 !important;
                    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1) !important;
                }
                #builder-left-sidebar label,
                #builder-right-sidebar label,
                #test-email-modal label {
                    color: #475569 !important;
                    font-weight: 700 !important;
                    font-size: 10px !important;
                    text-transform: uppercase !important;
                    letter-spacing: 0.5px !important;
                    display: block !important;
                    margin-bottom: 5px !important;
                    margin-top: 10px !important;
                }
                #builder-left-sidebar span,
                #builder-right-sidebar span {
                    color: #1e293b !important;
                }
                #builder-template-name {
                    background-color: #ffffff !important;
                    color: #0f172a !important;
                    border: 1px solid #cbd5e1 !important;
                    border-radius: 6px !important;
                    padding: 4px 8px !important;
                }
                
                /* Device switcher & undo/redo buttons */
                .h-14 button:not(#save-template-btn) {
                    background-color: #ffffff !important;
                    color: #475569 !important;
                    border: 1px solid #cbd5e1 !important;
                }
                .h-14 button:not(#save-template-btn):hover {
                    background-color: #f8fafc !important;
                    color: #0f172a !important;
                }
            </style>
            <div class="flex flex-col w-full h-full bg-[#f1f5f9] font-sans text-slate-700 overflow-hidden select-none">
                
                <!-- TOP HEADER ACTION BAR -->
                <div class="h-14 border-b border-slate-200 bg-white px-6 flex items-center justify-between shrink-0 z-45 shadow-sm">
                    <div class="flex items-center space-x-3">
                        <button onclick="exitEmailBuilder()" style="background-color: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;" class="p-2 hover:text-slate-900 rounded-lg transition" title="Exit Builder">
                            <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        </button>
                        <div class="border-l border-slate-200 h-6 mx-1"></div>
                        <div class="flex flex-col">
                            <input type="text" id="builder-template-name" onchange="updateTemplateDetails()" value="${templateName}" placeholder="Template Name" class="focus:border-indigo-500 focus:outline-none text-xs font-bold text-slate-800 py-0.5 max-w-[200px] transition">
                            <span id="autosave-status" class="text-[9px] text-slate-400 font-medium mt-0.5">Draft saved locally</span>
                        </div>
                    </div>

                    <!-- Responsive Mode Selectors -->
                    <div class="hidden md:flex items-center bg-slate-100 border border-slate-200 p-1 rounded-xl space-x-0.5 text-xs font-bold">
                        <button onclick="setDeviceView('desktop')" id="btn-device-desktop" style="background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" class="px-3 py-1.5 rounded-lg transition">
                            <i data-lucide="monitor" class="h-3.5 w-3.5 inline mr-1"></i>Desktop
                        </button>
                        <button onclick="setDeviceView('tablet')" id="btn-device-tablet" style="background-color: transparent !important; color: #64748b !important;" class="px-3 py-1.5 rounded-lg transition">
                            <i data-lucide="tablet" class="h-3.5 w-3.5 inline mr-1"></i>Tablet
                        </button>
                        <button onclick="setDeviceView('mobile')" id="btn-device-mobile" style="background-color: transparent !important; color: #64748b !important;" class="px-3 py-1.5 rounded-lg transition">
                            <i data-lucide="smartphone" class="h-3.5 w-3.5 inline mr-1"></i>Mobile
                        </button>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex items-center space-x-2">
                        <button onclick="builderUndo()" style="background-color: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;" class="p-2 hover:text-slate-900 rounded-lg transition" title="Undo">
                            <i data-lucide="undo-2" class="h-3.5 w-3.5"></i>
                        </button>
                        <button onclick="builderRedo()" style="background-color: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;" class="p-2 hover:text-slate-900 rounded-lg transition" title="Redo">
                            <i data-lucide="redo-2" class="h-3.5 w-3.5"></i>
                        </button>
                        <div class="border-l border-slate-200 h-6 mx-1"></div>
                        
                        <button onclick="openTestEmailModal()" style="background-color: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;" class="px-3 py-1.5 text-xs font-bold rounded-lg transition">
                            <i data-lucide="send" class="h-3.5 w-3.5 inline mr-1.5 text-blue-500"></i>Test Email
                        </button>
                        <button onclick="saveTemplateDraft()" id="save-template-btn" style="background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" class="px-4 py-1.5 text-white text-xs font-black rounded-lg shadow-md transition">
                            Save Draft
                        </button>
                    </div>
                </div>

                <!-- MAIN WORKSPACE LAYOUT -->
                <div class="flex flex-grow w-full overflow-hidden">
                    
                    <!-- LEFT PANEL - DRAGGABLE CONTROLS -->
                    <div id="builder-left-sidebar" class="w-80 border-r border-slate-200 bg-white flex flex-col shrink-0 overflow-hidden relative z-40 transition-all duration-300">
                        <!-- Search & Tabs Header -->
                        <div class="p-4 border-b border-slate-200 space-y-3">
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400"></i>
                                <input type="text" id="left-sidebar-search" oninput="searchSidebarElements(this.value)" placeholder="Search layout elements..." class="w-full pl-9 pr-4 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 placeholder-slate-400 focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="flex bg-slate-100 p-0.5 rounded-lg text-[10px] font-bold tracking-wider uppercase text-center border border-slate-200">
                                <button onclick="setLeftSidebarTab('elements')" id="tab-left-elements" style="background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" class="flex-grow py-1.5 rounded-md transition">Elements</button>
                                <button onclick="setLeftSidebarTab('sections')" id="tab-left-sections" style="background-color: transparent !important; color: #64748b !important;" class="flex-grow py-1.5 rounded-md transition">Sections</button>
                                <button onclick="setLeftSidebarTab('ai')" id="tab-left-ai" style="background-color: transparent !important; color: #64748b !important;" class="flex-grow py-1.5 rounded-md transition">AI Assistant</button>
                                <button onclick="setLeftSidebarTab('brand')" id="tab-left-brand" style="background-color: transparent !important; color: #64748b !important;" class="flex-grow py-1.5 rounded-md transition">Brand</button>
                            </div>
                        </div>

                        <!-- Sidebar tab contents -->
                        <div id="left-sidebar-scroll-area" class="flex-grow overflow-y-auto p-4 custom-scrollbar">
                            <!-- Injected dynamically based on selected tab -->
                        </div>
                    </div>

                    <!-- CENTER CANVAS - PREVIEW AREA -->
                    <div class="flex-grow bg-[#f1f5f9] flex flex-col items-center justify-start p-6 overflow-y-auto relative select-text" id="builder-canvas-viewport">
                        <!-- Canvas Responsive Resizable Frame -->
                        <div id="canvas-device-frame" class="w-full max-w-[760px] bg-[#f1f5f9] rounded-2xl shadow-2xl border border-slate-200 transition-all duration-300 flex flex-col overflow-hidden min-h-[600px] mb-12">
                            <!-- Canvas Frame Topbar -->
                            <div class="bg-white border-b border-slate-200 px-4 py-2 flex items-center justify-between text-xs text-slate-400 shrink-0">
                                <div class="flex items-center space-x-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span>
                                </div>
                                <span class="font-bold text-[10px] text-slate-500 uppercase tracking-widest" id="canvas-device-label">Desktop Preview</span>
                                <div class="w-10"></div>
                            </div>
                            
                            <!-- Main canvas dropping container -->
                            <div id="email-builder-canvas" 
                                 ondragover="onCanvasDragOver(event)"
                                 ondragleave="onCanvasDragLeave(event)"
                                 ondrop="onCanvasDrop(event)"
                                 class="flex-grow p-8 overflow-y-auto select-none bg-[#f1f5f9] min-h-[500px]" style="background-color: ${brandStyles.backgroundColor};">
                                <!-- Injected dynamically by renderCanvas() -->
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT PANEL - DYNAMIC PROPERTIES -->
                    <div id="builder-right-sidebar" class="w-80 border-l border-slate-200 bg-white flex flex-col shrink-0 overflow-hidden z-40">
                        <div class="p-4 border-b border-slate-200 shrink-0 flex items-center justify-between">
                            <h3 class="text-xs font-black tracking-wider uppercase text-slate-800 flex items-center">
                                <i data-lucide="settings" class="h-3.5 w-3.5 mr-1.5 text-indigo-500"></i>Properties Panel
                            </h3>
                            <span class="text-[9.5px] font-bold text-slate-500 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md" id="selected-type-badge">NONE</span>
                        </div>

                        <!-- Scrollable Controls Container -->
                        <div id="properties-panel-content" class="flex-grow overflow-y-auto p-4 custom-scrollbar text-xs">
                            <div class="py-12 text-center text-slate-400">
                                <i data-lucide="mouse-pointer" class="h-8 w-8 mx-auto mb-2 text-slate-300"></i>
                                <p class="font-bold text-slate-500">Select any element</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">Click any block inside the canvas to edit its properties, typography, or styling details.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
            <!-- TEST EMAIL POPUP COMPONENT -->
            <div id="test-email-modal" class="fixed inset-0 z-100 flex items-center justify-center bg-slate-950/60 backdrop-blur-xs hidden">
                <div class="bg-[#0F172A] border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl animate-fade-in text-xs font-sans text-slate-200">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-sm font-black text-white">Send Test Outbound</h4>
                        <button onclick="closeTestEmailModal()" class="text-slate-400 hover:text-white"><i data-lucide="x" class="h-4 w-4"></i></button>
                    </div>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Recipient Email</label>
                            <input type="email" id="test-recipient-email" placeholder="e.g. you@company.com" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Subject Line</label>
                            <input type="text" id="test-subject" value="${templateSubject}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div class="pt-2 flex justify-end space-x-2">
                            <button onclick="closeTestEmailModal()" class="px-4 py-2 bg-slate-850 hover:bg-slate-800 rounded-xl font-bold">Cancel</button>
                            <button onclick="submitSendTestEmail()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-750 text-white rounded-xl font-black shadow-lg" style="color:#ffffff !important;background-color:#4F46E5 !important;">Send Test</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Load content tabs & setup drag listeners
        setLeftSidebarTab(activeSidebarTab);
        renderCanvas();

        // Start Auto Saving sequence
        if (autoSaveInterval) clearInterval(autoSaveInterval);
        autoSaveInterval = setInterval(() => {
            saveTemplateDraft(true); // silent auto save
        }, 30000);
    };

    // Update state settings from name inputs
    window.updateTemplateDetails = function() {
        const inputVal = document.getElementById('builder-template-name').value.trim();
        if (inputVal) {
            templateName = inputVal;
        }
    };

    // Responsive Switcher Layout Modifier
    window.setDeviceView = function(device) {
        activeDevice = device;
        const frame = document.getElementById('canvas-device-frame');
        const label = document.getElementById('canvas-device-label');
        if (!frame) return;

        // Reset buttons active style
        const desktopBtn = document.getElementById('btn-device-desktop');
        const tabletBtn = document.getElementById('btn-device-tablet');
        const mobileBtn = document.getElementById('btn-device-mobile');

        if (desktopBtn && tabletBtn && mobileBtn) {
            desktopBtn.style.cssText = device === 'desktop' ? "background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
            tabletBtn.style.cssText = device === 'tablet' ? "background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
            mobileBtn.style.cssText = device === 'mobile' ? "background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
        }

        if (device === 'desktop') {
            frame.style.maxWidth = "760px";
            label.innerText = "Desktop Preview (760px)";
        } else if (device === 'tablet') {
            frame.style.maxWidth = "580px";
            label.innerText = "Tablet View (580px)";
        } else if (device === 'mobile') {
            frame.style.maxWidth = "380px";
            label.innerText = "Mobile View (380px)";
        }
    };

    // Left Tab Controller
    window.setLeftSidebarTab = function(tab) {
        activeSidebarTab = tab;
        const container = document.getElementById('left-sidebar-scroll-area');
        if (!container) return;

        // Reset active tabs highlight using inline style.cssText
        const elTab = document.getElementById('tab-left-elements');
        const secTab = document.getElementById('tab-left-sections');
        const aiTab = document.getElementById('tab-left-ai');
        const brandTab = document.getElementById('tab-left-brand');

        if (elTab) elTab.style.cssText = tab === 'elements' ? "background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
        if (secTab) secTab.style.cssText = tab === 'sections' ? "background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
        if (aiTab) aiTab.style.cssText = tab === 'ai' ? "background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
        if (brandTab) brandTab.style.cssText = tab === 'brand' ? "background-color: #4F46E5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";

        if (tab === 'elements') {
            renderElementsList(container);
        } else if (tab === 'sections') {
            renderSectionsList(container);
        } else if (tab === 'ai') {
            renderAISidebar(container);
        } else if (tab === 'brand') {
            renderBrandSidebar(container);
        }
    };

    // Render Draggable elements inside tab
    function renderElementsList(container, filterQuery = "") {
        const filtered = draggableElements.filter(el => el.label.toLowerCase().includes(filterQuery.toLowerCase()));
        
        container.innerHTML = `
            <div class="grid grid-cols-2 gap-3" id="draggable-items-grid">
                ${filtered.map(el => `
                    <div class="bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col items-center justify-center text-center cursor-grab active:cursor-grabbing hover:border-indigo-500 hover:bg-slate-50 transition select-none group"
                         draggable="true" 
                         ondragstart="onBuilderDragStart(event, '${el.type}')">
                        <div class="h-9 w-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-2 group-hover:scale-110 transition duration-200">
                            <i data-lucide="${el.icon}" class="h-4.5 w-4.5"></i>
                        </div>
                        <span class="font-bold text-[11px] text-slate-700">${el.label}</span>
                        <span class="text-[9px] text-slate-400 mt-1 truncate w-full">${el.desc}</span>
                    </div>
                `).join('')}
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Render Draggable pre-built sections
    function renderSectionsList(container) {
        container.innerHTML = `
            <div class="space-y-4">
                ${readySections.map((sec, idx) => `
                    <div class="bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between hover:border-indigo-500 transition select-none">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-xs text-slate-800">${sec.name}</span>
                            <span class="text-[9px] text-slate-500 uppercase font-black bg-slate-100 px-2 py-0.5 rounded">PRESET</span>
                        </div>
                        <p class="text-[10px] text-slate-450 leading-relaxed mb-3">Custom draggable elements configured as a template block ready to drag onto canvas.</p>
                        <button onclick="insertSectionDirectly(${idx})" style="background-color: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;" class="w-full py-2 hover:bg-slate-50 hover:text-slate-900 rounded-lg text-[10px] font-black tracking-wide uppercase transition">
                            Insert block into canvas
                        </button>
                    </div>
                `).join('')}
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Direct section injection helper
    window.insertSectionDirectly = function(sectionIndex) {
        recordState();
        const sec = readySections[sectionIndex];
        const newSec = {
            id: "sec_" + Date.now(),
            type: "section",
            settings: { paddingTop: "20px", paddingBottom: "20px", backgroundColor: "#ffffff" },
            elements: JSON.parse(JSON.stringify(sec.elements)).map(el => {
                el.id = "el_" + Math.random().toString(36).substr(2, 9);
                return el;
            })
        };
        canvasData.push(newSec);
        renderCanvas();
        showNotification('success', 'Inserted ready block.');
    };

    // Render AI Panel tab
    function renderAISidebar(container) {
        container.innerHTML = `
            <div class="space-y-4">
                <div class="p-3 bg-indigo-50 border border-indigo-100 rounded-xl space-y-1">
                    <span class="font-bold text-xs text-indigo-600 flex items-center">
                        <i data-lucide="sparkles" class="h-3.5 w-3.5 mr-1.5"></i>LinkPilot Copilot AI
                    </span>
                    <p class="text-[10px] text-slate-500 leading-relaxed">Let Gemini design compelling copy for your headlines, paragraphs, or call to actions directly inside your templates.</p>
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-600 text-[10px] uppercase">Goal Description</label>
                    <textarea id="ai-builder-prompt" rows="4" placeholder="e.g. Write a premium newsletter headline for black friday discount offer..." class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500"></textarea>
                </div>
                <div class="space-y-1">
                    <label class="font-bold text-slate-600 text-[10px] uppercase">Action Intent</label>
                    <select id="ai-builder-action" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-850 focus:outline-none focus:border-indigo-500">
                        <option value="subject">Generate Subject lines</option>
                        <option value="rewrite">Rewrite Text Block</option>
                        <option value="hero">Generate Headline & Subtitle</option>
                        <option value="cta">Persuasive CTA suggestions</option>
                    </select>
                </div>
                <button onclick="triggerAIBuilderGenerate()" id="btn-ai-generate" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-750 text-white rounded-xl font-black text-xs shadow-lg transition flex items-center justify-center space-x-1.5" style="color:#ffffff !important;background-color:#4F46E5 !important;">
                    <i data-lucide="sparkles" class="h-4 w-4 text-white" style="color:#ffffff !important;"></i>
                    <span>Generate AI Content</span>
                </button>
                <div id="ai-builder-response-box" class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl hidden space-y-2">
                    <div class="flex items-center justify-between text-[9px] uppercase tracking-wide text-slate-555 font-bold">
                        <span>AI Suggestion</span>
                        <button onclick="copyAICopilotText()" class="text-indigo-600 hover:text-indigo-800 transition">Copy text</button>
                    </div>
                    <div id="ai-builder-response-content" class="text-[11px] text-slate-800 leading-relaxed max-h-48 overflow-y-auto whitespace-pre-line select-text"></div>
                </div>
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Trigger AI Generation
    window.triggerAIBuilderGenerate = async function() {
        const prompt = document.getElementById('ai-builder-prompt').value.trim();
        const action = document.getElementById('ai-builder-action').value;
        const btn = document.getElementById('btn-ai-generate');
        const respBox = document.getElementById('ai-builder-response-box');
        const respContent = document.getElementById('ai-builder-response-content');

        if (!prompt) {
            showNotification('warning', 'Please write a prompt first.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="loader" class="h-4 w-4 animate-spin text-white"></i> <span class="text-white">Generating...</span>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();

        try {
            const res = await apiCall('crm/ai_builder_helper.php', 'POST', { prompt, action });
            if (res.status === 'success' && res.data && res.data.result) {
                respBox.classList.remove('hidden');
                respContent.innerText = res.data.result;
            } else {
                showNotification('error', res.message || 'AI request failed.');
            }
        } catch (e) {
            showNotification('error', e.message || 'Server error calling AI.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<i data-lucide="sparkles" class="h-4 w-4 text-white"></i> <span class="text-white">Generate AI Content</span>`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    };

    // Copy Copilot suggestions helper
    window.copyAICopilotText = function() {
        const text = document.getElementById('ai-builder-response-content').innerText;
        navigator.clipboard.writeText(text);
        showNotification('success', 'Copied suggest to clipboard.');
    };

    // Render Brand Assets Tab
    function renderBrandSidebar(container) {
        container.innerHTML = `
            <div class="space-y-4">
                <div class="space-y-3">
                    <span class="font-bold text-[10px] uppercase tracking-wide text-slate-500">Global Design Colors</span>
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-650">Primary Accent</span>
                            <div class="flex items-center space-x-2">
                                <input type="color" onchange="updateBrandStyle('primaryColor', this.value)" value="${brandStyles.primaryColor}" class="w-6 h-6 border-0 bg-transparent cursor-pointer rounded">
                                <span class="text-[10px] font-mono text-slate-500 uppercase">${brandStyles.primaryColor}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-650">Dark Secondary</span>
                            <div class="flex items-center space-x-2">
                                <input type="color" onchange="updateBrandStyle('secondaryColor', this.value)" value="${brandStyles.secondaryColor}" class="w-6 h-6 border-0 bg-transparent cursor-pointer rounded">
                                <span class="text-[10px] font-mono text-slate-500 uppercase">${brandStyles.secondaryColor}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-650">Email Canvas bg</span>
                            <div class="flex items-center space-x-2">
                                <input type="color" onchange="updateBrandStyle('backgroundColor', this.value)" value="${brandStyles.backgroundColor}" class="w-6 h-6 border-0 bg-transparent cursor-pointer rounded">
                                <span class="text-[10px] font-mono text-slate-500 uppercase">${brandStyles.backgroundColor}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="space-y-2 pt-2 border-t border-slate-200">
                    <span class="font-bold text-[10px] uppercase tracking-wide text-slate-550">Global Fonts</span>
                    <select onchange="updateBrandStyle('fontFamily', this.value)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-indigo-500">
                        <option value="Inter, Arial, sans-serif" ${brandStyles.fontFamily.includes('Inter') ? 'selected':''}>Inter (Modern Sans)</option>
                        <option value="Georgia, serif" ${brandStyles.fontFamily.includes('Georgia') ? 'selected':''}>Georgia (Editorial Serif)</option>
                        <option value="'Helvetica Neue', Arial, sans-serif" ${brandStyles.fontFamily.includes('Helvetica') ? 'selected':''}>Helvetica Neue</option>
                    </select>
                </div>
            </div>
        `;
    }

    // Update brand style helper
    window.updateBrandStyle = function(prop, val) {
        brandStyles[prop] = val;
        // Update canvas instantly
        const canvas = document.getElementById('email-builder-canvas');
        if (canvas) {
            canvas.style.fontFamily = brandStyles.fontFamily;
            if (prop === 'backgroundColor') {
                canvas.style.backgroundColor = val;
            }
        }
        renderCanvas();
    };

    // Left elements search bar helper
    window.searchSidebarElements = function(val) {
        const container = document.getElementById('left-sidebar-scroll-area');
        if (activeSidebarTab === 'elements' && container) {
            renderElementsList(container, val);
        }
    };

    // Drag start event
    window.onBuilderDragStart = function(ev, type) {
        ev.dataTransfer.setData("text", type);
        ev.dataTransfer.effectAllowed = "move";
    };

    // HTML Rendering function for Canvas
    window.renderCanvas = function() {
        const container = document.getElementById('email-builder-canvas');
        if (!container) return;

        if (canvasData.length === 0) {
            container.innerHTML = `
                <div class="py-20 border-2 border-dashed border-slate-300 rounded-2xl text-center text-slate-400 flex flex-col items-center justify-center p-6 bg-white"
                     ondragover="onCanvasDragOver(event)" 
                     ondragleave="onCanvasDragLeave(event)"
                     ondrop="onCanvasDrop(event)">
                     <i data-lucide="inbox" class="h-10 w-10 mb-2 text-indigo-300"></i>
                     <p class="font-bold text-slate-700">Your layout canvas is empty</p>
                     <p class="text-xs text-slate-400 mt-1">Drag and drop elements here from the Left Sidebar to start building your template.</p>
                </div>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }

        // Generate HTML nodes from JSON data
        let contentHtml = "";
        canvasData.forEach((sec, sIdx) => {
            let sectionSelected = (selectedSectionId === sec.id);
            let elementsHtml = "";

            if (sec.elements.length === 0) {
                elementsHtml = `
                    <div class="py-6 border border-dashed border-slate-300 rounded-lg text-center text-slate-400 text-xs bg-slate-50 flex items-center justify-center"
                         ondragover="onCanvasDragOver(event)"
                         ondragleave="onCanvasDragLeave(event)"
                         ondrop="onCanvasDrop(event, '${sec.id}', 0)">
                         Drop elements inside this section
                    </div>
                `;
            } else {
                sec.elements.forEach((el, eIdx) => {
                    let isSelected = (selectedElementId === el.id);
                    let elementInner = "";

                    switch (el.type) {
                        case 'heading':
                            elementInner = `<h2 style="margin:0; font-size:${el.settings.fontSize || '22px'}; color:${el.settings.color || '#0F172A'}; font-weight:bold; text-align:${el.settings.align || 'left'};">${el.settings.content || 'Heading Text'}</h2>`;
                            break;
                        case 'text':
                            elementInner = `<div style="font-size:${el.settings.fontSize || '15px'}; color:${el.settings.color || '#334155'}; line-height:${el.settings.lineHeight || '1.6'}; text-align:${el.settings.align || 'left'};">${el.settings.content || 'Paragraph text content details...'}</div>`;
                            break;
                        case 'image':
                            elementInner = `
                                <div style="text-align:${el.settings.align || 'center'};">
                                    <img src="${el.settings.imageUrl || 'https://placehold.co/600x300/e2e8f0/64748b?text=Image+Placeholder'}" style="max-width:100%; height:auto; border-radius:${el.settings.borderRadius || '6px'};" alt="Email Asset">
                                </div>
                            `;
                            break;
                        case 'button':
                            elementInner = `
                                <div style="text-align:${el.settings.align || 'center'};">
                                    <a href="${el.settings.link || '#'}" style="display:inline-block; font-size:14px; font-weight:bold; color:${el.settings.textColor || '#ffffff'}; background-color:${el.settings.backgroundColor || '#4F46E5'}; padding:${el.settings.paddingTop || '12px'} ${el.settings.paddingRight || '24px'} ${el.settings.paddingBottom || '12px'} ${el.settings.paddingLeft || '24px'}; border-radius:${el.settings.borderRadius || '8px'}; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.06);">${el.settings.text || 'Action Button'}</a>
                                </div>
                            `;
                            break;
                        case 'divider':
                            elementInner = `<hr style="border:0; border-top:1px solid ${el.settings.color || '#E2E8F0'}; margin:${el.settings.spacing || '15px'} 0;">`;
                            break;
                        case 'spacer':
                            elementInner = `<div style="height:${el.settings.height || '20px'};"></div>`;
                            break;
                        case 'logo':
                            elementInner = `
                                <div style="text-align:${el.settings.align || 'center'};">
                                    <img src="${el.settings.logoUrl || 'https://img.icons8.com/color/96/000000/send.png'}" style="height:${el.settings.height || '40px'}; width:auto;" alt="Company Logo">
                                </div>
                            `;
                            break;
                        case 'social':
                            elementInner = `
                                <div style="text-align:${el.settings.align || 'center'};" class="flex justify-center space-x-4">
                                    <a href="#" class="inline-block"><img src="https://img.icons8.com/color/48/000000/facebook-new.png" style="width:24px;height:24px;"></a>
                                    <a href="#" class="inline-block"><img src="https://img.icons8.com/color/48/000000/twitter.png" style="width:24px;height:24px;"></a>
                                    <a href="#" class="inline-block"><img src="https://img.icons8.com/color/48/000000/linkedin.png" style="width:24px;height:24px;"></a>
                                    <a href="#" class="inline-block"><img src="https://img.icons8.com/color/48/000000/instagram-new.png" style="width:24px;height:24px;"></a>
                                </div>
                            `;
                            break;
                        case 'coupon':
                            elementInner = `
                                <div style="background-color:${el.settings.backgroundColor || '#F1F5F9'}; border:2px dashed ${el.settings.borderColor || '#4F46E5'}; border-radius:10px; padding:20px; text-align:center;">
                                    <span style="font-size:10px; font-weight:black; text-transform:uppercase; color:#64748B; letter-spacing:1px;">Discount Coupon</span>
                                    <h3 style="font-size:24px; margin:8px 0; color:#0F172A; font-weight:extrabold;">${el.settings.discount || '50% OFF'}</h3>
                                    <p style="font-size:12px; margin:0 0 12px 0; color:#475569;">${el.settings.desc || 'Code valid on your entire inventory order.'}</p>
                                    <span style="display:inline-block; background-color:#ffffff; border:1px solid #E2E8F0; padding:6px 16px; font-family:monospace; font-size:14px; font-weight:bold; color:#0F172A; border-radius:6px; letter-spacing:1.5px;">${el.settings.code || 'WELCOME50'}</span>
                                </div>
                            `;
                            break;
                        case 'countdown':
                            elementInner = `
                                <div style="text-align:center; padding:15px 0;">
                                    <div class="flex justify-center space-x-3 text-slate-800">
                                        <div class="bg-white border border-slate-200 rounded-lg p-2 min-w-[50px] shadow-sm"><span class="text-lg font-black block">02</span><span class="text-[9px] uppercase font-bold text-slate-400">Days</span></div>
                                        <div class="bg-white border border-slate-200 rounded-lg p-2 min-w-[50px] shadow-sm"><span class="text-lg font-black block">14</span><span class="text-[9px] uppercase font-bold text-slate-400">Hours</span></div>
                                        <div class="bg-white border border-slate-200 rounded-lg p-2 min-w-[50px] shadow-sm"><span class="text-lg font-black block">48</span><span class="text-[9px] uppercase font-bold text-slate-400">Mins</span></div>
                                        <div class="bg-white border border-slate-200 rounded-lg p-2 min-w-[50px] shadow-sm"><span class="text-lg font-black block">30</span><span class="text-[9px] uppercase font-bold text-slate-400">Secs</span></div>
                                    </div>
                                    <p style="font-size:11px; color:#4F46E5; font-weight:bold; margin-top:8px;">Hurry! Offer ends soon.</p>
                                </div>
                            `;
                            break;
                        case 'hero':
                            elementInner = `
                                <div style="background-color:${el.settings.backgroundColor || '#F8FAFC'}; padding:${el.settings.padding || '24px'}; border-radius:12px; text-align:center;">
                                    <h2 style="font-size:24px; margin:0 0 10px 0; color:${el.settings.titleColor || '#0F172A'}; font-weight:bold;">${el.settings.title || 'Headline'}</h2>
                                    <p style="font-size:14px; margin:0 0 16px 0; color:${el.settings.subtitleColor || '#475569'}; line-height:1.5;">${el.settings.subtitle || 'Sub-headline details...'}</p>
                                    <a href="${el.settings.ctaLink || '#'}" style="display:inline-block; font-size:13px; font-weight:bold; color:#ffffff; background-color:#4F46E5; padding:10px 22px; border-radius:6px; text-decoration:none;">${el.settings.ctaText || 'Action'}</a>
                                </div>
                            `;
                            break;
                        case 'footer':
                            elementInner = `<p style="font-size:${el.settings.fontSize || '11px'}; color:${el.settings.color || '#94a3b8'}; line-height:1.6; text-align:${el.settings.align || 'center'}; margin:0;">${el.settings.content || 'Footer details'}</p>`;
                            break;
                    }

                    // Render Element Block Wrapper with controls
                    elementsHtml += `
                        <div class="group/element relative p-3 border-2 rounded-xl transition duration-150 cursor-pointer ${isSelected ? 'border-indigo-600 bg-indigo-500/5 shadow-md shadow-indigo-600/5' : 'border-transparent hover:border-slate-350 hover:bg-slate-100/50'}"
                             onclick="selectCanvasElement('${el.id}', event)"
                             ondragover="onCanvasDragOver(event)"
                             ondragleave="onCanvasDragLeave(event)"
                             ondrop="onCanvasDrop(event, '${sec.id}', ${eIdx})">
                            ${elementInner}

                            <!-- Element hover overlay toolbar -->
                            <div class="absolute right-2.5 top-2.5 hidden group-hover/element:flex items-center space-x-1 bg-white/95 backdrop-blur shadow-md rounded-lg p-1 border border-slate-200 select-none z-10">
                                <button onclick="cloneCanvasElement('${sec.id}', '${el.id}', event)" class="p-1 text-slate-500 hover:text-slate-900 rounded hover:bg-slate-100" title="Duplicate"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                                <button onclick="deleteCanvasElement('${sec.id}', '${el.id}', event)" class="p-1 text-rose-500 hover:text-rose-700 rounded hover:bg-rose-50" title="Delete"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                            </div>
                        </div>
                    `;
                });
            }

            // Render Section Wrapper with drag drop handles
            contentHtml += `
                <div class="group/section relative border-2 border-dashed rounded-2xl mb-5 p-4 transition-all duration-200 ${sectionSelected ? 'border-indigo-500 bg-white/70 shadow-lg' : 'border-slate-200 hover:border-slate-350 bg-white shadow-2xs'}"
                     style="padding-top:${sec.settings.paddingTop || '20px'}; padding-bottom:${sec.settings.paddingBottom || '20px'}; background-color:${sec.settings.backgroundColor || '#ffffff'}; border-radius:${sec.settings.borderRadius || '12px'};"
                     onclick="selectCanvasSection('${sec.id}', event)"
                     ondragover="onCanvasDragOver(event)"
                     ondragleave="onCanvasDragLeave(event)"
                     ondrop="onCanvasDrop(event, '${sec.id}')">
                    
                    <!-- Section Layout Area -->
                    <div class="space-y-2">
                        ${elementsHtml}
                    </div>

                    <!-- Drop Indicators -->
                    <div class="blue-drop-guide pointer-events-none absolute left-0 right-0 h-1 bg-indigo-500 rounded hidden" id="drop-indicator-${sec.id}"></div>

                    <!-- Section Actions Overlays -->
                    <div class="absolute -right-3 top-1/2 -translate-y-1/2 hidden group-hover/section:flex flex-col items-center space-y-1 bg-white border border-slate-200 rounded-lg p-1 shadow-md select-none z-20">
                        <button onclick="cloneCanvasSection('${sec.id}', event)" class="p-1 text-slate-500 hover:text-slate-950 rounded hover:bg-slate-100" title="Clone Section"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                        <button onclick="deleteCanvasSection('${sec.id}', event)" class="p-1 text-rose-500 hover:text-rose-700 rounded hover:bg-rose-50" title="Delete Section"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                    </div>
                </div>
            `;
        });

        // Add final dropping zone at bottom
        contentHtml += `
            <div class="py-8 border-2 border-dashed border-slate-300 rounded-2xl text-center text-slate-400 text-xs font-bold bg-white/40 hover:bg-white/80 hover:border-indigo-500 transition flex items-center justify-center cursor-pointer"
                 ondragover="onCanvasDragOver(event)"
                 ondragleave="onCanvasDragLeave(event)"
                 ondrop="onCanvasDrop(event, 'bottom')">
                 <i data-lucide="plus-circle" class="h-4 w-4 mr-1.5 text-slate-500"></i>Drag new elements here
            </div>
        `;

        container.innerHTML = `
            <div style="font-family:${brandStyles.fontFamily};" class="w-full">
                ${contentHtml}
            </div>
        `;

        if (typeof lucide !== 'undefined') lucide.createIcons();
    };

    // Drop over canvas handler
    window.onCanvasDragOver = function(ev) {
        ev.preventDefault();
        ev.dataTransfer.dropEffect = "move";
        const dropZone = ev.currentTarget;
        dropZone.classList.add("border-indigo-400", "bg-indigo-50/20");
    };

    // Leave canvas area handler
    window.onCanvasDragLeave = function(ev) {
        const dropZone = ev.currentTarget;
        dropZone.classList.remove("border-indigo-400", "bg-indigo-50/20");
    };

    // Drop item onto canvas
    window.onCanvasDrop = function(ev, targetSectionId = null, insertIndex = null) {
        ev.preventDefault();
        const dropZone = ev.currentTarget;
        dropZone.classList.remove("border-indigo-400", "bg-indigo-50/20");

        const elType = ev.dataTransfer.getData("text");
        if (!elType) return;

        recordState();

        // Create standard element configuration template
        const newEl = {
            id: "el_" + Math.random().toString(36).substr(2, 9),
            type: elType,
            settings: {}
        };

        // Assign default element configurations
        switch (elType) {
            case 'heading':
                newEl.settings = { content: "Main Headline Offer", fontSize: "22px", color: "#0F172A", align: "left" };
                break;
            case 'text':
                newEl.settings = { content: "This is a new paragraph element. Double click to type or edit text details directly.", fontSize: "15px", color: "#334155", lineHeight: "1.6", align: "left" };
                break;
            case 'image':
                newEl.settings = { imageUrl: "https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop", borderRadius: "8px", align: "center" };
                break;
            case 'button':
                newEl.settings = { text: "Action Link Button", link: "#", backgroundColor: "#4F46E5", textColor: "#ffffff", borderRadius: "8px", align: "center", paddingTop: "12px", paddingBottom: "12px", paddingLeft: "24px", paddingRight: "24px" };
                break;
            case 'divider':
                newEl.settings = { color: "#E2E8F0", spacing: "15px" };
                break;
            case 'spacer':
                newEl.settings = { height: "25px" };
                break;
            case 'logo':
                newEl.settings = { logoUrl: "https://img.icons8.com/color/96/000000/send.png", height: "40px", align: "center" };
                break;
            case 'social':
                newEl.settings = { align: "center" };
                break;
            case 'coupon':
                newEl.settings = { code: "LPNEW50", discount: "50% OFF", desc: "Start building and save half off first plan invoice.", backgroundColor: "#EEF2F6", borderColor: "#4F46E5" };
                break;
            case 'countdown':
                newEl.settings = { align: "center" };
                break;
        }

        if (targetSectionId === 'bottom') {
            // Drop outside sections: create a new section container
            const newSec = {
                id: "sec_" + Date.now(),
                type: "section",
                settings: { paddingTop: "20px", paddingBottom: "20px", backgroundColor: "#ffffff" },
                elements: [newEl]
            };
            canvasData.push(newSec);
            selectedElementId = newEl.id;
            selectedSectionId = newSec.id;
        } else if (targetSectionId) {
            // Drop inside existing section
            const sec = canvasData.find(s => s.id === targetSectionId);
            if (sec) {
                if (insertIndex !== null && insertIndex !== undefined) {
                    sec.elements.splice(insertIndex, 0, newEl);
                } else {
                    sec.elements.push(newEl);
                }
                selectedElementId = newEl.id;
                selectedSectionId = sec.id;
            }
        } else {
            // First drop on completely empty canvas
            const newSec = {
                id: "sec_" + Date.now(),
                type: "section",
                settings: { paddingTop: "20px", paddingBottom: "20px", backgroundColor: "#ffffff" },
                elements: [newEl]
            };
            canvasData.push(newSec);
            selectedElementId = newEl.id;
            selectedSectionId = newSec.id;
        }

        renderCanvas();
        renderPropertiesPanel();
    };

    // Duplicate element helper
    window.cloneCanvasElement = function(secId, elId, event) {
        if (event) event.stopPropagation();
        recordState();
        const sec = canvasData.find(s => s.id === secId);
        if (sec) {
            const idx = sec.elements.findIndex(e => e.id === elId);
            if (idx !== -1) {
                const elCopy = JSON.parse(JSON.stringify(sec.elements[idx]));
                elCopy.id = "el_" + Math.random().toString(36).substr(2, 9);
                sec.elements.splice(idx + 1, 0, elCopy);
                renderCanvas();
                showNotification('success', 'Duplicated block.');
            }
        }
    };

    // Delete element helper
    window.deleteCanvasElement = function(secId, elId, event) {
        if (event) event.stopPropagation();
        recordState();
        const sec = canvasData.find(s => s.id === secId);
        if (sec) {
            sec.elements = sec.elements.filter(e => e.id !== elId);
            if (selectedElementId === elId) {
                selectedElementId = null;
            }
            renderCanvas();
            renderPropertiesPanel();
            showNotification('success', 'Deleted block.');
        }
    };

    // Duplicate section helper
    window.cloneCanvasSection = function(secId, event) {
        if (event) event.stopPropagation();
        recordState();
        const idx = canvasData.findIndex(s => s.id === secId);
        if (idx !== -1) {
            const secCopy = JSON.parse(JSON.stringify(canvasData[idx]));
            secCopy.id = "sec_" + Date.now();
            secCopy.elements = secCopy.elements.map(el => {
                el.id = "el_" + Math.random().toString(36).substr(2, 9);
                return el;
            });
            canvasData.splice(idx + 1, 0, secCopy);
            renderCanvas();
            showNotification('success', 'Duplicated section.');
        }
    };

    // Delete section helper
    window.deleteCanvasSection = function(secId, event) {
        if (event) event.stopPropagation();
        recordState();
        canvasData = canvasData.filter(s => s.id !== secId);
        if (selectedSectionId === secId) {
            selectedSectionId = null;
            selectedElementId = null;
        }
        renderCanvas();
        renderPropertiesPanel();
        showNotification('success', 'Deleted section.');
    };

    // Select Canvas Element
    window.selectCanvasElement = function(id, event) {
        if (event) event.stopPropagation();
        selectedElementId = id;
        selectedSectionId = null; // Unselect section properties
        renderCanvas();
        renderPropertiesPanel();
    };

    // Select Section Element
    window.selectCanvasSection = function(id, event) {
        if (event) event.stopPropagation();
        // Only select section properties if we didn't click directly on an element
        if (event.target.closest('.group\\/element')) return;
        
        selectedSectionId = id;
        selectedElementId = null; // Unselect element properties
        renderCanvas();
        renderPropertiesPanel();
    };

    // Render Right Panel Properties controls dynamically
    function renderPropertiesPanel() {
        const container = document.getElementById('properties-panel-content');
        const badge = document.getElementById('selected-type-badge');
        if (!container || !badge) return;

        if (selectedElementId) {
            // Find selected element
            let selectedEl = null;
            let parentSec = null;
            for (const sec of canvasData) {
                const el = sec.elements.find(e => e.id === selectedElementId);
                if (el) {
                    selectedEl = el;
                    parentSec = sec;
                    break;
                }
            }

            if (!selectedEl) return;

            badge.innerText = selectedEl.type.toUpperCase();
            badge.className = "text-[9.5px] font-bold text-indigo-400 bg-indigo-950 border border-indigo-900 px-2 py-0.5 rounded-md";

            let controlsHtml = "";

            switch (selectedEl.type) {
                case 'heading':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Heading Content</label>
                                <textarea oninput="updateElementSetting('content', this.value)" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">${selectedEl.settings.content || ''}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Font Size</label>
                                    <input type="text" oninput="updateElementSetting('fontSize', this.value)" value="${selectedEl.settings.fontSize || '22px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Text Align</label>
                                    <select onchange="updateElementSetting('align', this.value)" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                        <option value="left" ${selectedEl.settings.align === 'left'?'selected':''}>Left</option>
                                        <option value="center" ${selectedEl.settings.align === 'center'?'selected':''}>Center</option>
                                        <option value="right" ${selectedEl.settings.align === 'right'?'selected':''}>Right</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Text Color</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" onchange="updateElementSetting('color', this.value)" value="${selectedEl.settings.color || '#000000'}" class="w-7 h-7 border-0 bg-transparent cursor-pointer rounded">
                                    <span class="text-[10px] font-mono uppercase text-slate-400">${selectedEl.settings.color || '#0F172A'}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'text':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Body Content (HTML tags supported)</label>
                                <textarea oninput="updateElementSetting('content', this.value)" rows="6" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">${selectedEl.settings.content || ''}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Font Size</label>
                                    <input type="text" oninput="updateElementSetting('fontSize', this.value)" value="${selectedEl.settings.fontSize || '15px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Line Height</label>
                                    <input type="text" oninput="updateElementSetting('lineHeight', this.value)" value="${selectedEl.settings.lineHeight || '1.6'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Text Align</label>
                                    <select onchange="updateElementSetting('align', this.value)" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                        <option value="left" ${selectedEl.settings.align === 'left'?'selected':''}>Left</option>
                                        <option value="center" ${selectedEl.settings.align === 'center'?'selected':''}>Center</option>
                                        <option value="right" ${selectedEl.settings.align === 'right'?'selected':''}>Right</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Text Color</label>
                                    <div class="flex items-center space-x-2">
                                        <input type="color" onchange="updateElementSetting('color', this.value)" value="${selectedEl.settings.color || '#334155'}" class="w-7 h-7 border-0 bg-transparent cursor-pointer rounded">
                                        <span class="text-[10px] font-mono uppercase text-slate-400">${selectedEl.settings.color || '#334155'}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'image':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Image Source URL</label>
                                <input type="text" oninput="updateElementSetting('imageUrl', this.value)" value="${selectedEl.settings.imageUrl || ''}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Border Radius</label>
                                    <input type="text" oninput="updateElementSetting('borderRadius', this.value)" value="${selectedEl.settings.borderRadius || '6px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Align</label>
                                    <select onchange="updateElementSetting('align', this.value)" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                        <option value="left" ${selectedEl.settings.align === 'left'?'selected':''}>Left</option>
                                        <option value="center" ${selectedEl.settings.align === 'center'?'selected':''}>Center</option>
                                        <option value="right" ${selectedEl.settings.align === 'right'?'selected':''}>Right</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'button':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Button Text</label>
                                <input type="text" oninput="updateElementSetting('text', this.value)" value="${selectedEl.settings.text || ''}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Link URL</label>
                                <input type="text" oninput="updateElementSetting('link', this.value)" value="${selectedEl.settings.link || ''}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Background</label>
                                    <div class="flex items-center space-x-1.5">
                                        <input type="color" onchange="updateElementSetting('backgroundColor', this.value)" value="${selectedEl.settings.backgroundColor || '#4F46E5'}" class="w-6 h-6 border-0 bg-transparent cursor-pointer rounded">
                                        <span class="text-[9px] font-mono uppercase text-slate-400">${selectedEl.settings.backgroundColor || '#4F46E5'}</span>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Text Color</label>
                                    <div class="flex items-center space-x-1.5">
                                        <input type="color" onchange="updateElementSetting('textColor', this.value)" value="${selectedEl.settings.textColor || '#ffffff'}" class="w-6 h-6 border-0 bg-transparent cursor-pointer rounded">
                                        <span class="text-[9px] font-mono uppercase text-slate-400">${selectedEl.settings.textColor || '#ffffff'}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Radius</label>
                                    <input type="text" oninput="updateElementSetting('borderRadius', this.value)" value="${selectedEl.settings.borderRadius || '8px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Align</label>
                                    <select onchange="updateElementSetting('align', this.value)" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                        <option value="left" ${selectedEl.settings.align === 'left'?'selected':''}>Left</option>
                                        <option value="center" ${selectedEl.settings.align === 'center'?'selected':''}>Center</option>
                                        <option value="right" ${selectedEl.settings.align === 'right'?'selected':''}>Right</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'logo':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Logo Image URL</label>
                                <input type="text" oninput="updateElementSetting('logoUrl', this.value)" value="${selectedEl.settings.logoUrl || ''}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Height</label>
                                    <input type="text" oninput="updateElementSetting('height', this.value)" value="${selectedEl.settings.height || '40px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Align</label>
                                    <select onchange="updateElementSetting('align', this.value)" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                        <option value="left" ${selectedEl.settings.align === 'left'?'selected':''}>Left</option>
                                        <option value="center" ${selectedEl.settings.align === 'center'?'selected':''}>Center</option>
                                        <option value="right" ${selectedEl.settings.align === 'right'?'selected':''}>Right</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'coupon':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Discount Headline</label>
                                <input type="text" oninput="updateElementSetting('discount', this.value)" value="${selectedEl.settings.discount || '50% OFF'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Coupon Code</label>
                                <input type="text" oninput="updateElementSetting('code', this.value)" value="${selectedEl.settings.code || ''}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Description</label>
                                <textarea oninput="updateElementSetting('desc', this.value)" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">${selectedEl.settings.desc || ''}</textarea>
                            </div>
                        </div>
                    `;
                    break;
                case 'divider':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Divider Line Color</label>
                                <div class="flex items-center space-x-2">
                                    <input type="color" onchange="updateElementSetting('color', this.value)" value="${selectedEl.settings.color || '#E2E8F0'}" class="w-7 h-7 border-0 bg-transparent cursor-pointer rounded">
                                    <span class="text-[10px] font-mono uppercase text-slate-400">${selectedEl.settings.color || '#E2E8F0'}</span>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Vertical spacing margin</label>
                                <input type="text" oninput="updateElementSetting('spacing', this.value)" value="${selectedEl.settings.spacing || '15px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>
                    `;
                    break;
                case 'spacer':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Spacer Height (px)</label>
                                <input type="text" oninput="updateElementSetting('height', this.value)" value="${selectedEl.settings.height || '20px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>
                    `;
                    break;
                case 'hero':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Hero Headline</label>
                                <input type="text" oninput="updateElementSetting('title', this.value)" value="${selectedEl.settings.title || ''}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Hero Subtitle</label>
                                <textarea oninput="updateElementSetting('subtitle', this.value)" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">${selectedEl.settings.subtitle || ''}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">CTA Text</label>
                                    <input type="text" oninput="updateElementSetting('ctaText', this.value)" value="${selectedEl.settings.ctaText || ''}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">CTA Link</label>
                                    <input type="text" oninput="updateElementSetting('ctaLink', this.value)" value="${selectedEl.settings.ctaLink || ''}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'footer':
                    controlsHtml = `
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Footer Text</label>
                                <textarea oninput="updateElementSetting('content', this.value)" rows="5" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500">${selectedEl.settings.content || ''}</textarea>
                            </div>
                        </div>
                    `;
                    break;
            }

            container.innerHTML = controlsHtml;

        } else if (selectedSectionId) {
            // Find selected section
            const selectedSec = canvasData.find(s => s.id === selectedSectionId);
            if (!selectedSec) return;

            badge.innerText = "SECTION";
            badge.className = "text-[9.5px] font-bold text-amber-400 bg-amber-950 border border-amber-900 px-2 py-0.5 rounded-md";

            container.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Padding Top</label>
                            <input type="text" oninput="updateSectionSetting('paddingTop', this.value)" value="${selectedSec.settings.paddingTop || '20px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Padding Bottom</label>
                            <input type="text" oninput="updateSectionSetting('paddingBottom', this.value)" value="${selectedSec.settings.paddingBottom || '20px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="font-bold text-slate-300">Section Background Color</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" onchange="updateSectionSetting('backgroundColor', this.value)" value="${selectedSec.settings.backgroundColor || '#ffffff'}" class="w-7 h-7 border-0 bg-transparent cursor-pointer rounded">
                            <span class="text-[10px] font-mono uppercase text-slate-400">${selectedSec.settings.backgroundColor || '#ffffff'}</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <label class="font-bold text-slate-300">Border Radius</label>
                        <input type="text" oninput="updateSectionSetting('borderRadius', this.value)" value="${selectedSec.settings.borderRadius || '12px'}" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
            `;
        }
    }

    // Update settings property helper for elements
    window.updateElementSetting = function(prop, val) {
        if (!selectedElementId) return;
        
        // Find selected element and update
        for (const sec of canvasData) {
            const el = sec.elements.find(e => e.id === selectedElementId);
            if (el) {
                el.settings[prop] = val;
                break;
            }
        }
        renderCanvas();
    };

    // Update settings property helper for sections
    window.updateSectionSetting = function(prop, val) {
        if (!selectedSectionId) return;

        const sec = canvasData.find(s => s.id === selectedSectionId);
        if (sec) {
            sec.settings[prop] = val;
        }
        renderCanvas();
    };

    // Autosave Status label renderer
    function updateAutoSaveStatus(statusText = "Draft saved locally") {
        const el = document.getElementById('autosave-status');
        if (el) {
            el.innerText = statusText;
        }
    }

    // Save Template to Database (Insert/Update)
    window.saveTemplateDraft = async function(isSilent = false) {
        if (isSaving) return;
        isSaving = true;

        if (!isSilent) {
            updateAutoSaveStatus("Saving template...");
            const saveBtn = document.getElementById('save-template-btn');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerText = "Saving...";
            }
        }

        // Generate Compiled HTML layout
        const compiledHtml = compileResponsiveHtml(canvasData);

        const payload = {
            id: activeTemplateId,
            name: templateName,
            subject: templateSubject,
            category: templateCategory,
            tag: templateTag,
            json_data: JSON.stringify(canvasData),
            html_content: compiledHtml
        };

        try {
            const res = await apiCall('crm/save_custom_template.php', 'POST', payload);
            if (res.status === 'success' && res.data && res.data.id) {
                activeTemplateId = res.data.id;
                updateAutoSaveStatus("All changes saved");
                if (!isSilent) {
                    showNotification('success', 'Custom template saved successfully.');
                }
            } else {
                if (!isSilent) {
                    showNotification('error', res.message || 'Failed to save template.');
                }
                updateAutoSaveStatus("Save failed");
            }
        } catch (e) {
            if (!isSilent) {
                showNotification('error', e.message || 'Server connection error.');
            }
            updateAutoSaveStatus("Save failed");
        } finally {
            isSaving = false;
            if (!isSilent) {
                const saveBtn = document.getElementById('save-template-btn');
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerText = "Save Draft";
                }
            }
        }
    };

    // Open Test Email Modal
    window.openTestEmailModal = function() {
        const modal = document.getElementById('test-email-modal');
        if (modal) {
            modal.classList.remove('hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    };

    // Close Test Email Modal
    window.closeTestEmailModal = function() {
        const modal = document.getElementById('test-email-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    // Submit send test email
    window.submitSendTestEmail = async function() {
        const recipient = document.getElementById('test-recipient-email').value.trim();
        const subject = document.getElementById('test-subject').value.trim();

        if (!recipient) {
            showNotification('warning', 'Please enter recipient email.');
            return;
        }

        // Compile HTML layout
        const compiledHtml = compileResponsiveHtml(canvasData);

        // Send test request to server SMTP helper
        try {
            showNotification('info', 'Sending test email...');
            closeTestEmailModal();

            const res = await apiCall('crm/email_intelligence/emails.php', 'POST', {
                action: 'send_outbound',
                to: recipient,
                subject: subject || templateSubject || 'LinkPilot Test Email',
                body: compiledHtml
            });

            if (res.status === 'success') {
                showNotification('success', 'Test email sent successfully.');
            } else {
                showNotification('error', res.message || 'Failed to send test email.');
            }
        } catch (err) {
            showNotification('error', err.message || 'An error occurred during send.');
        }
    };

    // Exit Builder and restore dashboard
    window.exitEmailBuilder = function() {
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
            autoSaveInterval = null;
        }
        
        // Restore global chat button
        const globalChat = document.getElementById('ai-chat-trigger-btn');
        if (globalChat) globalChat.classList.remove('hidden');

        // Route back to Templates listing
        location.hash = '#/email-templates';
    };

    // COMPILER ENGINE - Compile Canvas elements tree into responsive HTML Tables
    function compileResponsiveHtml(data) {
        let rowsHtml = "";

        data.forEach(sec => {
            let elementsHtml = "";

            sec.elements.forEach(el => {
                let inner = "";

                switch (el.type) {
                    case 'heading':
                        inner = `
                            <tr>
                                <td align="${el.settings.align || 'left'}" style="padding: 10px 0; font-family: ${brandStyles.fontFamily}; font-size: ${el.settings.fontSize || '22px'}; font-weight: bold; color: ${el.settings.color || '#0F172A'};">
                                    ${el.settings.content || 'Heading Title'}
                                </td>
                            </tr>
                        `;
                        break;
                    case 'text':
                        inner = `
                            <tr>
                                <td align="${el.settings.align || 'left'}" style="padding: 10px 0; font-family: ${brandStyles.fontFamily}; font-size: ${el.settings.fontSize || '15px'}; line-height: ${el.settings.lineHeight || '1.6'}; color: ${el.settings.color || '#334155'};">
                                    ${el.settings.content || 'Paragraph body content details...'}
                                </td>
                            </tr>
                        `;
                        break;
                    case 'image':
                        inner = `
                            <tr>
                                <td align="${el.settings.align || 'center'}" style="padding: 10px 0;">
                                    <img src="${el.settings.imageUrl || 'https://placehold.co/600x300/e2e8f0/64748b?text=Image+Placeholder'}" style="display: block; max-width: 100%; height: auto; border-radius: ${el.settings.borderRadius || '6px'};" alt="Email Asset">
                                </td>
                            </tr>
                        `;
                        break;
                    case 'button':
                        inner = `
                            <tr>
                                <td align="${el.settings.align || 'center'}" style="padding: 15px 0;">
                                    <table border="0" cellpadding="0" cellspacing="0" style="display: inline-block;">
                                        <tr>
                                            <td align="center" bgcolor="${el.settings.backgroundColor || '#4F46E5'}" style="border-radius: ${el.settings.borderRadius || '8px'};">
                                                <a href="${el.settings.link || '#'}" target="_blank" style="display: inline-block; padding: ${el.settings.paddingTop || '12px'} ${el.settings.paddingRight || '24px'} ${el.settings.paddingBottom || '12px'} ${el.settings.paddingLeft || '24px'}; font-family: ${brandStyles.fontFamily}; font-size: 14px; font-weight: bold; color: ${el.settings.textColor || '#ffffff'}; text-decoration: none;">
                                                    ${el.settings.text || 'Action Button'}
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        `;
                        break;
                    case 'divider':
                        inner = `
                            <tr>
                                <td style="padding: ${el.settings.spacing || '15px'} 0;">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td style="border-top: 1px solid ${el.settings.color || '#E2E8F0'}; height: 1px; line-height: 1px; font-size: 1px;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        `;
                        break;
                    case 'spacer':
                        inner = `
                            <tr>
                                <td style="height: ${el.settings.height || '20px'}; font-size: 1px; line-height: 1px;">&nbsp;</td>
                            </tr>
                        `;
                        break;
                    case 'logo':
                        inner = `
                            <tr>
                                <td align="${el.settings.align || 'center'}" style="padding: 10px 0;">
                                    <img src="${el.settings.logoUrl || 'https://img.icons8.com/color/96/000000/send.png'}" style="display: block; height: ${el.settings.height || '40px'}; width: auto;" alt="Logo">
                                </td>
                            </tr>
                        `;
                        break;
                    case 'social':
                        inner = `
                            <tr>
                                <td align="${el.settings.align || 'center'}" style="padding: 15px 0;">
                                    <table border="0" cellpadding="0" cellspacing="0" style="display: inline-block;">
                                        <tr>
                                            <td style="padding: 0 8px;"><a href="#" target="_blank"><img src="https://img.icons8.com/color/48/000000/facebook-new.png" width="24" height="24" style="display: block;" alt="FB"></a></td>
                                            <td style="padding: 0 8px;"><a href="#" target="_blank"><img src="https://img.icons8.com/color/48/000000/twitter.png" width="24" height="24" style="display: block;" alt="TW"></a></td>
                                            <td style="padding: 0 8px;"><a href="#" target="_blank"><img src="https://img.icons8.com/color/48/000000/linkedin.png" width="24" height="24" style="display: block;" alt="LN"></a></td>
                                            <td style="padding: 0 8px;"><a href="#" target="_blank"><img src="https://img.icons8.com/color/48/000000/instagram-new.png" width="24" height="24" style="display: block;" alt="IG"></a></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        `;
                        break;
                    case 'coupon':
                        inner = `
                            <tr>
                                <td style="padding: 15px 0;">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="${el.settings.backgroundColor || '#F1F5F9'}" style="border: 2px dashed ${el.settings.borderColor || '#4F46E5'}; border-radius: 10px; text-align: center;">
                                        <tr>
                                            <td style="padding: 24px;">
                                                <span style="font-family: ${brandStyles.fontFamily}; font-size: 10px; font-weight: bold; text-transform: uppercase; color: #64748B; letter-spacing: 1px;">Discount Coupon</span>
                                                <h3 style="font-family: ${brandStyles.fontFamily}; font-size: 26px; margin: 8px 0; color: #0F172A; font-weight: 850;">${el.settings.discount || '50% OFF'}</h3>
                                                <p style="font-family: ${brandStyles.fontFamily}; font-size: 13px; margin: 0 0 16px 0; color: #475569;">${el.settings.desc || ''}</p>
                                                <table border="0" cellpadding="0" cellspacing="0" style="display: inline-block;">
                                                    <tr>
                                                        <td bgcolor="#ffffff" style="border: 1px solid #E2E8F0; padding: 8px 20px; font-family: monospace; font-size: 15px; font-weight: bold; color: #0F172A; border-radius: 6px; letter-spacing: 1.5px;">
                                                            ${el.settings.code || 'WELCOME50'}
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        `;
                        break;
                    case 'hero':
                        inner = `
                            <tr>
                                <td style="padding: 15px 0;">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="${el.settings.backgroundColor || '#F8FAFC'}" style="border-radius: 12px; text-align: center;">
                                        <tr>
                                            <td style="padding: 30px 24px;">
                                                <h2 style="font-family: ${brandStyles.fontFamily}; font-size: 24px; margin: 0 0 10px 0; color: ${el.settings.titleColor || '#0F172A'}; font-weight: bold;">${el.settings.title || ''}</h2>
                                                <p style="font-family: ${brandStyles.fontFamily}; font-size: 14px; margin: 0 0 20px 0; color: ${el.settings.subtitleColor || '#475569'}; line-height: 1.5;">${el.settings.subtitle || ''}</p>
                                                <table border="0" cellpadding="0" cellspacing="0" style="display: inline-block;">
                                                    <tr>
                                                        <td bgcolor="#4F46E5" style="border-radius: 6px;">
                                                            <a href="${el.settings.ctaLink || '#'}" target="_blank" style="display: inline-block; padding: 10px 22px; font-family: ${brandStyles.fontFamily}; font-size: 13px; font-weight: bold; color: #ffffff; text-decoration: none;">
                                                                ${el.settings.ctaText || 'Get Started'}
                                                            </a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        `;
                        break;
                    case 'footer':
                        inner = `
                            <tr>
                                <td align="${el.settings.align || 'center'}" style="padding: 20px 0; font-family: ${brandStyles.fontFamily}; font-size: ${el.settings.fontSize || '11px'}; color: ${el.settings.color || '#94a3b8'}; line-height: 1.6;">
                                    ${el.settings.content || 'Footer copy text info'}
                                </td>
                            </tr>
                        `;
                        break;
                }

                elementsHtml += inner;
            });

            rowsHtml += `
                <!-- Section Container Row -->
                <tr>
                    <td bgcolor="${sec.settings.backgroundColor || '#ffffff'}" style="padding: ${sec.settings.paddingTop || '20px'} 40px ${sec.settings.paddingBottom || '20px'} 40px; border-radius: ${sec.settings.borderRadius || '12px'};">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            ${elementsHtml}
                        </table>
                    </td>
                </tr>
                <!-- Vertical gap row -->
                <tr><td style="height: 15px; font-size: 1px; line-height: 1px;">&nbsp;</td></tr>
            `;
        });

        // Combined output responsive HTML boiler wrapper
        return `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>${templateName}</title>
            <style>
                body { margin: 0; padding: 0; background-color: ${brandStyles.backgroundColor}; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                table, td { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
                img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
                p { display: block; margin: 13px 0; }
                @media only screen and (max-width: 600px) {
                    .email-container { width: 100% !important; padding: 10px !important; }
                }
            </style>
        </head>
        <body style="margin: 0; padding: 30px 10px; background-color: ${brandStyles.backgroundColor}; font-family: ${brandStyles.fontFamily};">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="center" valign="top">
                        <table class="email-container" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 600px;">
                            ${rowsHtml}
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        `;
    }

})();
