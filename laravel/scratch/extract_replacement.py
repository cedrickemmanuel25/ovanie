import json

with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\scratch\step_2817_raw.json", "r", encoding="utf-8") as f:
    data = json.load(f)

# Find the tool call for replace_file_content
tc = data.get("tool_calls", [])
for call in tc:
    if call.get("name") == "replace_file_content":
        args = call.get("args", {})
        replacement = args.get("ReplacementContent", "")
        with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\scratch\step_2817_replacement.txt", "w", encoding="utf-8") as f_out:
            f_out.write(replacement)
        print("Successfully extracted ReplacementContent!")
        # Print first 500 characters
        print(replacement[:500])
        break
