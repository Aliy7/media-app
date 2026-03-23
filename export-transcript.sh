#!/bin/bash
# Export Claude Code session transcripts to clean text
# Usage: ./export-transcript.sh [output_dir]

SESSIONS_DIR="$HOME/.claude/projects/-home-a-muktar-projects-media-app"
OUTPUT_DIR="${1:-$HOME/projects/media-app/transcripts}"

mkdir -p "$OUTPUT_DIR"

for jsonl_file in "$SESSIONS_DIR"/*.jsonl; do
    session_id=$(basename "$jsonl_file" .jsonl)
    output_file="$OUTPUT_DIR/${session_id}.md"

    python3 - "$jsonl_file" "$output_file" <<'PYTHON'
import sys, json

input_file = sys.argv[1]
output_file = sys.argv[2]

messages = []
with open(input_file) as f:
    for line in f:
        line = line.strip()
        if not line:
            continue
        try:
            obj = json.loads(line)
        except:
            continue

        if obj.get('isMeta'):
            continue

        msg_type = obj.get('type')
        if msg_type not in ('user', 'assistant'):
            continue

        msg = obj.get('message', {})
        role = msg.get('role', msg_type)
        content = msg.get('content', '')
        timestamp = obj.get('timestamp', '')

        if isinstance(content, list):
            parts = []
            for c in content:
                if isinstance(c, dict):
                    if c.get('type') == 'text':
                        parts.append(c.get('text', ''))
                    elif c.get('type') == 'tool_use':
                        parts.append(f"[Tool: {c.get('name','')}]")
                    elif c.get('type') == 'tool_result':
                        pass  # skip tool results for clean output
            text = '\n'.join(p for p in parts if p.strip())
        else:
            text = str(content)

        # Skip system/command messages and terminal UI noise
        if text.strip().startswith('<') and '>' in text[:30]:
            continue
        # Skip terminal escape sequences / UI frames
        if '╭' in text or '╰' in text or '▛' in text or '▜' in text:
            continue
        if not text.strip():
            continue

        messages.append((timestamp, role, text))

if not messages:
    sys.exit(0)

with open(output_file, 'w') as f:
    session_id = input_file.split('/')[-1].replace('.jsonl', '')
    f.write(f"# Claude Code Session\n")
    f.write(f"**Session:** {session_id}\n")
    if messages:
        f.write(f"**Date:** {messages[0][0][:10]}\n\n")
    f.write("---\n\n")

    for timestamp, role, text in messages:
        label = "You" if role == "user" else "Claude"
        f.write(f"### {label}\n")
        f.write(f"{text}\n\n")

print(f"Exported: {output_file}")
PYTHON
done

echo "Done. Transcripts saved to: $OUTPUT_DIR"
