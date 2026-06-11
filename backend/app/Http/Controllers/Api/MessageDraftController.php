<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageDraftRequest;
use App\Models\MessageDraft;
use App\Models\StudentRequest;
use App\Models\Tutor;
use App\Services\AuditLogger;
use App\Services\AI\AiAssistant;

class MessageDraftController extends Controller
{
    public function __invoke(StoreMessageDraftRequest $request, AiAssistant $aiAssistant, AuditLogger $auditLogger)
    {
        $validated = $request->validated();
        $studentRequest = StudentRequest::with(['subject', 'level'])->findOrFail($validated['student_request_id']);
        $tutor = isset($validated['tutor_id']) ? Tutor::findOrFail($validated['tutor_id']) : null;
        $draft = $aiAssistant->draftMessage($studentRequest, $tutor, $validated['audience'], $validated['channel'] ?? 'whatsapp');

        $messageDraft = MessageDraft::create([
            'student_request_id' => $studentRequest->id,
            'tutor_id' => $tutor?->id,
            'match_result_id' => $validated['match_result_id'] ?? null,
            'audience' => $draft['audience'],
            'channel' => $draft['channel'],
            'body' => $draft['body'],
            'generated_by' => $draft['generated_by'],
            'prompt_version' => $draft['prompt_version'] ?? 'message-draft-v1',
            'fallback_used' => $draft['fallback_used'] ?? false,
            'generation_metadata' => $draft['generation_metadata'] ?? null,
        ]);
        $auditLogger->record($request, 'message_draft.created', $messageDraft, [
            'student_request_id' => $studentRequest->id,
            'tutor_id' => $tutor?->id,
            'audience' => $messageDraft->audience,
            'channel' => $messageDraft->channel,
            'generated_by' => $messageDraft->generated_by,
            'fallback_used' => $messageDraft->fallback_used,
        ]);

        return response()->json(['data' => $messageDraft], 201);
    }
}
