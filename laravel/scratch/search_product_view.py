import os

search_dir = r"c:\Users\yaoce\Downloads\ovanie\laravel\app"
results = []

for root, dirs, files in os.walk(search_dir):
    for file in files:
        if file.endswith('.php'):
            filepath = os.path.join(root, file)
            try:
                with open(filepath, 'r', encoding='utf-8') as f:
                    content = f.read()
                    if "view('product')" in content or 'view("product")' in content:
                        results.append(filepath)
            except Exception as e:
                pass

print("=== view('product') controllers ===")
for r in results:
    print(r)
