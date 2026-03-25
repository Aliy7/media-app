#!/bin/bash
# Export Claude Code session transcripts to a single consolidated file
# Usage: ./export-transcript.sh [output_dir]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_NAME="$(basename "$SCRIPT_DIR")"

slugify_path() {
  # Claude Code project dirs typically look like: -home-a-muktar-projects-media-app
  # This mirrors that shape by replacing path separators and '_' with '-'.
  local input="$1"
  printf '%s' "$input" | sed -e 's|/|-|g' -e 's|_|-|g'
}

SESSIONS_ROOT="${CLAUDE_SESSIONS_ROOT:-$HOME/.claude/projects}"
SESSIONS_DIR="${CLAUDE_SESSIONS_DIR:-}"

if [[ -z "${SESSIONS_DIR}" ]]; then
  candidate1="$SESSIONS_ROOT/$(slugify_path "$SCRIPT_DIR")"
  candidate2="$SESSIONS_ROOT/$(printf '%s' "$SCRIPT_DIR" | sed -e 's|/|-|g')"

  if [[ -d "$candidate1" ]]; then
    SESSIONS_DIR="$candidate1"
  elif [[ -d "$candidate2" ]]; then
    SESSIONS_DIR="$candidate2"
  else
    # Fallback: pick the most recently modified project dir that looks related.
    # This prevents "no transcript created" when Claude Code's project slug differs.
    best_dir=""
    best_mtime=0
    for d in "$SESSIONS_ROOT"/*; do
      [[ -d "$d" ]] || continue
      base="$(basename "$d")"
      [[ "$base" == *"$PROJECT_NAME"* ]] || continue

      mtime="$(stat -c %Y "$d" 2>/dev/null || stat -f %m "$d" 2>/dev/null || echo 0)"
      if [[ "$mtime" =~ ^[0-9]+$ ]] && (( mtime > best_mtime )); then
        best_mtime="$mtime"
        best_dir="$d"
      fi
    done
    SESSIONS_DIR="$best_dir"
  fi
fi

OUTPUT_DIR="${1:-$SCRIPT_DIR/transcripts}"
CONSOLIDATED_FILE="$OUTPUT_DIR/cfd06922-1608-4794-8c6b-78433380987f.md"
LOG_FILE="${CLAUDE_TRANSCRIPT_LOG:-$OUTPUT_DIR/claude-transcript-export.log}"

mkdir -p "$OUTPUT_DIR"
mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true

log() {
  printf '[%s] %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*" >>"$LOG_FILE" 2>/dev/null || true
}

if [[ -z "${SESSIONS_DIR}" || ! -d "${SESSIONS_DIR}" ]]; then
  log "No sessions dir found. Tried root='$SESSIONS_ROOT' script_dir='$SCRIPT_DIR'."
  exit 0
fi

# Claude Code may flush its session jsonl slightly after hooks fire (especially on submit).
# Retry a couple times before giving up.
attempt=1
max_attempts=3
while :; do
  if ls "$SESSIONS_DIR"/*.jsonl >/dev/null 2>&1; then
    break
  fi
  if [[ "$attempt" -ge "$max_attempts" ]]; then
    log "No *.jsonl in sessions dir: $SESSIONS_DIR"
    exit 0
  fi
  attempt=$((attempt + 1))
  sleep 0.2
done

python3 - "$SESSIONS_DIR" "$CONSOLIDATED_FILE" <<'PYTHON'
import sys, json, os, glob

sessions_dir = sys.argv[1]
output_file = sys.argv[2]

all_sessions = []

for jsonl_file in sorted(glob.glob(os.path.join(sessions_dir, "*.jsonl"))):
    session_id = os.path.basename(jsonl_file).replace('.jsonl', '')
    messages = []

    with open(jsonl_file, encoding="utf-8", errors="replace") as f:
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

log "Exported transcripts from '$SESSIONS_DIR' to '$CONSOLIDATED_FILE'"
