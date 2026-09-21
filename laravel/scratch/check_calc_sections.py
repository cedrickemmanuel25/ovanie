with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\calculator\index.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "@section" in line or "<link" in line or "<script" in line:
        print(f"Line {i+1}: {line.strip()}")
