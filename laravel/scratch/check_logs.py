import os
import json

log_path = r"C:\Users\yaoce\.gemini\antigravity\brain\f21abc53-9e6e-4ac1-b40e-324fada5e531\.system_generated\logs\transcript.jsonl"
if not os.path.exists(log_path):
    print(f"Log path does not exist: {log_path}")
    # try guest parent dir
    parent = os.path.dirname(log_path)
    if os.path.exists(parent):
        print(f"Contents of parent: {os.listdir(parent)}")
    sys.exit(1)

edits = []
with open(log_path, 'r', encoding='utf-8') as f:
    for line in f:
        try:
            data = json.loads(line)
            if "guest.blade.php" in json.dumps(data):
                edits.append(data)
        except Exception as e:
            pass

print(f"Found {len(edits)} steps involving guest.blade.php")
# print the last 3 edits type and summary
for edit in edits[-3:]:
    print(f"Step {edit.get('step_index')}: Source={edit.get('source')}, Type={edit.get('type')}")
    # If it is a tool call to replace_file_content or write_to_file, print the description
    tc = edit.get('tool_calls', [])
    if tc:
        for c in tc:
            print(f"  Tool: {c.get('name')}, Args: {c.get('arguments')}")
