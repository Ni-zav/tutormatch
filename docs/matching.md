# Matching

TutorMatch Ops uses deterministic scoring as the source of truth.

| Factor | Weight |
|---|---:|
| Subject match | 30 |
| Level match | 20 |
| Location/mode fit | 15 |
| Budget fit | 15 |
| Availability fit | 10 |
| Tutor type preference | 5 |
| Historical acceptance/success | 5 |

The score is stored in `match_results.total_score`, while the individual factors are stored as JSON in `score_breakdown`.

## Limitations

The current model uses simple exact matches and demo success signals. In production, weights should be tuned using placement outcomes, tutor response time, parent feedback, student retention, rejection reasons, and coordinator overrides.

## Why Deterministic First

Coordinators need repeatable reasoning. AI can make an explanation easier to read, but it should not secretly decide which tutor is ranked higher.
