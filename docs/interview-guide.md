# Interview Guide

## 90-Second Video Script

Hi, I built TutorMatch Ops as a focused proof-of-concept for tuition assignment operations. The workflow starts with a parent request, including level, subject, location, budget, schedule, and tutor preference. The Laravel API stores the request, creates an assignment, and generates transparent tutor matches using deterministic scoring.

The React dashboard shows coordinator metrics, request detail, top matches, score breakdowns, tutor profiles, and WhatsApp-style message drafts. I kept AI assistive rather than authoritative: it can explain a match or draft a message, but the ranking comes from explicit business rules.

I used SQLite for a quick local demo, with Laravel migrations and indexes that can move to MySQL or PostgreSQL. If this were production, I would add auth, audit logs, queue-based match generation, stronger privacy controls, and tune weights using real placement outcomes.

## Honest Laravel Positioning

My strongest background is full-stack TypeScript, backend/API/data workflows, automation, and AI-assisted tooling. I built this Laravel + React proof-of-concept to show how I understand the TutorMatch domain and how I approach MindFlex's requested stack.

## Likely Questions

- Why deterministic matching before AI?
- Which indexes matter for high-volume assignment operations?
- How would auth and roles be added?
- How would WordPress fit? Public forms or CMS content could feed the same API.
- How would Expo fit? The proof-of-concept includes a mock tutor assignment feed, filters, detail, and bulk apply workflow.
- How would you measure matching quality? Placement rate, acceptance rate, response time, parent satisfaction, retention, and coordinator override rate.
