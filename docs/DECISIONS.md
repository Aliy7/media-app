# MediaFlow — Decision Log

**Project:** MediaFlow — Asynchronous Image Processing Platform
**Author:** A. Muktar
**Pairing:** A. Muktar (Architect/Reviewer) + Claude Code (Implementation Driver)
**Method:** Iterative Incremental + Kanban

This document is the authoritative record of every significant decision made
during the project — from the initial assignment brief through to implementation.
Each entry states what was decided, why, and what was rejected or considered.

---

## Part 1 — Project Selection (Brainstorming)

### B-001 · Assignment brief
**Context:** Technical assignment in Laravel PHP requiring demonstration of queued
background processing (async). The evaluator is a computer scientist, so
technical depth and correctness matter more than surface-level polish.

**Starting brief (verbatim):**
> "I am starting a technical assignment using Laravel PHP. In this assignment,
> I am required to use a coding agent and demonstrate queued background process
> such as async. I have already considered building a media processing web app,
> but I would like to explore a few other options."

**Constraint noted:** No code until options are evaluated and a decision is made.

---

### B-002 · Three options evaluated

Three project ideas were compared before any commitment was made:

#### Option 1: Media Processing App
Upload images → queue async jobs for resizing, thumbnail generation, format
conversion.

| Dimension | Assessment |
|---|---|
| Queue concepts | Chained jobs, failed job handling, retries |
| Complexity | Medium-High — binary dependencies (Imagick/FFmpeg) |
| Time estimate | 6–10 hours |
| Visual impact | High — rendered output is immediately observable |
| Risk | Environment issues with binary deps can consume hours |

#### Option 2: CSV / Data Import Pipeline
Upload a large CSV → queue batched row processing, validation, DB insertion,
progress tracking, error reporting.

| Dimension | Assessment |
|---|---|
| Queue concepts | Job chunking, batching, progress tracking, failure collection |
| Complexity | Low-Medium — pure PHP, no binary dependencies |
| Time estimate | 4–7 hours |
| Visual impact | Medium — rows in a table |
| Risk | Low — easy to scale artificially, easy to test |

#### Option 3: Report Generation & Delivery
User requests a report → queue PDF generation → email delivery when ready.

| Dimension | Assessment |
|---|---|
| Queue concepts | Queued mailables, delayed jobs, job middleware |
| Complexity | Low — mostly gluing built-in Laravel features |
| Time estimate | 3–5 hours |
| Visual impact | Low |
| Risk | Low, but technically thin for a CS evaluator |

**Initial agent recommendation:** CSV Import Pipeline — best balance of queue
concepts with least environment risk.

---

### B-003 · Final project selection: Media Processing App
**Decided by:** A. Muktar

**Why media processing was chosen over CSV import:**
- More visually compelling — progress and outputs are immediately observable
  in the browser, making the async behaviour tangible to a technical evaluator
- Covers a richer set of queue concepts: chained job orchestration, named
  queue priorities, real-time broadcasting, failed job recovery
- The binary dependency risk (Imagick) is mitigated by Docker — the environment
  is controlled and reproducible, so environment issues are solvable
- Naturally maps to WebSocket broadcasting in a way that CSV import does not —
  the real-time progress bar is a first-class demo feature

**Why report generation was rejected:**
Technically too thin. Queued mailables alone do not demonstrate job chaining,
priority queues, or real-time feedback.

---

## Part 2 — Architecture & Methodology (Phase 0)

### D-001 · Framework: Laravel 13.x
**Decided:** Laravel 13 (≥ 13.0), confirmed running at 13.1.1.
**Why:** First-class queue and broadcasting support. Horizon, Livewire, Breeze,
and Echo all integrate natively. The L11+ slim skeleton reduces boilerplate.
Resolved open question OQ-1 (Laravel 11 vs 12 — superseded by 13).

---

### D-002 · PHP version: 8.5.x
**Decided:** PHP 8.5.3 as shipped by Laravel Sail's runtime.
**Why:** Active branch. All required packages support ≥ PHP 8.2; running the
version Sail ships avoids version-mismatch risk.
Resolved open question OQ-2 (PHP 8.3 vs 8.4 — moot; Sail ships 8.5).

---

### D-003 · Image driver: Imagick, treated as a hard blocker
**Decided:** Imagick PHP extension (3.x via PECL) installed in the Docker image.
**Why:** Superior resampling quality over GD. GD produces noticeably lower
quality at resize and thumbnail stages.
**Policy:** If Imagick fails during the Docker build, it is a blocker — do not
silently fall back to GD. Fix the build.
Open question OQ-3 remains open until Phase 1 Docker build is verified.

---

### D-004 · Queue driver: Redis (not database)
**Decided:** `QUEUE_CONNECTION=redis`.
**Why:** Laravel Horizon requires Redis. The database queue driver is
incompatible with Horizon. Redis also provides sub-second job pickup latency
required by the NFR (< 1 second from dispatch to worker pickup).

---

### D-005 · WebSocket server: Soketi 1.6 (self-hosted Pusher-protocol)
**Decided:** Soketi 1.6 as the WebSocket server.
**Why:** Self-hosted — no external service dependency during development or demo.
Speaks the Pusher wire protocol, so Laravel Broadcasting and Laravel Echo work
without any code changes.
**Rejected:** Pusher hosted (external dependency), Reverb (newer, less
battle-tested at time of decision).

---

### D-006 · Frontend reactivity: Livewire 3
**Decided:** Livewire 3 with native Echo broadcasting integration.
**Why:** Keeps the stack PHP-first. No client-side framework to maintain.
Livewire's `#[On]` attribute handles real-time event binding from Laravel Echo
natively in Phase 4.
**Rejected:** Vue 3 / React (adds SPA complexity out of scope), Alpine.js alone
(insufficient for full component reactivity).

---

### D-007 · Authentication: Laravel Breeze (Blade stack)
**Decided:** Breeze 2.x with Blade stack.
**Why:** Consistent with the Livewire-first approach. Ships Tailwind CSS.
Minimal scaffold — no extra JS framework imposed.

---

### D-008 · Queue architecture: three named queues
**Decided:** `media-critical` (thumbnails, 2 workers), `media-standard`
(resize, 2 workers), `media-low` (optimise, 1 worker).
**Why:** Priority routing ensures thumbnail generation (fastest user feedback)
is never blocked behind slow optimise jobs. Mirrors real production queue design.
**Rejected:** Single default queue (no priority control), Laravel Batches
(adds batch table complexity; chaining is sufficient for a linear pipeline).
Resolved open question OQ-4.

---

### D-009 · Channel scoping: per-media UUID (not per-user)
**Decided:** Private channel name `private-media.{media.uuid}`.
**Why:** Scoping to the media item prevents one user's upload events from being
delivered to the same user's other open tabs watching a different upload. Clean
isolation per upload.
**Rejected:** Per-user channel (requires client-side filtering to distinguish
concurrent upload events).

---

### D-010 · No soft deletes on media records
**Decided:** Hard deletes only on the `media` table.
**Why:** Soft deletes add `withTrashed()` scope complexity throughout queries.
Not required by the assignment objectives.
Resolved open question OQ-7.

---

### D-011 · Upload file size limit: 10 MB
**Decided:** Maximum upload: 10 MB.
**Why:** Sufficient to make async behaviour observable during demo. Larger limits
increase timeout risk. A 10 MB image takes meaningfully long to process.
Resolved open question OQ-8.

---

### D-012 · Demo environment: assessor runs it themselves
**Decided:** The system must be reproducible by the assessor via
`docker compose up` + `README.md` — no recorded demo.
**Why:** A self-service demo is a stronger engineering signal than a recording.
To be finalised in Phase 6 (open question OQ-6 partially resolved).

---

### D-013 · Methodology: Iterative Incremental + Kanban, WIP limit = 1
**Decided:** One increment in progress at a time. Each phase has a human review
checkpoint before the next begins.
**Why:** Prevents integration surprises. Each phase builds on a verified
foundation. WIP = 1 keeps focus and makes progress observable to the evaluator.

---

### D-014 · Branching strategy
**Decided:** `main` is always stable. One `phase/N-description` branch per phase.
Branches deleted after merge. Final submission tagged `v1.0.0`.
**Why:** Clean, readable git history appropriate for assignment submission.

---

### D-015 · SOLID design principles applied deliberately
**Decided:** Each OOP layer has a single, explicit responsibility:
- Controllers: receive request, delegate, return response
- Services: own business logic
- Jobs: single processing step each
- Events: carry data only, no logic
- Listeners: handle broadcasting only, no domain logic

**Why:** The assignment explicitly evaluates OOP design. SOLID boundaries are
documented in PROJECT_SPEC.md § 5.3 and will be audited in Phase 5.

---

### D-016 · TDD practice
**Decided:** Write tests first (red → green → refactor) for all non-trivial
implementations. Laravel testing tools: PHPUnit, Queue::fake(), Event::fake(),
Bus::fake(), Storage::fake().
**Why:** Tests prove behaviour, not just that code runs. The assignment evaluates
correctness and testability, not just feature completeness.

---

## Part 3 — Infrastructure Decisions (Phase 1)

### D-017 · Custom Dockerfile (not Sail's runtime)
**Decided:** Project-owned `Dockerfile` based on `php:8.5-fpm-bookworm`.
**Why:** Sail's runtime lives in `vendor/` — not committed, not readable as
project documentation, and changes with Sail upgrades. A project-owned
Dockerfile is reproducible, readable, and appropriate for submission.
**Rejected:** Continuing to use `vendor/laravel/sail/runtimes/8.5`.

---

### D-018 · Debian base image (not Alpine) for the app container
**Decided:** `php:8.5-fpm-bookworm` (Debian Bookworm).
**Why:** Imagick's system dependencies (`libmagickwand-dev`, etc.) are
significantly more reliable on Debian. Alpine has a history of subtle
compatibility issues with PECL extensions that would violate the OQ-3 blocker
policy on Imagick.
**Rejected:** `php:8.5-fpm-alpine` — smaller image, but higher Imagick install
risk.

---

### D-019 · Horizon runs as a separate container
**Decided:** Dedicated `horizon` service in docker-compose reusing the
`mediaflow/app` image, with command override `php artisan horizon`.
**Why:** Separates HTTP (PHP-FPM) from queue processing cleanly. Each can be
restarted independently. Mirrors production practice.
**Rejected:** Running Horizon inside the app container via Supervisor (couples
HTTP and queue failure modes — a PHP-FPM crash would also kill queue workers).

---

### D-020 · Session and cache drivers: Redis
**Decided:** `SESSION_DRIVER=redis`, `CACHE_STORE=redis`.
**Why:** Consistent with the queue driver. All in-memory state uses the same
Redis service — simpler topology, better performance.
**Rejected:** `SESSION_DRIVER=database` (adds a DB query on every request for
session reads/writes).

---

### D-021 · PHP upload limit set 20% above spec maximum
**Decided:** `upload_max_filesize=12M`, `post_max_size=12M` in `php.ini`.
**Why:** The spec enforces a 10 MB limit in Laravel validation. Setting PHP's
limit to exactly 10 MB would cause PHP to silently reject the request before
Laravel's validator runs, making it impossible to return a proper 422 response.
The 20% headroom ensures Laravel always handles the error, not PHP.

---

### D-022 · Horizon admin gate: HORIZON_ADMIN_EMAIL env variable
**Decided:** A single env variable `HORIZON_ADMIN_EMAIL` controls access to
`/horizon`, checked in `HorizonServiceProvider`.
**Why:** No admin role in the schema (by design — out of scope for this
assignment). An email allowlist is the simplest mechanism that requires no
schema changes.
Resolved open question OQ-5.

---

### D-023 · Vite environment variables for browser-side Soketi config
**Decided:** Soketi connection values duplicated as `VITE_PUSHER_*` variables
in `.env.example`.
**Why:** Vite only exposes `VITE_`-prefixed variables to the browser bundle.
Laravel Echo needs Soketi connection details at runtime in the browser.

---

### D-025 · PHP version downgraded from 8.5 to 8.3 in Dockerfile
**Decided:** `php:8.3-fpm-bookworm` used as the Docker base image (was `php:8.5-fpm-bookworm`).
**Why:** The PHP 8.5 base image caused `docker-php-ext-install` to fail silently —
extensions compiled but produced no `.so` files (`cp: cannot stat 'modules/*'`).
The root cause is that the `docker-php-ext-install` tooling in the official PHP
Docker images had not been updated to support PHP 8.5's new extension API
timestamp (`20250925`) at the time of this build. PHP 8.3 is fully supported,
builds cleanly, and satisfies all package requirements (`laravel/framework ^13`,
`laravel/horizon ^5`, Imagick via PECL).
**Consequence:** The `composer.lock` file was regenerated from scratch because the
original lock file was produced on PHP 8.4+ and had `symfony/*` packages locked
to v8.x (requiring PHP ≥8.4). The new lock file resolves all dependencies to
symfony 7.x, which is compatible with PHP 8.3.
**Spec deviation:** PROJECT_SPEC.md § 4 specifies PHP 8.5.x. This deviation is
recorded here as a forced infrastructure constraint, not a design preference.
The spec version should be updated to reflect 8.3.x in Phase 5 hardening.

---

### D-024 · Nginx pinned to 1.27-alpine; Redis pinned to 7.2-alpine
**Decided:** `nginx:1.27-alpine`, `redis:7.2-alpine`.
**Why:** Pinned versions prevent unexpected behaviour from a `:latest` tag
changing between runs. Alpine for both keeps image size small — neither service
requires the full Debian package set.
