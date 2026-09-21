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
                    if "vendez sur" in content.lower():
                        lines = content.splitlines()
                        for i, line in enumerate(lines):
                            if "vendez sur" in line.lower():
                                results.append((filepath, i+1, line.strip()))
            except Exception as e:
                pass

print("=== 'vendez sur' occurrences ===")
for path, line_no, content in results:
    try:
        print(f"File: {path} (Line {line_no}): {content}")
    except UnicodeEncodeError:
        print(f"File: {path} (Line {line_no}): {content.encode('ascii', 'replace').decode('ascii')}")
