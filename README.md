# Image Processing Media App

I built this image processing media app for a technical assignment focused on queued background processing in Laravel, with Claude Code helping during development.

The application lets an authenticated user upload an image, get an immediate response, and then watch the processing continue in the background. The interface updates in real time as the job moves through each step.

I considered a CSV import pipeline and a few other ideas at the start. I chose an image processing media app because it suited the assignment, fit the time available, and made the asynchronous behaviour easier to show. It also gave me room to cover queue priorities, failure handling, retries, WebSocket updates, and OOP structure in Laravel.

## What This Project Demonstrates

- Laravel queue processing with Redis and Horizon
- Real-time UI updates with Soketi, Laravel Broadcasting, Echo, and Livewire
- Multiple named queues with different priorities
- A service layer that keeps business logic out of controllers
- Failed job handling and manual retry
- Docker-based reproducibility for local setup and evaluation
- Automated feature and unit tests around the queue and upload flow

## Core Features

- User authentication with Laravel Breeze
- Image upload with server-side validation
- Supported formats: JPEG, PNG, GIF, WebP
- Validation limits: max 10 MB, min 100x100, max 8000x8000
- Background processing pipeline for resize, thumbnail generation, and optimisation
- Live processing state in the UI: `pending`, `processing`, `completed`, `failed`
- Thumbnail preview for completed items
- Retry path for failed items from both the uploader and the media library
- Horizon dashboard for queue visibility and failure inspection

## Tech Stack

| Layer | Choice |
|---|---|
| Language | PHP 8.3 |
| Framework | Laravel 13.1.1 |
| UI | Blade + Livewire 3.7.11 + Tailwind 3.4.19 |
| Auth | Laravel Breeze 2.4.1 |
| Queue | Redis 7.2 |
| Queue Monitor | Laravel Horizon 5.45.4 |
| Image Processing | Intervention Image 3.11.7 + Imagick |
| Broadcasting | Laravel Broadcasting |
| WebSocket Server | Soketi 1.6 |
| Client WS | Laravel Echo 2.3.1 + pusher-js 8.4.3 |
| Database | MySQL 8.4 |
| Runtime | Docker Compose (Vite 8.0.2) |

## How The Flow Works

1. The user uploads an image from the upload page.
2. `MediaController` stays thin and delegates the work to `MediaUploadService`.
3. The service validates the file, stores it on the `media` disk, creates the `media` record, and dispatches `ProcessImageJob`.
4. `ProcessImageJob` is delayed by 5 seconds so the browser has time to subscribe to the private Echo channel before processing events are sent.
5. The main job marks the media as `processing`, fires `MediaProcessingStarted`, and then dispatches:
   - `ResizeImageJob` on `media-standard`
   - `GenerateThumbnailJob` on `media-critical`
6. Once both batch jobs finish, `OptimizeImageJob` runs on `media-low`.
7. Each step updates the database and broadcasts progress.
8. The Livewire components react to those events and update the page without a refresh.

## Queue Design

| Queue | Purpose |
|---|---|
| `media-critical` | Thumbnail generation for fast UI feedback |
| `media-standard` | Main processing and resize work |
| `media-low` | Final optimisation |

All jobs use:

- 3 attempts
- exponential backoff: `10`, `30`, and `60` seconds
- timeout: `120` seconds

## Architecture Notes

I tried to keep the structure simple and easy to explain:

- Controllers handle HTTP concerns only
- `MediaUploadService` owns upload and dispatch logic
- `ImageProcessingService` owns image transformations
- Each queue job has one processing responsibility
- Events carry state changes
- Broadcasting is scoped per media UUID through `private-media.{uuid}`

This helped keep the HTTP layer, the queue workers, and the browser side clearly separate.

## Why I Used Claude Code

This project was developed with Claude Code.

I mainly used it for:

- exploring and comparing project options before implementation
- scaffolding infrastructure and repetitive code
- reviewing architecture trade-offs
- generating and refining tests
- debugging queue, serialization, and broadcasting edge cases
- drafting and revising project documentation

I still reviewed the suggestions myself and made the final decisions.

## My Development Approach

I started by comparing a few project ideas before writing any code. After that, I worked in phases:

1. Infrastructure and Docker setup
2. Upload flow and domain model
3. Queue jobs and image processing
4. Broadcasting and real-time UI
5. Hardening, edge cases, and tests
6. Demo preparation and documentation

These documents were part of that process:

- `docs/PROJECT_SPEC.md`
- `docs/IMPLEMENTATION_PLAN.md`
- `docs/DECISIONS.md`
- `docs/DELIVERABLES.md`
- `docs/SEED_CREDENTIALS.md`

## Key Things I Ran Into

These were the main issues I ran into while building it:

1. `Bus::batch()` did not keep the queue assignment the way I first expected.
Jobs that used `$this->onQueue(...)` in their constructors still landed on the `default` queue when dispatched inside a batch. I fixed that by calling `->onQueue(...)` directly at the `Bus::batch([...])` call site.

2. `readonly` and `SerializesModels` did not work well together here.
I first kept the `Media` model on `ProcessImageJob` as `readonly`, but PHP 8.3 queue unserialization broke because Laravel rehydrates the model after serialization. I ended up removing `readonly` from that property.

3. Fast queue workers could finish before the browser had fully subscribed.
On a fast local machine, Soketi and Horizon could finish processing before the browser had subscribed to the private channel. I added a 5-second dispatch delay and kept a Livewire polling fallback so the UI could still catch up if a broadcast was missed.

I wrote these up in more detail in `docs/DECISIONS.md`.

## Local Setup

### Prerequisites

- Docker
- Docker Compose plugin

PHP, Composer, Node, and npm all run inside the container, so the host machine only needs Docker.

### First-Time Setup

```bash
git clone https://github.com/Aliy7/media-app.git
cd media-app
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm run build
```

After that, open:

- App: `http://localhost`
- Horizon: `http://localhost/horizon`

If port `80`, `3306`, or `6379` is already in use on your machine, change `APP_PORT`, `FORWARD_DB_PORT`, or `FORWARD_REDIS_PORT` in `.env` before running `docker compose up`.

## Demo Credentials

Seeded development users are documented in `docs/SEED_CREDENTIALS.md`.

Example login:

- Email: `alice@mediaflow.test`
- Password: `password`

In local development, any authenticated user can access `/horizon`.

## Demo Walkthrough

Prepared demo files are in the `demo/` directory.

### Happy path

1. Log in with one of the seeded users.
2. Upload `demo/demo-small.jpg` — small JPEG (800×600, 34 KB). Completes quickly and confirms the full pipeline.
3. Upload `demo/demo-large.jpg` — large JPEG (4000×3000, 5.6 MB). Processing takes longer; watch each step update in real time.
4. Open `/horizon` and inspect queue activity across `media-critical`, `media-standard`, and `media-low`.

### Validation rejection tests

These files are designed to be rejected at the upload boundary — none of them should reach the queue.

| File | Size | Dimensions | Expected behaviour |
|---|---|---|---|
| `demo/demo-corrupt.jpg` | ~2 KB | 300×300 | Valid JPEG header, corrupted pixel data — passes upload validation, dispatched to queue, fails during processing and shows the failed state in the UI |
| `demo/demo-too-large.jpg` | 22.4 MB | 5000×4000 | Rejected immediately at upload — exceeds the 10 MB limit |
| `demo/demo-too-small.jpg` | 754 B | 50×50 px | Rejected immediately at upload — below the 100×100 px minimum |

`demo-corrupt.jpg` is useful for demonstrating the queue failure path and the retry button. The other two show instant validation rejection with no job dispatched.

## Useful Commands

```bash
# Run the test suite
docker compose exec app php artisan test

# Verify the broadcast pipeline to Soketi
docker compose exec app php artisan mediaflow:verify-broadcast

# Dispatch sample jobs to observe Horizon queue behaviour
docker compose exec app php artisan media:benchmark 5

# Watch Soketi logs during a manual upload test
docker compose logs -f soketi
```

## Testing

The test suite covers:

- HTTP upload flow
- authentication and authorization boundaries
- queue dispatch behaviour
- queue retry behaviour
- broadcast-related behaviour
- image processing services
- media model behaviour
- edge cases and security checks

Run tests with:

```bash
docker compose exec app php artisan test
```

## Submission Evidence

Since the assignment asks for evidence of agentic development, I included the following:

- `transcripts/` contains the AI conversation history used during the assignment
- `export-transcript.sh` exports Claude Code session data into a consolidated markdown transcript
- `docs/DECISIONS.md` records the important technical decisions and trade-offs

## Scope Boundaries

To keep the assignment focused, I kept these items out of scope:

- video processing
- cloud storage such as S3 or MinIO
- public sharing or CDN delivery
- OAuth login
- image editing features such as crop or rotate
- a separate SPA frontend

## Final Note

The aim of this project was not just to use queues in Laravel, but to show that I understand why they are useful, how the main parts fit together, and where AI help was useful versus where my own judgement mattered.

