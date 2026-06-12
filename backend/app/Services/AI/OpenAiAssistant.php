<?php

namespace App\Services\AI;

use App\Models\MatchResult;
use App\Models\StudentRequest;
use App\Models\Tutor;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiAssistant implements AiAssistant
{
    public function __construct(private readonly MockAiAssistant $fallback)
    {
    }

    public function explainMatch(MatchResult $matchResult): array
    {
        $matchResult->loadMissing(['studentRequest.subject', 'studentRequest.level', 'tutor']);

        $prompt = [
            'prompt_version' => 'match-explain-v1',
            'task' => 'Explain this tuition tutor match for a coordinator. Use only provided facts. Do not include personal names. Return concise JSON fields: summary, strengths, risks, coordinator_note.',
            'request' => $matchResult->studentRequest->only(['location', 'teaching_mode', 'budget_min', 'budget_max', 'preferred_tutor_type', 'requested_day_of_week', 'requested_time_block', 'urgency']),
            'subject' => $matchResult->studentRequest->subject?->name,
            'level' => $matchResult->studentRequest->level?->name,
            'tutor' => $this->redactedTutorContext($matchResult->tutor),
            'score' => [
                'total' => $matchResult->total_score,
                'breakdown' => $matchResult->score_breakdown,
                'deterministic_explanation' => $this->redactKnownNames(
                    $matchResult->deterministic_explanation,
                    [$matchResult->tutor->name]
                ),
            ],
        ];

        return $this->completeJson($prompt) ?? [
            ...$this->fallback->explainMatch($matchResult),
            'fallback_used' => true,
            'generation_metadata' => [
                'provider' => 'openai',
                'fallback_provider' => 'mock',
                'reason' => 'provider_unavailable_or_invalid_json',
            ],
        ];
    }

    public function draftMessage(StudentRequest $request, ?Tutor $tutor, string $audience, string $channel): array
    {
        $request->loadMissing(['subject', 'level']);

        $prompt = [
            'prompt_version' => 'message-draft-v1',
            'task' => 'Draft a short WhatsApp message for a tuition coordinator. Use only provided facts. Do not include personal names. Do not promise outcomes. Return JSON fields: audience, channel, body.',
            'audience' => $audience,
            'channel' => $channel,
            'request' => $request->only(['location', 'teaching_mode', 'budget_min', 'budget_max', 'requested_day_of_week', 'requested_time_block', 'urgency']),
            'subject' => $request->subject?->name,
            'level' => $request->level?->name,
            'tutor' => $tutor ? $this->redactedTutorContext($tutor) : null,
        ];

        return $this->completeJson($prompt) ?? [
            ...$this->fallback->draftMessage($request, $tutor, $audience, $channel),
            'fallback_used' => true,
            'generation_metadata' => [
                'provider' => 'openai',
                'fallback_provider' => 'mock',
                'reason' => 'provider_unavailable_or_invalid_json',
            ],
        ];
    }

    private function completeJson(array $prompt): ?array
    {
        try {
            $response = Http::withToken((string) config('services.ai.openai_api_key'))
                ->timeout((int) config('services.ai.timeout_seconds', 20))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.ai.openai_model'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a careful operations assistant. Return valid JSON only.'],
                        ['role' => 'user', 'content' => json_encode($prompt, JSON_THROW_ON_ERROR)],
                    ],
                    'temperature' => 0.2,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $content = $response->json('choices.0.message.content');
            $decoded = is_string($content) ? json_decode($content, true) : null;

            return is_array($decoded) ? [
                ...$decoded,
                'generated_by' => 'openai',
                'prompt_version' => $prompt['prompt_version'] ?? 'unknown',
                'fallback_used' => false,
                'generation_metadata' => [
                    'provider' => 'openai',
                    'model' => config('services.ai.openai_model'),
                    'prompt_redaction' => 'personal_names_and_freeform_notes_removed',
                ],
            ] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function redactedTutorContext(Tutor $tutor): array
    {
        return [
            'label' => 'Tutor profile',
            ...$tutor->only(['tutor_type', 'teaching_mode', 'location', 'hourly_rate_min', 'hourly_rate_max', 'years_experience']),
        ];
    }

    private function redactKnownNames(string $value, array $names): string
    {
        foreach ($names as $name) {
            if (is_string($name) && $name !== '') {
                $value = str_replace($name, 'the tutor', $value);
            }
        }

        return $value;
    }
}
