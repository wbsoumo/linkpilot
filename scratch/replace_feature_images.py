import re

index_path = "/Users/wbsoumo/Desktop/LinkPilot AI/index.html"
with open(index_path, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Replace 1st Image block: chat management / unified inbox -> assets/img/tophero.png
first_block_urls = [
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/chat-management-linkpilot-mobile.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/chat-crm-management-linkpilot-mobile.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/chat-management-tablet.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/chat-management-crm-tablet.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/chat-management.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/chat-management-crm.png"
]
for url in first_block_urls:
    content = content.replace(url, "assets/img/tophero.png")

# 2. Replace 2nd Image block: book meetings -> assets/img/2nd.png
second_block_urls = [
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/book-meetings-mobile.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/book-meetings-mobile-2x.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/book-meetings-tablet.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/book-meetings-tablet-2x.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/book-meetings.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/book-meetings-2x.png"
]
for url in second_block_urls:
    content = content.replace(url, "assets/img/2nd.png")

# 3. Replace 3rd Image block: boost sales -> assets/img/3rd.png
third_block_urls = [
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/boost-sales-mobile.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/boost-sales-mobile-2x.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/boost-sales-tablet.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/boost-sales-tablet-2x.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/boost-sales.png",
    "//pcfcdn.kommo.com/images/pages/main/features-block/en/boost-sales-2x.png"
]
for url in third_block_urls:
    content = content.replace(url, "assets/img/3rd.png")

# 4. Replace 4th Image block: AI reply 24/7 block -> assets/img/whatsapp.png
fourth_block_urls = [
    "//pcfcdn.kommo.com/images/pages/main/ai-block/en/ai-block-desktop-reply-24-7.png",
    "//pcfcdn.kommo.com/images/pages/main/ai-block/en/ai-block-desktop-reply-24-7-2x.png",
    "//pcfcdn.kommo.com/images/pages/main/ai-block/en/ai-block-mobile-reply-24-7.png",
    "//pcfcdn.kommo.com/images/pages/main/ai-block/en/ai-block-mobile-reply-24-7-2x.png",
    "//pcfcdn.kommo.com/images/pages/main/ai-block/en/ai-block-tablet-reply-24-7.png",
    "//pcfcdn.kommo.com/images/pages/main/ai-block/en/ai-block-tablet-reply-24-7-2x.png"
]
for url in fourth_block_urls:
    content = content.replace(url, "assets/img/whatsapp.png")

# Also remove lazy loading 'lazy' class from these images to ensure they show up instantly
# and make sure src is set properly (some images might have lazy loading setup that looks for src attribute)
# Let's inspect the file after writing this, or we can run the replacement.
with open(index_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Images replaced in index.html successfully!")
