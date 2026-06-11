# Database

The local demo uses SQLite for speed. The schema uses Laravel migrations and portable column types so it can move to MySQL or PostgreSQL for production.

## Tables

- `users`: demo login table with role and hashed API token fields.
- `subjects`, `levels`: normalized academic dimensions.
- `tutors`: tutor profile, optional linked user account, teaching mode, location, rates, experience, acceptance, and success signals.
- `tutor_subjects`: tutor subject and level ability.
- `tutor_availabilities`: schedule slots.
- `student_requests`: parent/student request details.
- `assignments`: published assignment generated from a request.
- `applications`: tutor applications with duplicate prevention.
- `match_results`: deterministic scores, factor breakdowns, coordinator workflow status, outreach status, and notes.
- `message_drafts`: generated coordinator messages.

## Indexes

Indexes are added for fields likely to be filtered heavily: tutor active status, tutor type, teaching mode, location, rates, request subject/level, request status, urgency, created date, assignment status, application status, match workflow status, outreach status, and match score by request.

These matter because assignment operations often involve repeated filtering across fresh requests and available tutor pools. The proof-of-concept also uses eager loading for detail endpoints to avoid obvious N+1 query behavior.
