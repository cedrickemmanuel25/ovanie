import os

search_dir = r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views"
results = []

for root, dirs, files in os.walk(search_dir):
    for file in files:
        if file.endswith('.blade.php'):
            filepath = os.path.join(root, file)
            try:
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                    # Look for class="...ov-header..." or class='...ov-header...'
                    if "ov-header" in content:
                        # Check where it appears
                        lines = content.splitlines()
                        for i, line in enumerate(lines):
                            if "ov-header" in line and not line.strip().startswith(".") and not "{" in line and not ":" in line:
                                results.append((filepath, i+1, line.strip()))
            except Exception as e:
                pass

print("=== ov-header occurrences in HTML ===")
for path, line_no, content in results:
    print(f"File: {path} (Line {line_no}): {content}")
