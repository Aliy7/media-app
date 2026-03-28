# Media-App — Decision Log

**Project:** Media-App — Asynchronous Image Processing Platform
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

### D-002 · PHP version: 8.3.x
**Decided:** PHP 8.3.x (Docker base image `php:8.3-fpm-bookworm`), confirmed running in the app container.
**Why:** Broad ecosystem support and reliable Docker builds while satisfying all package requirements (Laravel 13, Horizon 5, Intervention Image 3, Imagick via PECL).
Resolved open question OQ-2 (PHP 8.3 vs 8.4 — choosing 8.3 for stability and compatibility).

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
**Decided:** Project-owned `Dockerfile` based on `php:8.3-fpm-bookworm`.
**Why:** Sail's runtime lives in `vendor/` — not committed, not readable as
project documentation, and changes with Sail upgrades. A project-owned
Dockerfile is reproducible, readable, and appropriate for submission.
**Rejected:** Continuing to use `vendor/laravel/sail/runtimes/8.5`.

---

### D-018 · Debian base image (not Alpine) for the app container
**Decided:** `php:8.3-fpm-bookworm` (Debian Bookworm).
**Why:** Imagick's system dependencies (`libmagickwand-dev`, etc.) are
significantly more reliable on Debian. Alpine has a history of subtle
compatibility issues with PECL extensions that would violate the OQ-3 blocker
policy on Imagick.
**Rejected:** `php:8.3-fpm-alpine` — smaller image, but higher Imagick install
risk.

---

### D-019 · Horizon runs as a separate container
**Decided:** Dedicated `horizon` service in Docker Compose reusing the
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
**Spec update:** PROJECT_SPEC.md was updated to reflect PHP 8.3.x as the pinned baseline to match the Docker build and keep the documentation consistent with the running system.

---

### D-024 · Nginx pinned to 1.27-alpine; Redis pinned to 7.2-alpine
**Decided:** `nginx:1.27-alpine`, `redis:7.2-alpine`.
**Why:** Pinned versions prevent unexpected behaviour from a `:latest` tag
changing between runs. Alpine for both keeps image size small — neither service
requires the full Debian package set.

---

## Part 4 — Phase 1 Incidents & Learnings

### D-026 · Laravel 13 renamed the CSRF middleware — all Breeze scaffold tests failed silently
**Date:** 2026-03-24
**Phase:** 1 (discovered during automated test run)

**What happened:**
All 14 Breeze scaffold tests that involved POST/PATCH/PUT/DELETE requests
failed. Tests produced two distinct failure signatures:

1. `Expected response status code [301, 302...] but received 419` — on
   profile updates, password changes, account deletion (any authenticated
   write request via `actingAs()`).
2. `The user is not authenticated` — on login and registration tests. These
   also received 419 internally, but the tests asserted on `assertAuthenticated()`
   before asserting on the status code, so the 419 was masked by the auth
   failure message.

GET requests all passed. Write-method tests all failed. The pattern was exact.

**Root cause:**
Laravel 13 renamed the CSRF middleware. The class previously known as
`Illuminate\Foundation\Http\Middleware\VerifyCsrfToken` (Laravel ≤10) and
`Illuminate\Foundation\Http\Middleware\ValidateCsrfToken` (Laravel 11–12) was
renamed to `Illuminate\Foundation\Http\Middleware\PreventRequestForgery` in
Laravel 13. The new class registers itself in the `web` middleware group and
runs on all non-GET requests.

With `SESSION_DRIVER=array` in tests, each test request starts with a fresh
empty session — no CSRF token stored. The request body also contains no
`_token`. `PreventRequestForgery::tokensMatch()` returns false → 419.

**Why the built-in bypass did not fire:**
`PreventRequestForgery` has a built-in `runningUnitTests()` check at line 99
of the class. It returns true when
`$this->app->runningInConsole() && $this->app->runningUnitTests()`. This
should auto-bypass CSRF in tests. Investigation confirmed that at the point
these requests are processed, `app()->runningUnitTests()` was not returning
true reliably within the middleware's execution context.

**Approaches tried and rejected:**

| Approach | Result | Why Rejected |
|---|---|---|
| Remove `ValidateCsrfToken` in `bootstrap/app.php` `withMiddleware` | No effect | Wrong class name — this class is not registered in Laravel 13 |
| Remove `PreventRequestForgery` in `bootstrap/app.php` using `app()->runningUnitTests()` | No effect | The condition evaluated too early in the boot cycle before the app environment was fully detected as `testing` |

**Fix applied:**
`tests/TestCase.php` — call `$this->withoutMiddleware(PreventRequestForgery::class)`
in `setUp()` after `parent::setUp()`:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
}
```

This is explicit, runs after the application is fully created, and applies
globally to all tests. All 25 tests pass after this change.

**Lessons recorded:**

1. When upgrading to a new major Laravel version, check middleware class names
   have not been renamed. The class name changed across three consecutive major
   versions.
2. Breeze scaffold tests are the fastest signal that the test environment is
   correctly wired. If they fail on a fresh install, the fault is almost always
   in the test infrastructure (CSRF, session, environment detection), not in
   Breeze itself.
3. The `bootstrap/app.php` `withMiddleware` callback is not a reliable location
   for environment-conditional middleware removal — the `app()` container may
   not have the environment bound at the point the callback runs. Prefer
   `TestCase::setUp()` for test-layer concerns.
4. The `array` session driver is correct for tests and was not the problem.
   The `SESSION_DRIVER=array` difference between `.env` (redis) and
   `.env.testing` (array) is intentional and expected.

**Files changed:**
- `tests/TestCase.php` — added `setUp()` with `withoutMiddleware()`
- `bootstrap/app.php` — reverted to clean empty `withMiddleware` callback
  (two intermediate attempts were made and reversed before the final fix)

**Breeze version at time of incident:** `laravel/breeze v2.4.1` (2026-03-10)
**Laravel framework version:** `^13.0`

---

## Part 4 — Phase 3 Implementation Decisions (2026-03-26)

### D-005 · `readonly` removed from `ProcessImageJob::$media`

**Context:** During Phase 3.4, the `$media` property on `ProcessImageJob` was
changed from `private readonly` to `public readonly` so feature tests could
assert `$job->media->is($media)` inside `Queue::assertPushed()` closures.
When real uploads were processed via Horizon (PHP 8.3), all `ProcessImageJob`
executions failed with:

```
Error: Typed property App\Jobs\ProcessImageJob::$media
       must not be accessed before initialization
```

**Root cause:** `SerializesModels` serialises model instances to a
`ModelIdentifier` placeholder (`__sleep`), then restores them in `__wakeup`.
In PHP 8.3 the `unserialize()` engine initialises a `readonly` property once
(to the placeholder). `__wakeup` then attempts to replace it with the live
Eloquent model — PHP 8.3 rejects this second assignment on a `readonly`
property, leaving it unresolved.

**Decision:** Remove `readonly` from the declaration — `public Media $media`.
`public` visibility is preserved so tests can inspect the property; the
`readonly` guarantee is unnecessary for a short-lived queue job object.

**Rejected alternative:** Implementing `__unserialize()` on the job — adds
boilerplate to every serialised job class; the simpler fix is sufficient.

**Files changed:** `app/Jobs/ProcessImageJob.php`

---

### D-006 · `Bus::batch()` jobs require explicit `->onQueue()` at the call site

**Context:** `ResizeImageJob` and `GenerateThumbnailJob` each call
`$this->onQueue(...)` in their constructors. When dispatched standalone they
land on the correct queues. When dispatched inside `Bus::batch([...])` in
`ProcessImageJob::handle()`, both arrived on the `default` queue.

**Root cause:** Laravel's batch dispatcher does not propagate the queue name
set in a job's constructor when wrapping jobs into a `BatchedJob`. The queue
must be set explicitly on each job instance before it is passed to the array.

**Decision:** Chain `->onQueue()` at the `Bus::batch()` call site:

```php
Bus::batch([
    (new ResizeImageJob(...))->onQueue('media-standard'),
    (new GenerateThumbnailJob(...))->onQueue('media-critical'),
])
```

The constructor `onQueue()` calls are kept as self-documentation for standalone
dispatch, but the batch call site is now the authoritative assignment.

**Files changed:** `app/Jobs/ProcessImageJob.php`

---

## Part 5 — Phase 4 Broadcasting & Real-time UI (2026-03-26)

### D-027 · Dispatch delay: 7 seconds on ProcessImageJob

**Context:** After Phase 4.4 wired Laravel Echo, uploads went from `pending`
directly to `completed` in the UI with no visible intermediate states — even
though `processing`, `resize`, `thumbnail`, and `optimize` transitions all
occurred correctly in the DB.

**Root cause:** The Echo timing race. The actual WebSocket subscription
timeline in local Docker is ~70–160ms (TCP connect → WebSocket handshake →
channel auth round-trip). Horizon picks up `ProcessImageJob` in < 100ms of
dispatch. On an unloaded machine, all three job steps complete in ~200ms —
before the browser has authenticated the private channel. Soketi does not
buffer past events, so they evaporate before the subscription is established.

**Decision:** Dispatch `ProcessImageJob` with a 7-second delay:
```php
ProcessImageJob::dispatch($media)->delay(now()->addSeconds(7));
```

**Why 5 seconds:**
- The Echo subscription needs ~70–160ms in practice; 5s provides a
  ~30–70× safety margin — enough for any realistic Docker networking
  condition on an assessor's machine.
- Creates a clearly visible `pending` state so the evaluator observes the
  full state machine without transitions outrunning the eye.
- 5s is a deliberate balance: long enough to demonstrate queueing behaviour
  and guarantee Echo subscription establishment; short enough that the
  system does not feel synchronous. 7s was considered and rejected as
  it pushes end-to-end latency unnecessarily beyond demo needs.
- This is a UX calibration decision, not an architectural constraint.

**Does it violate the NFR?** The NFR "Job pickup latency: < 1 second from
dispatch to worker pickup" refers to the Redis → Horizon worker handoff.
With a 5s delay, Horizon sees `available_at = now + 5s` and picks up the
job within ~100ms of that timestamp — the sub-1s pickup NFR is met on its
own terms. End-to-end from upload to first processing event is now 5s
minimum, which is an accepted cost for demo observability.

**Rejected alternatives:**
- 3s — technically sufficient for Echo but too short to clearly demonstrate
  queue backlog behaviour for an evaluator watching a single upload.
- 7s — excessive; risks making the system feel synchronous and slow.

**To revert for production:** Remove `->delay(...)` or set via env variable
(e.g. `QUEUE_DISPATCH_DELAY_SECONDS=0`). The polling fallback
(`wire:poll.1000ms`) handles the timing gap at 0-delay.

---

### D-028 · Frontend-first subscription rejected

**Proposal evaluated:** Block `ProcessImageJob` dispatch until the browser
confirms its Echo subscription is active — i.e., create the Media record,
return the UUID, wait for the browser to POST `/media/{uuid}/dispatch`, then
run the job.

**Rejected.** Reasons:

1. **Violates the decoupled async design.** The spec (§ 1, § 5.1) states
   the system must demonstrate that "the HTTP layer, queue workers, and
   browser client are decoupled processes communicating asynchronously."
   Frontend-first subscription makes the queue dependent on an active browser
   connection — the system becomes synchronous through the back door.

2. **Job starvation on browser disconnect.** If the tab is closed, the
   network drops, or JS throws after the Media record is created but before
   the dispatch confirmation arrives, the job is never dispatched. The Media
   record is stuck in `pending` indefinitely. A cleanup scheduled command
   would be required — solving a problem the design created.

3. **Scalability regression.** 50 concurrent uploads → 50 browser sessions
   each holding an open confirmation round-trip. Under load, the dispatch
   endpoint becomes a congestion point. The dispatch delay approach scales
   horizontally without any extra coordination.

4. **Breaks non-browser clients.** An API consumer, a curl client, a mobile
   app without WebSocket support cannot use the system. The delay approach
   makes no such assumption about the caller.

5. **Latency is better but not decisively so.** Frontend-first adds ~400–800ms
   vs the 7s delay's 7000ms. The latency win is real but moot given that the
   delay is an intentional demo calibration, not a system constraint.

**Accepted instead:** 7s dispatch delay (D-027) + `wire:poll.1000ms`
fallback (D-029).

---

### D-029 · wire:poll as secondary guarantee for state visibility

**Context:** Even with the dispatch delay, network issues or Soketi downtime
could cause Echo events to be missed. A single-mechanism system (Echo-only)
has no recovery path.

**Decision:** Add `wire:poll.1000ms="checkStatus"` to the `MediaUploader`
blade, rendered only while `uploadStatus` is `pending` or `processing`.
The `checkStatus()` method queries the DB directly and maps status to
component properties.

**Behaviour:**
- Echo delivers the event in real time (primary path, sub-second update)
- `wire:poll` fires every 1s as fallback (secondary path, ≤ 1s lag)
- Both converge on the same terminal state — no conflict
- The poll directive disappears from the DOM on `completed`/`failed`, so
  Livewire stops polling automatically — no manual teardown needed

**Complexity:**
- Time: O(1) per poll — a single indexed DB query (`WHERE uuid = ? AND user_id = ?`)
- Space: O(1) — no additional state
- Scalability: 1 DB query/second per active upload session. With 5 concurrent
  uploads per user (the spec's max), this is 5 queries/second per user —
  well within MySQL capacity. Polling stops within 1s of job completion.

---

### D-030 · Retry state machine added to scope

**Context:** The spec (§ 3) includes "Failed job handling with user
notification" as in-scope. Laravel's automatic retry (3 attempts, exponential
backoff — §7) handles transient failures at the queue layer without changing
the Media record status. After all 3 automatic attempts are exhausted, the
job moves to `failed_jobs` and Media `status = failed`. No manual recovery
path existed from the UI.

**Decision:** Add a manual retry path:
- UI: "Retry" button on `failed` cards in `MediaLibrary` and `MediaUploader`
- Backend: `POST /media/{uuid}/retry` (or Livewire action) that:
  1. Guards: `status = failed` + ownership check
  2. Resets `status = pending`, clears `error_message`, `processing_step`, `progress`
  3. Re-dispatches `ProcessImageJob` with the 7s delay (D-027)
  4. No re-upload — uses existing `stored_filename`

**State machine (full arc):**
```
pending → processing → [resize 33%] → [thumbnail 66%] → [optimize 100%] → completed
                    ↘ failed → [user clicks Retry] → pending → processing → ...
```

**Enum decision:** Retry-queued reuses `pending` status — it IS pending
(waiting for a worker). A separate `retrying` enum value would add a
migration for purely UI disambiguation and break the existing spec enum.
`pending` is sufficient and the enum stays unchanged.

**Notification approach:** On `failed`, the UI surfaces the error message
inline (already stored in `error_message`) with a Retry button. No new
notification system, no email, no separate state. This satisfies "user
notification" from §3 without introducing new states or breaking the
spec enum.

**Spec alignment:** "Failed job handling" (§ 3) covers this. The retry
re-uses the existing stored file and existing Media record — no duplicate
storage, no orphaned files.

**Out of scope:** Per-step retry (retrying only the failed step, not the
full chain). The full chain re-runs on retry because OptimizeImageJob
depends on ResizeImageJob's output — re-running from the failed step would
require intermediate output persistence and step-dependency resolution, which
is out of scope for this assignment.

**Files to change:** `MediaUploadService` (or a new `MediaRetryService`),
`MediaController` (new `retry` action), `routes/web.php` (new route),
`MediaUploader` and `MediaLibrary` blade (Retry button), tests.

---

## Part 6 — Phase 4.4 Echo / Soketi Configuration Decisions

### D-031 · `VITE_PUSHER_HOST=localhost` — browser vs. Docker-internal hostname
**Date:** 2026-03-26

**Context:** After wiring `window.Echo` in `bootstrap.js`, the WebSocket connection
silently failed. The browser could not reach Soketi.

**Root cause:** `.env` had `PUSHER_HOST=soketi` (the Docker Compose service name —
resolvable inside the Docker network by the PHP app container). Vite embeds
`VITE_*` variables into the compiled JS bundle, which runs in the **browser on the
host machine** — not inside Docker. `soketi` is not a hostname the host machine
can resolve. The browser connected to `ws://soketi:6001` → DNS failure → silent
WebSocket error.

**Decision:** Set `VITE_PUSHER_HOST=localhost` in `.env`. The Docker Compose port
mapping `6001:6001` means the browser reaches Soketi through the host's
`localhost:6001` port. The server-side `PUSHER_HOST=soketi` remains correct for
PHP → Soketi communication inside Docker.

**Key distinction recorded in `.env.example`:**
```
# Server-side (PHP → Soketi, inside Docker network)
PUSHER_HOST=soketi

# Browser-side (JS bundle → Soketi, from host machine through port mapping)
VITE_PUSHER_HOST=localhost
```

**Why this matters for assessors:** Any evaluator cloning the repo will run the
browser on their host machine. If this distinction is not documented, they see a
blank/stuck uploader with no error visible in the UI.

---

### D-032 · Echo client configuration: `enabledTransports` and `disableStats`
**Date:** 2026-03-26

**Context:** Default `pusher-js` configuration attempts HTTP long-polling fallback
and reports connection statistics to Pusher's cloud analytics endpoint.

**Decisions:**
1. `enabledTransports: ['ws', 'wss']` — Soketi only supports WebSocket transports.
   Enabling HTTP fallback would cause `pusher-js` to attempt HTTP polling to a
   Soketi endpoint that doesn't exist, producing connection errors after WebSocket
   failure. WS-only is correct and faster.

2. `disableStats: true` — `pusher-js` by default POSTs connection stats to
   `pusher.com/pusher/app/{key}/...`. We are self-hosted; those requests fail with
   CORS errors and pollute the browser console. `disableStats: true` suppresses
   the outbound call entirely.

**No rejected alternatives** — both are correct defaults for any self-hosted
Pusher-protocol server.

---

## Part 7 — Phase 4.5 Soketi Verification & Failure Path Testing

### D-033 · `mediaflow:verify-broadcast` artisan command for server-side Soketi diagnostics
**Date:** 2026-03-26

**Context:** Task 4.5 required proving that Laravel can publish to Soketi. Browser
devtools verification is manual and non-reproducible. A programmatic server-side
check is needed for CI and for the assessor.

**Decision:** Ship `app/Console/Commands/VerifyBroadcast.php` as a four-step
diagnostic command:
1. TCP socket from app container to `soketi:6001` — proves network reachability
2. Pusher SDK queries the channels REST API — proves auth credentials are correct
3. SDK publishes a test event — Soketi returns `{"ok": true}`
4. Laravel `BroadcastManager` dispatches through the full config chain —
   proves the production code path (not just the SDK) is wired correctly

**Why an artisan command over a test:** The integration test auto-skips when Soketi
is unreachable (CI without Docker). The artisan command is for the running stack —
it is the quick diagnostic a developer runs when they suspect a misconfiguration.
Both serve different audiences.

---

### D-034 · How to trigger the failure path when only images are accepted
**Context:** Challenge raised: "How can failure be tested since allowed file format
is only images?" All non-image files are rejected at the boundary before any job
is dispatched. The `MediaProcessingFailed` broadcast event can only fire if a file
passes validation but then fails inside the processing pipeline.

**Three approaches evaluated:**

| Approach | Description | Assessment |
|---|---|---|
| Corrupt JPEG fixture | JPEG with valid magic bytes but corrupt scan data — passes all validation, Imagick throws at decode | Realistic, reproducible, end-to-end |
| File deletion during delay window | Upload valid file, delete from storage before 5s delay expires | Manual timing, not reliably reproducible |
| Artisan trigger command | Create a Media record pointing to a non-existent file, dispatch with delay | Reliable for demo, doesn't require a browser upload |

**Decision:** Build both Approach 1 and Approach 3. Approach 2 requires manual
timing and cannot be scripted.

**Corrupt fixture design (`tests/fixtures/corrupt-for-failure-test.jpg`):**
- Valid JPEG SOI `FF D8 FF` magic bytes → `finfo` detects `image/jpeg`
- Valid APP0 + SOF0 headers with declared dimensions 800×600 → `getimagesize()` returns width=800, height=600
- All Laravel validation rules pass: `mimetypes:image/jpeg`, `dimensions:min_width=100`
- Corrupt scan data → `Imagick::resizeImage()` throws `ImagickException: negative or zero image size`

**Observable failure timeline:**
```
Upload corrupt.jpg
    ↓ pending (5s dispatch delay)
    ↓ processing → ResizeImageJob → ImagickException (attempt 1)
    ↓ 10s backoff
    ↓ processing → attempt 2 → same exception
    ↓ 30s backoff
    ↓ processing → attempt 3 → same exception → failed()
    ↓ failed — MediaProcessingFailed broadcast → red card + Retry button
```
Total: ~41s. The wait is deliberate — it makes the 3-attempt retry mechanism from
spec §7 visually observable in the Horizon dashboard.

---

### D-035 · Intervention Image v3: `scaleDown()` vs `resize()` for aspect ratio
**Date:** 2026-03-26

**Context:** After Phase 4.5 went live, the user reported images were cropped and
distorted after processing. Root cause confirmed: `ImageProcessingService::resize()`
was calling `->resize($width, $height)`.

**Root cause:** In Intervention Image v3, `->resize($width, $height)` stretches the
image to *exactly* the specified pixel dimensions with no regard for aspect ratio.
A portrait 3:4 image resized to 1920×1080 (16:9) is squashed horizontally.

**Decision:** Replace with `->scaleDown($width, $height)`.
`scaleDown()` scales the image so that neither dimension exceeds the target while
preserving the original aspect ratio. It never upscales (scale *down* only) — a
small image already within bounds is returned unchanged.

**Why `scaleDown` over `resize` with explicit ratio calculation:**
- Single method call, no manual width/height ratio arithmetic
- Handles both landscape and portrait correctly
- Matches the intent of "resize to fit a bounding box" which is what the spec
  describes (§ 4: "resize to 1920×1080 maximum")

**`thumbnail()` uses `->cover()` — unchanged.** Cover crops to fill the exact
dimensions (correct for square thumbnails). This is intentional and was not the bug.

**Files changed:** `app/Services/ImageProcessingService.php`

---

### D-036 · `SoketiBroadcastIntegrationTest` channel-registration isolation fix
**Date:** 2026-03-26

**Context:** When switching the broadcaster from `null` (PHPUnit default — prevents
actual broadcast calls) to `pusher` (to hit real Soketi) in a test's `setUp()`,
channel auth callbacks registered on the null driver were invisible to the pusher
driver. Channel auth tests returned 403 for the owner.

**Root cause:** Each Laravel broadcast driver instance maintains its own channel
registry. Switching drivers with `config(['broadcasting.default' => 'pusher'])` gives
you a fresh pusher driver instance with no registered channels.

**Decision:** In `setUp()`, before switching driver, snapshot the channel callbacks
from the null driver and re-register them on the pusher driver:
```php
$manager  = $this->app->make(BroadcastManager::class);
$channels = $manager->driver()->getChannels()->all();    // snapshot from null driver
config(['broadcasting.default' => 'pusher']);
foreach ($channels as $pattern => $callback) {           // re-register on pusher
    $manager->driver()->channel($pattern, $callback);
}
```
This is the same pattern used in `BroadcastChannelTest`. Extracted and documented
here as the canonical fix for this class of isolation problem.

**Auto-skip guard:** The entire `SoketiBroadcastIntegrationTest` class skips if
Soketi is unreachable at `soketi:6001`. This prevents CI failures in environments
without Docker (e.g., GitHub Actions without the compose stack).

---

## Part 8 — Phase 5.1 Edge Case Handling Decisions

### D-037 · MIME validation reads actual file content, not filename extension
**Date:** 2026-03-27

**Challenge raised:** "Upload a PDF with `somefile.jpg` — can the system detect it?"

**Verification:** `UploadedFile::getMimeType()` invokes PHP's `finfo` extension with
`FILEINFO_MIME_TYPE`, which reads the file's magic bytes regardless of filename.
A PDF file named `somefile.jpg` returns `application/pdf` from `getMimeType()` even
if the HTTP request headers claim `Content-Type: image/jpeg`.

Confirmed in a running container:
```
getMimeType():      application/pdf   ← from file bytes
getClientMimeType(): image/jpeg        ← what browser claimed
Laravel mimetypes: validator: FAILS   ← rejects correctly
```

**Decision:** No code change required — validation was already correct. A test
fixture was added (`tests/fixtures/pdf-disguised-as-jpg.jpg`) containing real PDF
bytes to prove this in automated tests. Two tests were added:

1. `test_pdf_disguised_as_jpg_detected_mime_is_not_image` — asserts `getMimeType()`
   returns `application/pdf` and `getClientMimeType()` returns `image/jpeg`, proving
   the fixture genuinely represents a spoofed upload. If this fails, the spoofing
   test below is meaningless.
2. `test_pdf_with_jpg_extension_is_rejected_by_content_detection` — proves the
   full HTTP stack rejects the spoofed file with 422 and no DB record created.

**Why use `mimetypes:` rule (not `mimes:`):** The `mimes` rule trusts the file
extension; `mimetypes` rule calls `getMimeType()` (finfo). Using `mimes` would
have been vulnerable to spoofing. Using `mimetypes` is the correct, content-based
check.

---

### D-038 · Multi-user concurrent upload isolation — UUID storage and ownership
**Date:** 2026-03-27

**Challenge raised:** "What about multiple users uploading at the same time — can
the system handle this? Ensure files don't get mixed up between users' libraries."

**Analysis:** The architecture has three isolation mechanisms already in place:

| Layer | Mechanism | Guarantees |
|---|---|---|
| Storage | `stored_filename = UUID.ext` — `Str::uuid()` per upload | No two uploads share a file path |
| Database | `media.user_id = $user->id` set explicitly at record creation | Ownership is always the authenticated user |
| Policy | `MediaPolicy::view/delete/retry` checks `$user->id === $media->user_id` | No cross-user access on any endpoint |

**Decision:** No code change required — isolation was already correct. Six new
tests were added to prove it under concurrent conditions:

1. Three users uploading simultaneously → three independent records, each with
   the correct `user_id`
2. All stored filenames are unique — no shared storage paths
3. User B cannot view User A's media (403)
4. User B cannot delete User A's media (403, record preserved)
5. Each queued job carries only its owner's `media.user_id` — no cross-linking
6. User-supplied filenames (e.g. `../../etc/passwd.jpg`) do not appear in
   `stored_filename` — path traversal input cannot leak into storage paths

**Why UUID not user-scoped paths:** Storing files under `{user_id}/filename` would
work for isolation, but UUID-per-file is strictly stronger: it guarantees
uniqueness globally, not just per user. Two users uploading files with the same
original name cannot collide even if the UUID-generation probability of collision
is astronomically low. UUID storage also allows files to be served by UUID without
exposing the `user_id` in the URL.

---

## Part 9 — Phase 5.2 OOP Audit Decisions

### D-039 · Business logic extracted from controller: `retry()` and `delete()` to service
**Date:** 2026-03-27

**Audit finding:** `MediaController::retry()` directly mutated the DB and dispatched
a job (business logic in the HTTP layer). The identical logic was copy-pasted into
`MediaUploader::retryProcessing()` and `MediaLibrary::retryMedia()` — the same
sequence triplicated across three files. `MediaController::deleteMediaFile()` was a
private method containing storage logic in a controller.

**Violations:**
- Controller doing DB mutations and job dispatch (not routing/response concerns)
- No single source of truth for retry semantics — three copies to keep in sync
- File deletion logic embedded in the HTTP layer

**Decision:** Extract to `MediaUploadService::retry(Media $media): void` and
`MediaUploadService::delete(Media $media): void`.

- All three callers (`MediaController`, `MediaUploader`, `MediaLibrary`) now
  delegate to the service — one definition, three consumers.
- `MediaController::deleteMediaFile()` private method removed entirely.
- The delete method preserves the correct operation order: file removed from
  storage first (`stored_filename` still accessible on the model), DB record
  deleted second. Reversing the order would orphan the file if the storage call
  threw.

**Why operation order in `delete()` matters:**
```php
Storage::disk('media')->delete($media->stored_filename);  // ① file deleted
$media->delete();                                          // ② record deleted
```
If ① throws, the DB record is preserved — we can retry. If the order were
reversed and ② succeeded before ① threw, the file would be orphaned with no
record pointing to it.

---

### D-040 · Event timestamps captured at construction time, not at broadcast time
**Date:** 2026-03-27

**Audit finding:** `MediaProcessingStarted`, `MediaProcessingCompleted`, and
`MediaProcessingFailed` called `now()` inside `broadcastWith()`. This means the
timestamp in the payload was generated at the moment the event was *broadcast*
(by a queue worker processing the broadcast job) — potentially seconds after the
event was *fired* (when the domain action occurred).

**Violation:** `broadcastWith()` should be a pure data accessor. Calling `now()`
inside it introduces side effects and produces timestamps that are semantically
wrong — they report the broadcast time, not the event time.

**Decision:** Capture the timestamp in each event's constructor and store it as a
`readonly` property. `broadcastWith()` returns the stored value.

```php
// Before — timestamp at broadcast time (wrong)
public function broadcastWith(): array {
    return ['started_at' => now()->toIso8601String()];  // ← now() is when Soketi receives it
}

// After — timestamp at event-fire time (correct)
public readonly string $startedAt;

public function __construct(public readonly Media $media) {
    $this->startedAt = now()->toIso8601String();  // ← now() is when job fired the event
}

public function broadcastWith(): array {
    return ['started_at' => $this->startedAt];    // ← pure accessor, no side effects
}
```

**Why it matters in practice:** With a queue delay or a loaded worker, the gap
between event dispatch and broadcast delivery can be several seconds. A timestamp
recorded at broadcast time would show the event happening later than it did,
making the timeline in the browser UI misleading for debugging.

---

### D-041 · `MediaUploadService` name does not reflect expanded scope
**Date:** 2026-03-27

**Observation:** After the OOP audit extracted `retry()` and `delete()` into
`MediaUploadService`, the class now handles the full media lifecycle: upload,
retry, and delete. The name `MediaUploadService` accurately described the class
when it only handled uploads (Phase 2) but is now misleading.

**Decision:** Rename to `MediaService` is the correct long-term fix. Deferred to
Phase 6 (documentation / polish) — it is a mechanical find-and-replace across the
codebase with no logic risk, but would generate a noisy diff with no behaviour
change during the active hardening phase.

**Impact of not renaming immediately:** None on behaviour. A code reader will find
`retry()` and `delete()` methods on a class called `MediaUploadService` and may
be confused — the docblocks on each method explain the scope.

**Accepted trade-off:** Clarity of naming is sacrificed temporarily in favour of
keeping the hardening-phase diff focused on behaviour, not refactoring. The rename
is logged here so it is not forgotten.

---

## Part 10 — Phase 5.3: Security Review

### D-042 · CSRF protection: asserting the middleware stack, not a 419 HTTP response
**Date:** 2026-03-26

**Context:** The three CSRF tests initially used `withMiddleware()` + `actingAs($user)`
to re-enable the `PreventRequestForgery` guard and attempted to assert that a
tokenless POST returned HTTP 419.

**Problem discovered:** Laravel's test HTTP client shares the session that
`actingAs()` establishes. That session object already contains a valid `_token`
(created by `StartSession`), and the test client copies it into the request
headers automatically when `withMiddleware()` is active. The CSRF guard therefore
sees a matching token and passes the request — returning 201, not 419 — even
though no token was supplied by the test author.

**Decision:** Replace the per-route 419 assertions with two complementary checks:
1. Assert that `PreventRequestForgery::class` is present in the `web` middleware
   group — proves the guard is wired to the global stack.
2. Assert that each mutating route (`store`, `destroy`, `retry`) declares `web`
   in its middleware — proves the guard applies to those specific routes.

**Why this is sufficient:** The Laravel framework owns `PreventRequestForgery`;
it is not our code and does not need integration-test coverage here. What _is_
our responsibility is confirming that our routes sit inside the group that
activates the guard. Checking the middleware registration is a direct,
deterministic assertion of that property.

**Alternatives rejected:** Using `withoutSession()` to suppress the auto-token
injection was considered but fragile — it relies on undocumented test-client
internals. Checking for 419 from an anonymous (non-`actingAs`) request would
prove CSRF fires but would conflate CSRF with auth (the route also requires a
logged-in user, so the real 401 would arrive before 419).

---

### D-043 · XSS: Blade's auto-escaping is the control; `stored_filename` is a separate concern
**Date:** 2026-03-26

**Context:** Phase 5.3 covers two distinct XSS risks that share a filename but
need different tests.

**Risk 1 — storage:** Does the service mutate or sanitise the client-supplied
filename before writing it to the DB? It must not — escaping belongs at render
time, not at storage time. Sanitising on write means the stored value diverges
from what the user uploaded, which breaks audit trails and download filenames.
The test seeds a record directly with `original_filename = '<script>alert(1)</script>.jpg'`
and reads it back, confirming round-trip fidelity.

**Why not upload a fake file with that name:** `UploadedFile::fake()->image()`
creates a real temp file on the OS filesystem. The OS (or PHP's temp-file layer)
silently strips angle brackets from the filename. The test would always pass
vacuously because the XSS payload is dropped before the application ever sees it.

**Risk 2 — API response:** Does `MediaController@show` return `original_filename`
as raw HTML in the JSON body? It must not. The test asserts that the JSON string
`<script>alert(1)</script>` does not appear literally in the response — it may
appear URL-encoded or escaped, but not as raw executable markup. Covered by
`test_xss_payload_in_original_filename_is_escaped_in_api_response`.

**Blade rendering:** Blade's `{{ }}` syntax HTML-encodes output by default. The
`{!! !!}` syntax (raw output) was found on one Blade expression in
`media-uploader.blade.php` (`$stepLabel`) and was corrected to `{{ }}` during
the Phase 5.3 audit. No other unescaped output was found.

---

### D-044 · Rate limiting on upload (10/min) and retry (5/min)
**Date:** 2026-03-26

**Context:** Without rate limiting, an authenticated user could flood the upload
endpoint and create arbitrarily many queued jobs, consuming queue workers, disk,
and DB rows without bound.

**Decision:** Apply Laravel's built-in `throttle` middleware:
- `POST /media` — `throttle:10,1` (10 requests per minute per user)
- `POST /media/{uuid}/retry` — `throttle:5,1` (5 retries per minute per user)

**Rationale for limits:** 10 uploads/min covers normal interactive use (bulk
drag-and-drop sessions). 5 retries/min is deliberately lower — retries indicate
processing failure, so a burst of retries likely signals a systematic problem
that more retries will not fix; throttling gives the queue time to recover.

**Keyed by user ID:** Laravel's default throttle key uses `auth()->id()` for
authenticated routes, so limits are per-user, not per-IP. This prevents a
single user from consuming shared worker capacity.

**Test approach:** `RateLimiter::clear()` is called before each rate-limit test
to prevent bleed from prior test runs. The 11th upload and 6th retry assert HTTP 429.

---

### D-045 · SQL injection: Eloquent parameter binding makes route-segment injection impossible
**Date:** 2026-03-26

**Context:** All resource routes use a UUID path segment (`/media/{uuid}`). A
classic injection payload like `'; DROP TABLE media; --` was submitted as the
UUID path segment.

**Finding:** Eloquent's `where('uuid', $uuid)` uses PDO prepared statements.
The segment is always a bound parameter, never string-interpolated into SQL.
The query returns zero rows, Eloquent calls `firstOrFail()`, and the controller
returns 404 — no DB error, no table drop, no data leakage.

**Test:** `test_sql_injection_in_uuid_path_returns_404_not_error` submits the
payload and asserts (a) HTTP 404, (b) `assertDatabaseCount('media', 0)` — the
table still exists and is empty.

---

### D-046 · Private broadcast channel auth enforces ownership
**Date:** 2026-03-26

**Context:** Each media item broadcasts on `private-media.{uuid}`. The
`/broadcasting/auth` endpoint must refuse auth tokens for channels belonging to
another user.

**Implementation:** `routes/channels.php` registers a closure for
`private-media.{uuid}` that loads the Media record by UUID and returns
`$user->id === $media->user_id`. A `false` return causes the broadcast manager
to respond 403.

**Test complication:** PHPUnit's default broadcaster is the `null` driver.
`channels.php` registers closures on the driver that is active at boot time
(null). `/broadcasting/auth` runs against whichever driver is current at
request time. If they differ, the auth endpoint has no registered callbacks
and always returns 403 — meaning even owner requests fail.

**Fix:** `SecurityTest::setUp()` and `BroadcastChannelTest::setUp()` both copy
the null-driver channel registrations onto the pusher driver before switching
`broadcasting.default` to `pusher`. This ensures the real ownership callback
runs on the test `/broadcasting/auth` request.

---

### D-047 · File format security: MIME detection from file content, not extension
**Date:** 2026-03-26

**Context:** A malicious actor could rename a PHP script or PDF to `photo.jpg`
and attempt to store it. Trusting the file extension or the client-declared
`Content-Type` would allow this.

**Implementation:** Laravel's `mimetypes:` validation rule calls
`UploadedFile::getMimeType()`, which invokes PHP's `finfo_file()` on the actual
file bytes — the same mechanism as the `file` command on Linux. The fixture
`tests/fixtures/pdf-disguised-as-jpg.jpg` contains a real PDF header (`%PDF-1.4`);
`finfo` correctly returns `application/pdf`, the validator rejects it, and the
upload returns 422 with no DB record created.

**Test coverage:** Three tests in `SecurityTest` and one in `MediaEdgeCaseTest`:
- PDF with `.jpg` extension rejected (both test classes, using the same fixture)
- `application/octet-stream` file rejected regardless of extension
- Path-traversal filename (`../../etc/passwd.jpg`) accepted for the content but
  the stored filename is always `{uuid}.{extension}`, never user-supplied path
  components — asserted by regex `/^[0-9a-f\-]{36}\.[a-z]+$/`.

---

### D-048 · Livewire `statusFilter` whitelisted via `updatedStatusFilter` hook
**Date:** 2026-03-26

**Context:** Livewire exposes public component properties to the browser. A user
can craft a WebSocket message that sets `statusFilter` to an arbitrary string
(e.g., `'; DROP TABLE media; --`). Even though Eloquent uses parameterised
queries, passing a non-enum value to `where('status', $this->statusFilter)` is
unnecessary attack surface.

**Implementation:** `MediaLibrary` declares `updatedStatusFilter(string $value)`
— a Livewire lifecycle hook called automatically after any property update.
If the new value is not in the whitelist `['all', 'pending', 'processing',
'completed', 'failed']`, it is reset to `'all'` before the next render.

**Test:** `test_invalid_status_filter_is_silently_reset_to_all` sets the
property via `$component->set()` and asserts it reads back as `'all'`. All five
valid values are also asserted to pass through unchanged.

---

### D-049 · Information disclosure: `stored_filename` hidden from API response
**Date:** 2026-03-26

**Context:** `stored_filename` is the UUID-based path used on the server
filesystem. Returning it in API responses would reveal the internal storage
structure and enable a user to guess or probe other file paths.

**Implementation:** `MediaController@show` calls `$media->makeHidden(['stored_filename'])`
before serialising to JSON. The field is present in the DB and on the Eloquent
object (needed for delete and storage operations) but is removed from the
outgoing JSON.

**Test:** `test_show_response_does_not_expose_stored_filename` asserts the key
is absent from `$response->json()`. A complementary test asserts that the
expected public fields (`uuid`, `status`, `original_filename`, `mime_type`) are
all present — confirming that `makeHidden` did not strip too much.
