import re

index_path = "/Users/wbsoumo/Desktop/LinkPilot AI/index.html"
with open(index_path, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Replace footer logo
old_logo_pattern = r'<a href="index\.html" aria-label="LinkPilot" class="pointer-events-none">.*?</a>'
new_logo = """<a href="index.html" aria-label="LinkPilot" class="pointer-events-none">
      <div class="relative logo__container flex items-center space-x-2" style="display: flex !important; align-items: center !important; gap: 0.5rem !important;">
        <img src="dashboard/assets/img/logo.png" class="h-7 w-7 object-contain" alt="LinkPilot AI" style="height: 1.75rem; width: 1.75rem;">
        <span class="text-xl font-black tracking-tight" style="font-size: 1.25rem; font-weight: 900; font-family: sans-serif; color: inherit;">LinkPilot</span>
      </div>
    </a>"""

content = re.sub(old_logo_pattern, new_logo, content, flags=re.DOTALL)

# 2. Replace support call link
old_call_pattern = r'<a href="tel:\+18305803077" class=" text-btn-link text-btn text-btn--primary text-left btn--content-center w-fit ">.*?</a>'
new_call = """<a href="tel:+919242322991" class=" text-btn-link text-btn text-btn--primary text-left btn--content-center w-fit ">
      <span class=" text-s font-medium leading-120 inline-block">Call: +91 9242322991</span>
  </a>"""

content = re.sub(old_call_pattern, new_call, content, flags=re.DOTALL)

# 3. Replace support sms link
old_sms_pattern = r'<a href="sms:\+18305803077" class=" text-btn-link text-btn text-btn--primary text-left btn--content-center w-fit ">.*?</a>'
new_sms = """<a href="sms:+919242322991" class=" text-btn-link text-btn text-btn--primary text-left btn--content-center w-fit ">
      <span class=" text-s font-medium leading-120 inline-block">Text message us</span>
  </a>"""

content = re.sub(old_sms_pattern, new_sms, content, flags=re.DOTALL)

# 4. Replace support email link
old_email_pattern = r'<a href="mailto:support@linkpilot\.com" class=" text-btn-link text-btn text-btn--primary text-left btn--content-center w-fit ">.*?</a>'
new_email = """<a href="mailto:support@linkpilot.work" class=" text-btn-link text-btn text-btn--primary text-left btn--content-center w-fit ">
      <span class=" text-s font-medium leading-120 inline-block">Email: support@linkpilot.work</span>
  </a>"""

content = re.sub(old_email_pattern, new_email, content, flags=re.DOTALL)

# 5. Remove store download buttons and update copyright block
old_copyright_div_pattern = r'<div class="flex flex-col gap-y-16 md:flex-row-reverse">.*?<p class="text-moonriverBliss text-s font-medium leading-120 inline-block">QSOFT LLC © 2009-2026\. All rights reserved\.</p>.*?</div>\s*</div>'
new_copyright_div = """<div class="flex flex-col gap-y-16 md:flex-row-reverse justify-center items-center">
        <div class="w-full text-center">
          <p class="text-moonriverBliss text-s font-medium leading-120 inline-block">Charge it with Taskbazi 2024 -26</p>
        </div>
      </div>
    </div>"""

content = re.sub(old_copyright_div_pattern, new_copyright_div, content, flags=re.DOTALL)

with open(index_path, "w", encoding="utf-8") as f:
    f.write(content)

print("index.html footer updated successfully!")

# Now do pricing.html
pricing_path = "/Users/wbsoumo/Desktop/LinkPilot AI/pricing.html"
with open(pricing_path, "r", encoding="utf-8") as f:
    p_content = f.read()

# Replace pricing.html copyright to "Charge it with Taskbazi 2024 -26"
p_content = p_content.replace('<div class="text-slate-700">© 2026 LinkPilot AI. All rights reserved.</div>', 
                              '<div class="text-slate-700">Charge it with Taskbazi 2024 -26</div>')

with open(pricing_path, "w", encoding="utf-8") as f:
    f.write(p_content)

print("pricing.html footer updated successfully!")
