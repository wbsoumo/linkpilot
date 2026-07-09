import re

# 1. Update index.html
index_path = "/Users/wbsoumo/Desktop/LinkPilot AI/index.html"
with open(index_path, "r", encoding="utf-8") as f:
    index_content = f.read()

start_pattern = r'<div id="PickYourPlan"'
end_pattern = r'<div class="w-full rounded-t-20 bg-tolopea md:px-40">'

start_match = re.search(start_pattern, index_content)
end_match = re.search(end_pattern, index_content)

if not start_match or not end_match:
    print("Error: Could not locate pricing boundaries in index.html.")
    exit(1)

start_idx = start_match.start()
end_idx = end_match.start()

new_index_pricing = """<div id="PickYourPlan" class="pt-20 pb-20 md:pt-0 md:pb-0 md:mb-0 lg:mt-20 flex flex-col max-w-screen-xl m-auto px-16 md:px-64 lg:px-96 w-full">
  <div class="mb-40 md:px-0 text-center md:text-left">
    <h2 class="text-tolopea text-4xl leading-120 font-medium md:text-6xl md:leading-130 lg:text-8xl mb-8">Simple, transparent pricing</h2>
    <p class="opacity-60 text-base leading-160 text-tolopea">Choose the plan that fits your business needs. No subscription fees.</p>
  </div>

  <div class="grid grid-cols-1 mb-40 gap-22 md:mb-48 lg:mb-60 lg:grid-cols-4">
    
    <!-- Free Plan -->
    <div class="min-h-264 md:min-h-320 relative px-24 py-24 pb-48 rounded-20 bg-linkWater md:pb-48 xxl:pb-40 md:px-32 md:pt-32 md:pb-48 lg:px-24 lg:py-24 lg:pb-40">
      <div class="relative flex h-full flex-col">
        <div class="flex justify-between items-center md:max-w-288 mb-8">
          <h2 class="text-tolopea text-lg leading-120 font-bold md:text-2xl lg:text-3xl">Free Plan</h2>
        </div>
        <p class="max-w-640 mb-24 opacity-60 text-s text-tolopea break-words line-clamp-2 md:max-w-288 text-s leading-140">Ideal for testing and setting up your workspace.</p>
        <div class="mt-20 flex flex-col flex-grow justify-between">
          <div class="flex flex-col items-start mb-20">
            <div class="flex">
              <h5 class="text-6xl font-bold leading-120 text-tolopea md:text-6xl text-2xl leading-120 font-bold md:text-3xl lg:text-6xl">₹0</h5>
            </div>
            <span class="opacity-60 text-tolopea text-s leading-140 inline-block">per month</span>
          </div>
          <div class="flex flex-col justify-center items-start pt-20 pb-20 space-y-12 border-t border-dashed border-tolopea/20">
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">200 credits</span>
              <span class="text-s leading-140 text-dreamsViolet">Free AI credits included</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">1 WhatsApp Number</span>
              <span class="text-s leading-140 text-dreamsViolet">WhatsApp number limit</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">Standard CRM</span>
              <span class="text-s leading-140 text-dreamsViolet">Shared Team Inbox included</span>
            </div>
          </div>
          <a href="dashboard/register.html" class="mt-20 w-full m-auto md:mx-0 md:w-fit lg:w-full btn btn--outline btn--small btn--center text-btn text-btn--primary-inversed btn--content-center text-btn-link flex items-center justify-center">
            <span class="text-s font-medium leading-120 inline-block">Get Started Free</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Recharge Plan (Hero) -->
    <div class="min-h-264 md:min-h-320 relative px-24 py-24 pb-48 rounded-20 bg-ai-gradient-purple md:pb-48 xxl:pb-40 md:px-32 md:pt-32 md:pb-48 lg:px-24 lg:py-24 lg:pb-40 overflow-hidden">
      <div class="absolute inset-0 overflow-hidden rounded-20 pointer-events-none">
        <picture class="absolute right-0 bottom-0 rounded-20 md:hidden"><source type="image/png" media="(max-width: 767.9999px)" data-srcset="//pcfcdn.kommo.com/images/pages/main/star-corner-mobile-new.png 1x, //pcfcdn.kommo.com/images/pages/main/star-corner-mobile-new-2x.png 2x"></source><img class="lazy" alt="" width="281" height="160"></picture><picture class="absolute right-0 bottom-0 hidden rounded-tr-20 overflow-hidden rounded-20 md:block lg:hidden"><source type="image/png" media="(min-width: 768px) and (max-width: 1199.9999px)" data-srcset="//pcfcdn.kommo.com/images/pages/main/star-corner-tablet-new.png 1x, //pcfcdn.kommo.com/images/pages/main/star-corner-tablet-new-2x.png 2x"></source><img class="lazy" alt="" width="420" height="420"></picture><picture class="absolute right-0 bottom-0 hidden rounded-br-20 overflow-hidden rounded-20 lg:block"><source type="image/png" media="(min-width: 1200px)" data-srcset="//pcfcdn.kommo.com/images/pages/main/star-corner-desktop-new.png 1x, //pcfcdn.kommo.com/images/pages/main/star-corner-desktop-new-2x.png 2x"></source><img class="lazy" alt="" width="236" height="135"></picture>
      </div>
      <div class="relative flex h-full flex-col z-10">
        <div class="inline-flex items-center justify-center px-10 py-4 text-[10px] font-bold leading-120 rounded-full text-white bg-irisMist mb-12 w-fit">
          MOST POPULAR
        </div>
        <div class="flex justify-between items-center md:max-w-288 mb-8">
          <h2 class="text-tolopea text-lg leading-120 font-bold md:text-2xl lg:text-3xl">Recharge Plan</h2>
        </div>
        <p class="max-w-640 mb-24 opacity-60 text-s text-tolopea break-words line-clamp-2 md:max-w-288 text-s leading-140">Pay-as-you-go. Zero monthly commitment.</p>
        <div class="mt-20 flex flex-col flex-grow justify-between">
          <div class="flex flex-col items-start mb-20">
            <div class="flex">
              <h5 class="text-6xl font-bold leading-120 text-tolopea md:text-6xl text-2xl leading-120 font-bold md:text-3xl lg:text-6xl">₹0</h5>
            </div>
            <span class="opacity-60 text-tolopea text-s leading-140 inline-block">per month subscription</span>
            <span class="text-s text-purpleHeart font-bold mt-4 bg-white/40 px-8 py-4 rounded">₹0.20 per auto AI reply</span>
          </div>
          <div class="flex flex-col justify-center items-start pt-20 pb-20 space-y-12 border-t border-dashed border-tolopea/20">
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">FREE All Apps</span>
              <span class="text-s leading-140 text-dreamsViolet">All internal & external apps free</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">FREE Automation Tools</span>
              <span class="text-s leading-140 text-dreamsViolet">Unlimited workflows & tools free</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">Unlimited Numbers</span>
              <span class="text-s leading-140 text-dreamsViolet">Connect unlimited WhatsApp numbers</span>
            </div>
          </div>
          <a href="dashboard/register.html" class="mt-20 w-full m-auto md:mx-0 md:w-fit lg:w-full btn btn--ai-gradient-3 btn--small btn--center text-btn text-btn--primary btn--content-center text-btn-link flex items-center justify-center">
            <span class="text-s font-medium leading-120 inline-block font-semibold">Get Started</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Premium Plan -->
    <div class="min-h-264 md:min-h-320 relative px-24 py-24 pb-48 rounded-20 bg-linkWater md:pb-48 xxl:pb-40 md:px-32 md:pt-32 md:pb-48 lg:px-24 lg:py-24 lg:pb-40">
      <div class="relative flex h-full flex-col">
        <div class="flex justify-between items-center md:max-w-288 mb-8">
          <h2 class="text-tolopea text-lg leading-120 font-bold md:text-2xl lg:text-3xl">Premium Plan</h2>
        </div>
        <p class="max-w-640 mb-24 opacity-60 text-s text-tolopea break-words line-clamp-2 md:max-w-288 text-s leading-140">For growing teams wanting predictable monthly budgeting.</p>
        <div class="mt-20 flex flex-col flex-grow justify-between">
          <div class="flex flex-col items-start mb-20">
            <div class="flex">
              <h5 class="text-6xl font-bold leading-120 text-tolopea md:text-6xl text-2xl leading-120 font-bold md:text-3xl lg:text-6xl">₹1,999</h5>
            </div>
            <span class="opacity-60 text-tolopea text-s leading-140 inline-block">per month</span>
          </div>
          <div class="flex flex-col justify-center items-start pt-20 pb-20 space-y-12 border-t border-dashed border-tolopea/20">
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">10,000 replies</span>
              <span class="text-s leading-140 text-dreamsViolet">Monthly AI auto replies included</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">FREE All Apps</span>
              <span class="text-s leading-140 text-dreamsViolet">All internal & external apps free</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">Unlimited Numbers</span>
              <span class="text-s leading-140 text-dreamsViolet">Connect unlimited WhatsApp numbers</span>
            </div>
          </div>
          <a href="dashboard/register.html" class="mt-20 w-full m-auto md:mx-0 md:w-fit lg:w-full btn btn--outline btn--small btn--center text-btn text-btn--primary-inversed btn--content-center text-btn-link flex items-center justify-center">
            <span class="text-s font-medium leading-120 inline-block">Get Started</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Enterprise -->
    <div class="min-h-264 md:min-h-320 relative px-24 py-24 pb-48 rounded-20 bg-linkWater md:pb-48 xxl:pb-40 md:px-32 md:pt-32 md:pb-48 lg:px-24 lg:py-24 lg:pb-40">
      <div class="relative flex h-full flex-col">
        <div class="flex justify-between items-center md:max-w-288 mb-8">
          <h2 class="text-tolopea text-lg leading-120 font-bold md:text-2xl lg:text-3xl">Enterprise</h2>
        </div>
        <p class="max-w-640 mb-24 opacity-60 text-s text-tolopea break-words line-clamp-2 md:max-w-288 text-s leading-140">Custom configurations for high-volume enterprises.</p>
        <div class="mt-20 flex flex-col flex-grow justify-between">
          <div class="flex flex-col items-start mb-20">
            <div class="flex">
              <h5 class="text-6xl font-bold leading-120 text-tolopea md:text-6xl text-2xl leading-120 font-bold md:text-3xl lg:text-6xl">Custom</h5>
            </div>
            <span class="opacity-60 text-tolopea text-s leading-140 inline-block">Depends on your setup</span>
          </div>
          <div class="flex flex-col justify-center items-start pt-20 pb-20 space-y-12 border-t border-dashed border-tolopea/20">
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">Custom Volume</span>
              <span class="text-s leading-140 text-dreamsViolet">Discounts on AI auto replies</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">Dedicated support</span>
              <span class="text-s leading-140 text-dreamsViolet">Direct developer SLA support</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-medium leading-120 text-juicyPurple">On-premise deploy</span>
              <span class="text-s leading-140 text-dreamsViolet">Custom database and API hosting</span>
            </div>
          </div>
          <a href="dashboard/register.html" class="mt-20 w-full m-auto md:mx-0 md:w-fit lg:w-full btn btn--outline btn--small btn--center text-btn text-btn--primary-inversed btn--content-center text-btn-link flex items-center justify-center">
            <span class="text-s font-medium leading-120 inline-block">Talk to Sales</span>
          </a>
        </div>
      </div>
    </div>

  </div>
</div>
"""

updated_index = index_content[:start_idx] + new_index_pricing + index_content[end_idx:]

with open(index_path, "w", encoding="utf-8") as f:
    f.write(updated_index)

print("index.html updated successfully with 4 plans!")


# 2. Update pricing.html
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
                                <th class="py-4 px-6 text-teal-400">Premium Plan (₹1,999)</th>
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
                                <td class="py-4 px-6 text-teal-400">Unlimited</td>
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

print("pricing.html updated successfully with 4 plans!")
