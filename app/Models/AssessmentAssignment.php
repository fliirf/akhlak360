<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_period_id',
        'assessor_employee_id',
        'assessee_employee_id',
        'assessor_type',
        'status',
        'submitted_at',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function assessmentPeriod(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assessor_employee_id');
    }

    public function assessee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assessee_employee_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AssessmentResponse::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    public function scopeActionable(Builder $query): Builder
    {
        return $query->pending()->whereHas(
            'assessmentPeriod',
            fn (Builder $periodQuery) => $periodQuery->open()
        );
    }

    public function scopeAssessorType(Builder $query, string $type): Builder
    {
        return $query->where('assessor_type', $type);
    }
}
