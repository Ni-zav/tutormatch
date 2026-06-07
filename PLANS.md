# TutorMatch Ops Implementation Plan

## Current Objective

Build an interview-ready proof-of-concept for MindFlex-style tuition assignment operations using a Laravel/PHP API, SQL database, React + TypeScript coordinator dashboard, responsible AI-assisted workflow features, tests, and clear documentation. Add a small Expo tutor flow if time and tooling allow.

## Progress Checklist

### Phase 0 - Repository Setup And Context

- [x] Inspect repository root.
- [x] Create `archive/` folder.
- [x] Ensure `.gitignore` contains `archive/`.
- [x] Move TutorMatch Codex Goal Pack files into `archive/tutormatch-codex-goal-pack/`.
- [x] Read archived build, database/API, AI, and acceptance specs.
- [x] Create clean root `AGENTS.md`, then remove and ignore it before any git init per clean-history preference.
- [x] Create this public implementation plan.
- [x] Confirm local toolchain: PHP `8.5.7`, Composer `2.10.1`, Node `v24.15.0`, and npm `11.12.1` are available.
- [x] Enable PHP CLI extensions needed for Laravel and SQLite: `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, `sqlite3`, and `zip`.

### Phase 1 - Public Project Skeleton And Docs

- [x] Replace goal-pack README with project README.
- [x] Create `docs/` with initial architecture, API, database, matching, AI, domain research, and interview guide files.
- [x] Create `backend/`, `frontend/`, and `scripts/` structure.
- [x] Decide whether `mobile/` is feasible after checking Node/Expo tooling.

### Phase 2 - Laravel Backend API

- [x] Scaffold Laravel app in `backend/`.
- [x] Configure safe `.env.example` and SQLite-friendly local setup.
- [x] Add migrations for coordinators/users, tutors, subjects, levels, tutor subjects, tutor availability, student requests, assignments, applications, match results, and message drafts.
- [x] Add indexed fields for high-volume filters and matching.
- [x] Add Eloquent models and relationships.
- [x] Add seeders with fictional Singapore-flavored demo data.
- [x] Add `/api/health`.
- [x] Add dashboard, request, tutor, assignment application, match explanation, and message draft routes.
- [x] Add form requests, API resources, controllers, and service classes.

### Phase 3 - Matching And AI Services

- [x] Implement deterministic weighted matching: subject 30, level 20, location/mode 15, budget 15, availability 10, tutor type 5, history 5.
- [x] Persist match results with score breakdown and deterministic explanation.
- [x] Implement AI explanation service abstraction.
- [x] Implement mock fallback when no API key exists.
- [x] Implement message draft generation with `generated_by`.
- [x] Add unit tests for matching and feature tests for core API endpoints.

### Phase 4 - React Coordinator Dashboard

- [x] Scaffold React + TypeScript + Vite app in `frontend/`.
- [x] Add typed API client and shared response types.
- [x] Build dashboard summary page.
- [x] Build student request list.
- [x] Build request detail with top matches and score breakdown.
- [x] Build tutor profile/list views.
- [x] Build message draft UI.
- [x] Include loading, error, and empty states.
- [x] Run frontend build.

### Phase 5 - Optional Expo Tutor Flow

- [x] Scaffold Expo app in `mobile/` if feasible.
- [x] Build assignment feed.
- [x] Build filters.
- [x] Build assignment detail.
- [x] Build bulk apply mock action.
- [x] Document whether mobile uses mock data or backend API.

### Phase 6 - Documentation And Interview Readiness

- [x] Complete `README.md` with why built, screenshots placeholders, setup, demo commands, features, architecture, and demo flow.
- [x] Complete `docs/domain-research.md`.
- [x] Complete `docs/architecture.md`.
- [x] Complete `docs/api.md`.
- [x] Complete `docs/database.md`.
- [x] Complete `docs/matching.md`.
- [x] Complete `docs/ai.md`.
- [x] Complete `docs/interview-guide.md` with 90-second script, likely questions, and honest Laravel positioning.
- [x] Add production tradeoffs: auth, privacy, queues, observability, WordPress, performance tuning, and deployment.

### Phase 7 - Final Verification

- [x] `php artisan migrate --seed` works or blocker is documented.
- [x] `php artisan route:list` shows required API routes or blocker is documented.
- [x] `php artisan test` passes or failures are documented.
- [x] `npm run build` passes for frontend or failures are documented.
- [x] Mobile start/build status is documented if mobile exists.
- [x] README setup commands match the actual project.

## API Target

- `GET /api/health`
- `GET /api/dashboard/summary`
- `GET /api/requests`
- `POST /api/requests`
- `GET /api/requests/{id}`
- `GET /api/requests/{id}/matches`
- `POST /api/requests/{id}/generate-matches`
- `GET /api/tutors`
- `GET /api/tutors/{id}`
- `POST /api/assignments/{id}/applications`
- `POST /api/matches/{id}/explain`
- `POST /api/message-drafts`

## Demo Story

1. Show a high-volume coordinator dashboard.
2. Open an urgent student request.
3. Generate ranked tutor matches.
4. Explain the transparent scoring breakdown.
5. Generate an AI-assisted but reviewable explanation.
6. Draft a WhatsApp-style message.
7. Show the optional tutor assignment feed if mobile is complete.
8. Explain production next steps honestly.

## Key Tradeoffs To Preserve

- SQLite is acceptable for local demo speed; schema and indexing should remain portable to MySQL/PostgreSQL.
- No full auth unless core workflows are already complete; document the auth boundary.
- AI is optional and assistive; deterministic scoring remains auditable.
- Demo data must be fictional and Singapore-flavored without pretending to be MindFlex internal data.
