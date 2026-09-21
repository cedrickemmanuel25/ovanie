import os

css_path = r"c:\Users\yaoce\Downloads\ovanie\laravel\resources\css\app.css"
if os.path.exists(css_path):
    with open(css_path, "r", encoding="utf-8") as f:
        content = f.read()
    if ".calculator" in content:
        print("Found .calculator in app.css")
    else:
        print(".calculator NOT found in app.css")
else:
    print("app.css does not exist")
