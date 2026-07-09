import re

pricing_path = "/Users/wbsoumo/Desktop/LinkPilot AI/pricing.html"
with open(pricing_path, "r", encoding="utf-8") as f:
    pricing_content = f.read()

pricing_grid_start_pattern = r'<!-- Cards Grid -->'
pricing_grid_end_pattern = r'<!-- Pricing FAQ -->'

grid_start_match = re.search(pricing_grid_start_pattern, pricing_content)
grid_end_match = re.search(pricing_grid_end_pattern, pricing_content)

if not grid_start_match or not grid_end_match:
    print("Error: Could not locate boundaries in pricing.html.")
    exit(1)

grid_start_idx = grid_start_match.start()
grid_end_idx = grid_end_match.start()

new_pricing_grid = """<!-- Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 items-start">
                
                <!-- Free Plan -->
                <div class="glass-panel p-8 rounded-2xl border-slate-800 space-y-6">
                    <div>
                        <h4 class="text-lg font-bold text-white">Free Plan</h4>
                        <p class="text-xs text-slate-500 mt-1">Ideal for testing and setting up your workspace.</p>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-4xl font-extrabold text-white">₹0</span>
                        <span class="text-xs text-slate-500 ml-2">/ month</span>
                    </div>
                    <ul class="text-xs text-slate-300 space-y-3 border-t border-slate-800/80 pt-6">
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>200 Free AI Credits included</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>1 WhatsApp number limit</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Shared Team Inbox & Standard CRM</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Internal & External App integrations</span>
                        </li>
                    </ul>
                    <a href="dashboard/register.html" class="block w-full py-3 text-center text-slate-300 font-bold bg-slate-900 border border-slate-800 rounded-xl hover:text-white hover:bg-slate-800/80 transition">
                        Get Started Free
                    </a>
                </div>

                <!-- Recharge Plan -->
                <div class="glass-panel p-8 rounded-2xl border-teal-500/50 ring-2 ring-teal-500/10 space-y-6 relative">
                    <div class="absolute -top-3.5 left-8">
                        <span class="px-3 py-1 bg-teal-500 text-slate-950 text-[10px] font-extrabold uppercase rounded-full tracking-wider">Most Popular</span>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-teal-400">Recharge Plan</h4>
                        <p class="text-xs text-slate-500 mt-1">Pay-as-you-go model. Zero monthly commitment.</p>
                    </div>
                    <div class="flex flex-col">
                        <div class="flex items-baseline">
                            <span class="text-4xl font-extrabold text-white">₹0</span>
                            <span class="text-xs text-slate-500 ml-2">/ month subscription</span>
                        </div>
                        <span class="text-xs text-teal-400 font-semibold mt-1">₹0.20 per auto AI reply</span>
                    </div>
                    <ul class="text-xs text-slate-300 space-y-3 border-t border-slate-800/80 pt-6">
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span class="font-semibold text-white">FREE All Internal Apps</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span class="font-semibold text-white">FREE All External Apps</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span class="font-semibold text-white">FREE All Automation Tools</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Unlimited WhatsApp Numbers</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Kanban Deals Board & Smart Segments</span>
                        </li>
                    </ul>
                    <a href="dashboard/register.html" class="block w-full py-3 text-center text-slate-950 font-bold btn-primary rounded-xl transition">
                        Get Started
                    </a>
                </div>

                <!-- Premium Plan -->
                <div class="glass-panel p-8 rounded-2xl border-slate-800 space-y-6">
                    <div>
                        <h4 class="text-lg font-bold text-white">Premium Plan</h4>
                        <p class="text-xs text-slate-500 mt-1">For growing teams wanting predictable monthly budgeting.</p>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-4xl font-extrabold text-white">₹1,999</span>
                        <span class="text-xs text-slate-500 ml-2">/ month</span>
                    </div>
                    <ul class="text-xs text-slate-300 space-y-3 border-t border-slate-800/80 pt-6">
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>10,000 AI replies per month included</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span class="font-semibold text-white">FREE All Internal Apps</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span class="font-semibold text-white">FREE All External Apps</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Unlimited WhatsApp Numbers</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Kanban Deals Board & Smart Segments</span>
                        </li>
                    </ul>
                    <a href="dashboard/register.html" class="block w-full py-3 text-center text-slate-300 font-bold bg-slate-900 border border-slate-800 rounded-xl hover:text-white hover:bg-slate-800/80 transition">
                        Get Started
                    </a>
                </div>

                <!-- Enterprise -->
                <div class="glass-panel p-8 rounded-2xl border-slate-800 space-y-6">
                    <div>
                        <h4 class="text-lg font-bold text-white">Enterprise</h4>
                        <p class="text-xs text-slate-500 mt-1">Custom configurations for high-volume enterprises.</p>
                    </div>
                    <div class="flex items-baseline">
                        <span class="text-4xl font-extrabold text-white">Custom</span>
                    </div>
                    <ul class="text-xs text-slate-300 space-y-3 border-t border-slate-800/80 pt-6">
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Custom volume discounts on AI replies</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Dedicated servers & priority API endpoints</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>Custom SLA & direct developer support</span>
                        </li>
                        <li class="flex items-center space-x-2">
                            <i data-lucide="check" class="h-4 w-4 text-teal-400"></i>
                            <span>On-premise deployment options</span>
                        </li>
                    </ul>
                    <a href="dashboard/register.html" class="block w-full py-3 text-center text-slate-300 font-bold bg-slate-900 border border-slate-800 rounded-xl hover:text-white hover:bg-slate-800/80 transition">
                        Talk to Sales
                    </a>
                </div>
            </div>

            <!-- Features Comparison Table -->
            <div class="space-y-6">
                <h3 class="text-xl font-bold text-white text-center">Feature Matrix</h3>
                <div class="glass-panel rounded-2xl overflow-hidden border-slate-800">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-900/40 border-b border-slate-800 text-slate-400 uppercase font-bold">
                                <th class="py-4 px-6">Core Feature</th>
                                <th class="py-4 px-6">Free Plan (₹0)</th>
                                <th class="py-4 px-6">Recharge Plan (₹0)</th>
                                <th class="py-4 px-6 text-teal-400 font-semibold">Premium Plan (₹1,999)</th>
                                <th class="py-4 px-6">Enterprise (Custom)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/40 text-slate-300">
                            <tr>
                                <td class="py-4 px-6 font-semibold">Monthly Subscription Fee</td>
                                <td class="py-4 px-6">₹0 / month</td>
                                <td class="py-4 px-6">₹0 / month</td>
                                <td class="py-4 px-6 text-teal-400 font-semibold">₹1,999 / month</td>
                                <td class="py-4 px-6">Custom pricing</td>
                            </tr>
                            <tr>
                                <td class="py-4 px-6 font-semibold">AI Auto Replies Cost</td>
                                <td class="py-4 px-6">Free (uses 200 credits)</td>
                                <td class="py-4 px-6">₹0.20 per auto AI reply</td>
                                <td class="py-4 px-6 text-teal-400 font-semibold">Free (up to 10,000 replies)</td>
                                <td class="py-4 px-6">Volume-based discount</td>
                            </tr>
                            <tr>
                                <td class="py-4 px-6 font-semibold">Internal & External Apps</td>
                                <td class="py-4 px-6">Standard Access</td>
                                <td class="py-4 px-6">100% FREE</td>
                                <td class="py-4 px-6 text-teal-400 font-semibold">100% FREE</td>
                                <td class="py-4 px-6">Full Access + Custom Apps</td>
                            </tr>
                            <tr>
                                <td class="py-4 px-6 font-semibold">Automation & Workflows</td>
                                <td class="py-4 px-6">Basic Workflows</td>
                                <td class="py-4 px-6">100% FREE & Unlimited</td>
                                <td class="py-4 px-6 text-teal-400 font-semibold">100% FREE & Unlimited</td>
                                <td class="py-4 px-6">Custom Enterprise flows</td>
                            </tr>
                            <tr>
                                <td class="py-4 px-6 font-semibold">WhatsApp Numbers Limit</td>
                                <td class="py-4 px-6">1 Number</td>
                                <td class="py-4 px-6">Unlimited</td>
                                <td class="py-4 px-6 text-teal-400 font-semibold text-teal-400">Unlimited</td>
                                <td class="py-4 px-6">Unlimited</td>
                            </tr>
                            <tr>
                                <td class="py-4 px-6 font-semibold">Leads Limit (per user)</td>
                                <td class="py-4 px-6">2,500 leads</td>
                                <td class="py-4 px-6">Unlimited</td>
                                <td class="py-4 px-6 text-teal-400">Unlimited</td>
                                <td class="py-4 px-6">Unlimited</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            """

updated_pricing = pricing_content[:grid_start_idx] + new_pricing_grid + pricing_content[grid_end_idx:]

with open(pricing_path, "w", encoding="utf-8") as f:
    f.write(updated_pricing)

print("pricing.html updated successfully with custom plans!")
