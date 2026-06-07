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

The mobile app uses local mock assignment data. It demonstrates the tutor-side assignment feed, filters, assignment detail, and bulk apply action without requiring the backend API.

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

- Backend migrations and seed data work.
- `php artisan route:list --path=api` shows 12 API routes.
- `php artisan test` passes: 4 tests, 14 assertions.
- `npm run build` passes for the frontend.
- Mobile TypeScript validation passes with `node_modules/.bin/tsc --noEmit`.
- `npm install` in `mobile/` currently reports 10 moderate audit findings from the Expo dependency tree; review with `npm audit` before production use.

## Features

- Coordinator dashboard summary
- Student request list and detail view
- Tutor profile list
- Deterministic tutor matching score
- Factor breakdown: subject, level, location/mode, budget, availability, tutor type, history
- AI/mock match explanation
- WhatsApp-style message draft endpoint and UI
- Expo tutor mini flow with feed, filters, detail, and bulk apply mock
- Fictional Singapore-flavored seed data
- Indexed schema and paginated list endpoints

## API Highlights

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
- `docs/domain-research.md`
- `docs/interview-guide.md`

## Demo Flow

1. Start the backend and frontend.
2. Open the dashboard.
3. Select the seeded urgent Chemistry request.
4. Generate matches.
5. Review the score breakdown and deterministic explanation.
6. Generate a match explanation.
7. Draft a client or tutor WhatsApp message.
8. Explain the production tradeoffs: auth, privacy, queues, tuning, WordPress intake, and tutor mobile flow.

## Production Next Steps

- Add role-based auth for coordinators, tutors, and admins.
- Add audit logs for matching decisions and generated messages.
- Add queue-based match generation for high-volume workloads.
- Add privacy controls and prompt redaction before real AI provider use.
- Tune scoring weights with placement and retention data.
- Add WordPress intake integration for public request forms.
- Build a fuller Expo tutor app for assignment feed, filters, detail, and bulk apply.

## AI Configuration

The demo works with no AI key:

```env
AI_PROVIDER=mock
OPENAI_API_KEY=
```

To try the optional provider path, set:

```env
AI_PROVIDER=openai
OPENAI_API_KEY=your_key_here
OPENAI_MODEL=gpt-4o-mini
```

The API falls back to deterministic mock output if the provider is unavailable.
