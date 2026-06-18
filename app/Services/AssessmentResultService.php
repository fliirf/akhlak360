<?php

namespace App\Services;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Models\AuditLog;
use App\Models\IdpRecommendation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentResultService
{
    private const CORE_VALUES = [
        'Amanah' => 'amanah_score',
        'Kompeten' => 'kompeten_score',
        'Harmonis' => 'harmonis_score',
        'Loyal' => 'loyal_score',
        'Adaptif' => 'adaptif_score',
        'Kolaboratif' => 'kolaboratif_score',
    ];

    private const ASSESSOR_TYPES = ['supervisor', 'peer', 'subordinate', 'self'];

    private const IDP_RECOMMENDATIONS = [
        'Amanah' => 'Coaching accountability, commitment management, and ethical responsibility.',
        'Kompeten' => 'Technical training, continuous learning plan, and knowledge sharing session.',
        'Harmonis' => 'Communication training, conflict management, and teamwork development.',
        'Loyal' => 'Corporate values alignment, BUMN culture program, and organizational commitment.',
        'Adaptif' => 'Change management workshop, innovation challenge, and problem-solving training.',
        'Kolaboratif' => 'Cross-functional project assignment, collaboration workshop, and information-sharing practice.',
    ];

    public function calculateForEmployeePeriod(int $employeeId, int $periodId, ?int $userId = null): ?AssessmentResult
    {
        return DB::transaction(function () use ($employeeId, $periodId, $userId): ?AssessmentResult {
            $assignments = AssessmentAssignment::query()
                ->with('responses')
                ->where('assessment_period_id', $periodId)
                ->where('assessee_employee_id', $employeeId)
                ->submitted()
                ->get()
                ->filter(fn (AssessmentAssignment $assignment) => $this->hasCompleteValidResponses($assignment))
                ->values();

            if ($assignments->isEmpty()) {
                AssessmentResult::query()
                    ->where('assessment_period_id', $periodId)
                    ->where('employee_id', $employeeId)
                    ->delete();
                IdpRecommendation::query()
                    ->where('assessment_period_id', $periodId)
                    ->where('employee_id', $employeeId)
                    ->delete();

                return null;
            }

            $period = AssessmentPeriod::with('weights')->findOrFail($periodId);
            $typeScores = $this->averageScoresByType($assignments);
            $weights = $period->weights->pluck('weight', 'assessor_type')->map(fn ($weight) => (float) $weight);

            $weightedCoreScores = $this->weightedCoreScores($typeScores, $weights, self::ASSESSOR_TYPES);
            $selfScore = $this->averageCoreScores($typeScores['self'] ?? []);
            $othersScore = $this->averageCoreScores($this->weightedCoreScores(
                $typeScores,
                $weights,
                ['supervisor', 'peer', 'subordinate'],
            ));
            $finalScore = $this->averageCoreScores($weightedCoreScores);
            $gapScore = $selfScore !== null && $othersScore !== null ? $selfScore - $othersScore : null;
            $weakestCoreValue = $this->weakestCoreValue($weightedCoreScores);

            $result = AssessmentResult::updateOrCreate(
                [
                    'assessment_period_id' => $periodId,
                    'employee_id' => $employeeId,
                ],
                [
                    'amanah_score' => $this->roundNullable($weightedCoreScores['Amanah'] ?? null),
                    'kompeten_score' => $this->roundNullable($weightedCoreScores['Kompeten'] ?? null),
                    'harmonis_score' => $this->roundNullable($weightedCoreScores['Harmonis'] ?? null),
                    'loyal_score' => $this->roundNullable($weightedCoreScores['Loyal'] ?? null),
                    'adaptif_score' => $this->roundNullable($weightedCoreScores['Adaptif'] ?? null),
                    'kolaboratif_score' => $this->roundNullable($weightedCoreScores['Kolaboratif'] ?? null),
                    'self_score' => $this->roundNullable($selfScore),
                    'others_score' => $this->roundNullable($othersScore),
                    'gap_score' => $this->roundNullable($gapScore),
                    'final_score' => $this->roundNullable($finalScore),
                    'category' => $this->category($finalScore),
                    'talent_mapping_category' => $this->talentMapping($finalScore, $gapScore),
                ],
            );

            if ($weakestCoreValue) {
                $this->updateIdpRecommendation($period, $employeeId, $weakestCoreValue);
            }

            $this->audit($userId, 'calculate', "Calculated assessment result for employee #{$employeeId} in period #{$periodId}.");

            return $result;
        });
    }

    public function calculateForPeriod(int $periodId, ?int $userId = null): int
    {
        $employeeIds = AssessmentAssignment::query()
            ->where('assessment_period_id', $periodId)
            ->submitted()
            ->distinct()
            ->pluck('assessee_employee_id')
            ->merge(
                AssessmentResult::query()
                    ->where('assessment_period_id', $periodId)
                    ->pluck('employee_id')
            )
            ->unique()
            ->values();

        $count = 0;

        foreach ($employeeIds as $employeeId) {
            if ($this->calculateForEmployeePeriod((int) $employeeId, $periodId, $userId)) {
                $count++;
            }
        }

        $this->audit($userId, 'recalculate_period', "Recalculated {$count} employee results for period #{$periodId}.");

        return $count;
    }

    private function averageScoresByType(Collection $assignments): array
    {
        return $assignments
            ->groupBy('assessor_type')
            ->map(fn (Collection $typeAssignments) => $this->averageAssignments($typeAssignments))
            ->all();
    }

    private function hasCompleteValidResponses(AssessmentAssignment $assignment): bool
    {
        if ($assignment->responses->count() !== 18) {
            return false;
        }

        $expectedCoreValues = array_keys(self::CORE_VALUES);
        $grouped = $assignment->responses->groupBy('core_value');

        if ($grouped->keys()->sort()->values()->all() !== collect($expectedCoreValues)->sort()->values()->all()) {
            return false;
        }

        return $grouped->every(
            fn (Collection $responses) => $responses->count() === 3
                && $responses->every(fn ($response) => in_array((int) $response->score, [1, 2, 3, 4, 5], true))
        );
    }

    private function averageAssignments(Collection $assignments): array
    {
        $assignmentScores = $assignments
            ->map(fn (AssessmentAssignment $assignment) => $assignment->responses
                ->groupBy('core_value')
                ->map(fn (Collection $responses) => $responses->avg('score'))
                ->all());

        $averages = [];

        foreach (array_keys(self::CORE_VALUES) as $coreValue) {
            $scores = $assignmentScores
                ->pluck($coreValue)
                ->filter(fn ($score) => $score !== null);

            if ($scores->isNotEmpty()) {
                $averages[$coreValue] = (float) $scores->avg();
            }
        }

        return $averages;
    }

    private function weightedCoreScores(array $typeScores, Collection $weights, array $assessorTypes): array
    {
        $availableTypes = collect($assessorTypes)
            ->filter(fn (string $type) => isset($typeScores[$type]) && $this->averageCoreScores($typeScores[$type]) !== null)
            ->values();

        $availableWeightTotal = $availableTypes->sum(fn (string $type) => (float) ($weights[$type] ?? 0));

        if ($availableTypes->isEmpty()) {
            return [];
        }

        if ($availableWeightTotal <= 0) {
            $normalizedWeights = $availableTypes->mapWithKeys(fn (string $type) => [$type => 1 / $availableTypes->count()]);
        } else {
            $normalizedWeights = $availableTypes->mapWithKeys(
                fn (string $type) => [$type => ((float) ($weights[$type] ?? 0)) / $availableWeightTotal]
            );
        }

        $scores = [];

        foreach (array_keys(self::CORE_VALUES) as $coreValue) {
            $coreValueTypes = $availableTypes->filter(fn (string $type) => isset($typeScores[$type][$coreValue]));
            $coreValueWeightTotal = $coreValueTypes->sum(fn (string $type) => $normalizedWeights[$type]);

            if ($coreValueTypes->isEmpty() || $coreValueWeightTotal <= 0) {
                continue;
            }

            $scores[$coreValue] = $coreValueTypes->sum(
                fn (string $type) => $typeScores[$type][$coreValue] * ($normalizedWeights[$type] / $coreValueWeightTotal)
            );
        }

        return $scores;
    }

    private function averageCoreScores(array $scores): ?float
    {
        $values = collect(array_keys(self::CORE_VALUES))
            ->map(fn (string $coreValue) => $scores[$coreValue] ?? null)
            ->filter(fn ($score) => $score !== null);

        return $values->isEmpty() ? null : (float) $values->avg();
    }

    private function weakestCoreValue(array $scores): ?string
    {
        if ($scores === []) {
            return null;
        }

        return collect($scores)->sort()->keys()->first();
    }

    private function category(?float $finalScore): ?string
    {
        if ($finalScore === null) {
            return null;
        }

        return match (true) {
            $finalScore < 3.00 => 'Perlu Pengembangan',
            $finalScore < 3.75 => 'Cukup',
            $finalScore < 4.50 => 'Baik',
            default => 'Sangat Baik',
        };
    }

    private function talentMapping(?float $finalScore, ?float $gapScore): ?string
    {
        if ($finalScore === null) {
            return null;
        }

        if ($finalScore >= 4.50 && $gapScore !== null && $gapScore >= -0.5 && $gapScore <= 0.5) {
            return 'High Potential';
        }

        return match (true) {
            $finalScore >= 3.75 => 'Solid Contributor',
            $finalScore >= 3.00 => 'Core Contributor',
            default => 'Need Development',
        };
    }

    private function updateIdpRecommendation(AssessmentPeriod $period, int $employeeId, string $weakestCoreValue): void
    {
        $recommendation = IdpRecommendation::firstOrNew([
            'assessment_period_id' => $period->id,
            'employee_id' => $employeeId,
        ]);

        $recommendation->fill([
            'weakest_core_value' => $weakestCoreValue,
            'recommendation' => self::IDP_RECOMMENDATIONS[$weakestCoreValue] ?? "Development plan for {$weakestCoreValue}.",
            'action_plan' => $recommendation->action_plan ?? "Create a 30-60-90 day action plan for {$weakestCoreValue} development.",
            'status' => $recommendation->exists ? $recommendation->status : 'draft',
            'due_date' => $recommendation->due_date ?? $period->end_date->copy()->addDays(30),
        ]);
        $recommendation->save();
    }

    private function roundNullable(?float $value): ?float
    {
        return $value === null ? null : round($value, 2);
    }

    private function audit(?int $userId, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'module' => 'assessment_results',
            'description' => $description,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent() ?? 'system',
        ]);
    }
}
