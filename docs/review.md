# Review Notes
## Phase 2/3 Code Review Checklist

This document captures what has been reviewed so far and what to manually verify next.

---

## Reviewed Changes

### `MediaController`
- Request validation includes: MIME type, max size, and image dimensions.
- `destroy()` delegates file deletion to a dedicated helper method and authorises ownership.

### `MediaUploader` (Livewire)
- Validation rules aligned with the controller so non-images do not reach the service layer.
- Upload action delegates to the service layer; UI shows preview before submission.

---

## Current Status (Phase 3)

The queue/image-processing pipeline is not started yet:
- No `ProcessImageJob` / step jobs exist.
- No `ImageProcessingService` exists.
- No processing events/listeners exist.
- No Horizon queue priority configuration (`media-critical`, `media-standard`, `media-low`) implemented.

---

## Manual Review Before Starting Phase 3

### `MediaUploadService`
- Confirm dual validation (controller + service) is intentional because the service is called from both HTTP and Livewire.
- Confirm corrupt image handling is explicit (e.g. `getimagesize()` failures should surface as a validation error).
- Confirm the stored path and delete path are consistent (`stored_filename` + disk root).

### `Media` model
- Confirm guarded vs fillable fields match the intended write paths.
- Confirm casts are correct (especially `outputs`, `progress`, and any future enum handling for `status`).

### `MediaPolicy`
- Confirm `view`/`delete` ownership checks are correct and policy discovery is working.

### `routes/web.php`
- Confirm the intended UX: Livewire uploads call the service directly; `POST /media` exists for HTTP upload usage.

### `InvalidMediaException`
- Confirm error messages are suitable to surface to end-users (they become UI validation/errors).
