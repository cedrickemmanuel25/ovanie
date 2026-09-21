with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\layouts\guest.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if "<header" in line or "class=\"ov-header\"" in line or "<body" in line:
        print(f"Line {i+1}: {line.strip()}")
        # print 50 lines after
        for j in range(i, min(len(lines), i + 60)):
            print(f"  {j+1}: {lines[j].rstrip()}")
        print("="*60)
        break
