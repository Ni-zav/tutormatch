# AI Assistance

AI is behind the `AiAssistant` interface. The default implementation binds to `MockAiAssistant`, so the demo works without an API key. If `AI_PROVIDER=openai` and `OPENAI_API_KEY` are present, the app uses `OpenAiAssistant` and falls back to the mock on provider errors.

## Current Features

- Explain a match using the deterministic score and breakdown.
- Draft WhatsApp-style client or tutor messages.
- Return `generated_by = mock_ai` for transparency.
- Store message draft metadata: `prompt_version`, `fallback_used`, and compact `generation_metadata`.

## Safety

- AI does not change rankings or scores.
- The demo uses fictional data.
- Generated messages should be reviewed by a human coordinator.
- Production prompts should instruct the model to use only provided facts, avoid guarantees, and return structured JSON.
- The app stores provider/debug metadata, not full prompt payloads.
- Real provider prompts remove student names, parent names, tutor names, and free-form request notes before sending context externally.
- Real provider calls use `AI_TIMEOUT_SECONDS` and fall back to mock output on errors or invalid JSON.

## Production Next Steps

Before real use, add provider-specific monitoring, prompt sampling reviews with redacted fixtures, and stricter data retention controls for generated drafts.
