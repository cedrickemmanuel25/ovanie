import os
import json

log_path = r"C:\Users\yaoce\.gemini\antigravity\brain\f21abc53-9e6e-4ac1-b40e-324fada5e531\.system_generated\logs\transcript.jsonl"
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
for i, edit in enumerate(edits[-3:]):
    print(f"--- Edit {i+1} (Step {edit.get('step_index')}) ---")
    print(json.dumps(edit, indent=2)[:2000])
