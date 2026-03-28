# OWNER_CONTROLLERBOOK.md
## Media-App — Human Controller Navigation Guide

**Project:** Media-App — Asynchronous Image Processing Platform
**Owner/Controller:** A. Muktar
**Agent:** Claude Code
**Methodology:** Iterative Incremental + Kanban
**Version:** 1.0
**Date:** 2026-03-23

---

> **How to use this book:**
> Open this file at the start of every session. Follow the section for your current phase.
> Never let the agent move to the next phase until YOU have signed off.
> Your signature at the end of each phase gate is the only thing that advances the project.

---

## CURRENT STATUS

```
Phase:        [ 0 — COMPLETE ]  →  [ 1 — COMPLETE ]  →  [ 2 — COMPLETE ]  →  [ 3 — IN REVIEW ]  →  [ 4 ]  →  [ 5 ]  →  [ 6 ]
Last sign-off: Phase 2 — 2026-03-24 (verbal, exit criteria met)
Next gate:     Phase 3 exit review — awaiting owner sign-off
```

> **Update this block at the start of every session.**

---

## MASTER CONTROL RULES

These rules apply for the entire project. Never waive them.

1. **You advance the phase — not the agent.** The agent cannot declare a phase done. Only you can.
2. **Always run tests before sign-off.** Ask the agent to show test output. Never accept "tests pass" in prose only.
3. **One phase at a time.** The agent must not start Phase N+1 tasks while Phase N is open.
4. **Decisions must be logged.** Any change to spec, architecture, or approach must go into `DECISIONS.md` before proceeding.
5. **Rejections are final in session.** If you reject phase exit, the agent fixes issues before you reopen the gate.
6. **You own the Kanban board.** The agent updates cards; you verify the board matches reality.
7. **Never accept "it works on my machine."** Acceptance means it works inside Docker as specified.

---

## SESSION OPENING CHECKLIST

Run this at the start of every working session, every time.

- [ ] What phase are we currently in? *(update the STATUS block above)*
- [ ] What was the last thing completed in the previous session?
- [ ] Are there any open blockers or unresolved decisions?
- [ ] Is the Kanban board up to date?

**Prompt to open a session:**
```
We are resuming work on Media-App. We are currently in Phase [N].
Last session we completed [X]. Today I want to work on [Y].
Before we start, update the Kanban board to reflect current status and tell me
what the next task to pull into In Progress should be.
```

---

## PHASE 0 — PROJECT SETUP & DECISIONS
### Status: ✅ COMPLETE — Signed off 2026-03-23

**What was delivered:**
- [x] Tech stack agreed
- [x] Architecture agreed
- [x] `docs/PROJECT_SPEC.md` written
- [x] `docs/IMPLEMENTATION_PLAN.md` written
- [x] `docs/DELIVERABLES.md` written

**Owner notes:**
- Improvements to be applied: human review gates, `DECISIONS.md`, `OWNER_CONTROLLERBOOK.md` (this file)

---

## PHASE 1 — INFRASTRUCTURE & SKELETON
### Status: ✅ COMPLETE — Signed off 2026-03-24

### Before You Start — Ask the Agent

Before pulling the first Phase 1 card into In Progress, ask:

```
Before we start Phase 1, confirm:
1. What exact Docker services will be in compose.yaml and why?
2. What PHP and Laravel versions will be installed?
3. What is the first task on the Kanban board for Phase 1?
4. What tests will prove Phase 1 infrastructure is working?
```

**What to watch for while agent works:**
- Docker Compose includes: `app`, `nginx`, `mysql`, `redis`, `queue worker` — challenge if any are missing
- Laravel version must match PROJECT_SPEC.md
- `.env.example` must be committed, never `.env`
- No application logic should appear — Phase 1 is skeleton only

### What You Must Verify Before Sign-Off

Run these checks yourself or ask the agent to run them and show output:

```bash
# 1. Containers start cleanly
docker compose up -d
docker compose ps  # All services should be "running"

# 2. Laravel responds
curl http://localhost/  # Should return 200

# 3. Database connects
docker compose exec app php artisan migrate:status

# 4. Redis connects
docker compose exec app php artisan tinker --execute="Redis::ping();"

# 5. Queue worker is running
docker compose exec app php artisan queue:work --once
```

**Ask the agent:**
```
Show me the output of all Phase 1 verification commands.
Do not summarise — paste the actual terminal output.
```

### Phase 1 Exit Gate

- [x] `docker compose up -d` starts all services with no errors — **confirmed 2026-03-24**
- [x] Laravel welcome page loads at `http://localhost` — **confirmed 2026-03-24**
- [x] `.env.example` exists and `.env` is in `.gitignore` — **confirmed 2026-03-24**
- [x] Database migrations run cleanly — **confirmed 2026-03-24** (3 default migrations: users, cache, jobs)
- [x] Redis connection confirmed — **confirmed 2026-03-24**
- [x] Queue worker starts without error — **confirmed 2026-03-24** (Horizon container running)
- [x] Horizon dashboard accessible at `/horizon` — **confirmed 2026-03-24**
- [x] User registration, login, logout work in browser — **confirmed 2026-03-24**
- [x] Soketi reachable at `http://localhost:6001` — **confirmed 2026-03-24**
- [x] All Breeze scaffold tests pass: 25/25 — **confirmed 2026-03-24**
- [x] No Phase 2 code has been written — **confirmed 2026-03-24**

**Incident recorded:** All Breeze scaffold tests initially failed (14/25) due to
Laravel 13 renaming the CSRF middleware to `PreventRequestForgery`. Fixed via
`tests/TestCase.php`. Full incident recorded in `DECISIONS.md` entry D-026.

**Owner sign-off:** `[x] Signed off — Date: 2026-03-24`

---

## PHASE 2 — DATABASE & MODELS

### Before You Start

```
Before Phase 2, confirm:
1. Show me the full database schema from PROJECT_SPEC.md.
2. List every migration file you plan to create.
3. List every Eloquent model and its relationships.
4. What factories and seeders will be created?
```

**What to watch for:**
- Schema must exactly match PROJECT_SPEC.md — no silent additions or removals
- Every model must have a factory
- Foreign keys must be enforced at the database level
- No queue or processing logic in this phase

### What You Must Verify

```bash
# Migrations run cleanly on fresh database
docker compose exec app php artisan migrate:fresh

# All models load without error
docker compose exec app php artisan tinker --execute="
  echo \App\Models\User::count();
  echo \App\Models\Media::count();
"

# Factories produce valid records
docker compose exec app php artisan tinker --execute="
  \App\Models\Media::factory(3)->create();
  echo \App\Models\Media::count();
"

# Run model tests
docker compose exec app php artisan test --filter=Model
```

### Phase 2 Exit Gate

- [ ] All migrations run on a fresh database with no errors
- [ ] Schema matches PROJECT_SPEC.md exactly — verify column names and types
- [ ] All models have factories
- [ ] Relationships tested (e.g. `media->user` returns a User)
- [ ] No queue jobs or processing logic present
- [ ] All Phase 2 Kanban cards in Done

**Owner sign-off:** `[ ] Signed off — Date: ____________`

---

## PHASE 3 — UPLOAD & QUEUE DISPATCH

### Before You Start

```
Before Phase 3, confirm:
1. What validation rules apply to image uploads? Match against PROJECT_SPEC.md.
2. What queue name and priority will dispatch use?
3. What event is fired on successful dispatch?
4. How will we fake the queue in tests?
```

**What to watch for:**
- Upload endpoint must validate file type and size per spec
- Job must be dispatched to the correct named queue
- `Queue::fake()` must be used in tests — no real queue processing in unit tests
- No image processing logic yet — Phase 3 is dispatch only

### What You Must Verify

```bash
# Upload endpoint test
docker compose exec app php artisan test --filter=Upload

# Queue dispatch test (must use Queue::fake())
docker compose exec app php artisan test --filter=Dispatch

# Manual test via curl (requires an authenticated session cookie)
curl -X POST http://localhost/media \
  -F "file=@test.jpg"
```

**Ask the agent to show:**
- The full test output with Queue::fake() assertions visible
- The job class and which queue it dispatches to

### Phase 3 Exit Gate

- [x] Upload endpoint validates correctly (rejects wrong type/size) — **confirmed 2026-03-26**
- [x] Successful upload dispatches `ProcessImageJob` to `media-standard` queue — **confirmed 2026-03-26**
- [x] Batch jobs on correct queues: Resize→`media-standard`, Thumbnail→`media-critical`, Optimize→`media-low` — **confirmed 2026-03-26**
- [x] Tests use `Queue::fake()` — `MediaUploadTest` setUp + `MediaUploadServiceTest` setUp — **confirmed 2026-03-26**
- [x] Events fired and asserted with `Event::fake()` on all four job classes — **confirmed 2026-03-26**
- [x] `failed()` implemented on all jobs: status→`failed`, error message stored — **confirmed 2026-03-26**
- [x] Status transitions: `pending → processing → completed` verified end-to-end — **confirmed 2026-03-26**
- [x] Output files on disk, paths recorded in `media.outputs` — **confirmed 2026-03-26**
- [x] Horizon dashboard: clean metrics, correct display names with filename and upload timestamp — **confirmed 2026-03-26**
- [x] All 107 tests pass — **confirmed 2026-03-26**

**Note — doc correction:** The original gate read "No processing logic in this phase." This was a copy error from Phase 2 notes. Phase 3 is the processing phase — `ImageProcessingService` and the full job chain are Phase 3 deliverables per `IMPLEMENTATION_PLAN.md §3`.

**Known decisions logged:**
- D-5: `public readonly Media $media` removed (PHP 8.3 + `SerializesModels` incompatibility) — see `DECISIONS.md`
- D-6: `Bus::batch()` jobs require explicit `->onQueue()` at call site — constructor `onQueue()` ignored by batch dispatcher

**Owner sign-off:** `[ ] Signed off — Date: ____________`

---

## PHASE 4 — IMAGE PROCESSING WORKER

### Before You Start

```
Before Phase 4, confirm:
1. What output dimensions are specified in PROJECT_SPEC.md?
2. What happens if processing fails — retry count and backoff?
3. What event is broadcast on completion?
4. Which channel will be used for broadcasting?
```

**What to watch for:**
- Output dimensions must exactly match PROJECT_SPEC.md
- Failed jobs must go to the failed_jobs table, not silently disappear
- Retry logic must match spec (count, delay)
- No broadcasting yet if it is in a separate phase — check IMPLEMENTATION_PLAN.md

### What You Must Verify

```bash
# Process a real image end to end
docker compose exec app php artisan queue:work --once

# Verify output files exist with correct dimensions
docker compose exec app php artisan tinker --execute="
  \$image = \App\Models\Image::latest()->first();
  echo \$image->status;
  // Inspect output path
"

# Test failed job handling
docker compose exec app php artisan test --filter=ProcessingWorker

# Check failed jobs table is empty after successful run
docker compose exec app php artisan queue:failed
```

### Phase 4 Exit Gate

- [ ] Images process to correct output dimensions per spec
- [ ] Status updates in database (pending → processing → complete)
- [ ] Failed jobs land in `failed_jobs` table
- [ ] Retry count and delay matches spec
- [ ] Worker tests pass with real processing (not faked)
- [ ] Output files stored in correct location

**Owner sign-off:** `[ ] Signed off — Date: ____________`

---

## PHASE 5 — BROADCASTING & REAL-TIME EVENTS

### Before You Start

```
Before Phase 5, confirm:
1. What private channel name pattern is used?
2. How is channel authorisation implemented?
3. What event payload is broadcast on completion?
4. How will we test broadcasting without a real WebSocket connection?
```

**What to watch for:**
- Channels must be private (not public)
- Auth middleware must prevent cross-user channel access
- `Event::fake()` used in tests
- Payload must match PROJECT_SPEC.md broadcasting design

### What You Must Verify

```bash
# Broadcasting tests
docker compose exec app php artisan test --filter=Broadcast

# Auth endpoint responds correctly
curl -X POST http://localhost/broadcasting/auth \
  -H "Authorization: Bearer {token}" \
  -d "channel_name=private-user.1"

# Unauthorised access rejected
curl -X POST http://localhost/broadcasting/auth \
  -H "Authorization: Bearer {other_user_token}" \
  -d "channel_name=private-user.1"
# Must return 403
```

### Phase 5 Exit Gate

- [ ] Events broadcast to correct private channel
- [ ] Channel auth endpoint returns 200 for owner, 403 for others
- [ ] Event payload matches spec
- [ ] Tests use `Event::fake()` with payload assertions
- [ ] No public channels used anywhere

**Owner sign-off:** `[ ] Signed off — Date: ____________`

---

## PHASE 6 — FINAL INTEGRATION & DEMO PREPARATION

### Before You Start

```
Before Phase 6, confirm:
1. Walk me through the full end-to-end flow we will demo.
2. Which DELIVERABLES.md items are not yet checked off?
3. Are there any known bugs or incomplete items from earlier phases?
4. Is the demo script in DELIVERABLES.md still accurate?
```

**What to watch for:**
- This phase is integration and polish only — no new features
- Demo script in DELIVERABLES.md must work exactly as written
- All prior phase sign-offs must be complete before this phase starts

### What You Must Verify — Full Demo Run

Run the complete demo script from `DELIVERABLES.md` yourself, top to bottom.

**Ask the agent:**
```
Run the full demo script from DELIVERABLES.md and show me every command
and its output. Do not skip any step.
```

### Phase 6 Exit Gate — Final Checklist

- [ ] Full demo script runs without error
- [ ] All DELIVERABLES.md items checked off
- [ ] All tests pass: `php artisan test`
- [ ] No hardcoded credentials or debug code in codebase
- [ ] README.md is accurate and setup instructions work on a fresh clone
- [ ] All phase sign-offs above are complete

**Final project sign-off:** `[ ] Project complete — Date: ____________`

---

## DECISIONS LOG

> Record every architectural decision made during the project.
> The agent must not make decisions silently — all decisions come here.

| # | Date | Decision | Reason | Rejected Alternative |
|---|------|----------|--------|----------------------|
| 1 | 2026-03-23 | Iterative Incremental + Kanban methodology | Suits assignment scope and pairing model | Waterfall, Scrum |
| 2 | 2026-03-23 | Tech stack per PROJECT_SPEC.md | Agreed in Phase 0 | — |
| 3 | 2026-03-24 | PHP 8.3 used instead of 8.5 (D-025) | PHP 8.5 Docker image broke ext-install tooling | Stay on 8.3 |
| 4 | 2026-03-24 | CSRF fix in TestCase.php not bootstrap/app.php (D-026) | Laravel 13 renamed middleware; bootstrap approach unreliable at boot time | bootstrap/app.php withMiddleware callback |
| 5 | 2026-03-26 | `readonly` removed from `ProcessImageJob::$media` (D-005) | PHP 8.3 + SerializesModels: __wakeup cannot re-assign readonly property after unserialize() init | Implement `__unserialize()` on job |
| 6 | 2026-03-26 | `Bus::batch()` jobs use explicit `->onQueue()` at call site (D-006) | Batch dispatcher ignores queue set in constructor; jobs landed on `default` | Accept default queue behaviour |

---

## HOW TO CHALLENGE THE AGENT

Use these prompts when something feels wrong:

**When the agent moves too fast:**
```
Stop. We have not completed Phase [N] exit gate.
Show me the checklist from OWNER_CONTROLLERBOOK.md for Phase [N]
and confirm which items are verified and which are not.
```

**When the agent adds something not in the spec:**
```
I did not see [X] in PROJECT_SPEC.md. Where is this specified?
If it is not in the spec, revert it and log a decision in DECISIONS.md
if you believe it should be added.
```

**When tests are missing:**
```
You have not shown me test output for [feature].
Write the test first, show me it fails, then implement, then show me it passes.
```

**When something is broken:**
```
This is not working as specified. Before continuing, rollback to the last
working state and tell me what went wrong and what the fix plan is.
```

**When you want a status report:**
```
Give me a project status report:
1. Current phase
2. Kanban board — all cards and their status
3. What is done, what is in progress, what is blocked
4. Any open decisions or risks
```

---

## QUICK REFERENCE — USEFUL PROMPTS BY SITUATION

| Situation | Prompt to Use |
|---|---|
| Start of session | See SESSION OPENING CHECKLIST |
| Phase exit review | See Phase [N] Exit Gate section |
| Agent going off-spec | "Where is this specified in PROJECT_SPEC.md?" |
| Need test evidence | "Show me the actual test output, not a summary" |
| Unclear next step | "What is the next Kanban card to pull into In Progress?" |
| Something broke | "Rollback and explain what happened before continuing" |
| Agent too verbose | "Give me a status in 5 bullet points maximum" |
| End of session | "Summarise what was completed today and update the Kanban board" |

---

*This document is for the human controller only. The agent follows IMPLEMENTATION_PLAN.md.*
*Last updated: 2026-03-24*
