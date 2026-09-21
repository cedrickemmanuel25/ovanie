with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\layouts\guest.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for idx in range(749, min(962, len(lines))):
    line = lines[idx].strip()
    if line:
        print(f"Line {idx+1}: {line}")
