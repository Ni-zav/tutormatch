# Deployment

TutorMatch is intended to stay cheap and boring at MVP scale: static frontend, Laravel API on a small VPS, PostgreSQL on the same VPS, database queue first, and Caddy for HTTPS. Move individual pieces out only when traffic or operational risk justifies it.

## Recommended Cheap Stack

- Frontend: Cloudflare Pages, Netlify, or the included static Caddy container.
- Backend: Laravel container on a small VPS.
- Database: PostgreSQL on the same VPS at first.
- Queue: Laravel database queue worker.
- HTTPS/reverse proxy: Caddy.
- CI: GitHub Actions running backend tests, frontend lint/build, and mobile type checks.

## Local Prod-Like Compose

Copy `backend/.env.example` to `backend/.env`, set a real `APP_KEY`, database password, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, and `FRONTEND_ALLOWED_ORIGINS`.

```bash
docker compose -f docker-compose.prod.example.yml build
docker compose -f docker-compose.prod.example.yml up -d postgres
docker compose -f docker-compose.prod.example.yml run --rm backend php artisan migrate --force
docker compose -f docker-compose.prod.example.yml up -d
```

Use `GET /api/health` for uptime checks. It returns `200` only when Laravel can reach the database, and returns `503` with a minimal degraded payload if the database check fails. Laravel's built-in `/up` route is also exposed through the example Caddy config.

## Production Environment

Required backend variables:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY`
- `APP_URL`
- `FRONTEND_ALLOWED_ORIGINS`
- `API_TOKEN_TTL_MINUTES=1440` or a shorter value for stricter production sessions
- `DB_CONNECTION=pgsql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`
- `AI_PROVIDER=mock` unless a real provider is intentionally configured
- `AI_TIMEOUT_SECONDS=20`
- `AUDIT_LOG_RETENTION_DAYS=365`
- `MESSAGE_DRAFT_RETENTION_DAYS=180`
- `FINALIZED_REQUEST_RETENTION_DAYS=730`
- `INACTIVE_TUTOR_RETENTION_DAYS=730`

For real AI, set `AI_PROVIDER=openai`, `OPENAI_API_KEY`, `OPENAI_MODEL`, and `AI_TIMEOUT_SECONDS`. Do not make paid AI required for core matching.

## Release Steps

1. Pull the new commit on the VPS.
2. Rebuild containers.
3. Run `php artisan migrate --force`.
4. Restart `backend`, `queue`, `frontend`, and `caddy`.
5. Check `/api/health`.
6. Log in with a non-demo production user and verify request, match, and workflow pages.

## Queue And Scheduler

Run one queue worker for now:

```bash
php artisan queue:work --tries=3 --timeout=60
```

Queued match generation uses the same database queue. Keep the worker supervised in production; otherwise `POST /api/requests/{id}/generate-matches?async=true` will leave requests in `matching` until a worker processes the job.

Run the retention cleanup manually first:

```bash
php artisan tutormatch:prune-retention --dry-run
php artisan tutormatch:prune-retention
```

When retention windows are approved, schedule the command daily through cron or Laravel Scheduler. The command removes audit logs older than `AUDIT_LOG_RETENTION_DAYS`, message drafts older than `MESSAGE_DRAFT_RETENTION_DAYS`, and finalized student requests older than `FINALIZED_REQUEST_RETENTION_DAYS`. It anonymizes inactive tutor profiles older than `INACTIVE_TUTOR_RETENTION_DAYS` instead of deleting tutor records.

## Backups

For the single-VPS PostgreSQL setup, schedule a daily dump and keep at least 7 daily and 4 weekly backups off the VPS.

```bash
pg_dump "$DATABASE_URL" | gzip > "tutormatch-$(date +%Y%m%d).sql.gz"
```

Restore drill:

```bash
gunzip -c tutormatch-YYYYMMDD.sql.gz | psql "$DATABASE_URL"
php artisan migrate --force
```

## Rollback

Keep the previous image or commit available. If a deploy fails after migrations, prefer a forward fix unless the migration is known reversible and no production writes happened. Always snapshot the database before risky schema changes.

## Upgrade Points

- Move PostgreSQL to managed hosting when backups, uptime, or storage become operational risk.
- Move queues to Redis when database queue latency becomes visible.
- Replace demo bearer tokens with Sanctum or another production-grade auth flow before real users.
- Add object storage only when uploaded documents or media become part of the workflow.
- Add alerting and review procedures around audit logs before production use with real student, parent, or tutor data.
