# API

Base path: `/api`

Most workflow routes require a bearer token from `POST /auth/login`. Tokens are stored hashed server-side, track last use, and expire after `API_TOKEN_TTL_MINUTES` minutes, defaulting to 1440. Auth user payloads include `token_issued_at`, `token_last_used_at`, and `token_expires_at`. The local seed data creates fictional demo users:

- `coordinator@tutormatch.test` / `password`
- `admin@tutormatch.test` / `password`
- `tutor@tutormatch.test` / `password`

## Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/health` | API health check |
| POST | `/auth/login` | Issue bearer token for a valid demo user |
| GET | `/auth/me` | Current authenticated user |
| POST | `/auth/logout` | Revoke the current bearer token |
| GET | `/audit-logs` | Coordinator/admin recent audit event review; supports `action` filter and `format=csv` export |
| GET | `/tutor/profile` | Tutor self-service profile and availability detail |
| PATCH | `/tutor/profile` | Tutor self-service profile and availability update |
| GET | `/dashboard/summary` | Coordinator/admin metrics |
| GET | `/subjects` | Coordinator/admin subject options for request forms |
| GET | `/levels` | Coordinator/admin level options for request forms |
| GET | `/requests` | Coordinator/admin paginated student requests |
| POST | `/requests` | Coordinator/admin create student request and assignment |
| GET | `/requests/{id}` | Coordinator/admin request detail |
| GET | `/requests/{id}/matches` | Coordinator/admin paginated match results |
| POST | `/requests/{id}/generate-matches` | Coordinator/admin generate deterministic matches |
| GET | `/tutors` | Coordinator/admin paginated tutors |
| GET | `/tutors/{id}` | Coordinator/admin tutor profile |
| GET | `/assignments` | Open assignment feed; tutors see their own application status, coordinators/admins see submitted applications |
| POST | `/assignments/{id}/applications` | Admin/coordinator/tutor application submission |
| DELETE | `/assignments/{id}/applications` | Admin/coordinator/tutor application withdrawal |
| PATCH | `/applications/{id}` | Coordinator/admin update application status |
| POST | `/matches/{id}/explain` | Coordinator/admin AI/mock match explanation |
| PATCH | `/matches/{id}/workflow` | Coordinator/admin shortlist, outreach, and outcome status update |
| POST | `/message-drafts` | Coordinator/admin AI/mock message draft |

## Health Response

`GET /health` is public and safe for uptime checks. It returns `200` when the API and database are reachable:

```json
{
  "status": "ok",
  "service": "TutorMatch Ops API",
  "checks": {
    "database": "ok"
  }
}
```

If the database check fails, the endpoint returns `503` with `status = degraded` and `checks.database = error`.

## Login Example

```json
{
  "email": "coordinator@tutormatch.test",
  "password": "password"
}
```

Use the returned `data.token` as `Authorization: Bearer <token>`.

## Create Request Example

```json
{
  "student_name": "Demo Student A",
  "parent_name": "Mrs Tan",
  "subject_id": 1,
  "level_id": 4,
  "location": "Bishan",
  "teaching_mode": "home",
  "budget_min": 45,
  "budget_max": 65,
  "preferred_tutor_type": "ex_moe",
  "requested_day_of_week": "saturday",
  "requested_time_block": "morning",
  "urgency": "urgent",
  "schedule_notes": "Weekend mornings preferred",
  "notes": "Needs help with O-Level Chemistry exam prep."
}
```

## Match Response Shape

```json
{
  "data": [
    {
      "id": 1,
      "total_score": 99,
      "score_breakdown": {
        "subject": 30,
        "level": 20,
        "location_mode": 15,
        "budget": 15,
        "availability": 10,
        "tutor_type": 5,
        "history": 4
      },
      "deterministic_explanation": "Daniel Lim scores 99/100 because...",
      "status": "recommended",
      "outreach_status": "not_contacted",
      "coordinator_notes": null
    }
  ]
}
```

## Match Workflow Update Example

```json
{
  "status": "shortlisted",
  "outreach_status": "contacted",
  "coordinator_notes": "Parent wants a weekend trial."
}
```

Allowed match statuses are `recommended`, `shortlisted`, `accepted`, `rejected`, `confirmed`, `needs_follow_up`, and `closed`. Allowed outreach statuses are `not_contacted`, `drafted`, `contacted`, `responded`, and `no_response`.

## Message Draft Metadata

Message draft responses include `generated_by`, `prompt_version`, `fallback_used`, and `generation_metadata`. `AI_PROVIDER=mock` works offline. If `AI_PROVIDER=openai` fails or returns invalid JSON, the API stores mock output with `fallback_used=true`.

## Tutor Assignment Feed

`GET /assignments` returns open published assignments. For tutor users, each row includes that tutor's current `application_status` when they have already applied and does not expose other tutor applications. For coordinator/admin users, each row includes an `applications` array with tutor id, tutor name, status, message, and applied timestamp for review.

Tutor users do not need to send `tutor_id` when applying or withdrawing. If a tutor sends a different `tutor_id`, the API still uses the authenticated tutor profile.

Tutor application submit/withdraw routes are rate-limited to reduce accidental repeat submissions and scripted workflow abuse.

Coordinator/admin users can update application status with:

```json
{
  "status": "accepted"
}
```

Allowed application statuses are `applied`, `accepted`, `rejected`, and `withdrawn`. Tutor users cannot call this direct status update route. Setting an application to `accepted` confirms the assignment and student request, and rejects other still-applied applications for that assignment.

## Tutor Profile Update

Tutor users can update `teaching_mode`, `location`, hourly rate range, `bio`, active/paused status, and availability slots. Availability slots are simple `{day_of_week, time_block}` pairs used by the matching prefilter.

## Audit Logging

The API writes internal audit rows for login/logout, request creation, match generation, match workflow updates, message draft creation, tutor applications, application status updates, and withdrawals. Coordinator/admin users can review recent events through `GET /audit-logs`; tutor users cannot access audit logs.

Audit review supports `GET /audit-logs?action=request.created` for action filtering and `GET /audit-logs?format=csv` for a CSV export capped at 500 recent rows.
