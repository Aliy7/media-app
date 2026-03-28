# Project Specification
## Media-App — Asynchronous Image Processing Platform

**Version:** 1.0
**Date:** 2026-03-23
**Authors:** A. Muktar + Claude Code Agent
**Methodology:** Iterative Incremental + Kanban

---

## 1. Problem Statement

Modern web applications frequently require resource-intensive operations — image resizing, thumbnail generation, optimisation — that are unsuitable for synchronous HTTP request/response cycles. Executing these operations synchronously degrades user experience, blocks server resources, and introduces timeout risks.

This project addresses that problem by implementing a fully asynchronous image processing pipeline using Laravel's queue system. It is built as a technical assignment to demonstrate: (a) mastery of queued background processing in a production-realistic context, (b) real-time user feedback via WebSocket broadcasting, and (c) deliberate object-oriented design within an MVC framework.

**End user:** An authenticated web user who uploads images and expects immediate acknowledgement of receipt, followed by observable processing progress and access to processed outputs — without waiting for synchronous operations or refreshing the page.

**Success:** The system demonstrates that the HTTP layer, queue workers, and browser client are decoupled processes communicating asynchronously — observable, testable, and reproducible.

---

## 2. Project Objectives

1. Demonstrate mastery of Laravel's queue system through a production-realistic implementation
2. Implement observable asynchronous processing with real-time UI feedback
3. Apply OOP principles within Laravel's MVC framework through deliberate architectural layering

> **Note:** Reproducibility via Docker is an infrastructure constraint, not an objective — see Section 12.

---

## 3. Scope

### In Scope
- User authentication (register, login, logout)
- Image upload with server-side validation
- Asynchronous job chain: resize → thumbnail → optimise
- Multiple output dimensions per uploaded image
- Real-time processing status updates via WebSocket broadcasting
- Failed job handling with user notification
- Queue monitoring via Laravel Horizon
- Dockerised full-stack environment

### Out of Scope
- Video processing of any kind
- Cloud storage (S3/MinIO) — local filesystem only
- Third-party OAuth (Google, GitHub)
- Image editing (crop, rotate, filter)
- Public sharing or CDN delivery
- Mobile application

---

## 4. Finalised Tech Stack

> **Version Policy:** All versions are pinned minimums. Never downgrade below the specified version. Upgrade only if a blocking bug requires it, and document the reason.

| Layer | Technology | Pinned Version | Justification |
|---|---|---|---|
| Language | PHP-FPM | **8.3.x** | Broad ecosystem support; matches the project Docker base image; supports all required packages and extensions (Imagick, Redis, GD) |
| Framework | Laravel | **13.x** (≥ 13.0) | Slim skeleton, first-class queue/broadcast support; current LTS-track release |
| Dependency Manager | Composer | **2.9.x** | Required for Laravel 13 install resolution |
| Authentication | Laravel Breeze | **2.x** | Rapid auth scaffold, Blade-compatible, ships Tailwind |
| Queue Driver | Redis | **7.2.x** | Production-grade, non-blocking pop, required for Horizon |
| Queue Monitor | Laravel Horizon | **5.x** | Real-time queue observability; requires Redis |
| Image Processing | Intervention Image | **3.x** (^3.0) | Fluent image API, supports Imagick driver |
| Image Driver | Imagick (PHP extension) | **3.x** | Superior resampling quality over GD; see Open Questions |
| Storage | Laravel Filesystem | built-in | Abstracted, S3-ready by design |
| WebSockets | Soketi | **1.6.x** | Self-hosted, Pusher-protocol compatible, no external dependency |
| Broadcasting | Laravel Broadcasting | built-in | Private channels, native event integration |
| Frontend Reactivity | Livewire | **3.x** (^3.0) | Reactive UI with native Echo/Broadcasting integration |
| JavaScript Runtime | Node.js | **24.x** | Active release; required for Vite asset compilation |
| Package Manager | NPM | **11.x** | Ships with Node 24 |
| WebSocket Client | Laravel Echo | **2.x** | Browser WebSocket client for Soketi/Pusher |
| WebSocket Transport | pusher-js | **8.x** | Required by Echo for Pusher-protocol connection |
| CSS Framework | Tailwind CSS | **3.x** | Ships with Breeze; utility-first, no custom CSS needed |
| Database | MySQL | **8.4.x** | Familiar, deterministic SQL, sufficient for project scope |
| Design Pattern | OOP + MVC + Service Layer | — | Explicit separation of concerns |
| Infrastructure | Docker Compose | **2.x** (plugin) | Reproducible single-command startup; use `docker compose` not `docker-compose` |

---

## 5. System Architecture

### 5.1 Request Lifecycle

```
Browser
  └── HTTP POST /media/upload
        └── MediaController::store()              [thin — validates, delegates]
              └── MediaUploadService::handle()    [business logic]
                    ├── validates file (type, size, dimensions)
                    ├── stores original via Storage facade
                    ├── creates Media model record (status: pending)
                    ├── dispatches ProcessImageJob → Redis queue
                    └── returns Media (uuid, status)
                              ↑
              HTTP 201 + { uuid, status: 'pending' }
                              ↓
        Livewire component receives uuid → subscribes to private-media.{uuid}

                    [Worker process — separate from HTTP lifecycle]
                    ProcessImageJob
                              └── job chain:
                                    ├── ResizeImageJob         → fires MediaStepCompleted
                                    ├── GenerateThumbnailJob   → fires MediaStepCompleted
                                    ├── OptimizeImageJob       → fires MediaStepCompleted
                                    └── fires MediaProcessingCompleted
                                              └── Listener broadcasts to private channel
                                                    └── Livewire component updates DOM
```

### 5.2 OOP Layer Responsibilities

| Layer | Class | Responsibility |
|---|---|---|
| Controller | `MediaController` | Receive request, delegate to service, return response |
| Service | `MediaUploadService` | Orchestrate upload business logic |
| Service | `ImageProcessingService` | Encapsulate Intervention Image operations |
| Model | `Media` | Data encapsulation, relationships, state transitions |
| Job | `ProcessImageJob` | Orchestrator — builds and dispatches job chain |
| Job | `ResizeImageJob` | Single responsibility: resize to configured dimensions |
| Job | `GenerateThumbnailJob` | Single responsibility: fixed-size thumbnail |
| Job | `OptimizeImageJob` | Single responsibility: compress, strip metadata |
| Event | `MediaProcessingStarted` | Fired when job chain begins |
| Event | `MediaStepCompleted` | Fired after each job step with progress data |
| Event | `MediaProcessingCompleted` | Fired on full chain completion |
| Event | `MediaProcessingFailed` | Fired on job failure |
| Listener | `BroadcastMediaEvent` | Broadcasts events to private user channel |
| Component | `MediaUploader` | Livewire: handles upload form, subscribes to channel |
| Component | `MediaLibrary` | Livewire: displays media list and status |

### 5.3 Design Principles Applied (SOLID)

- **SRP — Single Responsibility:** Each job class performs exactly one processing step; controllers handle only HTTP concerns; services own business logic
- **OCP — Open/Closed:** `ImageProcessingService` accepts dimension configuration; new output sizes extend behaviour without modifying existing job classes
- **LSP — Liskov Substitution:** Jobs implement `ShouldQueue`; any job can be substituted in the chain without breaking the orchestrator
- **ISP — Interface Segregation:** `ShouldBroadcast` applied only to events that broadcast; not imposed on all events
- **DIP — Dependency Inversion:** Controllers depend on `MediaUploadService` (injectable); jobs depend on `ImageProcessingService` (injectable) — no direct instantiation of concrete classes
- **Event-Driven Decoupling:** Jobs fire events; listeners handle broadcasting — jobs have no direct knowledge of the WebSocket layer

---

## 6. Database Schema

### `users` (Laravel default, extended by Breeze)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| email | varchar(255) unique | |
| password | varchar(255) | hashed |
| timestamps | | |

### `media`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK | belongs to users |
| uuid | char(36) unique | used in broadcast channel name |
| original_filename | varchar(255) | user-provided name |
| stored_filename | varchar(255) | system-generated name |
| mime_type | varchar(100) | validated on upload |
| file_size | bigint | bytes |
| status | enum | pending, processing, completed, failed |
| processing_step | varchar(100) | current step name (null if not processing) |
| progress | tinyint | 0–100 percentage |
| error_message | text nullable | populated on failure |
| outputs | json nullable | array of output file paths and dimensions |
| timestamps | | |

### `jobs` (Laravel default)
Managed by Laravel queue system. Stores pending/delayed jobs.

### `failed_jobs` (Laravel default)
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| uuid | varchar unique | |
| connection | text | queue connection name |
| queue | text | queue name |
| payload | longtext | serialised job |
| exception | longtext | failure reason |
| failed_at | timestamp | |

### `job_batches`
**Out of scope.** Job chaining is used exclusively — `Bus::batch()` is not implemented. This table will not be created.

---

## 7. Queue Architecture

### Queue Names and Priorities

| Queue | Priority | Workers | Purpose |
|---|---|---|---|
| `media-critical` | High | 2 | Thumbnails — fast user feedback |
| `media-standard` | Normal | 2 | Resize operations |
| `media-low` | Low | 1 | Optimisation, cleanup |

### Job Configuration

| Setting | Value | Reason |
|---|---|---|
| Max attempts | 3 | Transient failures (memory, I/O) are recoverable |
| Backoff | 10s, 30s, 60s (exponential) | Avoids thundering herd on failure |
| Timeout | 120 seconds | Sufficient for large images, prevents zombie jobs |
| `failOnTimeout` | true | Explicit failure over silent hang |

### Horizon Configuration
Horizon supervises workers via `config/horizon.php`. Worker counts and queue assignments are defined in code, not environment variables.

---

## 8. Broadcasting Architecture

### Channel
```
private-media.{media.uuid}
```

Private channel — requires signed authentication. Scoped to a single media item, not the user, preventing channel pollution.

### Channel Authentication
```
// routes/channels.php
Broadcast::channel('media.{uuid}', function (User $user, string $uuid) {
    return Media::where('uuid', $uuid)
                ->where('user_id', $user->id)
                ->exists();
});
```

### Events Broadcast

| Event | Channel | Payload |
|---|---|---|
| `MediaProcessingStarted` | `private-media.{uuid}` | `{ status, filename, started_at }` |
| `MediaStepCompleted` | `private-media.{uuid}` | `{ step, progress, output_path }` |
| `MediaProcessingCompleted` | `private-media.{uuid}` | `{ status, outputs, completed_at }` |
| `MediaProcessingFailed` | `private-media.{uuid}` | `{ step, error, failed_at }` |

---

## 9. Validation Rules

### Upload Validation
| Rule | Value |
|---|---|
| Allowed MIME types | image/jpeg, image/png, image/gif, image/webp |
| Maximum file size | 10MB |
| Maximum dimensions | 8000 × 8000 px |
| Minimum dimensions | 100 × 100 px |

### Processing Output Dimensions
| Output | Dimensions | Queue |
|---|---|---|
| Large | 1920 × 1080 (maintain aspect) | media-standard |
| Medium | 800 × 600 (maintain aspect) | media-standard |
| Thumbnail | 150 × 150 (crop centre) | media-critical |
| Optimised original | original dimensions, compressed | media-low |

---

## 10. Routes

```
GET    /                        → redirect to /dashboard
GET    /login                   → Breeze auth
POST   /login                   → Breeze auth
POST   /logout                  → Breeze auth
GET    /register                → Breeze auth
POST   /register                → Breeze auth

GET    /dashboard               → MediaLibrary Livewire component
GET    /media/upload            → MediaUploader Livewire component
POST   /media                   → MediaController::store()  → 201 + { uuid, status }
GET    /media/{uuid}            → MediaController::show()
DELETE /media/{uuid}            → MediaController::destroy()

GET    /horizon                 → Horizon dashboard (Horizon auth gate)

POST   /broadcasting/auth       → Laravel broadcast channel authentication
```

---

## 11. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Upload response time | < 500ms (job dispatched, not processed) |
| Job pickup latency | < 1 second from dispatch to worker pickup |
| Real-time update latency | < 2 seconds from job event to UI update |
| Maximum concurrent uploads | 5 per user (enforced via middleware) |
| Failed job visibility | Immediately visible in Horizon dashboard |
| Environment startup | `docker compose up` → running system in < 2 minutes |

---

## 12. Constraints and Assumptions

- PHP 8.3.x required — Intervention Image v3 and Laravel 13 require PHP ≥ 8.2; the project Docker image runs PHP 8.3.x
- Docker Engine ≥ 24.x and Docker Compose plugin ≥ 2.x must be installed on host
- Imagick PHP extension installed inside the Docker image — host machine does not need it
- Redis 7.2.x — required by Laravel Horizon; older Redis versions not supported by Horizon 5.x
- Soketi 1.6.x — compatible with Laravel Broadcasting Pusher driver via `PUSHER_HOST` override
- Node 24.x — required inside Docker for asset compilation only; not needed on host if building inside container
- **The system must be fully reproducible via a single command:** `docker compose up` → running app. No manual steps permitted beyond initial `cp .env.example .env`
- No production deployment in scope — local Docker environment only
- All `php artisan` commands run via Docker: `docker compose exec app php artisan ...`
- All `composer` and `npm` commands run inside the app container unless otherwise noted

### Branching Strategy

| Branch | Purpose |
|---|---|
| `main` | Stable, releasable code only. Each phase commit merges here after Human Review Checkpoint passes. |
| `phase/N-description` | One branch per phase (e.g., `phase/1-infrastructure`). Deleted after merge. |

No long-lived feature branches. No force pushes to `main`. Final submission tagged `v1.0.0` on `main`.

---

## 13. Open Questions

These are unresolved decisions that must be answered before or during Phase 1. Each has a recommended default if no answer is provided.

| # | Question | Impact | Recommended Default | Must Resolve By |
|---|---|---|---|---|
| ~~OQ-1~~ | ~~**Laravel 11 vs Laravel 12?**~~ | — | **RESOLVED:** Project runs on Laravel **13.x** (13.1.1). Breeze 2.4 and Horizon 5.x confirmed compatible. | ✅ Closed |
| ~~OQ-2~~ | ~~**PHP 8.3 vs 8.4?**~~ | — | **RESOLVED:** Project runs on PHP **8.3.x** (confirmed in Docker). This satisfies all package requirements and keeps Docker builds reliable. | ✅ Closed |
| OQ-3 | **Imagick fallback to GD?** If Imagick fails to install in the Docker image, should we fall back to GD or treat it as a blocker? | Image quality, Dockerfile | Treat as **blocker** — resolve Imagick install, do not silently downgrade to GD | Phase 1 exit |
| ~~OQ-4~~ | ~~Single queue vs three named queues?~~ | — | **RESOLVED:** Three named queues agreed in architecture. See Section 7. | ✅ Closed |
| OQ-5 | **Horizon auth gate — who is admin?** `/horizon` requires an auth gate. No admin role exists in schema. | Route protection, seeder | Use **email allowlist** in `HorizonServiceProvider` seeded via `.env` | Phase 1 exit |
| OQ-6 | **Demo environment — live or recorded?** Will the assessor run the project themselves or watch a live demo? | Demo script, seeder data requirements | Assume **assessor runs it themselves** — README must be self-sufficient | Phase 6 start |
| OQ-7 | **Soft deletes on `media` records?** Permanent delete loses processing history. Soft delete preserves audit trail. | Schema, controller destroy method | **No soft deletes** — adds scope complexity, not required for assignment objectives | Phase 2 start |
| OQ-8 | **Maximum file size — 10MB hard limit?** Very large images stress-test async clearly but slow the demo. | Validation rule, demo image selection | **10MB hard limit** — sufficient for demo, prevents timeout risk | Phase 2 start |
