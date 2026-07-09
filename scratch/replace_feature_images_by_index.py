import re

index_path = "/Users/wbsoumo/Desktop/LinkPilot AI/index.html"
with open(index_path, "r", encoding="utf-8") as f:
    content = f.read()

def find_div_blocks(html):
    blocks = []
    pattern = re.compile(r'<div [^>]*class="[^"]*feature-block-image[^"]*"')
    for match in pattern.finditer(html):
        start_idx = match.start()
        depth = 0
        end_idx = None
        tag_pattern = re.compile(r'<(div|/div)\b[^>]*>', re.IGNORECASE)
        for tag_match in tag_pattern.finditer(html, pos=start_idx):
            tag_name = tag_match.group(1).lower()
            if tag_name == 'div':
                depth += 1
            elif tag_name == '/div':
                depth -= 1
                if depth == 0:
                    end_idx = tag_match.end()
                    break
        if end_idx is not None:
            blocks.append((start_idx, end_idx))
    return blocks

blocks = find_div_blocks(content)
print(f"Found {len(blocks)} feature-block-image containers.")

if len(blocks) != 8:
    print("Error: Expected 8 blocks, found something else. Exiting.")
    exit(1)

# Rebuild content from last block to first block to avoid changing index positions
new_content = content
for i in reversed(range(8)):
    start, end = blocks[i]
    # We replace:
    # 0 (1st swiper: chat) -> tophero.png
    # 2 (3rd swiper: meetings) -> 2nd.png
    # 3 (4th swiper: sales) -> 3rd.png
    # 4 (1st grid: chat) -> tophero.png
    # 6 (3rd grid: meetings) -> 2nd.png
    # 7 (4th grid: sales) -> 3rd.png
    
    if i in [0, 4]:
        # Chat Management
        h_class = "h-224 sm:h-320" if i == 0 else "h-320 lg:col-span-8 lg:h-400"
        replacement = f"""<div class="{h_class} rounded-20 overflow-hidden feature-block-image">
  <img src="assets/img/tophero.png" class="w-full h-full object-cover object-right" alt="Shared Team Inbox & AI Chatbot">
</div>"""
        new_content = new_content[:start] + replacement + new_content[end:]
    elif i in [2, 6]:
        # Book Meetings
        h_class = "h-224 sm:h-320" if i == 2 else "h-320 lg:col-span-8 lg:h-400"
        replacement = f"""<div class="{h_class} rounded-20 overflow-hidden feature-block-image">
  <img src="assets/img/2nd.png" class="w-full h-full object-cover object-left" alt="Meeting Scheduling & Reminders">
</div>"""
        new_content = new_content[:start] + replacement + new_content[end:]
    elif i in [3, 7]:
        # Boost Sales
        h_class = "h-224 sm:h-320" if i == 3 else "h-320 lg:col-span-8 lg:h-400"
        replacement = f"""<div class="{h_class} rounded-20 overflow-hidden feature-block-image">
  <img src="assets/img/3rd.png" class="w-full h-full object-cover object-left" alt="Campaign Performance & Boost Sales">
</div>"""
        new_content = new_content[:start] + replacement + new_content[end:]

# Now replace the AI block (4th block: reply-24-7)
# Note that in 104fd5c, these might be using remote cdn URLs, so we target them using patterns or directly.
# Let's inspect index.html for what it has for reply-24-7.
# Actually, since it has the class js-ai-snippet-block-mobile-preview and js-ai-snippet-block-preview,
# we can write a simple regex replacement or direct string replacement for those blocks!
# Let's see:
# mobile preview:
# <div class="js-ai-snippet-block-mobile-preview ... " data-name="reply-24-7"> ... </div>
# desktop preview:
# <div class="js-ai-snippet-block-preview " data-name="reply-24-7"> ... </div>

# Let's match:
# <div class="js-ai-snippet-block-mobile-preview  mt-12 flex items-center justify-center overflow-hidden rounded-20 bg-morningSkylight md:mt-16" data-name="reply-24-7">...</div>
# but wait, let's use search/replace that handles whatever is inside.
# Since in 104fd5c it has the cdn images or whatsapp.png depending on what was pushed, let's find the tags.
# In 104fd5c, the cdn images were replaced by whatsapp.png already!
# Let's verify by replacing:
old_ai_mobile_1 = """  <div class="js-ai-snippet-block-mobile-preview  mt-12 flex items-center justify-center overflow-hidden rounded-20 bg-morningSkylight md:mt-16" data-name="reply-24-7">
    <picture class="shrink-0 md:hidden"><source type="image/png" media="(max-width: 767.9999px)" srcset="assets/img/whatsapp.png 1x, assets/img/whatsapp.png 2x"></source><img class="main-ai-snippet-block__preview-image block max-w-none" src="assets/img/whatsapp.png" width="687" height="386" alt="AI assistant collecting an email address from a prospective student and sending a registration form for course enrollment"></picture><picture class="hidden shrink-0 md:block"><source type="image/png" media="(min-width: 768px) and (max-width: 1199.9999px)" srcset="assets/img/whatsapp.png 1x, assets/img/whatsapp.png 2x"></source><img class="main-ai-snippet-block__preview-image block max-w-none" src="assets/img/whatsapp.png" width="1007" height="566" alt="AI assistant collecting an email address from a prospective student and sending a registration form for course enrollment"></picture>
</div>"""

new_ai_mobile = """  <div class="js-ai-snippet-block-mobile-preview  mt-12 flex items-center justify-center overflow-hidden rounded-20 bg-morningSkylight md:mt-16" data-name="reply-24-7">
    <img src="assets/img/whatsapp.png" class="main-ai-snippet-block__preview-image block max-w-full" alt="AI assistant 24/7 replies">
</div>"""

new_content = new_content.replace(old_ai_mobile_1, new_ai_mobile)

old_ai_desktop_1 = """  <div class="js-ai-snippet-block-preview " data-name="reply-24-7">
    <div class="main-ai-snippet-block__preview-frame flex items-center justify-center overflow-hidden rounded-20">
      <picture class="shrink-0"><source type="image/png" media="(min-width: 1200px)" srcset="assets/img/whatsapp.png 1x, assets/img/whatsapp.png 2x"></source><img class="main-ai-snippet-block__preview-image block max-w-none" src="assets/img/whatsapp.png" width="1136" height="639" alt="AI assistant collecting an email address from a prospective student and sending a registration form for course enrollment"></picture>
</div>
  </div>"""

new_ai_desktop = """  <div class="js-ai-snippet-block-preview " data-name="reply-24-7">
    <div class="main-ai-snippet-block__preview-frame flex items-center justify-center overflow-hidden rounded-20">
      <img src="assets/img/whatsapp.png" class="main-ai-snippet-block__preview-image block max-w-full" alt="AI assistant 24/7 replies">
</div>
  </div>"""

new_content = new_content.replace(old_ai_desktop_1, new_ai_desktop)

with open(index_path, "w", encoding="utf-8") as f:
    f.write(new_content)

print("index.html rewritten successfully!")
