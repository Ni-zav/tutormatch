# AI Assistance

AI is behind the `AiAssistant` interface. The default implementation binds to `MockAiAssistant`, so the demo works without an API key. If `AI_PROVIDER=openai` and `OPENAI_API_KEY` are present, the app uses `OpenAiAssistant` and falls back to the mock on provider errors.

## Current Features

- Explain a match using the deterministic score and breakdown.
- Draft WhatsApp-style client or tutor messages.
- Return `generated_by = mock_ai` for transparency.

## Safety

- AI does not change rankings or scores.
- The demo uses fictional data.
- Generated messages should be reviewed by a human coordinator.
- Production prompts should instruct the model to use only provided facts, avoid guarantees, and return structured JSON.

## Production Next Steps

Add a real provider implementation only when `OPENAI_API_KEY` or another provider key exists, add prompt versioning, log generation metadata, and redact sensitive data before sending prompts.
