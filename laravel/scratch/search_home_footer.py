with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\public\home.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "footer" in line.lower() or "scroll-top" in line.lower():
        print(f"Line {i+1}: {line.strip()}")
