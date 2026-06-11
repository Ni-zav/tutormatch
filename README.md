# TutorMatch Ops

TutorMatch Ops is an interview proof-of-concept for a MindFlex-style tuition assignment workflow. It models how coordinators can review student requests, generate ranked tutor matches, inspect transparent score breakdowns, and draft client/tutor messages with AI-assisted fallback behavior.

This is a demo project with fictional data. It does not claim access to MindFlex internal systems or production metrics.

## Why This Exists

Tuition assignment operations need fast filtering, explainable matching, coordinator judgment, and clear communication. This project shows a maintainable Laravel/PHP + React/TypeScript + SQL approach to that workflow.

## Stack

- Backend: Laravel 13, PHP 8.5, Eloquent, SQLite local demo
- Frontend: React 19, TypeScript, Vite
- Mobile: Expo React Native mini tutor flow
- AI: service abstraction with deterministic mock fallback
- Tests: PHPUnit backend unit and feature tests

## Screenshots

Place screenshots here after running the demo:

- Dashboard summary
- Request detail with generated matches
- Message draft screen

## Setup

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

The API runs at `http://127.0.0.1:8000/api`.

Seeded demo users:

- Coordinator: `coordinator@tutormatch.test` / `password`
- Admin: `admin@tutormatch.test` / `password`
- Tutor: `tutor@tutormatch.test` / `password`

These credentials are fictional local demo data only.

### Frontend

```bash
cd frontend
npm install
npm run dev
```

The dashboard runs at the Vite URL, usually `http://localhost:5173`.

If the API runs somewhere else:

```bash
VITE_API_BASE_URL=http://127.0.0.1:8000/api npm run dev
```

### Mobile

```bash
cd mobile
npm install
npm run start
```

The mobile app logs in with the seeded tutor account, reads open assignments from the backend, lets the tutor apply or withdraw interest, and can update basic profile and availability data. If the API runs somewhere else, start Expo with:

```bash
EXPO_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api npm run start
```

## Verification

```bash
cd backend
php artisan migrate:fresh --seed
php artisan route:list --path=api
php artisan test
```

```bash
cd frontend
npm run build
```

Current local verification:

- Backend migrations and seed data should be verified with `php artisan migrate:fresh --seed` when PHP is available.
- `php artisan route:list --path=api` should show the protected auth, request, matching, tutor, application, and message draft routes.
- `php artisan test` covers auth, matching, workflow status updates, and AI mock behavior when PHP is available.
- `npm run build` passes for the frontend.
- Mobile TypeScript validation passes with `node_modules/.bin/tsc --noEmit`.
- `npm install` in `mobile/` currently reports 10 moderate audit findings from the Expo dependency tree; review with `npm audit` before production use.

## Features

- Coordinator dashboard summary
- Student request creation, list, and detail view
- Tutor profile list
- Deterministic tutor matching score
- Database candidate prefilter before scoring
- Factor breakdown: subject, level, location/mode, budget, availability, tutor type, history
- Coordinator workflow actions for shortlist, contacted, follow-up, confirmed, and rejected match states
- Structured audit logs for auth, request creation, match generation, workflow updates, message drafts, and tutor applications
- AI/mock match explanation
- WhatsApp-style message draft endpoint and UI
- Expo tutor mini flow with authenticated feed, filters, detail, apply/withdraw, profile, and availability updates
- Fictional Singapore-flavored seed data
- Indexed schema and paginated list endpoints

## API Highlights

- `GET /api/health`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`
- `GET /api/tutor/profile`
- `PATCH /api/tutor/profile`
- `GET /api/dashboard/summary`
- `GET /api/subjects`
- `GET /api/levels`
- `GET /api/requests`
- `POST /api/requests`
- `GET /api/requests/{id}`
- `GET /api/requests/{id}/matches`
- `POST /api/requests/{id}/generate-matches`
- `GET /api/tutors`
- `GET /api/tutors/{id}`
- `GET /api/assignments`
- `POST /api/assignments/{id}/applications`
- `DELETE /api/assignments/{id}/applications`
- `POST /api/matches/{id}/explain`
- `PATCH /api/matches/{id}/workflow`
- `POST /api/message-drafts`

## Architecture

```text
React Dashboard -> Laravel REST API -> SQL database
                         |
                         +-> TutorMatchingService
                         +-> AiAssistant mock fallback
```

More detail is in:

- `docs/architecture.md`
- `docs/api.md`
- `docs/database.md`
- `docs/matching.md`
- `docs/ai.md`
- `docs/deployment.md`
- `docs/security-privacy.md`
- `docs/domain-research.md`
- `docs/interview-guide.md`

## Demo Flow

1. Start the backend and frontend.
2. Open the dashboard.
3. Create a new request or select the seeded urgent Chemistry request.
4. Generate matches.
5. Review the score breakdown and deterministic explanation.
6. Shortlist a tutor, mark outreach, or move the match to follow-up, confirmed, or rejected.
7. Generate a match explanation.
8. Draft a client or tutor WhatsApp message.
9. Explain the production tradeoffs: auth, privacy, queues, tuning, WordPress intake, and tutor mobile flow.

## Production Next Steps

- Replace the demo bearer-token login with Sanctum or another expiring token/session flow before real users.
- Add audit log review/export screens for administrators.
- Add queue-based match generation for high-volume workloads.
- Add privacy controls and prompt redaction before real AI provider use.
- Tune scoring weights with placement and retention data.
- Add WordPress intake integration for public request forms.
- Build a fuller Expo tutor app for status notifications and richer profile editing.

## AI Configuration

The demo works with no AI key:

```env
AI_PROVIDER=mock
AI_TIMEOUT_SECONDS=20
OPENAI_API_KEY=
```

To try the optional provider path, set:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=your_key_here
OPENAI_MODEL=gpt-4o-mini
```

The API falls back to deterministic mock output if the provider is unavailable and stores compact generation metadata on message drafts.
