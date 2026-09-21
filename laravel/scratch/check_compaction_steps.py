import os
import json

log_path = r"C:\Users\yaoce\.gemini\antigravity\brain\f21abc53-9e6e-4ac1-b40e-324fada5e531\.system_generated\logs\transcript.jsonl"
edits = []
with open(log_path, 'r', encoding='utf-8') as f:
    for line in f:
        try:
            data = json.loads(line)
            step_idx = data.get('step_index', 0)
            if 2816 <= step_idx <= 2823:
                edits.append((step_idx, data))
        except Exception as e:
            pass

print(f"Found {len(edits)} steps between 2816 and 2823")
for step_idx, edit in edits:
    print(f"--- Step {step_idx} ---")
    print(json.dumps(edit, indent=2)[:3000])
