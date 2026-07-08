# scratch/apply_clean_customizations.py
import re

with open("index.html", "r", encoding="utf-8") as f:
    html = f.read()

def report_replacement(name, old_text, new_text):
    global html
    if old_text in html:
        html = html.replace(old_text, new_text)
        print(f"SUCCESS: {name} replaced.")
    else:
        print(f"FAILED: {name} not found in HTML.")

# 1. Favicon
report_replacement(
    "Favicon",
    '<link href="//pcfcdn.kommo.com/favicon.ico" rel="shortcut icon">',
    '<link rel="icon" type="image/png" href="dashboard/assets/img/logo.png">'
)

# 2. Rename Capture tab to Automation
report_replacement(
    "Rename Tab",
    '<span class=" text-base font-normal  inline-block">Capture</span>',
    '<span class=" text-base font-normal  inline-block">Automation</span>'
)

# 3. Lottie frame integration
old_stage_block = """    <div class="main-demo-block__mobile-frame relative left-1/2 -translate-x-1/2 overflow-hidden bg-irisMist rounded-20 md:box-content md:w-auto md:border-4 md:border-irisMist lg:max-w-800">

      <div class="pointer-events-none absolute inset-0 z-10 hidden md:block">
        <div class="js-demo-block-loader-overlay h-full w-full">
          


<div class="main-demo-block__loader absolute inset-0 m-auto h-fit w-fit">
  <div class="w-32 h-32 rounded-full relative loader__in border-white ">
  </div>
</div>        </div>
      </div>

      <div class="overflow-hidden md:aspect-video">
        <div class="hidden h-full w-full md:block">
          <div class="h-full w-full">
            <div class="js-demo-block-stage flex h-full w-full items-start justify-end"></div>
          </div>
        </div>

        <div class="min-h-300 w-full md:hidden">
                      <div class="js-demo-block-mobile-panel h-full w-full " data-name="capture">
              <picture class="block h-full w-full"><source type="image/png" srcset="//pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-capture-image.png 1x, //pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-capture-image-2x.png 2x"></source><img class="block h-full w-full object-contain object-left-top" src="//pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-capture-image.png" alt="Medical appointment booking interface with service selection menu and available time slots displayed in a weekly calendar"></picture>
</div>
                      <div class="js-demo-block-mobile-panel h-full w-full hidden" data-name="automate">
              <picture class="block h-full w-full"><source type="image/png" srcset="//pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-automate-image.png 1x, //pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-automate-image-2x.png 2x"></source><img class="block h-full w-full object-contain object-left-top" src="//pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-automate-image.png" alt="AI agent checking calendar availability and matching chat requests with open appointment slots"></picture>
</div>
                      <div class="js-demo-block-mobile-panel h-full w-full hidden" data-name="scale">
              <picture class="block h-full w-full"><source type="image/png" srcset="//pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-scale-image.png 1x, //pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-scale-image-2x.png 2x"></source><img class="block h-full w-full object-contain object-left-top" src="//pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-scale-image.png" alt="AI assistant helping a client find properties by budget and sending a payment link to continue the purchase process"></picture>
</div>
                      <div class="js-demo-block-mobile-panel h-full w-full hidden" data-name="analyze">
              <picture class="block h-full w-full"><source type="image/png" srcset="//pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-analyze-image.png 1x, //pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-analyze-image-2x.png 2x"></source><img class="block h-full w-full object-contain object-left-top" src="//pcfcdn.kommo.com/images/pages/main/demo-block/en/demo-block-analyze-image.png" alt="LinkPilot Copilot analyzing lead drop-off after booking invitations and recommending follow-up actions to improve conversion"></picture>
</div>
                  </div>
      </div>
    </div>"""

new_stage_block = """    <div class="main-demo-block__mobile-frame relative left-1/2 -translate-x-1/2 overflow-hidden bg-irisMist rounded-20 md:box-content md:w-auto md:border-4 md:border-irisMist lg:max-w-800 flex items-center justify-center" style="display: flex !important; align-items: center !important; justify-content: center !important; min-height: 520px; background: #eceaf9;">
      
      <!-- Keep the JS stage/loaders hidden to prevent Javascript errors -->
      <div style="display: none !important;">
        <div class="js-demo-block-loader-overlay">
          <div class="js-demo-block-loader"></div>
        </div>
        <div class="js-demo-block-stage"></div>
        <div class="js-demo-block-mobile-panel" data-name="capture"></div>
        <div class="js-demo-block-mobile-panel" data-name="automate"></div>
        <div class="js-demo-block-mobile-panel" data-name="scale"></div>
        <div class="js-demo-block-mobile-panel" data-name="analyze"></div>
      </div>

      <!-- Main Lottie File Model Content -->
      <div class="flex items-center justify-center" style="display: flex !important; align-items: center !important; justify-content: center !important; width: 100%; height: 100%; min-height: 480px; padding: 1.5rem;">
        <script src="https://unpkg.com/@lottiefiles/dotlottie-wc@0.9.14/dist/dotlottie-wc.js" type="module"></script>
        <dotlottie-wc src="https://lottie.host/4c8c121f-ad1e-424f-9065-8d9e37f573de/Cig3lPLtTH.lottie" style="width: 420px; height: 420px; max-width: 100%;" autoplay loop></dotlottie-wc>
      </div>
    </div>"""

report_replacement("Lottie Frame", old_stage_block, new_stage_block)

# 4. Rewrite trust banner text
report_replacement(
    "Trust banner label",
    '<h6 class="px-40 pb-24 text-center md:px-0 md:pb-32 lg:pb-24 text-lg leading-120 font-bold md:text-2xl lg:text-3xl">      Trusted by fast-growing businesses in 100+ countries',
    '<h6 class="px-40 pb-24 text-center md:px-0 md:pb-32 lg:pb-24 text-lg leading-120 font-bold md:text-2xl lg:text-3xl">      Trusted by India\'s fastest-growing startups and digital brands'
)

# 5. Replace Marquee with pure CSS infinite-scrolling original logos marquee
# Let's locate the original marquee block and replace it
# We will read it from f and find the start and end of it.
marquee_start = '<div class="js-marquee marquee relative m-auto flex h-80 w-full max-w-screen-xl items-center overflow-hidden px-16 md:px-64 lg:h-120 lg:px-96 ">'
# The marquee ends before the closing of main-trust-block.
# In the original, the marquee ends with </div>\n</div>\n\n  \n\n\n\n<div class="max-w-screen-xl m-auto px-16 md:px-64' or similar.
# Let's use a regex to replace exactly the marquee block in HTML:
# From '<div class="js-marquee marquee ...>' up to the next '<div class="max-w-screen-xl m-auto px-16 md:px-64 lg:px-96">'
# Let's see: we want to replace:
# <div class="js-marquee marquee ...> ... </div>\n</div>
# Let's inspect how it ends.
# The marquee block starts with <div class="js-marquee marquee relative m-auto flex h-80 w-full max-w-screen-xl items-center overflow-hidden px-16 md:px-64 lg:h-120 lg:px-96 ">
# And the inner is: <div class="js-marquee-inner marquee__inner flex w-max">
# Then tracks, then </div>\n</div>
# Let's write a targeted replacement:
original_marquee_block_regex = r'<div class="js-marquee marquee relative m-auto flex h-80 w-full max-w-screen-xl items-center overflow-hidden px-16 md:px-64 lg:h-120 lg:px-96 ">.*?</div>\s*</div>'

custom_css_marquee = """<div class="relative m-auto flex h-80 w-full max-w-screen-xl items-center overflow-hidden px-16 md:px-64 lg:h-120 lg:px-96">
    <style>
      @keyframes custom-marquee-scroller {
        0% { transform: translateX(0%); }
        100% { transform: translateX(-50%); }
      }
      .custom-marquee-container {
        display: flex;
        width: max-content;
        animation: custom-marquee-scroller 30s linear infinite;
      }
      .custom-marquee-container:hover {
        animation-play-state: paused;
      }
    </style>
    <div class="custom-marquee-container flex items-center space-x-16">
      <!-- Track 1 -->
      <div class="flex items-center space-x-16 pr-16 shrink-0" style="gap: 5rem !important; display: flex !important; align-items: center !important;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/f/ff/Zoho_logo.svg" alt="Zoho" style="height: 35px; max-width: 120px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/8/89/Razorpay_logo.svg" alt="Razorpay" style="height: 28px; max-width: 140px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/2/24/Paytm_Logo.svg" alt="Paytm" style="height: 28px; max-width: 100px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/8/8e/Zerodha_logo.svg" alt="Zerodha" style="height: 35px; max-width: 140px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/1/13/Swiggy_logo.svg" alt="Swiggy" style="height: 35px; max-width: 110px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/b/bd/Zomato_Logo.svg" alt="Zomato" style="height: 28px; max-width: 110px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/7/7a/Flipkart_logo.svg" alt="Flipkart" style="height: 32px; max-width: 120px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/5/50/Reliance_Jio_Logo.svg" alt="Jio" style="height: 38px; max-width: 80px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/b/b1/Tata_Consultancy_Services_Logo.svg" alt="TCS" style="height: 38px; max-width: 120px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/95/Infosys_logo.svg" alt="Infosys" style="height: 26px; max-width: 110px; object-fit: contain;">
      </div>
      <!-- Duplicate Track for Seamless Loop -->
      <div class="flex items-center space-x-16 pr-16 shrink-0" style="gap: 5rem !important; display: flex !important; align-items: center !important;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/f/ff/Zoho_logo.svg" alt="Zoho" style="height: 35px; max-width: 120px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/8/89/Razorpay_logo.svg" alt="Razorpay" style="height: 28px; max-width: 140px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/2/24/Paytm_Logo.svg" alt="Paytm" style="height: 28px; max-width: 100px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/8/8e/Zerodha_logo.svg" alt="Zerodha" style="height: 35px; max-width: 140px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/1/13/Swiggy_logo.svg" alt="Swiggy" style="height: 35px; max-width: 110px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/b/bd/Zomato_Logo.svg" alt="Zomato" style="height: 28px; max-width: 110px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/7/7a/Flipkart_logo.svg" alt="Flipkart" style="height: 32px; max-width: 120px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/5/50/Reliance_Jio_Logo.svg" alt="Jio" style="height: 38px; max-width: 80px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/b/b1/Tata_Consultancy_Services_Logo.svg" alt="TCS" style="height: 38px; max-width: 120px; object-fit: contain;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/95/Infosys_logo.svg" alt="Infosys" style="height: 26px; max-width: 110px; object-fit: contain;">
      </div>
    </div>
  </div>"""

if re.search(original_marquee_block_regex, html, flags=re.DOTALL):
    html = re.sub(original_marquee_block_regex, custom_css_marquee, html, flags=re.DOTALL)
    print("SUCCESS: Marquee block replaced.")
else:
    print("FAILED: Marquee block not found in HTML.")

# 6. Remove Watch real stories link
old_watch_stories = """  <a href="https://www.youtube.com/@linkpilotglobal" class="w-full pt-24 md:pt-20 lg:pt-8 text-btn-link text-btn text-btn--primary btn--content-center w-fit " rel="noopener noreferrer" target="_blank">
      
  









  <span class=" text-base font-bold leading-120 inline-block">Watch real stories</span>

      <span class="block ml-8">
      <svg width="16" height="16" viewbox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.6 0C7.6 3.88571 9.66667 5.94286 12.3778 6.72V6.85714H0V9.14286H12.3778V9.25714C9.66667 10.0571 7.6 12.1143 7.6 16H10.0444C10.0444 12.5486 12.6222 10.5143 16 8.82286V7.2C12.6222 5.50857 10.0444 3.47429 10.0444 0H7.6Z" fill="currentColor"></path></svg></span>
  

      <div class="js-control-button-spinner btn__spinner hidden absolute inset-0 m-auto w-16 h-16 opacity-0 border-2 border-current border-r-transparent border-b-transparent rounded-full animate-spin">
  </div>

  </a>"""

report_replacement("Watch Stories Link", old_watch_stories, "")

# 7. Remove floating hello cta button wrapper
old_hello_cta = """              <div class="js-sticky-cta-wrapper sticky-cta-button-holder fixed inset-x-0 bottom-88 z-30 pointer-events-none hidden">
          <div class="sticky-cta-button-wrapper relative flex w-full justify-end px-16 md:px-32 lg:px-48">
            

<div id="sticky-cta-button" class="sticky-cta-button js-try-it-free relative flex items-center justify-start overflow-hidden rounded-full border border-electricBlue bg-white pointer-events-auto cursor-pointer" data-name="sticky-ai-cta" data-register-source="ai" data-preserve-text="true" aria-expanded="false">
  <span class="sticky-cta-button__overlay absolute inset-0 z-20 rounded-full"></span>

  <div class="sticky-cta-button__media z-10 shrink-0 overflow-hidden rounded-full">
    <video class="sticky-cta-button__video block z-30 object-cover" autoplay loop muted playsinline preload="metadata"><source src="//pcfcdn.kommo.com/images/pages/main/video/waving-hand.mp4" type="video/mp4"></source></video>
</div>

  <div class="sticky-cta-button__copy z-30 overflow-hidden whitespace-nowrap">
      









  

  <button class="js-control-button whitespace-nowrap enabled:focus:text-tolopea enabled:hover:text-tolopea text-btn text-btn--primary btn--content-center text-btn-link text-btn-link--no-hover " type="button">
      
  






  <span class=" text-base font-medium leading-120 inline-block">Try LinkPilot AI for free</span>

  

      <div class="js-control-button-spinner btn__spinner hidden absolute inset-0 m-auto w-16 h-16 opacity-0 border-2 border-current border-r-transparent border-b-transparent rounded-full animate-spin">
  </div>

  </button>

  </div>
</div>
          </div>
        </div>"""

report_replacement("Hello Floating CTA", old_hello_cta, "")

# 8. Remove the amoSocialButton script
old_social_script = """                                  <script nonce="e9af510aa1a2">
  (function (a, m, o, c, r, m) {
    a[m] = {
      id: '2783', hash: 'e08d399e6dae8cd9893e374275eb4c9b8add8da87e989b60e1bdfc68705fe1a4', locale: 'en', setMeta: function (p) {
        this.params = (this.params || []).concat([p]);
      },
    };
    a[o] = a[o] || function () {
      (a[o].q = a[o].q || []).push(arguments);
    };
    var d = a.document, s = d.createElement('script');
    s.async = true;
    s.id = m + '_script';
          s.src = 'https://gso.kommo.com/js/button.js?1610457157';
        d.head && d.head.appendChild(s);
  }(window, 0, 'amoSocialButton', 0, 0, 'amo_social_button'));
</script>"""

report_replacement("Amo Social Script", old_social_script, "")

with open("index.html", "w", encoding="utf-8") as f:
    f.write(html)

print("All customizations applied successfully!")
