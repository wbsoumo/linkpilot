import re

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

new_index_pricing = """<div id="PickYourPlan" class="pt-20 pb-20 md:pt-0 md:pb-0 md:mb-0 lg:mt-20 flex flex-col">
  <div class="flex-row-reverse flex flex-wrap justify-between items-center mb-40 md:px-0">
    <div class="overflow-auto no-scrollbar block flex-auto order-2 mt-16 -mx-16 md:mt-0">
      <div class="w-max px-16">
        <div class="flex w-fit space-x-4 px-6 py-6 bg-linkWater rounded-full md:min-w-max">
          <span class="px-20 py-12 rounded-full bg-pastelOrange text-base font-bold text-tolopea">6 months</span>
          <span class="px-20 py-12 rounded-full text-base font-medium text-tolopea opacity-60">9 months</span>
          <span class="px-20 py-12 rounded-full text-base font-medium text-tolopea opacity-60">1 year</span>
          <span class="px-20 py-12 rounded-full text-base font-medium text-tolopea opacity-60">2 years</span>
        </div>
      </div>
    </div>
    <div class="border border-tolopea/30 rounded-full px-20 py-10 flex items-center space-x-8 text-base font-bold text-tolopea bg-white shadow-sm">
      <span>₹ INR</span>
      <svg class="w-12 h-12 text-tolopea" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
    </div>
  </div>

  <div class="grid grid-cols-1 mb-40 gap-22 md:mb-48 lg:mb-60 lg:grid-cols-4">
    
    <!-- Free Plan -->
    <div class="min-h-264 md:min-h-320 relative px-24 py-24 pb-48 rounded-20 bg-linkWater md:pb-48 xxl:pb-40 md:px-32 md:pt-32 md:pb-48 lg:px-24 lg:py-24 lg:pb-40">
      <div class="relative flex h-full flex-col">
        <div class="flex justify-between items-center md:max-w-288 mb-8">
          <h2 class="text-tolopea text-lg leading-120 font-bold md:text-2xl lg:text-3xl">Free Plan</h2>
        </div>
        <p class="max-w-640 mb-24 opacity-60 text-s text-tolopea break-words line-clamp-2 md:max-w-288 text-s leading-140">Ideal for testing and setting up your workspace.</p>
        
        <div class="mt-20">
          <div class="mt-auto flex flex-col items-start">
            <div class="flex">
              <h5 class="text-6xl font-bold leading-120 text-tolopea md:text-6xl text-2xl leading-120 font-bold md:text-3xl lg:text-6xl">₹0</h5>
              <span class="mt-4 ml-4 mb-2 font-bold md:mb-4 lg:mb-2 text-s leading-140 inline-block">INR</span>
            </div>
            <span class="opacity-60 text-tolopea mb-2 text-s leading-140 inline-block">per month</span>
          </div>

          <div class="flex flex-col justify-center items-start w-full pt-20 pb-20 space-y-12 border-t border-dashed border-tolopea/20 mt-12">
            <div class="flex flex-col">
              <span class="text-base font-bold leading-120 text-juicyPurple">200 credits</span>
              <span class="text-s leading-140 text-dreamsViolet">Free AI credits included</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-bold leading-120 text-juicyPurple">1 WhatsApp Number</span>
              <span class="text-s leading-140 text-dreamsViolet">WhatsApp number limit</span>
            </div>
            <div class="text-juicyPurple underline underline-offset-4 decoration-dashed decoration-1">
              <span class="text-s leading-140 text-juicyPurple">Standard CRM included</span>
            </div>
          </div>

          <a href="dashboard/register.html" class="mt-20 w-full m-auto md:mx-0 md:w-fit lg:w-full btn btn--outline btn--small btn--center text-btn text-btn--primary-inversed btn--content-center text-btn-link flex items-center justify-center">
            <span class="text-s font-medium leading-120 inline-block">Try it free</span>
          </a>
        </div>
      </div>
    </div>

    <!-- Recharge Plan (Hero) -->
    <div class="min-h-264 md:min-h-320 relative px-24 py-24 pb-48 rounded-20 bg-ai-gradient-purple md:pb-48 xxl:pb-40 md:px-32 md:pt-32 md:pb-48 lg:px-24 lg:py-24 lg:pb-40">
      <div class="absolute inset-x-0 top-0 z-10 flex justify-start pl-24 pointer-events-none md:pl-32 lg:pl-0 lg:justify-center">
        <div class="-mt-12 pointer-events-auto">
          <div class="inline-flex items-center justify-center px-8 py-6 text-s font-bold leading-120 rounded-8 text-white bg-irisMist">
            most popular
          </div>
        </div>
      </div>
      
      <div class="absolute inset-0 overflow-hidden rounded-20 pointer-events-none">
        <picture class="absolute right-0 bottom-0 rounded-20 md:hidden"><source type="image/png" media="(max-width: 767.9999px)" data-srcset="//pcfcdn.kommo.com/images/pages/main/star-corner-mobile-new.png 1x, //pcfcdn.kommo.com/images/pages/main/star-corner-mobile-new-2x.png 2x"></source><img class="lazy" alt="" width="281" height="160"></picture><picture class="absolute right-0 bottom-0 hidden rounded-tr-20 overflow-hidden rounded-20 md:block lg:hidden"><source type="image/png" media="(min-width: 768px) and (max-width: 1199.9999px)" data-srcset="//pcfcdn.kommo.com/images/pages/main/star-corner-tablet-new.png 1x, //pcfcdn.kommo.com/images/pages/main/star-corner-tablet-new-2x.png 2x"></source><img class="lazy" alt="" width="420" height="420"></picture><picture class="absolute right-0 bottom-0 hidden rounded-br-20 overflow-hidden rounded-20 lg:block"><source type="image/png" media="(min-width: 1200px)" data-srcset="//pcfcdn.kommo.com/images/pages/main/star-corner-desktop-new.png 1x, //pcfcdn.kommo.com/images/pages/main/star-corner-desktop-new-2x.png 2x"></source><img class="lazy" alt="" width="236" height="135"></picture>
      </div>

      <div class="relative flex h-full flex-col z-10">
        <div class="flex justify-between items-center md:max-w-288 mb-8">
          <h2 class="text-tolopea text-lg leading-120 font-bold md:text-2xl lg:text-3xl">Recharge Plan</h2>
        </div>
        <p class="max-w-640 mb-24 opacity-60 text-s text-tolopea break-words line-clamp-2 md:max-w-288 text-s leading-140">Pay-as-you-go. Zero monthly commitment.</p>
        
        <div class="mt-20">
          <div class="mt-auto flex flex-col items-start">
            <div class="flex">
              <h5 class="text-6xl font-bold leading-120 text-tolopea md:text-6xl text-2xl leading-120 font-bold md:text-3xl lg:text-6xl">₹0</h5>
              <span class="mt-4 ml-4 mb-2 font-bold md:mb-4 lg:mb-2 text-s leading-140 inline-block">INR</span>
            </div>
            <span class="opacity-60 text-tolopea mb-2 text-s leading-140 inline-block">per month subscription</span>
            <span class="text-s text-purpleHeart font-bold mt-4 bg-white/40 px-8 py-4 rounded">₹0.20 per auto AI reply</span>
          </div>

          <div class="flex flex-col justify-center items-start w-full pt-20 pb-20 space-y-12 border-t border-dashed border-tolopea/20 mt-12">
            <div class="flex flex-col">
              <span class="text-base font-bold leading-120 text-juicyPurple">FREE All Apps</span>
              <span class="text-s leading-140 text-dreamsViolet">All internal & external apps free</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-bold leading-120 text-juicyPurple">FREE Automation Tools</span>
              <span class="text-s leading-140 text-dreamsViolet">Unlimited workflows & tools free</span>
            </div>
            <div class="text-juicyPurple underline underline-offset-4 decoration-dashed decoration-1">
              <span class="text-s leading-140 text-juicyPurple">Unlimited WhatsApp Numbers</span>
            </div>
          </div>

          <a href="dashboard/register.html" class="mt-20 w-full m-auto md:mx-0 md:w-fit lg:w-full btn btn--ai-gradient-3 btn--small btn--center text-btn text-btn--primary btn--content-center text-btn-link flex items-center justify-center shadow-md">
            <span class="text-s font-medium leading-120 inline-block font-semibold">Try it free</span>
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
        
        <div class="mt-20">
          <div class="mt-auto flex flex-col items-start">
            <div class="flex">
              <h5 class="text-6xl font-bold leading-120 text-tolopea md:text-6xl text-2xl leading-120 font-bold md:text-3xl lg:text-6xl">1,999</h5>
              <span class="mt-4 ml-4 mb-2 font-bold md:mb-4 lg:mb-2 text-s leading-140 inline-block">INR</span>
            </div>
            <span class="opacity-60 text-tolopea mb-2 text-s leading-140 inline-block">per month</span>
          </div>

          <div class="flex flex-col justify-center items-start w-full pt-20 pb-20 space-y-12 border-t border-dashed border-tolopea/20 mt-12">
            <div class="flex flex-col">
              <span class="text-base font-bold leading-120 text-juicyPurple">10 000 replies</span>
              <span class="text-s leading-140 text-dreamsViolet">Monthly AI auto replies included</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-bold leading-120 text-juicyPurple">FREE All Apps</span>
              <span class="text-s leading-140 text-dreamsViolet">All internal & external apps free</span>
            </div>
            <div class="text-juicyPurple underline underline-offset-4 decoration-dashed decoration-1">
              <span class="text-s leading-140 text-juicyPurple">Unlimited WhatsApp Numbers</span>
            </div>
          </div>

          <a href="dashboard/register.html" class="mt-20 w-full m-auto md:mx-0 md:w-fit lg:w-full btn btn--outline btn--small btn--center text-btn text-btn--primary-inversed btn--content-center text-btn-link flex items-center justify-center">
            <span class="text-s font-medium leading-120 inline-block">Try it free</span>
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
        
        <div class="mt-20">
          <div class="mt-auto flex flex-col items-start">
            <div class="flex">
              <h5 class="text-6xl font-bold leading-120 text-tolopea md:text-6xl text-2xl leading-120 font-bold md:text-3xl lg:text-6xl">Custom</h5>
            </div>
            <span class="opacity-60 text-tolopea mb-2 text-s leading-140 inline-block">Depends on your setup</span>
          </div>

          <div class="flex flex-col justify-center items-start w-full pt-20 pb-20 space-y-12 border-t border-dashed border-tolopea/20 mt-12">
            <div class="flex flex-col">
              <span class="text-base font-bold leading-120 text-juicyPurple">Custom Volume</span>
              <span class="text-s leading-140 text-dreamsViolet">Discounts on AI replies</span>
            </div>
            <div class="flex flex-col">
              <span class="text-base font-bold leading-120 text-juicyPurple">Dedicated support</span>
              <span class="text-s leading-140 text-dreamsViolet">Direct developer SLA support</span>
            </div>
            <div class="text-juicyPurple underline underline-offset-4 decoration-dashed decoration-1">
              <span class="text-s leading-140 text-juicyPurple">On-premise deploy option</span>
            </div>
          </div>

          <a href="dashboard/register.html" class="mt-20 w-full m-auto md:mx-0 md:w-fit lg:w-full btn btn--outline btn--small btn--center text-btn text-btn--primary-inversed btn--content-center text-btn-link flex items-center justify-center">
            <span class="text-s font-medium leading-120 inline-block">Try it free</span>
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

print("index.html updated successfully with same-to-same look and custom plans!")
