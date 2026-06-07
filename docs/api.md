# API

Base path: `/api`

## Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/health` | API health check |
| GET | `/dashboard/summary` | Coordinator metrics |
| GET | `/requests` | Paginated student requests |
| POST | `/requests` | Create student request and assignment |
| GET | `/requests/{id}` | Request detail |
| GET | `/requests/{id}/matches` | Paginated match results |
| POST | `/requests/{id}/generate-matches` | Generate deterministic matches |
| GET | `/tutors` | Paginated tutors |
| GET | `/tutors/{id}` | Tutor profile |
| POST | `/assignments/{id}/applications` | Tutor application mock |
| POST | `/matches/{id}/explain` | AI/mock match explanation |
| POST | `/message-drafts` | AI/mock message draft |

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
      "deterministic_explanation": "Daniel Lim scores 99/100 because..."
    }
  ]
}
```
