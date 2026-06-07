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
            'task' => 'Explain this tuition tutor match for a coordinator. Use only provided facts. Return concise JSON fields: summary, strengths, risks, coordinator_note.',
            'request' => $matchResult->studentRequest->only(['location', 'teaching_mode', 'budget_min', 'budget_max', 'preferred_tutor_type', 'requested_day_of_week', 'requested_time_block', 'urgency']),
            'subject' => $matchResult->studentRequest->subject?->name,
            'level' => $matchResult->studentRequest->level?->name,
            'tutor' => $matchResult->tutor->only(['name', 'tutor_type', 'teaching_mode', 'location', 'hourly_rate_min', 'hourly_rate_max', 'years_experience']),
            'score' => [
                'total' => $matchResult->total_score,
                'breakdown' => $matchResult->score_breakdown,
                'deterministic_explanation' => $matchResult->deterministic_explanation,
            ],
        ];

        return $this->completeJson($prompt) ?? $this->fallback->explainMatch($matchResult);
    }

    public function draftMessage(StudentRequest $request, ?Tutor $tutor, string $audience, string $channel): array
    {
        $request->loadMissing(['subject', 'level']);

        $prompt = [
            'task' => 'Draft a short WhatsApp message for a tuition coordinator. Use only provided facts. Do not promise outcomes. Return JSON fields: audience, channel, body.',
            'audience' => $audience,
            'channel' => $channel,
            'request' => $request->only(['student_name', 'parent_name', 'location', 'teaching_mode', 'budget_min', 'budget_max', 'schedule_notes', 'notes']),
            'subject' => $request->subject?->name,
            'level' => $request->level?->name,
            'tutor' => $tutor?->only(['name', 'tutor_type', 'teaching_mode', 'location', 'hourly_rate_min', 'hourly_rate_max', 'years_experience']),
        ];

        return $this->completeJson($prompt) ?? $this->fallback->draftMessage($request, $tutor, $audience, $channel);
    }

    private function completeJson(array $prompt): ?array
    {
        try {
            $response = Http::withToken((string) config('services.ai.openai_api_key'))
                ->timeout(20)
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

            return is_array($decoded) ? ['generated_by' => 'openai', ...$decoded] : null;
        } catch (Throwable) {
            return null;
        }
    }
}
