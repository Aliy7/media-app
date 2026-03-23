# Implementation Plan
## MediaFlow — Iterative Incremental Lifecycle

**Version:** 1.0
**Date:** 2026-03-23
**Methodology:** Iterative Incremental + Kanban
**Pairing:** A. Muktar (Architect/Reviewer) + Claude Code (Implementation Driver)

---

## Pairing Roles

| Responsibility | Human (Architect) | Agent (Driver) |
|---|---|---|
| Architecture decisions | Owner | Advisor |
| Scope control | Owner | Flags risks |
| Implementation | Reviewer / approves | Writes code |
| Test design | Co-owner | Writes tests |
| Code review | Owner | Self-reviews before presenting |
| Debugging | Collaborative | First responder |

**WIP Rule:** One increment in progress at a time. No increment begins until the previous exits all criteria.

---

## Kanban Board Structure

```
BACKLOG → READY → IN PROGRESS → IN REVIEW → DONE
```

- **Backlog:** Defined but not yet the active increment
- **Ready:** Next increment — all prerequisites met
- **In Progress:** Active work — WIP limit = 1 increment
- **In Review:** Human reviews and tests before closing
- **Done:** Exit criteria verified, committed to version control

---

## TDD Practice

For every non-trivial implementation:
1. Write the test first (define expected behaviour)
2. Run the test — confirm it fails (red)
3. Implement the minimum code to pass (green)
4. Refactor without breaking the test (refactor)

Laravel testing tools used:
- `PHPUnit` — unit and feature tests
- `Queue::fake()` — assert jobs dispatched without processing
- `Event::fake()` — assert events fired without broadcasting
- `Bus::fake()` — assert job chains dispatched correctly
- `Storage::fake()` — assert file operations without disk I/O

---

## Phase 0 — Project Inception *(Complete)*

**Goal:** All decisions made before touching code.

| Task | Status |
|---|---|
| Project idea selection | Done |
| Tech stack finalised | Done |
| Architecture designed | Done |
| OOP layer responsibilities defined | Done |
| Database schema designed | Done |
| Queue architecture designed | Done |
| Broadcasting design finalised | Done |
| Methodology agreed | Done |
| Project specification written | Done |
| Implementation plan written | Done |
| Deliverables documented | Done |

**Exit Criterion:** All specification documents written and agreed. No open architectural decisions.

### Human Review Checkpoint — Phase 0
| Check | How to Verify |
|---|---|
| All open questions in PROJECT_SPEC.md acknowledged | Read section 13, confirm each has a default or decision recorded |
| Tech stack versions agreed | Read section 4, confirm no version marked "TBD" |
| Increment exit criteria understood | Read each phase exit criterion — no ambiguity |

**Rollback:** N/A — no code written. Amend spec documents directly.

**Git Commit:** Commit all three spec documents together.
```
Files: docs/PROJECT_SPEC.md  docs/IMPLEMENTATION_PLAN.md  docs/DELIVERABLES.md
Message: "docs: add project specification, implementation plan, and deliverables"
```

---

## Phase 1 — Infrastructure & Skeleton

**Goal:** A running, authenticated Laravel application with all infrastructure services connected.

### Tasks

#### 1.1 Docker Compose Environment
- [ ] Write `docker-compose.yml` with services: `app`, `nginx`, `mysql`, `redis`, `soketi`, `horizon`
- [ ] Write `Dockerfile` for PHP 8.3-FPM with Imagick extension installed
- [ ] Write `nginx.conf` for Laravel
- [ ] Write `.env.example` with all required variables documented
- [ ] Verify: `docker compose up` starts all services without errors
- [ ] Verify: services communicate (app → mysql, app → redis, app → soketi)

#### 1.2 Laravel Installation & Configuration
- [ ] Verify existing Laravel installation: `docker compose exec app php artisan --version` → must show Laravel 13.x
- [ ] Verify PHP version: `docker compose exec app php -v` → must show PHP 8.3.x
- [ ] Verify Node version: `docker compose exec app node -v` → must show v22.x
- [ ] Install Laravel Breeze (Blade stack)
- [ ] Configure `.env`: `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=pusher` (Soketi)
- [ ] Configure `config/broadcasting.php` with Soketi connection details
- [ ] Install and configure Laravel Horizon
- [ ] Publish Horizon assets, configure `config/horizon.php` with queue workers

#### 1.3 Database Setup
- [ ] Run default Laravel migrations (users, password_resets, etc.)
- [ ] Verify Breeze auth migrations run cleanly
- [ ] Verify MySQL connection from application container

#### 1.4 Verification Tests
- [ ] Register a user via `/register` — record persists in DB
- [ ] Login with registered user — session created
- [ ] Access `/horizon` — dashboard loads
- [ ] Confirm Redis connection in Horizon

**Exit Criterion:**
- `docker compose up` → all services healthy
- User can register, login, logout
- Horizon dashboard accessible and showing Redis connection
- No errors in application logs

### Human Review Checkpoint — Phase 1
**Before running checks: reset to a clean state.**
```bash
docker compose exec app php artisan migrate:fresh
```
**You must personally run every command below and confirm the result before Phase 2 begins.**

| Check | Command to Run | Expected Result |
|---|---|---|
| All containers healthy | `docker compose ps` | All services show `running` or `healthy`, none `exited` |
| App container logs clean | `docker compose logs app` | No PHP fatal errors, no missing extension warnings |
| MySQL reachable from app | `docker compose exec app php artisan db:show` | Shows database name, tables, connection details |
| Redis reachable from app | `docker compose exec app php artisan tinker --execute="Redis::ping()"` | Returns `+PONG` |
| Soketi reachable | `curl http://localhost:6001/app/app-key` | Returns JSON response (not connection refused) |
| Migrations ran | `docker compose exec app php artisan migrate:status` | All migrations show `Ran` |
| Register a user | Navigate to `http://localhost/register` in browser | Form submits, redirects to dashboard, no 500 error |
| Login with that user | Navigate to `http://localhost/login` | Login succeeds, session cookie set |
| Logout | Click logout | Redirects to login page |
| Horizon loads | Navigate to `http://localhost/horizon` | Dashboard renders, shows Redis connection, no blank page |
| Automated tests pass | `docker compose exec app php artisan test` | All tests green (Breeze default tests) |

**Rollback Instructions — Phase 1:**
If Phase 1 must be abandoned or restarted:
```bash
# Stop and remove all containers AND volumes (wipes MySQL data)
docker compose down -v

# Remove generated Laravel config caches
docker compose exec app php artisan config:clear   # if containers still up

# If Laravel was installed fresh and you want to start over
# Delete all files except: docs/, transcripts/, export-transcript.sh, .git/
# Then re-run laravel new . inside the directory
```
> If only a specific service is broken (e.g., Soketi), stop that container only: `docker compose stop soketi` and fix its config before restarting.

**Git Commit — Phase 1:**
Commit once all services are running and tests pass. Do not commit broken state.
```
Files: docker-compose.yml  Dockerfile  nginx.conf  .env.example
       config/horizon.php  config/broadcasting.php
       config/queue.php    composer.json  composer.lock
       package.json        package-lock.json
       database/migrations/ (all Breeze migrations)
Message: "infra: add Docker environment, configure Redis queue, Soketi broadcast, Horizon"
```

---

## Phase 2 — Core Domain (Vertical Slice 1)

**Goal:** Authenticated user can upload an image. Record persists. File stored. No processing yet.

### Tasks

#### 2.1 Database Migration
- [ ] Write migration for `media` table (all columns per spec)
- [ ] Run migration, verify schema in MySQL
- [ ] Write test: migration creates correct schema

#### 2.2 Media Model
- [ ] Create `Media` Eloquent model
- [ ] Define `fillable`, `casts` (status enum, outputs JSON)
- [ ] Define `belongsTo` User relationship
- [ ] Define status constants or enum: `pending`, `processing`, `completed`, `failed`
- [ ] Write unit tests for model relationships and casts

#### 2.3 MediaUploadService
- [ ] Create `App\Services\MediaUploadService`
- [ ] Method: `handle(UploadedFile $file, User $user): Media`
- [ ] Validate file (MIME type, size, dimensions) — throw `InvalidMediaException` on failure
- [ ] Generate UUID for the media record
- [ ] Store file via `Storage::disk('local')->put()`
- [ ] Create and return `Media` model record (status: `pending`)
- [ ] Write unit tests: valid file → Media record created, invalid file → exception thrown

#### 2.4 MediaController
- [ ] Create `MediaController` with methods: `store()`, `show()`, `destroy()`
- [ ] `store()`: validate request, call `MediaUploadService::handle()`, return response
- [ ] `show()`: return media record for authenticated owner only
- [ ] `destroy()`: delete media record and file, authorise ownership
- [ ] Write feature tests: POST `/media` with valid image → 201 response + DB record

#### 2.5 Livewire Upload Component
- [ ] Create `MediaUploader` Livewire component
- [ ] File input with client-side preview
- [ ] Livewire action method calls `MediaUploadService::handle()` directly — not via HTTP POST to the controller
- [ ] Display validation errors reactively
- [ ] On success, store returned `uuid` in component state for Phase 4 channel subscription

#### 2.6 Routes
- [ ] Register resource routes for media in `routes/web.php`
- [ ] Apply `auth` middleware to all media routes
- [ ] Write route tests: unauthenticated access → redirect to login

**Exit Criterion:**
- Authenticated user uploads a valid image
- `media` record created in DB with status `pending`
- File present on disk at expected path
- Invalid files (wrong type, oversized) return validation errors
- Unauthenticated upload attempt redirected to login
- All tests pass: `php artisan test`

### Human Review Checkpoint — Phase 2
**Before running checks: reset to a clean state.**
```bash
docker compose exec app php artisan migrate:fresh
```
**You must personally verify each item below before Phase 3 begins.**

| Check | Command / Action | Expected Result |
|---|---|---|
| Migration schema correct | `docker compose exec app php artisan migrate:status` | `media` migration shows `Ran` |
| Inspect schema in DB | `docker compose exec app php artisan tinker --execute="Schema::getColumnListing('media')"` | Lists all columns matching spec |
| Upload valid JPEG | Open `http://localhost/media/upload`, upload a valid JPG | Redirects, `media` record in DB with `status = pending` |
| Confirm DB record | `docker compose exec app php artisan tinker --execute="App\Models\Media::latest()->first()"` | Shows record with correct user_id, uuid, filename, status |
| Confirm file on disk | `docker compose exec app ls storage/app/media/` | File present with system-generated name |
| Upload PNG file | Upload a valid PNG | Accepted, record created |
| Upload PDF (invalid) | Upload a PDF file | 422 validation error, no DB record created |
| Upload 11MB image (oversized) | Upload file > 10MB | 422 validation error, no DB record created |
| Unauthenticated upload | Logout, attempt `POST /media` | Redirected to `/login` |
| Ownership on show | Login as different user, attempt `GET /media/{uuid}` of another user's media | 403 Forbidden |
| Automated tests pass | `docker compose exec app php artisan test` | All tests green |

**Rollback Instructions — Phase 2:**
```bash
# Roll back only the media migration (leaves Breeze migrations intact)
docker compose exec app php artisan migrate:rollback --step=1

# Delete created files manually or via git
git checkout HEAD -- app/Http/Controllers/MediaController.php  # if committed
git checkout HEAD -- app/Models/Media.php
git checkout HEAD -- app/Services/MediaUploadService.php
git checkout HEAD -- app/Livewire/MediaUploader.php
git checkout HEAD -- routes/web.php

# Clear uploaded files from storage
docker compose exec app php artisan storage:clear   # or manually:
docker compose exec app rm -rf storage/app/media/*

# If not yet committed, simply delete the files and re-run migration rollback
```

**Git Commit — Phase 2:**
Commit only after all Phase 2 exit criteria are verified.
```
Files: database/migrations/xxxx_create_media_table.php
       app/Models/Media.php
       app/Http/Controllers/MediaController.php
       app/Services/MediaUploadService.php
       app/Exceptions/InvalidMediaException.php
       app/Livewire/MediaUploader.php
       resources/views/livewire/media-uploader.blade.php
       routes/web.php
       tests/Unit/MediaUploadServiceTest.php
       tests/Feature/MediaUploadTest.php
Message: "feat: add media upload — model, service, controller, Livewire component, tests"
```

---

## Phase 3 — Queue Jobs (Vertical Slice 2)

**Goal:** Upload dispatches a job chain. Each job processes one step. Horizon shows job states. Failed jobs surface correctly.

### Tasks

#### 3.1 ImageProcessingService
- [ ] Create `App\Services\ImageProcessingService`
- [ ] Inject Intervention Image manager
- [ ] Method: `resize(string $path, int $width, int $height): string` — returns output path
- [ ] Method: `thumbnail(string $path, int $size): string`
- [ ] Method: `optimize(string $path): string`
- [ ] Write unit tests for each method with real images (use test fixtures)

#### 3.2 Job Classes
- [ ] Create `ResizeImageJob`: accepts `Media $media`, dimension config
  - Calls `ImageProcessingService::resize()`
  - Updates `Media` status and `processing_step`
  - Fires `MediaStepCompleted` event
- [ ] Create `GenerateThumbnailJob`: accepts `Media $media`
  - Calls `ImageProcessingService::thumbnail()`
  - Updates `Media` model
  - Fires `MediaStepCompleted` event
- [ ] Create `OptimizeImageJob`: accepts `Media $media`
  - Calls `ImageProcessingService::optimize()`
  - Updates `Media` status to `completed`
  - Fires `MediaProcessingCompleted` event
- [ ] Create `ProcessImageJob`: orchestrator
  - Updates `Media` status to `processing`
  - Fires `MediaProcessingStarted` event
  - Builds and dispatches chain: `ResizeImageJob → GenerateThumbnailJob → OptimizeImageJob`
- [ ] Configure on each job: `$queue`, `$tries = 3`, `$backoff = [10, 30, 60]`, `$timeout = 120`
- [ ] Implement `failed()` method on each job: update `Media` status, store error, fire `MediaProcessingFailed`
- [ ] Write unit tests with `Queue::fake()`, `Bus::fake()`, `Event::fake()`

#### 3.3 Horizon Worker Configuration
- [ ] Configure `config/horizon.php` with named queues and worker counts
- [ ] Assign jobs to correct queues per spec (thumbnail → `media-critical`, resize → `media-standard`, optimise → `media-low`)
- [ ] Verify Horizon dashboard shows queue metrics after test dispatches

#### 3.4 Dispatch from Service
- [ ] Update `MediaUploadService::handle()` to dispatch `ProcessImageJob` after creating Media record
- [ ] Write feature test: upload image → `ProcessImageJob` dispatched (using `Queue::fake()`)

**Exit Criterion:**
- Uploading an image dispatches `ProcessImageJob`
- Job chain processes: resize → thumbnail → optimize
- `Media` record status transitions: `pending → processing → completed`
- Output files present on disk
- Horizon dashboard shows jobs: queued, processing, completed
- Deliberately corrupt file → job fails, `Media` status `failed`, error recorded, Horizon shows failed job
- All tests pass: `php artisan test`

### Human Review Checkpoint — Phase 3
**Before running checks: reset to a clean state.**
```bash
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan queue:flush
```
**You must personally verify each item below before Phase 4 begins.**

| Check | Command / Action | Expected Result |
|---|---|---|
| Horizon workers running | Navigate to `http://localhost/horizon` | Workers shown as active, queues visible |
| Upload image, watch Horizon | Upload image, switch immediately to Horizon dashboard | Jobs appear in `media-critical`, `media-standard`, `media-low` queues in real time |
| Status transitions | After upload, poll: `docker compose exec app php artisan tinker --execute="App\Models\Media::latest()->first()->status"` | Transitions from `pending` → `processing` → `completed` |
| Output files exist | `docker compose exec app ls storage/app/media/outputs/` | Multiple output files present (large, medium, thumbnail, optimised) |
| Named queues assigned correctly | Check Horizon dashboard queue view | Thumbnail job appears in `media-critical`, not default queue |
| Simulate failure | Upload a deliberately corrupt file (rename a .txt to .jpg) | `Media` record status becomes `failed`, error_message populated |
| Failed job in Horizon | After failure, check `/horizon/failed` | Failed job entry visible with full exception trace |
| Retry configuration | Click retry on failed job in Horizon | Job re-queued and attempts again |
| Job chain order | Review Horizon job history | `ResizeImageJob` → `GenerateThumbnailJob` → `OptimizeImageJob` completed in sequence |
| Automated tests pass | `docker compose exec app php artisan test` | All tests green including new job tests |

**Rollback Instructions — Phase 3:**
```bash
# Flush all pending jobs from all queues
docker compose exec app php artisan queue:flush

# Clear failed jobs table
docker compose exec app php artisan queue:forget --all   # or:
docker compose exec app php artisan tinker --execute="DB::table('failed_jobs')->truncate()"

# Remove job classes via git if committed
git checkout HEAD -- app/Jobs/
git checkout HEAD -- app/Services/ImageProcessingService.php

# Revert MediaUploadService to not dispatch (remove dispatch call)
# Edit app/Services/MediaUploadService.php — remove ProcessImageJob::dispatch() line

# Delete processed output files
docker compose exec app rm -rf storage/app/media/outputs/*
```

**Git Commit — Phase 3:**
```
Files: app/Jobs/ProcessImageJob.php
       app/Jobs/ResizeImageJob.php
       app/Jobs/GenerateThumbnailJob.php
       app/Jobs/OptimizeImageJob.php
       app/Services/ImageProcessingService.php
       app/Services/MediaUploadService.php   (updated: adds dispatch)
       config/horizon.php                    (updated: queue workers)
       tests/Unit/ImageProcessingServiceTest.php
       tests/Unit/Jobs/ResizeImageJobTest.php
       tests/Unit/Jobs/GenerateThumbnailJobTest.php
       tests/Unit/Jobs/OptimizeImageJobTest.php
       tests/Feature/MediaQueueTest.php
Message: "feat: add image processing job chain with Horizon queue configuration"
```

---

## Phase 4 — Broadcasting & Real-Time UI (Vertical Slice 3)

**Goal:** UI updates in real-time as each job step completes. No page refresh required.

### Tasks

#### 4.1 Events
- [ ] Create `MediaProcessingStarted` — implements `ShouldBroadcast`
  - Broadcast on: `new PrivateChannel('media.' . $this->media->uuid)`
  - Payload: `status`, `filename`, `started_at`
- [ ] Create `MediaStepCompleted` — implements `ShouldBroadcast`
  - Payload: `step`, `progress`, `output_path`
- [ ] Create `MediaProcessingCompleted` — implements `ShouldBroadcast`
  - Payload: `status`, `outputs`, `completed_at`
- [ ] Create `MediaProcessingFailed` — implements `ShouldBroadcast`
  - Payload: `step`, `error`, `failed_at`
- [ ] Write unit tests with `Event::fake()` to verify events fired from correct job steps

#### 4.2 Channel Authentication
- [ ] Register private channel in `routes/channels.php`
- [ ] Auth callback: verify `Media` belongs to authenticated user via UUID
- [ ] Write feature test: authenticated owner → authorised, other user → denied

#### 4.3 Livewire Components — Broadcasting Integration
- [ ] Update `MediaUploader` component:
  - After successful upload, subscribe to `private-media.{uuid}` via `#[On]`
  - React to `MediaProcessingStarted`, `MediaStepCompleted`, `MediaProcessingCompleted`, `MediaProcessingFailed`
  - Update progress bar and status display reactively
- [ ] Create `MediaLibrary` Livewire component:
  - List all media for authenticated user
  - Show current status, progress, output thumbnails
  - Subscribe to channels for any in-progress items

#### 4.4 Frontend Assets
- [ ] Install Laravel Echo and Soketi client: `npm install laravel-echo pusher-js`
- [ ] Configure Echo in `resources/js/bootstrap.js` to connect to Soketi
- [ ] Verify WebSocket connection established on page load (browser devtools)

#### 4.5 Soketi Verification
- [ ] Verify Soketi receives connections from browser
- [ ] Verify Laravel can publish events to Soketi
- [ ] Verify browser receives events from Soketi channel subscription

**Exit Criterion:**
- Upload an image, observe UI updating in real-time without page refresh
- Progress bar advances through each step: resize → thumbnail → optimize
- On completion: output images displayed in UI
- On failure: error message displayed in UI
- Second browser tab / different user cannot receive events for another user's media
- All tests pass: `php artisan test`

### Human Review Checkpoint — Phase 4
**Before running checks: reset to a clean state.**
```bash
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan queue:flush
```
**You must personally verify each item below before Phase 5 begins. This is the most critical checkpoint — broadcasting failures are subtle.**

| Check | Command / Action | Expected Result |
|---|---|---|
| WebSocket connection established | Open browser devtools → Network → WS tab, load any authenticated page | WebSocket connection to `ws://localhost:6001` shows `101 Switching Protocols` |
| Channel subscription logged | In browser console after upload | `Subscribed to channel: private-media.{uuid}` (Echo debug log) |
| Real-time progress | Upload image, watch upload page without refreshing | Progress bar advances through steps without any page refresh |
| `MediaProcessingStarted` received | Upload image, watch browser console | Event received on channel subscription |
| `MediaStepCompleted` received | Watch console during processing | Multiple events received, each with increasing `progress` value |
| `MediaProcessingCompleted` received | After processing finishes | Event received, output image URLs appear in UI |
| Failure event | Upload corrupt file | `MediaProcessingFailed` event received, error message displayed in UI |
| Channel auth blocks other users | Login as User B, manually subscribe to User A's channel UUID | Soketi returns `403 Forbidden` on auth request |
| No page refresh at any point | Observe browser URL bar and network tab during full flow | Zero full-page reloads after initial upload submission |
| Soketi event log | `docker compose logs soketi` | Shows connection events, channel subscriptions, published messages |
| Automated tests pass | `docker compose exec app php artisan test` | All tests green including event tests |

**Rollback Instructions — Phase 4:**
```bash
# Remove broadcast event classes
git checkout HEAD -- app/Events/

# Remove listener
git checkout HEAD -- app/Listeners/

# Revert job classes to not fire events (remove event dispatch from failed() and handle())
git checkout HEAD -- app/Jobs/

# Revert Livewire components to Phase 2 state (remove #[On] listeners)
git checkout HEAD -- app/Livewire/

# Remove channel registration
git checkout HEAD -- routes/channels.php

# Remove Echo configuration from JS bootstrap
git checkout HEAD -- resources/js/bootstrap.js

# Uninstall Echo and pusher-js if needed
docker compose exec app npm uninstall laravel-echo pusher-js
docker compose exec app npm run build
```

**Git Commit — Phase 4:**
```
Files: app/Events/MediaProcessingStarted.php
       app/Events/MediaStepCompleted.php
       app/Events/MediaProcessingCompleted.php
       app/Events/MediaProcessingFailed.php
       app/Listeners/BroadcastMediaEvent.php
       app/Jobs/ResizeImageJob.php          (updated: fires events)
       app/Jobs/GenerateThumbnailJob.php    (updated: fires events)
       app/Jobs/OptimizeImageJob.php        (updated: fires events)
       app/Jobs/ProcessImageJob.php         (updated: fires started event)
       app/Livewire/MediaUploader.php       (updated: #[On] listeners)
       app/Livewire/MediaLibrary.php
       resources/views/livewire/media-uploader.blade.php  (updated: progress bar)
       resources/views/livewire/media-library.blade.php
       resources/js/bootstrap.js            (updated: Echo config)
       routes/channels.php
       tests/Unit/Events/MediaEventsTest.php
       tests/Feature/BroadcastChannelTest.php
Message: "feat: add real-time broadcasting — events, private channels, Livewire integration"
```

---

## Phase 5 — Integration & Hardening

**Goal:** Full system works reliably under edge cases. OOP boundaries reviewed. Tests comprehensive.

### Tasks

#### 5.1 Edge Case Handling
- [ ] Upload non-image file (PDF, executable) → validation error, no record created
- [ ] Upload oversized file (>10MB) → validation error
- [ ] Upload image below minimum dimensions → validation error
- [ ] Simulate job timeout → `failed()` method fires, UI notified
- [ ] Kill worker mid-processing → job returns to queue, retried
- [ ] Concurrent uploads from same user → all process independently

#### 5.2 OOP Audit
- [ ] Review: are controllers thin? No business logic in HTTP layer?
- [ ] Review: does each job have single responsibility?
- [ ] Review: does `ImageProcessingService` depend on abstractions, not concrete classes?
- [ ] Review: do events carry data only, no logic?
- [ ] Review: do listeners handle broadcasting only, no domain logic?

#### 5.3 Security Review
- [ ] Confirm MIME type validation rejects disguised files (check actual file header, not extension)
- [ ] Confirm media endpoints authorise ownership before serving/deleting
- [ ] Confirm private channels require valid session
- [ ] Confirm stored filenames are system-generated (no user-controlled path components)

#### 5.4 Test Coverage Completion
- [ ] Unit tests: all service methods, all job methods, all model methods
- [ ] Feature tests: all HTTP endpoints, all auth scenarios
- [ ] Integration test: full upload → process → broadcast → UI update flow

**Exit Criterion:**
- All edge cases handled gracefully with appropriate user feedback
- OOP audit passed — no violations of layer responsibilities
- Security review passed
- `php artisan test` — all tests green
- No `TODO` or stub code remaining

### Human Review Checkpoint — Phase 5
**You must personally verify each item below before Phase 6 begins.**

| Check | Command / Action | Expected Result |
|---|---|---|
| All edge cases tested manually | Upload PDF, oversized file, tiny image, corrupt file | Each returns correct error, no unhandled exceptions |
| Controller LOC check | Review `MediaController` — count lines of logic | No database queries, no image operations, no job dispatching directly — only delegates |
| Service layer audit | Review `MediaUploadService` — does it call Storage and dispatch jobs? | Yes. Does it also handle HTTP concerns (request, response)? No |
| Job single responsibility | Review each job class | Each job does exactly one processing operation, nothing else |
| Event purity | Review each event class | No methods beyond constructor and `broadcastWith()` / `broadcastOn()` |
| Listener purity | Review `BroadcastMediaEvent` | Only calls broadcast — no domain logic |
| MIME type spoofing test | Rename a `.txt` file to `.jpg`, attempt upload | Rejected — MIME validated against file header not extension |
| Ownership enforcement | As User B, attempt `DELETE /media/{uuid}` for User A's media | 403 Forbidden returned |
| Full test suite | `docker compose exec app php artisan test --coverage` | All tests pass; review coverage report for gaps |
| No TODOs remaining | `grep -r "TODO\|FIXME\|HACK" app/` | Zero results |

**Rollback Instructions — Phase 5:**
Phase 5 is hardening only — no new architecture. Rollback means reverting specific fixes:
```bash
# Revert any specific file that introduced a regression
git diff HEAD app/Http/Controllers/MediaController.php  # inspect first
git checkout HEAD -- app/Http/Controllers/MediaController.php  # then revert if needed

# If edge case handling broke existing tests, revert and re-approach
git stash   # save current work
docker compose exec app php artisan test   # confirm baseline
git stash pop   # restore work
```

**Git Commit — Phase 5:**
```
Files: app/Http/Controllers/MediaController.php   (if hardened)
       app/Services/MediaUploadService.php         (if hardened)
       app/Http/Middleware/MaxConcurrentUploads.php (if added)
       tests/Feature/EdgeCaseTest.php
       tests/Feature/SecurityTest.php
Message: "hardening: edge case handling, OOP audit fixes, security validation, test coverage"
```

---

## Phase 6 — Demo Preparation

**Goal:** Reproducible, impressive demo from cold start.

### Tasks

#### 6.1 Database Seeders
- [ ] `UserSeeder`: create demo user (credentials in `.env.example`)
- [ ] `MediaSeeder`: create sample media records in various states (pending, processing, completed, failed)

#### 6.2 Demo Script
- [ ] Verify `docker compose up` → all services healthy in < 2 minutes
- [ ] Verify Horizon dashboard accessible at `/horizon`
- [ ] Verify full upload → real-time processing → completion flow works cleanly
- [ ] Prepare 3 test images: small (fast), large (slow, good for demonstrating async), corrupt (demonstrates failure handling)

#### 6.3 Documentation
- [ ] Update `README.md` with: prerequisites, startup steps, demo credentials, architecture summary
- [ ] Ensure `CLAUDE.md` reflects final project structure

#### 6.4 CI — Automated Test Run on Push
- [ ] Create `.github/workflows/tests.yml`
- [ ] Workflow: on push to `main` → spin up MySQL + Redis services → `composer install` → `php artisan test`
- [ ] Verify workflow passes on GitHub before tagging `v1.0.0`

**Exit Criterion:**
- New terminal, `git clone` + `docker compose up` → running system
- Demo flows rehearsed and working
- `README.md` sufficient for examiner to run the project independently

### Human Review Checkpoint — Phase 6
**Final gate. Nothing ships without passing this checkpoint.**

| Check | Command / Action | Expected Result |
|---|---|---|
| Cold start from clone | Fresh terminal: `git clone` → `cp .env.example .env` → `docker compose up -d` → `docker compose exec app php artisan migrate --seed` | All services healthy, app accessible within 2 minutes |
| Demo credentials work | Login with seeded demo user credentials from `.env.example` | Login succeeds |
| Full demo script | Execute every step in `DELIVERABLES.md` Section 3 in order | Every step produces the expected result |
| Horizon visible | Navigate to `/horizon` with demo user | Dashboard renders, shows worker activity |
| README self-sufficient | Read `README.md` only, follow its instructions on a clean machine | Project runs without asking any questions |
| Final test suite | `docker compose exec app php artisan test` | All tests green on clean database |
| No secrets committed | `git log --all --full-history -- .env` | `.env` never appears in git history |
| `.env.example` complete | Every variable in `.env` exists in `.env.example` with a safe default or documented placeholder | No undocumented variables |

**Rollback Instructions — Phase 6:**
```bash
# If seeder corrupts database state
docker compose exec app php artisan migrate:fresh --seed   # wipe and re-seed

# If README instructions don't work on clean machine, fix README — not the code
```

**Git Commit — Phase 6:**
```
Files: database/seeders/DatabaseSeeder.php
       database/seeders/UserSeeder.php
       database/seeders/MediaSeeder.php
       README.md
       CLAUDE.md
       .env.example   (final, all variables present)
Message: "chore: add demo seeders, finalise README and environment documentation"
```

**Final tag after Phase 6 passes:**
```bash
git tag -a v1.0.0 -m "MediaFlow v1.0.0 — assignment submission"
```

---

## Increment Summary

> **Phases are strictly sequential.** No phase begins until its predecessor's Human Review Checkpoint is fully passed. There is no parallelism.

| Phase | Focus | Key Exit Criterion | Depends On |
|---|---|---|---|
| 0 | Inception | All decisions made, specs written | — |
| 1 | Infrastructure | `docker compose up` → authenticated app | Phase 0 |
| 2 | Core Domain | Upload → DB record → file on disk | Phase 1 |
| 3 | Queue Jobs | Upload → job chain → Horizon shows progress | Phase 2 |
| 4 | Broadcasting | Upload → real-time UI updates | Phase 3 |
| 5 | Hardening | Edge cases handled, OOP audit passed | Phase 4 |
| 6 | Demo | Cold start demo works reproducibly | Phase 5 |
