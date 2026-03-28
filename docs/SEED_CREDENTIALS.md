# Seed User Credentials

These are **development-only** fake credentials created by `php artisan db:seed`.
All accounts share the password `password`.

> These credentials are intentionally public — they exist only in the local dev environment and contain no real personal data.

## Demo accounts

| # | Name | Email | Password |
|---|------|-------|----------|
| 1 | Alice Adams | alice@mediaflow.test | password |
| 2 | Bob Baker | bob@mediaflow.test | password |
| 3 | Carol Chen | carol@mediaflow.test | password |
| 4 | Dave Dixon | dave@mediaflow.test | password |
| 5 | Eve Evans | eve@mediaflow.test | password |
| 6 | Frank Foster | frank@mediaflow.test | password |
| 7 | Grace Green | grace@mediaflow.test | password |
| 8 | Henry Hill | henry@mediaflow.test | password |
| 9 | Iris Ibarra | iris@mediaflow.test | password |
| 10 | Jack Jones | jack@mediaflow.test | password |

## Original test account

| Name | Email | Password |
|------|-------|----------|
| Test User | test@example.com | password |

## Seeded media

Each demo user receives **5 media items**:

| Status | Count | Notes |
|--------|-------|-------|
| Completed | 3 | Real GD-generated images; thumbnails render in the UI |
| Pending | 1 | Source file on disk; pipeline not yet run |
| Failed | 1 | Source file on disk; simulated pipeline error |

Formats cycle across records: JPEG → PNG → GIF → WebP → repeat.

## Re-seeding

```bash
# Fresh seed (wipes and re-seeds)
php artisan migrate:fresh --seed

# Seed only (idempotent — uses firstOrCreate for users)
php artisan db:seed
```
