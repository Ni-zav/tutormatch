# Security And Privacy

TutorMatch may handle student, parent, tutor, schedule, budget, location, and lesson preference data. Treat that as personal data in production even when the demo seed data is fictional.

## Current MVP Controls

- Coordinator and admin workflow routes require bearer-token authentication.
- Bearer tokens are stored hashed server-side and expire through `API_TOKEN_TTL_MINUTES`.
- Tutor application submission requires an authenticated admin, coordinator, or tutor. Tutor users are mapped to their own tutor profile server-side so they cannot apply as another tutor.
- Tutor profile updates are restricted to authenticated tutor users and update only their linked profile.
- Matching remains deterministic and reviewable; AI does not make final ranking or assignment decisions.
- `AI_PROVIDER=mock` is the default so the app works without sending data to a paid external AI provider.
- CORS origins are configured through `FRONTEND_ALLOWED_ORIGINS`.
- Public health checks avoid secrets and personal data.
- Structured audit logs record key operational events with user id, action, target object, compact metadata, IP address, and user agent.
- `tutormatch:prune-retention` can remove old audit logs, message drafts, and finalized requests using configured retention windows, and anonymize old inactive tutor profiles.

## Production Requirements Before Real Users

- Replace demo bearer tokens with Sanctum tokens or a comparable production auth flow with token rotation, device/session review, and stronger account lifecycle controls.
- Use strong passwords, secure reset flows, and role review for admin/coordinator access.
- Define audit review/export access policy and approve production retention windows before real production use.
- Store only the personal data needed for assignment operations.
- Expand retention rules for rejected matches, archived backups, and inactive users.
- Encrypt production backups and restrict restore access.
- Keep `.env`, API keys, database credentials, and AI provider keys out of Git.

## AI Data Handling

AI is optional and assistive. Do not send unnecessary personal data to an AI provider. Prefer match IDs, coarse request details, and redacted notes where possible. Message drafts must remain coordinator-reviewed and must not be auto-sent by the system.

When a real AI provider is enabled, log enough metadata to debug failures, such as provider, model, prompt version, and fallback status. Avoid storing full prompts if they contain sensitive student, parent, tutor, budget, or location details.

## Operational Notes

- Use HTTPS everywhere in production.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Limit production CORS origins to the deployed frontend domains.
- Run database migrations intentionally during deploys and snapshot the database before risky schema changes.
- Review access when a coordinator, tutor, or admin leaves the operation.
- Run `php artisan tutormatch:prune-retention --dry-run` before enabling scheduled deletion, then schedule `php artisan tutormatch:prune-retention` once retention windows are approved.
