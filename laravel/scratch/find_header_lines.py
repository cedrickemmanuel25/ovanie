with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\layouts\guest.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "<header" in line or "class=\"ov-header\"" in line or "ov-main-nav" in line or "ov-header-top" in line:
        print(f"Line {i+1}: {line.strip()}")
