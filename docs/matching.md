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

## Candidate Prefilter

Match generation first builds a database candidate set, then runs deterministic scoring only on those tutors. The prefilter keeps tutors who are active, teach the requested subject and exact or general level, fit the requested online/home/hybrid mode and rough location requirement, overlap the request budget, and match the requested availability slot when one is provided.

Existing match rows for tutors that no longer pass the prefilter are removed during regeneration so repeated runs stay aligned with current tutor data. Final ordering is deterministic: total score, tutor success score, acceptance rate, then tutor id.

When no tutors pass the prefilter, generation returns an empty match list and marks the student request as `no_matches` so coordinators can distinguish "searched but no candidate found" from "not generated yet."

## Limitations

The current model uses simple exact matches and demo success signals. In production, weights should be tuned using placement outcomes, tutor response time, parent feedback, student retention, rejection reasons, and coordinator overrides.

## Why Deterministic First

Coordinators need repeatable reasoning. AI can make an explanation easier to read, but it should not secretly decide which tutor is ranked higher.
