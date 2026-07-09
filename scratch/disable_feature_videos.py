index_path = "/Users/wbsoumo/Desktop/LinkPilot AI/index.html"
with open(index_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

new_lines = []
target_keywords = ["chat-management", "book-meetings", "boost-sales"]

for line in lines:
    # 1. Strip data-video-path attributes for the target keywords
    for kw in target_keywords:
        attr = f'data-video-path="/images/pages/main/features-block/en/{kw}.webm"'
        if attr in line:
            line = line.replace(attr, "")
    
    # 2. Hide the videos for target keywords by changing lg:block to hidden
    if "<video" in line and "feature-block-video" in line:
        if any(kw in line for kw in target_keywords):
            line = line.replace("lg:block", "hidden")
            
    new_lines.append(line)

with open(index_path, "w", encoding="utf-8") as f:
    f.writelines(new_lines)

print("index.html processed successfully!")
