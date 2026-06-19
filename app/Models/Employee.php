<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'department_id',
        'position_id',
        'employee_number',
        'name',
        'email',
        'supervisor_id',
        'employment_status',
        'role_override',
        'hris_external_id',
        'sso_code_hash',
        'sso_code_generated_at',
        'last_synced_at',
    ];

    protected $hidden = [
        'sso_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'sso_code_generated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function assessmentsToComplete(): HasMany
    {
        return $this->hasMany(AssessmentAssignment::class, 'assessor_employee_id');
    }

    public function assessmentAssignmentsReceived(): HasMany
    {
        return $this->hasMany(AssessmentAssignment::class, 'assessee_employee_id');
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    public function idpRecommendations(): HasMany
    {
        return $this->hasMany(IdpRecommendation::class);
    }

    public function peerApprovals(): HasMany
    {
        return $this->hasMany(PeerApproval::class);
    }

    public function supervisorPeerApprovals(): HasMany
    {
        return $this->hasMany(PeerApproval::class, 'supervisor_employee_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('employment_status', 'active');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('employment_status', 'inactive');
    }

    public function scopeSupervisorCandidates(Builder $query): Builder
    {
        $leadershipLevels = config('akhlak360.supervisor_candidate_position_levels', ['L3', 'L4', 'L5']);

        return $query->where(function (Builder $candidateQuery) use ($leadershipLevels): void {
            $candidateQuery->whereHas('subordinates')
                ->orWhereHas('position', fn (Builder $positionQuery) => $positionQuery
                    ->whereIn('level', $leadershipLevels));
        });
    }

    public function isSupervisorCandidate(): bool
    {
        return $this->subordinates()->exists()
            || ($this->position
                && in_array(
                    $this->position->level,
                    config('akhlak360.supervisor_candidate_position_levels', ['L3', 'L4', 'L5']),
                    true
                ));
    }

    public function scopeInDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $query) => $query->where(
            fn (Builder $searchQuery) => $searchQuery
                ->where('name', 'like', "%{$term}%")
                ->orWhere('employee_number', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
        ));
    }
}
