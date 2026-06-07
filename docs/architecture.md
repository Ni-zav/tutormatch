# Architecture

```text
React + TypeScript Dashboard
        |
        | REST JSON over /api
        v
Laravel API
  - Controllers and form requests
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

Auth is intentionally omitted in the proof-of-concept to keep the demo focused on matching workflow. In production, the API should add role-based access for coordinators, tutors, and admins, plus audit logs for generated drafts and shortlist decisions.
