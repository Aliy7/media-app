# Demo Images

Three prepared images for the live demonstration. Use them in the order described below.

| File | Size | Dimensions | Purpose |
|------|------|-----------|---------|
| `demo-small.jpg` | ~34 KB | 800×600 | Happy path — fast processing, confirms the full flow works |
| `demo-large.jpg` | ~5.6 MB | 4000×3000 | Async demo — slow enough to observe each job step live |
| `demo-corrupt.jpg` | ~8 KB | — | Failure path — real PDF renamed `.jpg`, fails MIME validation immediately |

## Recommended demo sequence

1. **Login** as `alice@mediaflow.test` / `password` (see `docs/SEED_CREDENTIALS.md`)
2. **Upload `demo-small.jpg`** — confirm the full pipeline completes and a thumbnail appears
3. **Upload `demo-large.jpg`** — open browser devtools → Network → WS tab first, then upload; watch WebSocket frames arrive as each job step fires
4. **Open `/horizon`** — show job throughput, queue names, completed jobs
5. **Upload `demo-corrupt.jpg`** — observe the MIME error returned immediately, no job dispatched

## Regenerating the images

If the large binary is missing after a fresh clone:

```bash
docker compose exec app php artisan db:seed  # re-seeds users and media if needed
```

Then re-run the PHP GD snippet in the project README to regenerate the image files.
