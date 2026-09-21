with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\layouts\guest.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

for i in range(961, min(1130, len(lines))):
    try:
        print(f"{i+1}: {lines[i].rstrip()}")
    except UnicodeEncodeError:
        print(f"{i+1}: {lines[i].rstrip().encode('ascii', 'replace').decode('ascii')}")
