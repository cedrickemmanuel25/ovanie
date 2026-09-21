with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\app\Http\Controllers\ProductController.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

show_start = -1
for i, line in enumerate(lines):
    if "public function show" in line:
        show_start = i
        break

if show_start != -1:
    for j in range(show_start, show_start + 80):
        if j < len(lines):
            print(f"{j+1}: {lines[j].rstrip()}")
