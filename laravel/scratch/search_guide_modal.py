with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\layouts\guest.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "guide" in line.lower() or "modal" in line.lower():
        print(f"Line {i+1}: {line.strip()}")
