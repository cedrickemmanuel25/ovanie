import os

search_dir = r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views"
results = []

for root, dirs, files in os.walk(search_dir):
    for file in files:
        if file.endswith('.blade.php'):
            filepath = os.path.join(root, file)
            try:
                with open(filepath, 'r', encoding='utf-8') as f:
                    for line in f:
                        if "@extends" in line:
                            results.append((filepath, line.strip()))
                            break
            except Exception as e:
                pass

print("=== @extends occurrences ===")
for path, line in results:
    print(f"File: {path} -> {line}")
