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
    if i in [1, 5]:
        # Pipeline Management
        h_class = "h-224 sm:h-320" if i == 1 else "h-320 lg:col-span-8 lg:h-400"
        replacement = f"""<div class="{h_class} rounded-20 overflow-hidden feature-block-image">
  <img src="assets/img/4th.png" class="w-full h-full object-cover object-left" alt="Sales Pipeline & Kanban Deals">
</div>"""
        new_content = new_content[:start] + replacement + new_content[end:]

with open(index_path, "w", encoding="utf-8") as f:
    f.write(new_content)

print("index.html updated successfully with pipeline image!")
