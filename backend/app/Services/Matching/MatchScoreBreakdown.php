<?php

namespace App\Services\Matching;

final readonly class MatchScoreBreakdown
{
    public function __construct(
        public int $totalScore,
        public array $factors,
        public string $explanation,
    ) {
    }
}
