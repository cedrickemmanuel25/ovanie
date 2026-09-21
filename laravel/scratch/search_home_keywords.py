with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\public\home.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "centre d'assistance" in line.lower() or "besoin d'aide" in line.lower():
        print(f"Line {i+1}: {line.strip()}")
        start = max(0, i - 15)
        end = min(len(lines), i + 15)
        for j in range(start, end):
            try:
                print(f"  {j+1}: {lines[j].rstrip()}")
            except UnicodeEncodeError:
                print(f"  {j+1}: {lines[j].rstrip().encode('ascii', 'replace').decode('ascii')}")
        print("-" * 50)
