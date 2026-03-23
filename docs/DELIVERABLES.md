# Deliverables
## MediaFlow — Assessment Criteria & Demo Requirements

**Version:** 1.0
**Date:** 2026-03-23

---

## 1. Primary Deliverable

A fully functional, containerised web application built with Laravel 13 that demonstrates:

1. Asynchronous image processing via Laravel queues
2. Real-time user feedback via WebSocket broadcasting
3. Production-realistic queue infrastructure with monitoring
4. Object-oriented design within an MVC framework

The application runs from a single command on any machine with Docker installed.

---

## 2. Deliverable Checklist

> **Traceability key:** Each item is linked to the phase that produces it and the demo step that shows it. Use this to verify nothing is missed before moving to the next phase.

### 2.1 Infrastructure
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| `docker-compose.yml` defining all services | [ ] | Phase 1 | Step 1 |
| `Dockerfile` for the PHP application container | [ ] | Phase 1 | Step 1 |
| `nginx.conf` web server configuration | [ ] | Phase 1 | Step 1 |
| `.env.example` with all variables documented | [ ] | Phase 1 | Step 1 |
| All services start and communicate with `docker compose up` | [ ] | Phase 1 | Step 1 |

### 2.2 Authentication
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| User registration with email/password | [ ] | Phase 1 | Step 2 |
| User login and logout | [ ] | Phase 1 | Step 2 |
| All media routes protected behind authentication | [ ] | Phase 2 | Step 2 |
| Unauthenticated access redirected appropriately | [ ] | Phase 2 | Step 2 |

### 2.3 Image Upload
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| Upload form accepting image files | [ ] | Phase 2 | Step 3 |
| Server-side validation: MIME type, file size, image dimensions | [ ] | Phase 2 | Step 6 |
| User-facing validation error messages | [ ] | Phase 2 | Step 6 |
| Uploaded file stored on disk with system-generated filename | [ ] | Phase 2 | Step 3 |
| `media` database record created with status `pending` | [ ] | Phase 2 | Step 3 |

### 2.4 Queue Processing
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| `ProcessImageJob` dispatched on upload | [ ] | Phase 3 | Step 4 |
| Job chain: `ResizeImageJob → GenerateThumbnailJob → OptimizeImageJob` | [ ] | Phase 3 | Step 4 |
| Multiple output dimensions generated per upload | [ ] | Phase 3 | Step 4 |
| `Media` record status transitions correctly through each step | [ ] | Phase 3 | Step 4 |
| Output files stored and paths recorded in database | [ ] | Phase 3 | Step 4 |

### 2.5 Queue Configuration
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| Redis as queue driver | [ ] | Phase 1 | Step 5 |
| Named queues with priorities: `media-critical`, `media-standard`, `media-low` | [ ] | Phase 3 | Step 5 |
| Jobs configured with retry attempts and exponential backoff | [ ] | Phase 3 | Step 6 |
| Job timeout configured | [ ] | Phase 3 | Step 5 |
| Laravel Horizon supervising workers | [ ] | Phase 1 | Step 5 |

### 2.6 Failed Job Handling
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| Invalid/corrupt file triggers job failure | [ ] | Phase 3 | Step 6 |
| `Media` record updated with `failed` status and error message | [ ] | Phase 3 | Step 6 |
| Failed job visible in Horizon dashboard | [ ] | Phase 3 | Step 6 |
| User notified of failure via real-time broadcast | [ ] | Phase 4 | Step 6 |

### 2.7 Real-Time Broadcasting
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| Soketi WebSocket server running in Docker | [ ] | Phase 1 | Step 4 |
| Private broadcast channels scoped to individual media items | [ ] | Phase 4 | Step 4 |
| Channel authentication verifies media ownership | [ ] | Phase 4 | Step 7 |
| Events broadcast: started, step completed, completed, failed | [ ] | Phase 4 | Step 4 |
| UI updates without page refresh | [ ] | Phase 4 | Step 4 |

### 2.8 Livewire Frontend
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| `MediaUploader` component: upload form with real-time progress | [ ] | Phase 4 | Step 3–4 |
| `MediaLibrary` component: list of uploads with statuses | [ ] | Phase 4 | Step 4 |
| Progress bar advancing through processing steps | [ ] | Phase 4 | Step 4 |
| Output thumbnails displayed on completion | [ ] | Phase 4 | Step 4 |
| Error state displayed on failure | [ ] | Phase 4 | Step 6 |

### 2.9 Horizon Dashboard
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| Accessible at `/horizon` (authenticated) | [ ] | Phase 1 | Step 5 |
| Real-time job throughput visible | [ ] | Phase 3 | Step 5 |
| Failed jobs visible with exception details | [ ] | Phase 3 | Step 6 |
| Queue metrics: jobs per minute, wait time, runtime | [ ] | Phase 3 | Step 5 |

### 2.10 OOP & MVC Architecture
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| Controllers contain no business logic | [ ] | Phase 2 | Step 7 |
| `MediaUploadService` encapsulates upload logic | [ ] | Phase 2 | Step 7 |
| `ImageProcessingService` encapsulates Intervention Image operations | [ ] | Phase 3 | Step 7 |
| Each job class has a single processing responsibility | [ ] | Phase 3 | Step 7 |
| Events carry data only, no logic | [ ] | Phase 4 | Step 7 |
| Listeners handle broadcasting only, no domain logic | [ ] | Phase 4 | Step 7 |

### 2.11 Testing
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| Unit tests: service classes, job classes, model relationships | [ ] | Phase 2–4 | — |
| Feature tests: all HTTP endpoints | [ ] | Phase 2 | — |
| Auth tests: protected routes reject unauthenticated requests | [ ] | Phase 2 | — |
| Queue tests: `Queue::fake()` asserts jobs dispatched correctly | [ ] | Phase 3 | — |
| Event tests: `Event::fake()` asserts events fired from correct job steps | [ ] | Phase 4 | — |
| All tests pass: `php artisan test` | [ ] | Phase 5 | — |

### 2.12 Documentation
| Deliverable | Done | Phase | Demo Step |
|---|---|---|---|
| `README.md`: setup, demo credentials, architecture overview | [ ] | Phase 6 | Step 1 |
| `docs/PROJECT_SPEC.md` | [ ] | Phase 0 | Step 7 |
| `docs/IMPLEMENTATION_PLAN.md` | [ ] | Phase 0 | Step 7 |
| `docs/DELIVERABLES.md` | [ ] | Phase 0 | Step 7 |
| Code comments on non-obvious logic only | [ ] | Phase 5 | Step 7 |
| `transcripts/` — session transcripts as evidence of coding agent usage | [ ] | All phases | Step 7 |
| `.github/workflows/tests.yml` — CI pipeline | [ ] | Phase 6 | Step 7 |

---

## 3. Time Estimates & Schedule

### 3.1 Principles
- **Target hours are tight but realistic** — calculated at focused, uninterrupted coding pace with AI pairing
- **You can finish faster, never slower** — if a phase exceeds its target, stop and identify the blocker immediately
- **Hours are net working hours** — exclude breaks, interruptions, context switching
- **AI pairing multiplier** — estimates already account for Claude Code accelerating implementation; human review time is included

### 3.2 Phase-by-Phase Estimates

| Phase | Focus | Tasks | Target Hours | Cumulative Hours | Calendar Days* |
|---|---|---|---|---|---|
| 0 | Inception | Spec, design, architecture | **Complete** | — | Done |
| 1 | Infrastructure & Skeleton | Docker, Laravel, Breeze, Horizon, DB | **5.0 hrs** | 5.0 hrs | Day 1 |
| 2 | Core Domain | Model, service, controller, upload UI | **6.0 hrs** | 11.0 hrs | Day 1–2 |
| 3 | Queue Jobs | Job chain, Horizon config, failure handling | **6.0 hrs** | 17.0 hrs | Day 2–3 |
| 4 | Broadcasting & Real-Time UI | Events, channels, Livewire integration | **5.5 hrs** | 22.5 hrs | Day 3–4 |
| 5 | Hardening | Edge cases, OOP audit, security, test coverage | **4.5 hrs** | 27.0 hrs | Day 4 |
| 6 | Demo Preparation | Seeders, README, demo rehearsal | **2.0 hrs** | **29.0 hrs** | Day 5 |

*Calendar days assume **6 productive hours/day**. Phase 1 completes Day 1. Phase 2 completes Day 2.

### 3.3 Task-Level Breakdown

| Phase | Task | Hours |
|---|---|---|
| **1** | Docker Compose + Dockerfile + nginx config | 2.5 |
| **1** | Laravel config: queue, broadcast, Horizon | 1.5 |
| **1** | Database setup + Breeze migrations | 0.5 |
| **1** | Verification + human checkpoint | 0.5 |
| | *Phase 1 subtotal* | **5.0** |
| **2** | `media` migration + schema | 0.5 |
| **2** | `Media` model + casts + relationships + unit tests | 1.0 |
| **2** | `MediaUploadService` + `InvalidMediaException` + unit tests | 1.5 |
| **2** | `MediaController` + feature tests | 1.0 |
| **2** | `MediaUploader` Livewire component + view | 1.5 |
| **2** | Routes + auth middleware + route tests | 0.5 |
| | *Phase 2 subtotal* | **6.0** |
| **3** | `ImageProcessingService` + unit tests | 2.0 |
| **3** | 4 Job classes + `failed()` methods + unit tests | 3.0 |
| **3** | Horizon queue config + verification | 0.5 |
| **3** | Dispatch integration + feature test | 0.5 |
| | *Phase 3 subtotal* | **6.0** |
| **4** | 4 Event classes (`ShouldBroadcast`) + unit tests | 1.5 |
| **4** | Channel auth (`routes/channels.php`) + feature test | 0.5 |
| **4** | Livewire `#[On]` integration + progress bar UI | 2.0 |
| **4** | Echo + pusher-js setup, npm build | 0.5 |
| **4** | Soketi end-to-end verification | 1.0 |
| | *Phase 4 subtotal* | **5.5** |
| **5** | Edge case implementation + tests | 1.5 |
| **5** | OOP audit + refactoring | 1.0 |
| **5** | Security review + MIME hardening | 0.5 |
| **5** | Test coverage completion | 1.5 |
| | *Phase 5 subtotal* | **4.5** |
| **6** | `UserSeeder` + `MediaSeeder` | 0.5 |
| **6** | Demo rehearsal + test images prepared | 0.5 |
| **6** | README + CLAUDE.md finalisation | 1.0 |
| **6** | GitHub Actions CI workflow | 0.5 |
| | *Phase 6 subtotal* | **2.5** |
| | **TOTAL** | **29.5 hrs** |

### 3.4 Risk Contingency

| Risk | Likelihood | Time Impact | Mitigation |
|---|---|---|---|
| Imagick extension fails to install in Docker | Medium | +2 hrs | See OQ-3 in spec — resolve immediately, do not defer |
| Soketi WebSocket connection from browser fails | Medium | +2 hrs | Test Soketi connection at end of Phase 1, not Phase 4 |
| Livewire `#[On]` broadcast listener not firing | Low | +1.5 hrs | Use Echo debug mode (`Echo.connector.pusher.connection.bind`) to trace |
| MySQL migration error | Low | +0.5 hrs | `migrate:fresh` and re-inspect column types |

**Contingency budget:** 4 hrs (adds at most 1 calendar day if all risks materialise simultaneously)
**Worst-case total:** 33 hrs / 6 days

---

## 4. Acceptance Criteria

These are the criteria an assessor would evaluate against. Each maps to demonstrable evidence.

| Criterion | Evidence | Verified By |
|---|---|---|
| Queued background processing implemented | Redis queue, job chain visible in Horizon | Demo Step 4–5 |
| Jobs run asynchronously (not blocking HTTP) | HTTP responds < 500ms; processing continues after response | Demo Step 3 |
| Real-time feedback without page refresh | Progress bar advances via WebSocket, zero page reloads | Demo Step 4 + devtools |
| Failed jobs handled gracefully | `failed()` method, Horizon failed queue, UI error display | Demo Step 6 |
| OOP design with MVC separation | Thin controllers, service layer, single-responsibility jobs | Demo Step 7 + code review |
| Laravel framework used effectively | Jobs, Events, Listeners, Broadcasting, Eloquent, Middleware | Code review |
| Coding agent used in development | Session transcripts in `transcripts/` | `transcripts/` directory |
| Tests written and passing | `php artisan test` — all green | CI badge + test run |
| Reproducible environment | Cold start from `git clone` in < 2 minutes | Demo Step 1 |

---

## 5. Demo Script

The following sequence demonstrates the full system in a live demo:

### Step 1: Cold Start (Infrastructure)
```bash
git clone <repository>
cd media-app
cp .env.example .env
docker compose up -d
docker compose exec app php artisan migrate --seed
```
**Shows:** Docker infrastructure, service orchestration, environment setup.

### Step 2: Authentication
- Navigate to `http://localhost`
- Register a new user OR login with seeded demo credentials
**Shows:** Breeze auth, session management, route protection.

### Step 3: Image Upload
- Navigate to `/media/upload`
- Upload `demo-large.jpg` (~8MB) — large enough that processing takes several visible seconds
- Observe: form validation, immediate HTTP response (< 500ms), UI transitions to "processing" state without redirect
**Shows:** Thin controller, service layer, job dispatch, non-blocking HTTP response.

### Step 4: Real-Time Processing (key demo moment)
- **Open browser devtools → Network → WS tab** — show live WebSocket connection to `ws://localhost:6001`
- Show WebSocket frames arriving as each job step completes (visible in devtools frame list)
- Observe progress bar advancing in real-time without page refresh
- Each step visible in UI: "Resizing...", "Generating thumbnail...", "Optimising..."
- On completion: output images appear in the UI
**Shows:** Queue jobs, job chain, events, broadcasting, Livewire reactivity, observable WebSocket communication.

### Step 5: Horizon Dashboard
- Navigate to `/horizon`
- Show: job throughput, queue metrics, completed jobs, worker status
**Shows:** Production queue monitoring, queue priorities, job configuration.

### Step 6: Failure Handling
- Upload `demo-corrupt.jpg` (a renamed `.txt` file)
- Observe: job fails after retry attempts, Horizon shows failed job with exception trace, UI displays error message in real-time
**Shows:** Failed job handling, retry logic, user notification on failure, Horizon failure visibility.

### Step 7: Code Walkthrough (if required)
Walk through the OOP architecture:
- `MediaController` → thin, no business logic
- `MediaUploadService` → business logic encapsulated
- `ProcessImageJob` → orchestrator, builds chain
- `ResizeImageJob` → single responsibility
- `MediaStepCompleted` event → data only
- `BroadcastMediaEvent` listener → broadcasting only
- Channel auth in `routes/channels.php`
- Livewire component with `#[On]` for broadcast subscription

---

## 6. Assessment Criteria Mapping

| Assignment Requirement | How Demonstrated |
|---|---|
| Queue / background processing | Redis queue, job chain, Horizon monitoring |
| Asynchronous operations | HTTP returns immediately, processing happens in separate worker process |
| Real-time feedback | WebSocket broadcasting via Soketi + Livewire |
| Laravel framework usage | Jobs, Events, Listeners, Broadcasting, Eloquent, Middleware, Service Container |
| OOP design | Service layer, single-responsibility jobs, dependency injection |
| MVC pattern | Thin controllers, Eloquent models, Blade/Livewire views |
| Error handling | Failed jobs, retry logic, user notification, Horizon visibility |
| Testing | PHPUnit unit + feature tests with Laravel test helpers |
| Coding agent usage | Claude Code used for implementation, architecture review, test writing |

---

## 7. Technical Demonstration Points

These are the moments in the demo that specifically demonstrate understanding beyond surface-level implementation:

| Point | What it Proves |
|---|---|
| Job dispatched inside service, not controller | Understanding of separation of concerns |
| Events fired from within queue job (not HTTP request) | Understanding that jobs run outside the HTTP lifecycle |
| Private channels with ownership auth | Understanding that broadcasting requires security |
| Exponential backoff on retry | Understanding of production failure recovery patterns |
| Multiple named queues with different priorities | Understanding of queue management and resource allocation |
| Horizon config in code, not .env | Understanding that worker configuration is deployable infrastructure |
| `Storage` facade used throughout (not direct paths) | Understanding of filesystem abstraction |
| `Queue::fake()` in tests, not real queue | Understanding of test isolation |

---

## 8. Definition of Done

The project is complete when:

1. `docker compose up` starts all services with zero manual intervention
2. Human Review Checkpoint for every phase (0–6) completed and all checks passed — not merely ticked
3. Full demo script (Section 5) executes without errors on a clean environment
4. `php artisan test` reports all tests passing; CI workflow green on GitHub
5. No hardcoded credentials, file paths, or environment-specific values in committed code
6. `README.md` allows an unfamiliar person to run the project independently without assistance
