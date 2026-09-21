with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\layouts\guest.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i in range(800, len(lines)):
    line = lines[i]
    if "<header" in line or "class=\"ov-header\"" in line or "ov-header" in line or "ov-main-nav" in line:
        print(f"Line {i+1}: {line.strip()}")
