import os
import json

log_path = r"C:\Users\yaoce\.gemini\antigravity\brain\f21abc53-9e6e-4ac1-b40e-324fada5e531\.system_generated\logs\transcript.jsonl"
edits = []
with open(log_path, 'r', encoding='utf-8') as f:
    for line in f:
        try:
            data = json.loads(line)
            step_idx = data.get('step_index', 0)
            if step_idx < 2820:
                # check if guest.blade.php is in tool calls
                tc = data.get('tool_calls', [])
                for c in tc:
                    args_str = json.dumps(c.get('args', {}))
                    if "guest.blade.php" in args_str:
                        edits.append((step_idx, data))
                        break
        except Exception as e:
            pass

print(f"Found {len(edits)} steps involving guest.blade.php before compaction")
for step_idx, edit in edits[-5:]:
    print(f"--- Step {step_idx} ---")
    print(json.dumps(edit, indent=2)[:3000])
