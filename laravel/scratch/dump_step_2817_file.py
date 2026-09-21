import os
import json

log_path = r"C:\Users\yaoce\.gemini\antigravity\brain\f21abc53-9e6e-4ac1-b40e-324fada5e531\.system_generated\logs\transcript_full.jsonl"
if not os.path.exists(log_path):
    log_path = r"C:\Users\yaoce\.gemini\antigravity\brain\f21abc53-9e6e-4ac1-b40e-324fada5e531\.system_generated\logs\transcript.jsonl"

found = False
with open(log_path, 'r', encoding='utf-8') as f:
    for line in f:
        try:
            data = json.loads(line)
            if data.get('step_index') == 2817:
                with open(r"c:\Users\yaoce\Downloads\ovanie\laravel\scratch\step_2817_raw.json", "w", encoding="utf-8") as f_out:
                    json.dump(data, f_out, indent=2, ensure_ascii=False)
                found = True
                print("Saved Step 2817 details to scratch/step_2817_raw.json")
                break
        except Exception as e:
            pass

if not found:
    print("Step 2817 not found!")
