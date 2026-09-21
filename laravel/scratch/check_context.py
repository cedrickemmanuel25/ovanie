with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\layouts\guest.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "besoin d'aide" in line.lower() or "centre d'assistance" in line.lower():
        print(f"Line {i+1}: {line.strip()}")
        # print context
        start = max(0, i - 15)
        end = min(len(lines), i + 15)
        for j in range(start, end):
            print(f"  {j+1}: {lines[j].rstrip()}")
        print("-" * 50)
