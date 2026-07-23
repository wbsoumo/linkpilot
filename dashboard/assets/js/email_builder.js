(function() {
    // Visual Email Builder State Variables
    let canvasData = [];
    let undoStack = [];
    let redoStack = [];
    let selectedElementId = null;
    let selectedSectionId = null;
    let activeSidebarTab = 'elements'; // elements, sections, templates, blocks, brand, ai
    let activeDevice = 'desktop'; // desktop, tablet, mobile
    let activeTemplateId = null;
    let isSaving = false;
    let autoSaveInterval = null;
    let isCodeViewActive = false;
    let customHtmlOverride = null;

    // Template metadata
    let templateName = "New Campaign Layout";
    let templateSubject = "Exclusive updates from our team";
    let templateCategory = "Sales";
    let templateTag = "Outreach";

    // Global Brand Kit Styles
    let brandStyles = {
        primaryColor: "#6D5EF5", // LinkPilot Purple
        secondaryColor: "#0F172A",
        backgroundColor: "#f1f5f9",
        fontFamily: "Inter, system-ui, -apple-system, sans-serif"
    };

    // Elements Library definitions
    const draggableElements = [
        { type: "heading", label: "Heading", icon: "heading", desc: "Main title text" },
        { type: "text", label: "Text", icon: "align-left", desc: "Longform message block" },
        { type: "image", label: "Image", icon: "image", desc: "Custom image upload" },
        { type: "button", label: "Button", icon: "mouse-pointer", desc: "Action conversion link" },
        { type: "divider", label: "Divider", icon: "minus", desc: "Horizontal separator line" },
        { type: "spacer", label: "Spacer", icon: "move", desc: "Vertical spacing height" },
        { type: "columns", label: "Columns", icon: "columns", desc: "Multi-column layout wrapper" },
        { type: "social", label: "Social", icon: "share-2", desc: "Social media sharing links" },
        { type: "html", label: "HTML", icon: "code", desc: "Embed custom HTML script" },
        { type: "menu", label: "Menu", icon: "menu", desc: "Navigation menu header" },
        { type: "icon", label: "Icon", icon: "star", desc: "Simple icon decorator" },
        { type: "video", label: "Video", icon: "video", desc: "Embed hosted video link" },
        { type: "countdown", label: "Countdown", icon: "clock", desc: "Promotional urgency timer" },
        { type: "product", label: "Product", icon: "shopping-bag", desc: "Feature direct product card" },
        { type: "coupon", label: "Coupon", icon: "ticket", desc: "Promotional discount card" },
        { type: "signature", label: "Signature", icon: "pen-tool", desc: "Add personal sender signature" },
        { type: "faq", label: "FAQ", icon: "help-circle", desc: "Accordion questions and answers" },
        { type: "testimonial", label: "Testimonial", icon: "message-square", desc: "Add customer review block" }
    ];

    // Pre-built preset sections templates
    const readySections = [
        {
            name: "Hero Banner Accent",
            elements: [
                {
                    type: "logo",
                    settings: { logoUrl: "https://img.icons8.com/color/96/000000/send.png", height: "36px", align: "center" }
                },
                {
                    type: "heading",
                    settings: { content: "Build Emails That Drive Real Results", fontSize: "32px", color: "#0f172a", align: "center" }
                },
                {
                    type: "text",
                    settings: { content: "Create stunning, responsive email campaigns in minutes with our powerful drag & drop builder.", fontSize: "16px", color: "#475569", align: "center" }
                },
                {
                    type: "button",
                    settings: { text: "Start Building Now →", link: "#", backgroundColor: "#6D5EF5", textColor: "#ffffff", borderRadius: "8px", align: "center", paddingTop: "12px", paddingBottom: "12px", paddingLeft: "24px", paddingRight: "24px" }
                }
            ]
        },
        {
            name: "Coupon Promo",
            elements: [
                {
                    type: "coupon",
                    settings: {
                        code: "WELCOME50",
                        discount: "50% OFF",
                        desc: "Apply this coupon code at checkout for your first order.",
                        backgroundColor: "#EEF2F6",
                        borderColor: "#6D5EF5"
                    }
                }
            ]
        },
        {
            name: "Call to Action Accent",
            elements: [
                {
                    type: "heading",
                    settings: { content: "Ready to accelerate outreach?", fontSize: "20px", color: "#0f172a", align: "center" }
                },
                {
                    type: "button",
                    settings: { text: "Upgrade Plan Now", link: "#", backgroundColor: "#10B981", textColor: "#ffffff", borderRadius: "6px", align: "center" }
                }
            ]
        }
    ];

    // Record builder state for Undo / Redo mechanics
    function recordState() {
        undoStack.push(JSON.stringify(canvasData));
        redoStack = []; // Clear redo stack on new action
        updateAutoSaveStatus();
    }

    // Undo action trigger
    window.builderUndo = function() {
        if (undoStack.length === 0) return;
        redoStack.push(JSON.stringify(canvasData));
        canvasData = JSON.parse(undoStack.pop());
        renderCanvas();
        selectedElementId = null;
        renderPropertiesPanel();
    };

    // Redo action trigger
    window.builderRedo = function() {
        if (redoStack.length === 0) return;
        undoStack.push(JSON.stringify(canvasData));
        canvasData = JSON.parse(redoStack.pop());
        renderCanvas();
        selectedElementId = null;
        renderPropertiesPanel();
    };

    // Main route visual entry point
    window.renderEmailBuilder = async function(container, templateId = null) {
        activeTemplateId = templateId;
        selectedElementId = null;
        selectedSectionId = null;
        
        // Hide standard bottom right AI assistant launcher to avoid layout overlay collisions
        const globalChat = document.getElementById('ai-chat-trigger-btn');
        if (globalChat) globalChat.classList.add('hidden');

        // Fetch template details if templateId is specified
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
                        const parsed = JSON.parse(t.json_data);
                        if (parsed && parsed.isRawHtml) {
                            customHtmlOverride = parsed.customHtml;
                            isCodeViewActive = true;
                            // Set Code View button styling when editor renders
                            setTimeout(() => {
                                const btn = document.getElementById('btn-canvas-code-view');
                                if (btn) btn.style.cssText = "background-color: #6D5EF5 !important; color: #ffffff !important; font-weight: bold !important;";
                            }, 100);
                        } else {
                            canvasData = parsed;
                        }
                    }
                }
            } catch (err) {
                console.error("Failed to load custom template details", err);
            }
        } else {
            templateName = "New Campaign Layout";
            templateSubject = "Exclusive updates from our team";
            // Initialize with default standard layout tree
            canvasData = [
                {
                    id: "sec_default",
                    type: "section",
                    settings: { paddingTop: "20px", paddingBottom: "20px", backgroundColor: "#ffffff" },
                    elements: [
                        {
                            id: "el_logo",
                            type: "logo",
                            settings: { logoUrl: "https://img.icons8.com/color/96/000000/send.png", height: "36px", align: "center" }
                        },
                        {
                            id: "el_head",
                            type: "heading",
                            settings: { content: "Build Emails That Drive Real Results", fontSize: "32px", color: "#0F172A", align: "center" }
                        },
                        {
                            id: "el_txt",
                            type: "text",
                            settings: { content: "Create stunning, responsive email campaigns in minutes with our powerful drag & drop builder.", fontSize: "16px", color: "#475569", align: "center" }
                        },
                        {
                            id: "el_btn",
                            type: "button",
                            settings: { text: "Start Building Now →", link: "#", backgroundColor: "#6D5EF5", textColor: "#ffffff", borderRadius: "8px", align: "center", paddingTop: "12px", paddingBottom: "12px", paddingLeft: "24px", paddingRight: "24px" }
                        }
                    ]
                }
            ];
        }

        // Draw structural layout HTML
        container.innerHTML = `
            <style>
                /* Enterprise light theme variables and overrides */
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
                    border-color: #6D5EF5 !important;
                    box-shadow: 0 0 0 2px rgba(109, 94, 245, 0.15) !important;
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
                .h-14 button:not(#save-template-btn):not(#save-exit-btn) {
                    background-color: #ffffff !important;
                    color: #475569 !important;
                    border: 1px solid #cbd5e1 !important;
                }
                .h-14 button:not(#save-template-btn):not(#save-exit-btn):hover {
                    background-color: #f8fafc !important;
                    color: #0f172a !important;
                }
            </style>
            <div class="flex flex-col w-full h-full bg-[#f8fafc] font-sans text-slate-700 overflow-hidden select-none">
                
                <!-- TOP HEADER ACTION BAR -->
                <div class="h-14 border-b border-slate-200 bg-white px-6 flex items-center justify-between shrink-0 z-45 shadow-sm">
                    <div class="flex items-center space-x-3">
                        <button onclick="exitEmailBuilder()" style="background-color: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;" class="p-2 hover:text-slate-900 rounded-lg transition" title="Exit Builder">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                        <div class="border-l border-slate-200 h-6 mx-1"></div>
                        <div class="flex items-center space-x-2">
                            <span class="font-extrabold text-sm text-[#0F172A] flex items-center">
                                <i data-lucide="send" class="h-4 w-4 mr-1.5 text-[#6D5EF5]"></i>LinkPilot
                            </span>
                            <span class="text-xs text-slate-400 font-medium">Email Builder</span>
                            <i data-lucide="edit-3" class="h-3 w-3 text-slate-400 cursor-pointer ml-1"></i>
                        </div>
                        <div class="border-l border-slate-200 h-6 mx-1"></div>
                        <div class="flex items-center space-x-1.5">
                            <span id="autosave-status" class="text-[10px] text-slate-500 font-medium flex items-center">
                                <i data-lucide="check-circle-2" class="h-3.5 w-3.5 mr-1 text-emerald-500"></i>Saved
                            </span>
                        </div>
                    </div>

                    <!-- Responsive Mode Selectors -->
                    <div class="hidden md:flex items-center bg-slate-100 border border-slate-200 p-1 rounded-xl space-x-0.5 text-xs font-bold">
                        <button onclick="setDeviceView('desktop')" id="btn-device-desktop" style="background-color: #6D5EF5 !important; color: #ffffff !important; font-weight: bold !important;" class="px-3 py-1.5 rounded-lg transition">
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
                        <button onclick="saveTemplateDraft()" id="save-template-btn" style="background-color: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;" class="px-3 py-1.5 text-xs font-bold rounded-lg transition">
                            <i data-lucide="save" class="h-3.5 w-3.5 inline mr-1.5 text-slate-500"></i>Save
                        </button>
                        <button onclick="exitEmailBuilder()" id="save-exit-btn" style="background-color: #6D5EF5 !important; color: #ffffff !important; font-weight: bold !important;" class="px-4 py-1.5 text-white text-xs font-black rounded-lg shadow-md transition flex items-center">
                            Save & Exit <i data-lucide="chevron-down" class="h-3 w-3 ml-1.5"></i>
                        </button>
                    </div>
                </div>

                <!-- MAIN WORKSPACE LAYOUT -->
                <div class="flex flex-grow w-full overflow-hidden">
                    
                    <!-- 1. LEFT NAVIGATION RAIL -->
                    <div class="w-16 border-r border-slate-200 bg-white flex flex-col items-center py-4 space-y-4 shrink-0">
                        <button onclick="setLeftSidebarTab('elements')" id="rail-tab-elements" style="background-color: #indigo-50;" class="p-3.5 rounded-xl text-[#6D5EF5] transition-all relative group" title="Elements">
                            <i data-lucide="layout-grid" class="h-5 w-5"></i>
                            <span class="absolute left-16 bg-slate-900 text-white text-[10px] px-2 py-1 rounded hidden group-hover:block whitespace-nowrap z-50">Elements</span>
                        </button>
                        <button onclick="setLeftSidebarTab('sections')" id="rail-tab-sections" class="p-3.5 rounded-xl text-slate-400 hover:text-slate-800 transition-all relative group" title="Sections">
                            <i data-lucide="layout-template" class="h-5 w-5"></i>
                            <span class="absolute left-16 bg-slate-900 text-white text-[10px] px-2 py-1 rounded hidden group-hover:block whitespace-nowrap z-50">Sections</span>
                        </button>
                        <button onclick="setLeftSidebarTab('templates')" id="rail-tab-templates" class="p-3.5 rounded-xl text-slate-400 hover:text-slate-800 transition-all relative group" title="Templates">
                            <i data-lucide="folder-open" class="h-5 w-5"></i>
                            <span class="absolute left-16 bg-slate-900 text-white text-[10px] px-2 py-1 rounded hidden group-hover:block whitespace-nowrap z-50">Templates</span>
                        </button>
                        <button onclick="setLeftSidebarTab('blocks')" id="rail-tab-blocks" class="p-3.5 rounded-xl text-slate-400 hover:text-slate-800 transition-all relative group" title="Saved Blocks">
                            <i data-lucide="database" class="h-5 w-5"></i>
                            <span class="absolute left-16 bg-slate-900 text-white text-[10px] px-2 py-1 rounded hidden group-hover:block whitespace-nowrap z-50">Saved Blocks</span>
                        </button>
                        <button onclick="setLeftSidebarTab('brand')" id="rail-tab-brand" class="p-3.5 rounded-xl text-slate-400 hover:text-slate-800 transition-all relative group" title="Brand Kit">
                            <i data-lucide="palette" class="h-5 w-5"></i>
                            <span class="absolute left-16 bg-slate-900 text-white text-[10px] px-2 py-1 rounded hidden group-hover:block whitespace-nowrap z-50">Brand Kit</span>
                        </button>
                        <button onclick="setLeftSidebarTab('ai')" id="rail-tab-ai" class="p-3.5 rounded-xl text-slate-400 hover:text-slate-800 relative group transition-all" title="AI Assistant">
                            <i data-lucide="sparkles" class="h-5 w-5"></i>
                            <span class="absolute left-16 bg-slate-900 text-white text-[10px] px-2 py-1 rounded hidden group-hover:block whitespace-nowrap z-50">AI Assistant</span>
                        </button>
                    </div>

                    <!-- 2. LEFT ELEMENTS PANEL -->
                    <div id="builder-left-sidebar" class="w-72 border-r border-slate-200 bg-white flex flex-col shrink-0 overflow-hidden relative z-40 transition-all duration-300">
                        <div class="p-4 border-b border-slate-100 space-y-3">
                            <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider" id="left-sidebar-title">Elements</h3>
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-3 top-2.5 h-3.5 w-3.5 text-slate-400"></i>
                                <input type="text" id="left-sidebar-search" oninput="searchSidebarElements(this.value)" placeholder="Search elements...          ⌘ K" class="w-full pl-9 pr-4 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 placeholder-slate-450 focus:outline-none focus:border-[#6D5EF5]">
                            </div>
                        </div>

                        <!-- Scroll tab content container -->
                        <div id="left-sidebar-scroll-area" class="flex-grow overflow-y-auto p-4 custom-scrollbar">
                            <!-- Injected dynamically based on selected tab -->
                        </div>

                        <!-- Drag and Drop footer badge -->
                        <div class="p-4 border-t border-slate-100 bg-[#FAF9FF] flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="sparkles" class="h-4 w-4 text-[#6D5EF5]"></i>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-bold text-slate-700">Drag & Drop</span>
                                    <span class="text-[8px] text-slate-400">Add elements to build your email</span>
                                </div>
                            </div>
                            <i data-lucide="x" class="h-3 w-3 text-slate-400 cursor-pointer"></i>
                        </div>
                    </div>

                    <!-- 3. CENTER CANVAS - PREVIEW AREA -->
                    <div class="flex-grow bg-[#F1F5F9] flex flex-col overflow-hidden relative select-text" id="builder-canvas-viewport">
                        
                        <!-- Canvas Header Area Controls (matching layout) -->
                        <div class="w-full bg-white border-b border-slate-200/80 py-2.5 px-6 flex items-center justify-between shrink-0 text-slate-550 select-none z-30 shadow-2xs">
                            <div class="w-full max-w-[760px] mx-auto flex items-center justify-between">
                                <div class="flex items-center space-x-1.5 bg-slate-100 border border-slate-200/50 p-1 rounded-xl">
                                    <button onclick="setDeviceView('desktop')" class="w-8 h-8 rounded-lg flex items-center justify-center bg-white border border-slate-200/60 shadow-2xs hover:bg-slate-50 transition text-slate-700" title="Desktop View">
                                        <i data-lucide="layout" class="h-4 w-4"></i>
                                    </button>
                                    <button onclick="setDeviceView('mobile')" class="w-8 h-8 flex items-center justify-center hover:bg-white rounded-lg transition text-slate-500 hover:text-slate-800" title="Mobile View">
                                        <i data-lucide="smartphone" class="h-4 w-4"></i>
                                    </button>
                                    <button onclick="setDeviceView('tablet')" class="w-8 h-8 rounded-lg flex items-center justify-center bg-white border border-slate-200/60 shadow-2xs hover:bg-slate-50 transition text-slate-700" title="Tablet View">
                                        <i data-lucide="rotate-cw" class="h-4 w-4"></i>
                                    </button>
                                </div>
                                
                                <div class="flex items-center space-x-4 text-xs font-bold">
                                    <div class="h-4 border-l border-slate-300 mx-1"></div>
                                    <button onclick="builderUndo()" class="text-slate-655 hover:text-slate-900 transition flex items-center space-x-1">
                                        <i data-lucide="undo" class="h-3.5 w-3.5 text-slate-505"></i><span>Undo</span>
                                    </button>
                                    <button onclick="builderRedo()" class="text-slate-350 hover:text-slate-500 transition flex items-center space-x-1">
                                        <i data-lucide="redo" class="h-3.5 w-3.5 text-slate-300"></i><span>Redo</span>
                                    </button>
                                    <button class="text-slate-655 hover:text-slate-900 transition flex items-center space-x-1">
                                        <i data-lucide="history" class="h-3.5 w-3.5 text-slate-505"></i><span>Version History</span>
                                    </button>
                                    <button onclick="openLivePreviewTab()" class="text-slate-655 hover:text-slate-900 transition flex items-center space-x-1">
                                        <i data-lucide="external-link" class="h-3.5 w-3.5 text-slate-505"></i><span>View in Browser</span>
                                    </button>
                                </div>

                                <div class="flex items-center space-x-3.5">
                                    <button onclick="setLeftSidebarTab('ai')" class="px-4 py-1.5 border border-slate-200 bg-white hover:bg-slate-50 rounded-xl text-xs font-black text-[#6D5EF5] flex items-center space-x-2 transition shadow-xs">
                                        <i data-lucide="sparkles" class="h-3.5 w-3.5 text-[#6D5EF5]"></i> <span>AI Generate</span>
                                    </button>
                                    <button onclick="toggleCanvasCodeView()" id="btn-canvas-code-view" class="px-3 py-1.5 hover:bg-slate-100 border border-transparent hover:border-slate-200 text-slate-655 hover:text-slate-900 rounded-xl text-xs font-black flex items-center space-x-1.5 transition">
                                        <i data-lucide="code-2" class="h-3.5 w-3.5 text-slate-505"></i> <span>Code View</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable Center Canvas Body -->
                        <div class="flex-grow overflow-y-auto p-4 select-text relative flex flex-col bg-[#F1F5F9]">
                            <!-- Canvas Responsive Resizable Frame -->
                            <div id="canvas-device-frame" class="w-full max-w-[760px] bg-[#f1f5f9] rounded-2xl transition-all duration-300 flex flex-col min-h-[600px] mt-4 mb-20 mx-auto select-none relative">
                                
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

                        <!-- Footer breadcrumbs bar (stay locked at bottom) -->
                        <div class="w-full h-10 bg-white border-t border-slate-200 px-6 flex items-center justify-between text-[11px] font-medium text-slate-400 select-none z-30 shrink-0">
                            <div class="flex items-center space-x-1.5" id="canvas-breadcrumbs">
                                <span>Body</span> <i data-lucide="chevron-right" class="h-3 w-3"></i>
                                <span>Section</span> <i data-lucide="chevron-right" class="h-3 w-3"></i>
                                <span>Row</span> <i data-lucide="chevron-right" class="h-3 w-3"></i>
                                <span>Column</span> <i data-lucide="chevron-right" class="h-3 w-3"></i>
                                <span class="text-[#6D5EF5] font-bold">Heading</span>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="flex items-center"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full mr-1.5"></span>Autosaved just now</span>
                                <button class="hover:text-slate-800 transition">Feedback</button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. RIGHT PANEL - DYNAMIC PROPERTIES -->
                    <div id="builder-right-sidebar" class="w-80 border-l border-slate-200 bg-white flex flex-col shrink-0 overflow-hidden z-40">
                        <div class="p-4 border-b border-slate-200 shrink-0 flex flex-col">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-xs font-black tracking-wider uppercase text-slate-850 flex items-center" id="properties-panel-title">
                                    <i data-lucide="settings" class="h-3.5 w-3.5 mr-1.5 text-indigo-500"></i>Heading
                                </h3>
                                <span class="text-[9.5px] font-bold text-slate-500 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md" id="selected-type-badge">NONE</span>
                            </div>
                            <!-- Panel Tabs -->
                            <div class="flex bg-slate-100 p-0.5 rounded-lg text-[10px] font-bold uppercase tracking-wide text-center border border-slate-200">
                                <button onclick="setPropertiesTab('content')" id="tab-prop-content" style="background-color:#ffffff; color:#0f172a; font-weight:bold;" class="flex-grow py-1 rounded-md transition shadow-2xs">Content</button>
                                <button onclick="setPropertiesTab('style')" id="tab-prop-style" style="background-color:transparent; color:#64748b;" class="flex-grow py-1 rounded-md transition">Style</button>
                                <button onclick="setPropertiesTab('advanced')" id="tab-prop-advanced" style="background-color:transparent; color:#64748b;" class="flex-grow py-1 rounded-md transition">Advanced</button>
                            </div>
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

            <!-- SMTP Test delivery modal -->
            <div id="test-email-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center hidden z-100">
                <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-md p-6 shadow-2xl relative">
                    <button onclick="closeTestEmailModal()" class="absolute right-4 top-4 text-slate-400 hover:text-slate-800 transition">
                        <i data-lucide="x" class="h-4.5 w-4.5"></i>
                    </button>
                    <div class="flex items-center space-x-2.5 mb-4">
                        <div class="h-8 w-8 rounded-lg bg-indigo-50 text-[#6D5EF5] flex items-center justify-center">
                            <i data-lucide="send" class="h-4.5 w-4.5"></i>
                        </div>
                        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Send Test Email</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-600 text-[10px] uppercase">Recipient Email</label>
                            <input type="email" id="test-recipient-email" placeholder="e.g. wbsoumo@gmail.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800">
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-600 text-[10px] uppercase">Subject Line Override</label>
                            <input type="text" id="test-subject" placeholder="e.g. LinkPilot Visual Builder Delivery Test" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800">
                        </div>
                    </div>
                    <div class="flex items-center justify-end space-x-3 mt-6">
                        <button onclick="closeTestEmailModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-lg transition">Cancel</button>
                        <button onclick="submitSendTestEmail()" class="px-4 py-2 bg-[#6D5EF5] hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition shadow-md">Send Test Outbound</button>
                    </div>
                </div>
            </div>
        `;

        // Initialize left Elements tab default content
        setLeftSidebarTab('elements');
        renderCanvas();

        if (typeof lucide !== 'undefined') lucide.createIcons();

        // Auto Save Interval setup (every 30 seconds)
        if (autoSaveInterval) clearInterval(autoSaveInterval);
        autoSaveInterval = setInterval(() => {
            saveTemplateDraft(true);
        }, 30000);
    };

    // Toggle Left sidebar active Tab selection
    window.setLeftSidebarTab = function(tab) {
        activeSidebarTab = tab;
        const container = document.getElementById('left-sidebar-scroll-area');
        const titleEl = document.getElementById('left-sidebar-title');
        if (!container || !titleEl) return;

        // Toggle title header text
        titleEl.innerText = tab.toUpperCase();

        // Toggle vertical rail highlighted active styling
        const tabs = ['elements', 'sections', 'templates', 'blocks', 'brand', 'ai'];
        tabs.forEach(t => {
            const btn = document.getElementById('rail-tab-' + t);
            if (btn) {
                if (t === tab) {
                    btn.style.cssText = "background-color: #FAF9FF !important; color: #6D5EF5 !important; border-left: 3px solid #6D5EF5; border-radius: 0 12px 12px 0;";
                } else {
                    btn.style.cssText = "background-color: transparent !important; color: #94a3b8 !important; border-left: 3px solid transparent;";
                }
            }
        });

        // Load specific tab renderer
        if (tab === 'elements') {
            renderElementsList(container);
        } else if (tab === 'sections') {
            renderSectionsList(container);
        } else if (tab === 'templates') {
            renderTemplatesTab(container);
        } else if (tab === 'blocks') {
            renderBlocksTab(container);
        } else if (tab === 'brand') {
            renderBrandSidebar(container);
        } else if (tab === 'ai') {
            renderAISidebar(container);
        }
    };

    // Element rendering list logic
    function renderElementsList(container, filterQuery = "") {
        const filtered = draggableElements.filter(el => el.label.toLowerCase().includes(filterQuery.toLowerCase()));
        container.innerHTML = `
            <div class="grid grid-cols-2 gap-3" id="draggable-items-grid">
                ${filtered.map(el => `
                    <div class="bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col items-center justify-center text-center cursor-grab active:cursor-grabbing hover:border-[#6D5EF5] hover:bg-slate-50 transition select-none group"
                         draggable="true" 
                         ondragstart="onBuilderDragStart(event, '${el.type}')">
                        <div class="h-9 w-9 rounded-lg bg-indigo-50 text-[#6D5EF5] flex items-center justify-center mb-2 group-hover:scale-115 transition duration-200">
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

    // Draggable preset sections renderer
    function renderSectionsList(container) {
        container.innerHTML = `
            <div class="space-y-4">
                ${readySections.map((sec, idx) => `
                    <div class="bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between hover:border-[#6D5EF5] transition select-none">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-xs text-slate-800">${sec.name}</span>
                            <span class="text-[9px] text-[#6D5EF5] uppercase font-black bg-indigo-50 px-2 py-0.5 rounded">PRESET</span>
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
        showNotification('success', 'Inserted preset section.');
    };

    // Sidebar templates view list
    function renderTemplatesTab(container) {
        container.innerHTML = `
            <div class="space-y-3.5">
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center text-slate-500">
                    <p class="font-bold text-xs">Templates Library</p>
                    <p class="text-[9.5px] text-slate-450 mt-1">Select and load preset layout blueprints directly into your current campaign draft.</p>
                </div>
                <div class="space-y-2">
                    <div class="p-3 bg-white border border-slate-200 rounded-xl hover:border-[#6D5EF5] cursor-pointer transition flex items-center justify-between">
                        <div>
                            <p class="font-bold text-xs text-slate-800">Black Friday Outreach</p>
                            <span class="text-[9px] text-[#6D5EF5] font-bold">Marketing</span>
                        </div>
                        <i data-lucide="arrow-right" class="h-4 w-4 text-slate-400"></i>
                    </div>
                    <div class="p-3 bg-white border border-slate-200 rounded-xl hover:border-[#6D5EF5] cursor-pointer transition flex items-center justify-between">
                        <div>
                            <p class="font-bold text-xs text-slate-800">Monthly Product Launch</p>
                            <span class="text-[9px] text-[#6D5EF5] font-bold">Newsletters</span>
                        </div>
                        <i data-lucide="arrow-right" class="h-4 w-4 text-slate-400"></i>
                    </div>
                </div>
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Sidebar saved blocks view list
    function renderBlocksTab(container) {
        container.innerHTML = `
            <div class="space-y-3.5 text-center text-slate-500 py-6">
                <i data-lucide="database" class="h-8 w-8 mx-auto text-[#6D5EF5] mb-2"></i>
                <p class="font-bold text-xs">No Saved Blocks Yet</p>
                <p class="text-[9.5px] text-slate-400 max-w-[200px] mx-auto mt-1">Right-click or click Save as Block on any layout element inside the canvas to store it for reuse here.</p>
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Sidebar AI Copy Assistant Tab
    function renderAISidebar(container) {
        container.innerHTML = `
            <div class="space-y-4">
                <div class="p-3 bg-indigo-50 border border-indigo-100 rounded-xl space-y-1">
                    <span class="font-bold text-xs text-[#6D5EF5] flex items-center">
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
                <button onclick="triggerAIBuilderGenerate()" id="btn-ai-generate" class="w-full py-2.5 bg-[#6D5EF5] hover:bg-indigo-750 text-white rounded-xl font-black text-xs shadow-lg transition flex items-center justify-center space-x-1.5" style="color:#ffffff !important;background-color:#6D5EF5 !important;">
                    <i data-lucide="sparkles" class="h-4 w-4 text-white" style="color:#ffffff !important;"></i>
                    <span>Generate AI Content</span>
                </button>
                <div id="ai-builder-response-box" class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl hidden space-y-2">
                    <div class="flex items-center justify-between text-[9px] uppercase tracking-wide text-slate-555 font-bold">
                        <span>AI Suggestion</span>
                        <button onclick="copyAICopilotText()" class="text-[#6D5EF5] hover:text-indigo-800 transition">Copy text</button>
                    </div>
                    <div id="ai-builder-response-content" class="text-[11px] text-slate-800 leading-relaxed max-h-48 overflow-y-auto whitespace-pre-line select-text"></div>
                </div>
            </div>
        `;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Trigger AI Generation Action
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

    // Copy Copilot text output helper
    window.copyAICopilotText = function() {
        const text = document.getElementById('ai-builder-response-content').innerText;
        navigator.clipboard.writeText(text);
        showNotification('success', 'Copied suggestion to clipboard.');
    };

    // Brand Kit Editor Tab
    function renderBrandSidebar(container) {
        container.innerHTML = `
            <div class="space-y-4">
                <div class="space-y-3">
                    <span class="font-bold text-[10px] uppercase tracking-wide text-slate-500">Global Design Colors</span>
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-650">Primary Accent</span>
                            <div class="flex items-center space-x-2">
                                <input type="color" oninput="updateBrandStyle('primaryColor', this.value)" value="${brandStyles.primaryColor}" class="w-6 h-6 border-0 bg-transparent cursor-pointer rounded">
                                <span class="text-[10px] font-mono text-slate-500 uppercase">${brandStyles.primaryColor}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-650">Dark Secondary</span>
                            <div class="flex items-center space-x-2">
                                <input type="color" oninput="updateBrandStyle('secondaryColor', this.value)" value="${brandStyles.secondaryColor}" class="w-6 h-6 border-0 bg-transparent cursor-pointer rounded">
                                <span class="text-[10px] font-mono text-slate-500 uppercase">${brandStyles.secondaryColor}</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-650">Email Canvas bg</span>
                            <div class="flex items-center space-x-2">
                                <input type="color" oninput="updateBrandStyle('backgroundColor', this.value)" value="${brandStyles.backgroundColor}" class="w-6 h-6 border-0 bg-transparent cursor-pointer rounded">
                                <span class="text-[10px] font-mono text-slate-500 uppercase">${brandStyles.backgroundColor}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="space-y-2 pt-2 border-t border-slate-200">
                    <span class="font-bold text-[10px] uppercase tracking-wide text-slate-555">Global Fonts</span>
                    <select onchange="updateBrandStyle('fontFamily', this.value)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-indigo-500">
                        <option value="Inter, Arial, sans-serif" ${brandStyles.fontFamily.includes('Inter') ? 'selected':''}>Inter (Modern Sans)</option>
                        <option value="Georgia, serif" ${brandStyles.fontFamily.includes('Georgia') ? 'selected':''}>Georgia (Editorial Serif)</option>
                        <option value="'Helvetica Neue', Arial, sans-serif" ${brandStyles.fontFamily.includes('Helvetica') ? 'selected':''}>Helvetica Neue</option>
                    </select>
                </div>
            </div>
        `;
    }

    // Update global styles in Brand Kit
    window.updateBrandStyle = function(prop, val) {
        brandStyles[prop] = val;
        const canvas = document.getElementById('email-builder-canvas');
        if (canvas) {
            canvas.style.fontFamily = brandStyles.fontFamily;
            if (prop === 'backgroundColor') {
                canvas.style.backgroundColor = val;
            }
        }
        renderCanvas();
        const container = document.getElementById('left-sidebar-scroll-area');
        if (activeSidebarTab === 'brand' && container) {
            renderBrandSidebar(container);
        }
    };

    // Filter elements in sidebar search box
    window.searchSidebarElements = function(val) {
        const container = document.getElementById('left-sidebar-scroll-area');
        if (activeSidebarTab === 'elements' && container) {
            renderElementsList(container, val);
        }
    };

    // Drag-and-drop start transfer
    window.onBuilderDragStart = function(ev, type) {
        ev.dataTransfer.setData("text/plain", JSON.stringify({ type: 'new_element', elType: type }));
        ev.dataTransfer.effectAllowed = "move";
    };

    window.onCanvasElementDragStart = function(ev, secId, elId) {
        ev.stopPropagation();
        ev.dataTransfer.setData("text/plain", JSON.stringify({ type: 'move_element', secId: secId, elId: elId }));
        ev.dataTransfer.effectAllowed = "move";
        if (ev.currentTarget) ev.currentTarget.classList.add("opacity-50");
    };

    window.onCanvasSectionDragStart = function(ev, secId) {
        ev.dataTransfer.setData("text/plain", JSON.stringify({ type: 'move_section', secId: secId }));
        ev.dataTransfer.effectAllowed = "move";
        if (ev.currentTarget) ev.currentTarget.classList.add("opacity-50");
    };

    window.onCanvasDragEnd = function(ev) {
        if (ev.currentTarget) ev.currentTarget.classList.remove("opacity-50");
    };

    function escapeHtml(text) {
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    window.toggleCanvasCodeView = function() {
        if (isCodeViewActive) {
            // We are switching from Code View -> Visual View
            if (customHtmlOverride !== null) {
                if (!confirm("You have edited the HTML code directly. Switching back to visual editing will discard your HTML edits. Do you want to continue?")) {
                    return;
                }
                customHtmlOverride = null;
            }
            isCodeViewActive = false;
        } else {
            // We are switching from Visual View -> Code View
            isCodeViewActive = true;
        }

        const btn = document.getElementById('btn-canvas-code-view');
        if (btn) {
            if (isCodeViewActive) {
                btn.style.cssText = "background-color: #6D5EF5 !important; color: #ffffff !important; font-weight: bold !important;";
            } else {
                btn.style.cssText = "background-color: #ffffff !important; color: #475569 !important;";
            }
        }
        renderCanvas();
    };

    window.onCustomHtmlEditorInput = function(val) {
        customHtmlOverride = val;
    };

    window.resetCustomHtmlOverride = function() {
        if (confirm("Are you sure you want to reset and discard all raw HTML edits to return to visual block editing?")) {
            customHtmlOverride = null;
            isCodeViewActive = false;
            const btn = document.getElementById('btn-canvas-code-view');
            if (btn) {
                btn.style.cssText = "background-color: #ffffff !important; color: #475569 !important;";
            }
            renderCanvas();
        }
    };

    window.copyCanvasCompiledHtml = function() {
        const compiledHtml = customHtmlOverride !== null ? customHtmlOverride : compileResponsiveHtml(canvasData);
        navigator.clipboard.writeText(compiledHtml);
        showNotification('success', 'Copied compiled HTML code to clipboard.');
    };

    window.openLivePreviewTab = function() {
        const compiledHtml = customHtmlOverride !== null ? customHtmlOverride : compileResponsiveHtml(canvasData);
        const newTab = window.open('about:blank', '_blank');
        if (newTab) {
            newTab.document.open();
            newTab.document.write(compiledHtml);
            newTab.document.close();
        } else {
            showNotification('error', 'Popup blocker prevented opening preview in new tab.');
        }
    };

    // Responsive frame width changer
    window.setDeviceView = function(device) {
        activeDevice = device;
        const frame = document.getElementById('canvas-device-frame');
        const label = document.getElementById('canvas-device-label');
        if (!frame) return;

        // Reset buttons active styles
        const desktopBtn = document.getElementById('btn-device-desktop');
        const tabletBtn = document.getElementById('btn-device-tablet');
        const mobileBtn = document.getElementById('btn-device-mobile');

        if (desktopBtn && tabletBtn && mobileBtn) {
            desktopBtn.style.cssText = device === 'desktop' ? "background-color: #6D5EF5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
            tabletBtn.style.cssText = device === 'tablet' ? "background-color: #6D5EF5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
            mobileBtn.style.cssText = device === 'mobile' ? "background-color: #6D5EF5 !important; color: #ffffff !important; font-weight: bold !important;" : "background-color: transparent !important; color: #64748b !important;";
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

    // HTML Output render onto Center Canvas
    window.renderCanvas = function() {
        const container = document.getElementById('email-builder-canvas');
        if (!container) return;

        if (isCodeViewActive) {
            const compiledHtml = customHtmlOverride !== null ? customHtmlOverride : compileResponsiveHtml(canvasData);
            container.innerHTML = `
                <div class="flex flex-col w-full h-full bg-slate-900 rounded-2xl overflow-hidden border border-slate-800 shadow-2xl p-4 min-h-[500px]">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4 select-none">
                        <span class="text-xs font-bold text-slate-400 flex items-center">
                            <i data-lucide="code" class="h-4 w-4 mr-1.5 text-indigo-400"></i>HTML Source Editor (Direct Code Editing Enabled)
                        </span>
                        <div class="flex items-center space-x-2">
                            <button onclick="copyCanvasCompiledHtml()" class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold rounded-lg transition shadow-md flex items-center border border-slate-750">
                                <i data-lucide="copy" class="h-3.5 w-3.5 mr-1 text-blue-400"></i> Copy Code
                            </button>
                            <button onclick="resetCustomHtmlOverride()" class="px-3 py-1 bg-rose-950 hover:bg-rose-900 text-rose-300 text-[10px] font-bold rounded-lg transition shadow-md flex items-center border border-rose-900">
                                <i data-lucide="rotate-ccw" class="h-3.5 w-3.5 mr-1 text-rose-400"></i> Reset to Visual
                            </button>
                        </div>
                    </div>
                    <textarea oninput="onCustomHtmlEditorInput(this.value)" class="flex-grow w-full bg-slate-950 text-emerald-400 font-mono text-xs p-4 rounded-xl border border-slate-850 outline-none resize-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" style="min-height: 420px; font-family: monospace; line-height: 1.5; background-color: #020617 !important; color: #34d399 !important; border: 1px solid #1e293b !important;">${escapeHtml(compiledHtml)}</textarea>
                </div>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
            return;
        }

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

        // Loop over data blocks and generate HTML previews
        let contentHtml = "";
        canvasData.forEach((sec, sIdx) => {
            let sectionSelected = (selectedSectionId === sec.id);
            let elementsHtml = "";

            if (sec.elements.length === 0) {
                elementsHtml = `
                    <div class="py-6 border border-dashed border-slate-355 rounded-lg text-center text-slate-400 text-xs bg-slate-50 flex items-center justify-center"
                         ondragover="onCanvasDragOver(event)"
                         ondragleave="onCanvasDragLeave(event)"
                         ondrop="onCanvasDrop(event, '${sec.id}', 0)">
                         Drop elements inside this section
                    </div>
                `;
            } else {
                sec.elements.forEach((el, eIdx) => {
                    if (!el.settings) el.settings = {};
                    let isSelected = (selectedElementId === el.id);
                    let elementInner = "";

                    switch (el.type) {
                        case 'heading':
                            elementInner = `<h2 style="margin:0; font-size:${el.settings.fontSize || '22px'}; color:${el.settings.color || '#0F172A'}; font-weight:${el.settings.fontWeight || 'bold'}; text-align:${el.settings.align || 'left'};">${el.settings.content || 'Heading Text'}</h2>`;
                            break;
                        case 'text':
                            elementInner = `<div style="font-size:${el.settings.fontSize || '15px'}; color:${el.settings.color || '#334155'}; line-height:${el.settings.lineHeight || '1.6'}; text-align:${el.settings.align || 'left'};">${el.settings.content || 'Paragraph text content details...'}</div>`;
                            break;
                        case 'image':
                            elementInner = `
                                <div style="text-align:${el.settings.align || 'center'};">
                                    <img src="${el.settings.imageUrl || 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop'}" style="max-width:100%; height:auto; border-radius:${el.settings.borderRadius || '6px'};" alt="Email Asset">
                                </div>
                            `;
                            break;
                        case 'button':
                            elementInner = `
                                <div style="text-align:${el.settings.align || 'center'};">
                                    <a href="${el.settings.link || '#'}" onclick="event.preventDefault();" style="display:inline-block; font-size:14px; font-weight:bold; color:${el.settings.textColor || '#ffffff'}; background-color:${el.settings.backgroundColor || '#6D5EF5'}; padding:${el.settings.paddingTop || '12px'} ${el.settings.paddingRight || '24px'} ${el.settings.paddingBottom || '12px'} ${el.settings.paddingLeft || '24px'}; border-radius:${el.settings.borderRadius || '8px'}; text-decoration:none; box-shadow:0 2px 4px rgba(0,0,0,0.06);">${el.settings.text || 'Action Button'}</a>
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
                                    <img src="${el.settings.logoUrl || 'https://img.icons8.com/color/96/000000/send.png'}" style="height:${el.settings.height || '40px'}; width:auto;" alt="Logo">
                                </div>
                            `;
                            break;
                        case 'social':
                            elementInner = `
                                <div style="text-align:${el.settings.align || 'center'};" class="flex justify-center space-x-4">
                                    <a href="#" onclick="event.preventDefault();" class="inline-block"><img src="https://img.icons8.com/color/48/000000/facebook-new.png" style="width:24px;height:24px;"></a>
                                    <a href="#" onclick="event.preventDefault();" class="inline-block"><img src="https://img.icons8.com/color/48/000000/twitter.png" style="width:24px;height:24px;"></a>
                                    <a href="#" onclick="event.preventDefault();" class="inline-block"><img src="https://img.icons8.com/color/48/000000/linkedin.png" style="width:24px;height:24px;"></a>
                                    <a href="#" onclick="event.preventDefault();" class="inline-block"><img src="https://img.icons8.com/color/48/000000/instagram-new.png" style="width:24px;height:24px;"></a>
                                </div>
                            `;
                            break;
                        case 'coupon':
                            elementInner = `
                                <div style="background-color:${el.settings.backgroundColor || '#FAF9FF'}; border:2px dashed ${el.settings.borderColor || '#6D5EF5'}; border-radius:10px; padding:20px; text-align:center;">
                                    <span style="font-size:10px; font-weight:black; text-transform:uppercase; color:#64748B; letter-spacing:1px;">Discount Coupon</span>
                                    <h3 style="font-size:24px; margin:8px 0; color:#0F172A; font-weight:extrabold;">${el.settings.discount || '50% OFF'}</h3>
                                    <p style="font-size:12px; margin:0 0 12px 0; color:#475569;">${el.settings.desc || 'Code valid on your entire inventory order.'}</p>
                                    <span style="display:inline-block; background-color:#ffffff; border:1px solid #E2E8F0; padding:6px 16px; font-family:monospace; font-size:14px; font-weight:bold; color:#0F172A; border-radius:6px; letter-spacing:1.5px;">${el.settings.code || 'WELCOME50'}</span>
                                </div>
                            `;
                            break;
                        default:
                            elementInner = `<div class="p-4 bg-slate-50 border border-slate-200 rounded-lg text-slate-500 font-bold">${el.type.toUpperCase()} block preview</div>`;
                    }

                    // Render Element Block Wrapper with controls
                    elementsHtml += `
                        <div class="group/element relative p-3 border-2 rounded-xl transition duration-150 cursor-pointer ${isSelected ? 'border-[#6D5EF5] bg-indigo-500/5 shadow-md shadow-indigo-600/5' : 'border-transparent hover:border-slate-350 hover:bg-slate-100/50'}"
                             onclick="selectCanvasElement('${el.id}', event)"
                             draggable="true"
                             ondragstart="onCanvasElementDragStart(event, '${sec.id}', '${el.id}')"
                             ondragend="onCanvasDragEnd(event)"
                             ondragover="onCanvasDragOver(event)"
                             ondragleave="onCanvasDragLeave(event)"
                             ondrop="onCanvasDrop(event, '${sec.id}', ${eIdx})">
                            ${elementInner}

                            <!-- Premium Floating blue toolbar directly above selected element (matching reference layout) -->
                            ${isSelected ? `
                            <div class="absolute -top-10 left-1/2 -translate-x-1/2 flex items-center space-x-1 bg-[#6D5EF5] text-white shadow-xl rounded-lg p-1.5 border border-indigo-400 select-none z-50">
                                <button class="p-1 hover:bg-indigo-700 rounded cursor-grab animate-pulse" title="Move"><i data-lucide="move" class="h-3.5 w-3.5"></i></button>
                                <button onclick="cloneCanvasElement('${sec.id}', '${el.id}', event)" class="p-1 hover:bg-indigo-700 rounded" title="Duplicate"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                                <button class="p-1 hover:bg-indigo-700 rounded" title="Lock"><i data-lucide="lock" class="h-3.5 w-3.5"></i></button>
                                <button onclick="deleteCanvasElement('${sec.id}', '${el.id}', event)" class="p-1 hover:bg-rose-700 rounded" title="Delete"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                            </div>
                            ` : ''}
                        </div>
                    `;
                });
            }

            // Render Section Wrapper with drag drop handles
            contentHtml += `
                <div class="group/section relative border-2 border-dashed rounded-2xl mb-5 p-4 transition-all duration-200 ${sectionSelected ? 'border-[#6D5EF5] bg-white/70 shadow-lg' : 'border-slate-200 hover:border-slate-350 bg-white shadow-2xs'}"
                     style="padding-top:${sec.settings.paddingTop || '20px'}; padding-bottom:${sec.settings.paddingBottom || '20px'}; background-color:${sec.settings.backgroundColor || '#ffffff'}; border-radius:${sec.settings.borderRadius || '12px'};"
                     onclick="selectCanvasSection('${sec.id}', event)"
                     draggable="true"
                     ondragstart="onCanvasSectionDragStart(event, '${sec.id}')"
                     ondragend="onCanvasDragEnd(event)"
                     ondragover="onCanvasDragOver(event)"
                     ondragleave="onCanvasDragLeave(event)"
                     ondrop="onCanvasDrop(event, '${sec.id}')">
                    
                    <!-- Section Layout Area -->
                    <div class="space-y-2">
                        ${elementsHtml}
                    </div>

                    <!-- Drop Indicators -->
                    <div class="blue-drop-guide pointer-events-none absolute left-0 right-0 h-1 bg-[#6D5EF5] rounded hidden" id="drop-indicator-${sec.id}"></div>

                    <!-- Section Actions Overlays -->
                    <div class="absolute -right-3 top-1/2 -translate-y-1/2 hidden group-hover/section:flex flex-col items-center space-y-1 bg-white border border-slate-200 rounded-lg p-1 shadow-md select-none z-20">
                        <button onclick="cloneCanvasSection('${sec.id}', event)" class="p-1 text-slate-500 hover:text-slate-950 rounded hover:bg-slate-100" title="Clone Section"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
                        <button onclick="deleteCanvasSection('${sec.id}', event)" class="p-1 text-rose-500 hover:text-rose-700 rounded hover:bg-rose-50" title="Delete Section"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
                    </div>
                </div>
            `;
        });

        // Add final drop target at the bottom of the sections
        contentHtml += `
            <div class="py-8 border-2 border-dashed border-slate-300 rounded-2xl text-center text-slate-400 text-xs font-bold bg-white/40 hover:bg-white/80 hover:border-[#6D5EF5] transition flex items-center justify-center cursor-pointer"
                 ondragover="onCanvasDragOver(event)"
                 ondragleave="onCanvasDragLeave(event)"
                 ondrop="onCanvasDrop(event, 'bottom')">
                 <button class="px-4 py-2 bg-[#6D5EF5] hover:bg-indigo-750 text-white rounded-lg text-[10px] font-black flex items-center shadow-xs transition" style="background-color: #6D5EF5 !important; color: #ffffff !important;">
                     <i data-lucide="plus" class="h-3.5 w-3.5 mr-1 text-white"></i> Add New Section
                 </button>
            </div>
        `;

        container.innerHTML = `
            <div style="font-family:${brandStyles.fontFamily};" class="w-full">
                ${contentHtml}
            </div>
        `;

        if (typeof lucide !== 'undefined') lucide.createIcons();
    };

    // Drag-over canvas drop zone helper
    window.onCanvasDragOver = function(ev) {
        ev.preventDefault();
        ev.dataTransfer.dropEffect = "move";
        const dropZone = ev.currentTarget;
        dropZone.classList.add("border-[#6D5EF5]", "bg-indigo-50/20");
    };

    // Drag-leave canvas helper
    window.onCanvasDragLeave = function(ev) {
        const dropZone = ev.currentTarget;
        dropZone.classList.remove("border-[#6D5EF5]", "bg-indigo-50/20");
    };

    // Canvas Dropping logic helper
    window.onCanvasDrop = function(ev, targetSectionId = null, insertIndex = null) {
        ev.preventDefault();
        ev.stopPropagation(); // Prevent drag event from bubbling up to parent drop zones!

        const dropZone = ev.currentTarget;
        dropZone.classList.remove("border-[#6D5EF5]", "bg-indigo-50/20");

        let dragData = null;
        const rawData = ev.dataTransfer.getData("text/plain") || ev.dataTransfer.getData("text");
        if (!rawData) return;

        try {
            if (rawData.trim().startsWith('{') || rawData.trim().startsWith('[')) {
                dragData = JSON.parse(rawData);
            } else {
                dragData = { type: 'new_element', elType: rawData };
            }
        } catch (err) {
            dragData = { type: 'new_element', elType: rawData };
        }

        recordState();

        // Helper to construct default element settings
        function createDefaultElement(elType) {
            const newEl = {
                id: "el_" + Math.random().toString(36).substr(2, 9),
                type: elType,
                settings: {}
            };
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
                    newEl.settings = { text: "Action Link Button", link: "#", backgroundColor: "#6D5EF5", textColor: "#ffffff", borderRadius: "8px", align: "center", paddingTop: "12px", paddingBottom: "12px", paddingLeft: "24px", paddingRight: "24px" };
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
                    newEl.settings = { code: "LPNEW50", discount: "50% OFF", desc: "Start building and save half off first plan invoice.", backgroundColor: "#FAF9FF", borderColor: "#6D5EF5" };
                    break;
            }
            return newEl;
        }

        if (dragData.type === 'new_element') {
            const elType = dragData.elType;
            const newEl = createDefaultElement(elType);

            if (targetSectionId === 'bottom') {
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
                // Drop on general canvas wrapper background (append new section)
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
        } else if (dragData.type === 'move_element') {
            // Find element in original section and remove it
            let foundEl = null;
            for (const sec of canvasData) {
                const idx = sec.elements.findIndex(e => e.id === dragData.elId);
                if (idx !== -1) {
                    foundEl = sec.elements[idx];
                    sec.elements.splice(idx, 1);
                    break;
                }
            }

            if (!foundEl) return;

            if (targetSectionId === 'bottom') {
                const newSec = {
                    id: "sec_" + Date.now(),
                    type: "section",
                    settings: { paddingTop: "20px", paddingBottom: "20px", backgroundColor: "#ffffff" },
                    elements: [foundEl]
                };
                canvasData.push(newSec);
                selectedElementId = foundEl.id;
                selectedSectionId = newSec.id;
            } else if (targetSectionId) {
                const targetSec = canvasData.find(s => s.id === targetSectionId);
                if (targetSec) {
                    if (insertIndex !== null && insertIndex !== undefined) {
                        targetSec.elements.splice(insertIndex, 0, foundEl);
                    } else {
                        targetSec.elements.push(foundEl);
                    }
                    selectedElementId = foundEl.id;
                    selectedSectionId = targetSec.id;
                }
            } else {
                // Drop on general canvas wrapper background
                const newSec = {
                    id: "sec_" + Date.now(),
                    type: "section",
                    settings: { paddingTop: "20px", paddingBottom: "20px", backgroundColor: "#ffffff" },
                    elements: [foundEl]
                };
                canvasData.push(newSec);
                selectedElementId = foundEl.id;
                selectedSectionId = newSec.id;
            }
        } else if (dragData.type === 'move_section') {
            const sourceIdx = canvasData.findIndex(s => s.id === dragData.secId);
            if (sourceIdx === -1) return;
            const [movedSec] = canvasData.splice(sourceIdx, 1);

            if (targetSectionId === 'bottom') {
                canvasData.push(movedSec);
            } else if (targetSectionId) {
                const targetIdx = canvasData.findIndex(s => s.id === targetSectionId);
                if (targetIdx !== -1) {
                    canvasData.splice(targetIdx, 0, movedSec);
                } else {
                    canvasData.push(movedSec);
                }
            } else {
                canvasData.push(movedSec);
            }
            selectedSectionId = movedSec.id;
            selectedElementId = null;
        }

        renderCanvas();
        renderPropertiesPanel();
        updateCanvasBreadcrumbs();
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
            updateCanvasBreadcrumbs();
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
        updateCanvasBreadcrumbs();
        showNotification('success', 'Deleted section.');
    };

    // Select Canvas Element helper
    window.selectCanvasElement = function(id, event) {
        if (event) event.stopPropagation();
        selectedElementId = id;
        selectedSectionId = null;
        renderCanvas();
        renderPropertiesPanel();
        updateCanvasBreadcrumbs();
    };

    // Select Section Element helper
    window.selectCanvasSection = function(id, event) {
        if (event) event.stopPropagation();
        if (event.target.closest('.group\\/element')) return;
        
        selectedSectionId = id;
        selectedElementId = null;
        renderCanvas();
        renderPropertiesPanel();
        updateCanvasBreadcrumbs();
    };

    // Footer breadcrumbs dynamic builder path text helper
    function updateCanvasBreadcrumbs() {
        const breadcrumbs = document.getElementById('canvas-breadcrumbs');
        if (!breadcrumbs) return;

        if (selectedElementId) {
            let elType = "Element";
            for (const sec of canvasData) {
                const el = sec.elements.find(e => e.id === selectedElementId);
                if (el) {
                    elType = el.type.charAt(0).toUpperCase() + el.type.slice(1);
                    break;
                }
            }
            breadcrumbs.innerHTML = `
                <span>Body</span> <i data-lucide="chevron-right" class="h-3 w-3 inline text-slate-400"></i>
                <span>Section</span> <i data-lucide="chevron-right" class="h-3 w-3 inline text-slate-400"></i>
                <span>Row</span> <i data-lucide="chevron-right" class="h-3 w-3 inline text-slate-400"></i>
                <span>Column</span> <i data-lucide="chevron-right" class="h-3 w-3 inline text-slate-400"></i>
                <span class="text-[#6D5EF5] font-bold">${elType}</span>
            `;
        } else if (selectedSectionId) {
            breadcrumbs.innerHTML = `
                <span>Body</span> <i data-lucide="chevron-right" class="h-3 w-3 inline text-slate-400"></i>
                <span class="text-[#6D5EF5] font-bold">Section</span>
            `;
        } else {
            breadcrumbs.innerHTML = `
                <span class="text-[#6D5EF5] font-bold">Body</span>
            `;
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Toggle Properties sidebar Tab selection (Content / Style / Advanced)
    let activePropertiesTab = 'content';
    window.setPropertiesTab = function(tab) {
        activePropertiesTab = tab;
        const cBtn = document.getElementById('tab-prop-content');
        const sBtn = document.getElementById('tab-prop-style');
        const aBtn = document.getElementById('tab-prop-advanced');

        if (cBtn && sBtn && aBtn) {
            cBtn.style.cssText = tab === 'content' ? "background-color:#ffffff; color:#0f172a; font-weight:bold;" : "background-color:transparent; color:#64748b;";
            sBtn.style.cssText = tab === 'style' ? "background-color:#ffffff; color:#0f172a; font-weight:bold;" : "background-color:transparent; color:#64748b;";
            aBtn.style.cssText = tab === 'advanced' ? "background-color:#ffffff; color:#0f172a; font-weight:bold;" : "background-color:transparent; color:#64748b;";
        }
        renderPropertiesPanel();
    };

    // Render Right Panel controls dynamically depending on active selection
    function renderPropertiesPanel() {
        const container = document.getElementById('properties-panel-content');
        const badge = document.getElementById('selected-type-badge');
        const titleEl = document.getElementById('properties-panel-title');
        if (!container || !badge || !titleEl) return;

        if (selectedElementId) {
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

            const elTitle = selectedEl.type.charAt(0).toUpperCase() + selectedEl.type.slice(1);
            titleEl.innerHTML = `<i data-lucide="settings" class="h-3.5 w-3.5 mr-1.5 text-indigo-500"></i>${elTitle}`;
            badge.innerText = selectedEl.type.toUpperCase();
            badge.className = "text-[9.5px] font-bold text-[#6D5EF5] bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded-md";

            let controlsHtml = "";

            if (activePropertiesTab === 'content') {
                switch (selectedEl.type) {
                    case 'heading':
                        controlsHtml = `
                            <div class="space-y-4">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <label class="font-bold text-slate-350">Heading Text</label>
                                        <button onclick="setLeftSidebarTab('ai')" class="text-[10px] font-bold text-[#6D5EF5] flex items-center hover:underline"><i data-lucide="sparkles" class="h-3 w-3 mr-1"></i> AI Write</button>
                                    </div>
                                    <textarea oninput="updateElementSetting('content', this.value)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:outline-none focus:border-indigo-500">${selectedEl.settings.content || ''}</textarea>
                                    <div class="text-[9px] text-slate-400 text-right mt-0.5">${(selectedEl.settings.content || '').length}/300</div>
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">HTML Tag</label>
                                    <select onchange="updateElementSetting('htmlTag', this.value)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                                        <option value="H1">H1</option>
                                        <option value="H2">H2</option>
                                        <option value="H3">H3</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        break;
                    case 'text':
                        controlsHtml = `
                            <div class="space-y-4">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <label class="font-bold text-slate-355">Paragraph Content</label>
                                        <button onclick="setLeftSidebarTab('ai')" class="text-[10px] font-bold text-[#6D5EF5] flex items-center hover:underline"><i data-lucide="sparkles" class="h-3 w-3 mr-1"></i> AI Rewrite</button>
                                    </div>
                                    <textarea oninput="updateElementSetting('content', this.value)" rows="6" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800">${selectedEl.settings.content || ''}</textarea>
                                </div>
                            </div>
                        `;
                        break;
                    case 'image':
                        controlsHtml = `
                            <div class="space-y-4">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Image Source URL</label>
                                    <input type="text" oninput="updateElementSetting('imageUrl', this.value)" value="${selectedEl.settings.imageUrl || ''}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800">
                                </div>
                            </div>
                        `;
                        break;
                    case 'button':
                        controlsHtml = `
                            <div class="space-y-4">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Button Text</label>
                                    <input type="text" oninput="updateElementSetting('text', this.value)" value="${selectedEl.settings.text || ''}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Link URL</label>
                                    <input type="text" oninput="updateElementSetting('link', this.value)" value="${selectedEl.settings.link || ''}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800">
                                </div>
                            </div>
                        `;
                        break;
                    case 'logo':
                        controlsHtml = `
                            <div class="space-y-4">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Logo Image URL</label>
                                    <input type="text" oninput="updateElementSetting('logoUrl', this.value)" value="${selectedEl.settings.logoUrl || ''}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800">
                                </div>
                            </div>
                        `;
                        break;
                    case 'coupon':
                        controlsHtml = `
                            <div class="space-y-4">
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Discount Headline</label>
                                    <input type="text" oninput="updateElementSetting('discount', this.value)" value="${selectedEl.settings.discount || '50% OFF'}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Coupon Code</label>
                                    <input type="text" oninput="updateElementSetting('code', this.value)" value="${selectedEl.settings.code || ''}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800">
                                </div>
                                <div class="space-y-1">
                                    <label class="font-bold text-slate-300">Description</label>
                                    <textarea oninput="updateElementSetting('desc', this.value)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-slate-800">${selectedEl.settings.desc || ''}</textarea>
                                </div>
                            </div>
                        `;
                        break;
                    default:
                        controlsHtml = `<p class="text-slate-400">Content properties are not available for this block.</p>`;
                }
            } else if (activePropertiesTab === 'style') {
                // Typography, Alignment, colors options inside Style Tab
                let customColorControls = '';
                if (selectedEl.type === 'button') {
                    customColorControls = `
                        <div class="space-y-1">
                            <label class="font-bold text-slate-350">Button Background Color</label>
                            <div class="flex items-center space-x-2">
                                <input type="color" oninput="updateElementSetting('backgroundColor', this.value)" value="${selectedEl.settings.backgroundColor || '#6D5EF5'}" class="w-8 h-8 border border-slate-250 cursor-pointer rounded-lg bg-transparent">
                                <span class="text-[10px] font-mono uppercase text-slate-500">${selectedEl.settings.backgroundColor || '#6D5EF5'}</span>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-355">Button Text Color</label>
                            <div class="flex items-center space-x-2">
                                <input type="color" oninput="updateElementSetting('textColor', this.value)" value="${selectedEl.settings.textColor || '#ffffff'}" class="w-8 h-8 border border-slate-250 cursor-pointer rounded-lg bg-transparent">
                                <span class="text-[10px] font-mono uppercase text-slate-500">${selectedEl.settings.textColor || '#ffffff'}</span>
                            </div>
                        </div>
                    `;
                } else {
                    customColorControls = `
                        <div class="space-y-1">
                            <label class="font-bold text-slate-350">Text Color</label>
                            <div class="flex items-center space-x-2">
                                <input type="color" oninput="updateElementSetting('color', this.value)" value="${selectedEl.settings.color || '#000000'}" class="w-8 h-8 border border-slate-250 cursor-pointer rounded-lg bg-transparent">
                                <span class="text-[10px] font-mono uppercase text-slate-500">${selectedEl.settings.color || '#0F172A'}</span>
                            </div>
                        </div>
                    `;
                }

                controlsHtml = `
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Font Family</label>
                            <select onchange="updateElementSetting('fontFamily', this.value)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                                <option value="Inter">Inter</option>
                                <option value="Arial">Arial</option>
                                <option value="Georgia">Georgia</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Font Weight</label>
                            <select onchange="updateElementSetting('fontWeight', this.value)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                                <option value="normal">400 - Normal</option>
                                <option value="bold" selected>700 - Bold</option>
                                <option value="800">800 - Extra Bold</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <label class="font-bold text-slate-300">Font Size</label>
                                <span class="text-[10px] text-slate-500 font-bold" id="lbl-prop-fontsize">${selectedEl.settings.fontSize || '22px'}</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <input type="range" min="12" max="72" value="${parseInt(selectedEl.settings.fontSize) || 22}" oninput="document.getElementById('lbl-prop-fontsize').innerText = this.value + 'px'; updateElementSetting('fontSize', this.value + 'px');" class="flex-grow accent-[#6D5EF5]">
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Text Align</label>
                            <div class="flex bg-slate-100 p-0.5 rounded-lg border border-slate-200 max-w-[160px]">
                                <button onclick="updateElementSetting('align', 'left')" class="flex-grow py-1 hover:bg-white rounded transition"><i data-lucide="align-left" class="h-3.5 w-3.5 mx-auto"></i></button>
                                <button onclick="updateElementSetting('align', 'center')" class="flex-grow py-1 hover:bg-white rounded transition"><i data-lucide="align-center" class="h-3.5 w-3.5 mx-auto"></i></button>
                                <button onclick="updateElementSetting('align', 'right')" class="flex-grow py-1 hover:bg-white rounded transition"><i data-lucide="align-right" class="h-3.5 w-3.5 mx-auto"></i></button>
                            </div>
                        </div>
                        ${customColorControls}
                    </div>
                `;
            } else if (activePropertiesTab === 'advanced') {
                controlsHtml = `
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Border Radius (px)</label>
                            <input type="text" oninput="updateElementSetting('borderRadius', this.value)" value="${selectedEl.settings.borderRadius || '6px'}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Padding Top (px)</label>
                            <input type="text" oninput="updateElementSetting('paddingTop', this.value)" value="${selectedEl.settings.paddingTop || '12px'}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Padding Bottom (px)</label>
                            <input type="text" oninput="updateElementSetting('paddingBottom', this.value)" value="${selectedEl.settings.paddingBottom || '12px'}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                        </div>
                    </div>
                `;
            }

            container.innerHTML = controlsHtml;
            if (typeof lucide !== 'undefined') lucide.createIcons();

        } else if (selectedSectionId) {
            const selectedSec = canvasData.find(s => s.id === selectedSectionId);
            if (!selectedSec) return;

            titleEl.innerHTML = `<i data-lucide="settings" class="h-3.5 w-3.5 mr-1.5 text-indigo-500"></i>Section`;
            badge.innerText = "SECTION";
            badge.className = "text-[9.5px] font-bold text-amber-500 bg-amber-50 border border-amber-100 px-2 py-0.5 rounded-md";

            if (activePropertiesTab === 'content') {
                container.innerHTML = `<p class="text-slate-400">Section components hold element structures. Click on Style or Advanced tabs to configure margin/padding spacing parameters.</p>`;
            } else if (activePropertiesTab === 'style') {
                container.innerHTML = `
                    <div class="space-y-4">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Section Background Color</label>
                            <div class="flex items-center space-x-2">
                                <input type="color" oninput="updateSectionSetting('backgroundColor', this.value)" value="${selectedSec.settings.backgroundColor || '#ffffff'}" class="w-8 h-8 border border-slate-250 cursor-pointer rounded-lg bg-transparent">
                                <span class="text-[10px] font-mono uppercase text-slate-500">${selectedSec.settings.backgroundColor || '#ffffff'}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else if (activePropertiesTab === 'advanced') {
                container.innerHTML = `
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Padding Top</label>
                                <input type="text" oninput="updateSectionSetting('paddingTop', this.value)" value="${selectedSec.settings.paddingTop || '20px'}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                            </div>
                            <div class="space-y-1">
                                <label class="font-bold text-slate-300">Padding Bottom</label>
                                <input type="text" oninput="updateSectionSetting('paddingBottom', this.value)" value="${selectedSec.settings.paddingBottom || '20px'}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-300">Border Radius</label>
                            <input type="text" oninput="updateSectionSetting('borderRadius', this.value)" value="${selectedSec.settings.borderRadius || '12px'}" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-1.5 text-slate-800">
                        </div>
                    </div>
                `;
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            // Defaults to empty selection placeholder
            titleEl.innerHTML = `<i data-lucide="settings" class="h-3.5 w-3.5 mr-1.5 text-indigo-500"></i>Properties`;
            badge.innerText = "NONE";
            badge.className = "text-[9.5px] font-bold text-slate-400 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md";

            container.innerHTML = `
                <div class="py-12 text-center text-slate-400">
                    <i data-lucide="mouse-pointer" class="h-8 w-8 mx-auto mb-2 text-slate-300"></i>
                    <p class="font-bold text-slate-500">Select any element</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Click any block inside the canvas to edit its properties, typography, or styling details.</p>
                </div>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    // Update individual properties details settings
    window.updateElementSetting = function(prop, val) {
        if (!selectedElementId) return;
        for (const sec of canvasData) {
            const el = sec.elements.find(e => e.id === selectedElementId);
            if (el) {
                el.settings[prop] = val;
                break;
            }
        }
        renderCanvas();
        renderPropertiesPanel();
    };

    window.updateSectionSetting = function(prop, val) {
        if (!selectedSectionId) return;
        const sec = canvasData.find(s => s.id === selectedSectionId);
        if (sec) {
            sec.settings[prop] = val;
        }
        renderCanvas();
        renderPropertiesPanel();
    };

    // Update templates autosave status badge
    function updateAutoSaveStatus(statusText = "Saved") {
        const el = document.getElementById('autosave-status');
        if (el) {
            el.innerHTML = `<i data-lucide="check-circle-2" class="h-3.5 w-3.5 mr-1 text-emerald-500"></i>${statusText}`;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    }

    // Save Template to Database (Insert/Update)
    window.saveTemplateDraft = async function(isSilent = false) {
        if (isSaving) return;
        isSaving = true;

        if (!isSilent) {
            updateAutoSaveStatus("Saving...");
        }

        const compiledHtml = customHtmlOverride !== null ? customHtmlOverride : compileResponsiveHtml(canvasData);

        const payload = {
            id: activeTemplateId,
            name: templateName,
            subject: templateSubject,
            category: templateCategory,
            tag: templateTag,
            json_data: customHtmlOverride !== null ? JSON.stringify({ isRawHtml: true, customHtml: customHtmlOverride }) : JSON.stringify(canvasData),
            html_content: compiledHtml
        };

        try {
            const res = await apiCall('crm/save_custom_template.php', 'POST', payload);
            if (res.status === 'success' && res.data && res.data.id) {
                activeTemplateId = res.data.id;
                updateAutoSaveStatus("Saved");
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
        }
    };

    // SMTP Test modal helpers
    window.openTestEmailModal = function() {
        const modal = document.getElementById('test-email-modal');
        if (modal) {
            modal.classList.remove('hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    };

    window.closeTestEmailModal = function() {
        const modal = document.getElementById('test-email-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    window.submitSendTestEmail = async function() {
        const recipient = document.getElementById('test-recipient-email').value.trim();
        const subject = document.getElementById('test-subject').value.trim();

        if (!recipient) {
            showNotification('warning', 'Please enter recipient email.');
            return;
        }

        const compiledHtml = compileResponsiveHtml(canvasData);

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

    // Exit Builder SPA flow
    window.exitEmailBuilder = function() {
        if (autoSaveInterval) {
            clearInterval(autoSaveInterval);
            autoSaveInterval = null;
        }
        
        const globalChat = document.getElementById('ai-chat-trigger-btn');
        if (globalChat) globalChat.classList.remove('hidden');

        location.hash = '#/email-templates';
    };

    // Generate responsive HTML table matching all mail clients
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
                                <td align="${el.settings.align || 'left'}" style="padding: 10px 0; font-family: ${brandStyles.fontFamily}; font-size: ${el.settings.fontSize || '22px'}; font-weight: ${el.settings.fontWeight || 'bold'}; color: ${el.settings.color || '#0F172A'};">
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
                                    <img src="${el.settings.imageUrl || 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800&auto=format&fit=crop'}" style="display: block; max-width: 100%; height: auto; border-radius: ${el.settings.borderRadius || '6px'};" alt="Email Asset">
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
                                            <td align="center" bgcolor="${el.settings.backgroundColor || '#6D5EF5'}" style="border-radius: ${el.settings.borderRadius || '8px'};">
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
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="${el.settings.backgroundColor || '#FAF9FF'}" style="border: 2px dashed ${el.settings.borderColor || '#6D5EF5'}; border-radius: 10px; text-align: center;">
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
