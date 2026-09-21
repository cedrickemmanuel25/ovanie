with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\public\home.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for idx in range(len(lines) - 50, len(lines)):
    print(f"{idx+1}: {lines[idx].rstrip()}")
