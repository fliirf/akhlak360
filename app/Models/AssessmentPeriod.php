<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'semester',
        'year',
        'start_date',
        'end_date',
        'status',
        'threshold_score',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'threshold_score' => 'decimal:2',
        ];
    }

    public function weights(): HasMany
    {
        return $this->hasMany(AssessmentWeight::class);
    }

    public function peerApprovals(): HasMany
    {
        return $this->hasMany(PeerApproval::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssessmentAssignment::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    public function idpRecommendations(): HasMany
    {
        return $this->hasMany(IdpRecommendation::class);
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->active()
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today());
    }

    public function isOpen(): bool
    {
        return $this->status === 'active'
            && $this->start_date->startOfDay()->lte(today())
            && $this->end_date->startOfDay()->gte(today());
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }
}
