<?php

namespace App\Services\Matching;

use App\Models\MatchResult;
use App\Models\StudentRequest;
use App\Models\Tutor;
use Illuminate\Support\Collection;

class TutorMatchingService
{
    public function generateForRequest(StudentRequest $request, int $limit = 10): Collection
    {
        $request->loadMissing(['subject', 'level']);

        return Tutor::query()
            ->with(['tutorSubjects.subject', 'tutorSubjects.level', 'availabilities'])
            ->get()
            ->map(function (Tutor $tutor) use ($request): MatchResult {
                $score = $this->score($tutor, $request);

                return MatchResult::query()->updateOrCreate(
                    [
                        'student_request_id' => $request->id,
                        'tutor_id' => $tutor->id,
                    ],
                    [
                        'total_score' => $score->totalScore,
                        'score_breakdown' => $score->factors,
                        'deterministic_explanation' => $score->explanation,
                        'generated_at' => now(),
                    ]
                )->load('tutor');
            })
            ->sortByDesc('total_score')
            ->take($limit)
            ->values();
    }

    public function score(Tutor $tutor, StudentRequest $request): MatchScoreBreakdown
    {
        $tutor->loadMissing(['tutorSubjects', 'availabilities']);
        $request->loadMissing(['subject', 'level']);

        $subjectMatch = $tutor->tutorSubjects->contains('subject_id', $request->subject_id);
        $levelMatch = $tutor->tutorSubjects->contains(
            fn ($ability) => $ability->subject_id === $request->subject_id
                && ($ability->level_id === null || $ability->level_id === $request->level_id)
        );

        $factors = [
            'subject' => $subjectMatch ? 30 : 0,
            'level' => $levelMatch ? 20 : 0,
            'location_mode' => $this->locationModeScore($tutor, $request),
            'budget' => $this->budgetScore($tutor, $request),
            'availability' => $this->availabilityScore($tutor, $request),
            'tutor_type' => $request->preferred_tutor_type && $tutor->tutor_type === $request->preferred_tutor_type ? 5 : 0,
            'history' => (int) round((($tutor->acceptance_rate + $tutor->success_score) / 2) * 5),
        ];

        return new MatchScoreBreakdown(
            min(100, array_sum($factors)),
            $factors,
            $this->explain($tutor, $request, $factors)
        );
    }

    private function locationModeScore(Tutor $tutor, StudentRequest $request): int
    {
        if ($request->teaching_mode === 'online' && in_array($tutor->teaching_mode, ['online', 'hybrid'], true)) {
            return 15;
        }

        if ($tutor->location === $request->location && in_array($tutor->teaching_mode, [$request->teaching_mode, 'hybrid'], true)) {
            return 15;
        }

        if ($tutor->location === $request->location || $tutor->teaching_mode === 'hybrid') {
            return 8;
        }

        return 0;
    }

    private function budgetScore(Tutor $tutor, StudentRequest $request): int
    {
        if ($tutor->hourly_rate_min <= $request->budget_max && $tutor->hourly_rate_max >= ($request->budget_min ?? 0)) {
            return 15;
        }

        if ($tutor->hourly_rate_min <= $request->budget_max + 10) {
            return 8;
        }

        return 0;
    }

    private function availabilityScore(Tutor $tutor, StudentRequest $request): int
    {
        if (! $request->requested_day_of_week || ! $request->requested_time_block) {
            return 5;
        }

        return $tutor->availabilities->contains(
            fn ($slot) => $slot->day_of_week === $request->requested_day_of_week
                && $slot->time_block === $request->requested_time_block
        ) ? 10 : 0;
    }

    private function explain(Tutor $tutor, StudentRequest $request, array $factors): string
    {
        $strengths = [];

        if ($factors['subject'] > 0) {
            $strengths[] = "teaches {$request->subject->name}";
        }
        if ($factors['level'] > 0) {
            $strengths[] = "covers {$request->level->name}";
        }
        if ($factors['location_mode'] >= 15) {
            $strengths[] = "fits the {$request->location} {$request->teaching_mode} lesson requirement";
        }
        if ($factors['budget'] >= 15) {
            $strengths[] = 'fits the stated hourly budget';
        }
        if ($factors['availability'] >= 10) {
            $strengths[] = "is available on {$request->requested_day_of_week} {$request->requested_time_block}";
        }

        $summary = $strengths ? implode(', ', $strengths) : 'has limited direct fit for the request';

        return "{$tutor->name} scores ".array_sum($factors)."/100 because the tutor {$summary}.";
    }
}
