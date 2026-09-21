with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\app\Http\Controllers\ProductController.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "function show" in line:
        print(f"Line {i+1}: {line.strip()}")
        for j in range(i, min(len(lines), i + 30)):
            print(f"  {j+1}: {lines[j].rstrip()}")
        break
