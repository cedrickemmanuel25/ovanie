with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\views\layouts\guest.blade.php", "r", encoding="utf-8") as f:
    lines = f.readlines()

output = []
for i in range(961, min(1130, len(lines))):
    output.append(f"{i+1}: {lines[i].rstrip()}")

with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\scratch\guest_body_inspect.txt", "w", encoding="utf-8") as f_out:
    f_out.write("\n".join(output))
