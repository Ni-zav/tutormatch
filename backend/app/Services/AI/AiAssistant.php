<?php

namespace App\Services\AI;

use App\Models\MatchResult;
use App\Models\StudentRequest;
use App\Models\Tutor;

interface AiAssistant
{
    public function explainMatch(MatchResult $matchResult): array;

    public function draftMessage(StudentRequest $request, ?Tutor $tutor, string $audience, string $channel): array;
}
