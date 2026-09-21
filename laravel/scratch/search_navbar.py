import os

keywords = ["centre d'assistance", "besoin d'aide", "navbar"]
search_dir = r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views"

results = []
for root, dirs, files in os.walk(search_dir):
    for file in files:
        if file.endswith('.blade.php'):
            filepath = os.path.join(root, file)
            try:
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                    for keyword in keywords:
                        if keyword.lower() in content.lower():
                            results.append((filepath, keyword))
            except Exception as e:
                pass

print("=== Search Results ===")
for path, kw in set(results):
    print(f"File: {path} contains keyword: '{kw}'")
