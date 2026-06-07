# Domain Research

TutorMatch Ops models a high-volume tuition assignment workflow similar to what a Singapore tuition agency may need: parents submit requests, tutors maintain preferences, coordinators shortlist tutors, and communication has to move quickly.

The assumptions are based on public workflow patterns: assignment feeds, filters, tutor applications, tutor type preferences, budget constraints, location/mode fit, and coordinator review. This is not MindFlex internal information.

## Modeled Workflow

1. Parent or client submits a request with subject, level, location, budget, tutor type, schedule, and notes.
2. The system stores the request and publishes an assignment.
3. Coordinators generate deterministic tutor matches.
4. The dashboard shows why each tutor is ranked.
5. AI-assisted text helps explain or draft messages, but a coordinator remains responsible for approval.

## Why It Matters

For a high-volume assignment operation, speed only helps if the result remains explainable. This proof-of-concept shows how matching, communication, and operational visibility can be combined without hiding the business rules inside an opaque AI ranking.
