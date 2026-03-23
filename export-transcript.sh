#!/bin/bash
# Export Claude Code session transcripts to a single consolidated file
# Usage: ./export-transcript.sh [output_dir]

SESSIONS_DIR="$HOME/.claude/projects/-home-a-muktar-projects-media-app"
OUTPUT_DIR="${1:-$HOME/projects/media-app/transcripts}"
CONSOLIDATED_FILE="$OUTPUT_DIR/cfd06922-1608-4794-8c6b-78433380987f.md"

mkdir -p "$OUTPUT_DIR"

python3 - "$SESSIONS_DIR" "$CONSOLIDATED_FILE" <<'PYTHON'
import sys, json, os, glob

sessions_dir = sys.argv[1]
output_file = sys.argv[2]

all_sessions = []

for jsonl_file in sorted(glob.glob(os.path.join(sessions_dir, "*.jsonl"))):
    session_id = os.path.basename(jsonl_file).replace('.jsonl', '')
    messages = []

    with open(jsonl_file) as f:
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

    if messages:
        all_sessions.append((session_id, messages))

if not all_sessions:
    sys.exit(0)

with open(output_file, 'w') as f:
    f.write("# Claude Code Transcripts\n")
    f.write("**Project:** media-app\n\n")
    f.write("---\n\n")

    for session_id, messages in all_sessions:
        date = messages[0][0][:10] if messages else ''
        f.write(f"## Session: {session_id}\n")
        f.write(f"**Date:** {date}\n\n")

        for timestamp, role, text in messages:
            label = "You" if role == "user" else "Claude"
            f.write(f"### {label}\n")
            f.write(f"{text}\n\n")

        f.write("---\n\n")

print(f"Exported {len(all_sessions)} session(s) to: {output_file}")
PYTHON

echo "Done. Transcripts saved to: $CONSOLIDATED_FILE"
