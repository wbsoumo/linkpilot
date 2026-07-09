import os

pages = [
    "privacy.html",
    "terms.html",
    "cookies.html",
    "disclaimer.html",
    "pricing.html",
    "about.html",
    "contact.html",
    "thank-you.html",
    "coming-soon.html"
]

base_dir = "/Users/wbsoumo/Desktop/LinkPilot AI"

for page in pages:
    page_path = os.path.join(base_dir, page)
    if not os.path.exists(page_path):
        continue
    
    with open(page_path, "r", encoding="utf-8") as f:
        content = f.read()
    
    # 1. Update font and html/body background and font tags
    # Add Outfit font
    font_link = '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">'
    if "</head>" in content and "Outfit" not in content:
        content = content.replace("</head>", f"    {font_link}\n</head>")
        
    # Replace background classes
    content = content.replace('class="h-full bg-slate-950"', 'class="h-full bg-[#FAFAFC] font-[\'Outfit\',sans-serif]"')
    content = content.replace('class="h-full bg-slate-950 text-slate-100 flex flex-col antialiased"', 'class="h-full bg-[#FAFAFC] text-slate-600 flex flex-col antialiased"')
    content = content.replace('class="h-full bg-slate-950 text-slate-100 flex flex-col justify-between antialiased"', 'class="h-full bg-[#FAFAFC] text-slate-600 flex flex-col justify-between antialiased"')
    
    # 2. Update navigation bar to light theme
    nav_old_pattern = r'<nav class="glass-nav[^"]*">.*?</nav>'
    # Since regex can be tricky with multiline matches, let's target the exact text of navigation:
    # We can match from `<nav` to `</nav>` using DOTALL
    import re
    nav_match = re.search(r'<nav\b[^>]*>.*?</nav>', content, re.DOTALL)
    if nav_match:
        old_nav = nav_match.group(0)
        # Check if it has About/Docs etc links or is simple (like thank-you / coming-soon)
        if "About" in old_nav:
            new_nav = """<!-- Sticky Navigation -->
    <nav class="sticky top-0 z-50 w-full px-6 py-4 bg-white/80 backdrop-blur-md border-b border-slate-100/80">
        <div class="max-w-6xl mx-auto w-full flex items-center justify-between">
            <a href="index.html" class="flex items-center space-x-2 text-xl font-bold tracking-tight text-[#0B0B1E]">
                <img src="dashboard/assets/img/logo.png" class="h-7 w-7 object-contain" alt="LinkPilot AI">
                <span class="font-extrabold text-[#0B0B1E]">LinkPilot</span>
            </a>
            <div class="hidden lg:flex items-center space-x-8 text-sm font-medium text-slate-500">
                <a href="index.html#features" class="hover:text-[#5f43d0] transition">Features</a>
                <a href="index.html#solutions" class="hover:text-[#5f43d0] transition">Solutions</a>
                <a href="pricing.html" class="hover:text-[#5f43d0] transition">Pricing</a>
                <a href="docs.html" class="hover:text-[#5f43d0] transition">Docs</a>
                <a href="about.html" class="hover:text-[#5f43d0] transition">About</a>
            </div>
            <div class="hidden sm:flex items-center space-x-4">
                <a href="dashboard/login.html" class="text-sm font-semibold text-slate-500 hover:text-[#5f43d0] transition">Log In</a>
                <a href="dashboard/register.html" class="px-5 py-2.5 text-sm font-bold text-white bg-[#5f43d0] hover:bg-[#4f32c0] rounded-xl shadow-sm transition">Get Started</a>
            </div>
        </div>
    </nav>"""
        else:
            new_nav = """<!-- Sticky Navigation -->
    <nav class="sticky top-0 z-50 w-full px-6 py-4 bg-white/80 backdrop-blur-md border-b border-slate-100/80">
        <div class="max-w-6xl mx-auto w-full flex items-center justify-between">
            <a href="index.html" class="flex items-center space-x-2 text-xl font-bold tracking-tight text-[#0B0B1E]">
                <img src="dashboard/assets/img/logo.png" class="h-7 w-7 object-contain" alt="LinkPilot AI">
                <span class="font-extrabold text-[#0B0B1E]">LinkPilot</span>
            </a>
        </div>
    </nav>"""
        content = content.replace(old_nav, new_nav)

    # 3. Replace headings colors (text-white -> text-[#0B0B1E], text-teal-400 / text-purple-400 -> text-[#5f43d0])
    content = content.replace('text-white', 'text-[#0B0B1E]')
    content = content.replace('text-teal-400', 'text-[#5f43d0]')
    content = content.replace('text-purple-400', 'text-[#5f43d0]')
    content = content.replace('text-slate-300', 'text-slate-600')
    content = content.replace('text-slate-400', 'text-slate-500')
    content = content.replace('border-slate-800', 'border-slate-100')
    content = content.replace('border-slate-900', 'border-slate-100')
    content = content.replace('bg-slate-900', 'bg-slate-50 border border-slate-150')
    
    # 4. Replace panels and inputs
    content = content.replace('glass-panel', 'bg-white border border-slate-150/80 shadow-md shadow-slate-100/40')
    content = content.replace('bg-slate-950', 'bg-[#FAFAFC]')
    content = content.replace('bg-slate-900/40', 'bg-slate-50/80')
    content = content.replace('divide-slate-800/40', 'divide-slate-100')
    content = content.replace('focus:ring-teal-500 focus:border-teal-500', 'focus:ring-[#5f43d0] focus:border-[#5f43d0]')
    content = content.replace('bg-teal-500/10', 'bg-[#5f43d0]/10')
    
    # 5. Buttons replacement
    content = content.replace('bg-teal-400 hover:bg-teal-300 text-slate-950', 'bg-[#5f43d0] hover:bg-[#4f32c0] text-white')
    content = content.replace('shadow-teal-500/20', 'shadow-purple-500/10')
    content = content.replace('shadow-teal-500/25', 'shadow-purple-500/10')
    content = content.replace('btn-primary', 'bg-[#5f43d0] hover:bg-[#4f32c0] text-white')
    content = content.replace('border-slate-850', 'border-slate-150')
    content = content.replace('hover:border-slate-700', 'hover:border-slate-300')
    
    # 6. Pricing specific tweaks
    if page == "pricing.html":
        # Make the recharge card popular badge violet
        content = content.replace('bg-teal-500 text-slate-950', 'bg-[#5f43d0] text-white')
        # Outline for popular card
        content = content.replace('border-teal-500/50 ring-2 ring-teal-500/10', 'border-[#5f43d0]/80 ring-4 ring-[#5f43d0]/10')
        # Check icons inside list should be purple
        content = content.replace('text-teal-400', 'text-[#5f43d0]')
        # Table feature list checks
        content = content.replace('check class="h-4 w-4 text-teal-400"', 'check class="h-4 w-4 text-[#5f43d0]"')
        
    # 7. Update footer block
    footer_match = re.search(r'<footer\b[^>]*>.*?</footer>', content, re.DOTALL)
    if footer_match:
        old_footer = footer_match.group(0)
        new_footer = """<!-- Footer -->
    <footer class="border-t border-slate-100 bg-[#FAFAFC] py-12 text-slate-400 text-xs">
        <div class="max-w-6xl mx-auto px-6 grid grid-cols-2 md:grid-cols-5 gap-8">
            <div class="col-span-2 space-y-4">
                <div class="flex items-center space-x-2 text-[#0B0B1E] font-bold text-sm">
                    <img src="dashboard/assets/img/logo.png" class="h-6 w-6 object-contain" alt="LinkPilot AI">
                    <span class="font-extrabold text-[#0B0B1E]">LinkPilot AI</span>
                </div>
                <p class="max-w-xs text-slate-500 leading-relaxed">AI CRM to automate WhatsApp, Gmail, and Instagram conversations, appointments, and workflows.</p>
                <div class="text-slate-400">Charge it with Taskbazi 2024 -26. All rights reserved.</div>
            </div>
            <div class="space-y-3">
                <h5 class="text-slate-600 font-bold uppercase tracking-wider text-[10px]">Product</h5>
                <ul class="space-y-2">
                    <li><a href="index.html#features" class="hover:text-[#5f43d0] transition">Features</a></li>
                    <li><a href="pricing.html" class="hover:text-[#5f43d0] transition">Pricing</a></li>
                    <li><a href="docs.html" class="hover:text-[#5f43d0] transition">Documentation</a></li>
                </ul>
            </div>
            <div class="space-y-3">
                <h5 class="text-slate-600 font-bold uppercase tracking-wider text-[10px]">Company</h5>
                <ul class="space-y-2">
                    <li><a href="about.html" class="hover:text-[#5f43d0] transition">About Us</a></li>
                    <li><a href="blog.html" class="hover:text-[#5f43d0] transition">Blog & Updates</a></li>
                    <li><a href="contact.html" class="hover:text-[#5f43d0] transition">Support Contact</a></li>
                </ul>
            </div>
            <div class="space-y-3">
                <h5 class="text-slate-600 font-bold uppercase tracking-wider text-[10px]">Legal</h5>
                <ul class="space-y-2">
                    <li><a href="privacy.html" class="hover:text-[#5f43d0] transition">Privacy Policy</a></li>
                    <li><a href="terms.html" class="hover:text-[#5f43d0] transition">Terms of Service</a></li>
                    <li><a href="cookies.html" class="hover:text-[#5f43d0] transition">Cookie Policy</a></li>
                    <li><a href="disclaimer.html" class="hover:text-[#5f43d0] transition">Disclaimer</a></li>
                    <li><a href="sitemap.html" class="hover:text-[#5f43d0] transition">Sitemap</a></li>
                </ul>
            </div>
        </div>
    </footer>"""
        content = content.replace(old_footer, new_footer)
        
    with open(page_path, "w", encoding="utf-8") as f:
        f.write(content)
        
    print(f"Updated {page} successfully!")
