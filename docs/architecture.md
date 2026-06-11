# Architecture

```text
React + TypeScript Dashboard
        |
        | REST JSON over /api
        v
Laravel API
  - Controllers and form requests
  - Role-aware bearer-token middleware
  - API resources
  - TutorMatchingService
  - AiAssistant abstraction with mock fallback
        |
        v
SQLite for local demo
Portable schema for MySQL/PostgreSQL

Expo Tutor Mini App
  - Local mocked assignment feed
  - Filters, detail, bulk apply mock
```

## Module Boundaries

- `backend/app/Http/Controllers/Api`: thin HTTP layer.
- `backend/app/Http/Requests`: validation for writes.
- `backend/app/Http/Resources`: stable JSON response shapes.
- `backend/app/Services/Matching`: deterministic scoring and explanations.
- `backend/app/Services/AI`: provider boundary and mock fallback.
- `frontend/src/api`: typed API client.
- `frontend/src/types`: shared response types used by the dashboard.
- `mobile/App.tsx`: compact tutor-side assignment workflow using mocked data.

## Production Tradeoffs

The current auth layer is a small MVP foundation: seeded users have admin, coordinator, or tutor roles, login issues a hashed bearer token, and workflow routes are role-protected. Before real users, replace this with Sanctum or another expiring token/session flow, add token rotation and audit logs, and keep coordinator-only actions separate from tutor-facing endpoints.
