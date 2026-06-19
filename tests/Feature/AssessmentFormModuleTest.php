<?php

namespace Tests\Feature;

use App\Http\Controllers\Assessment\AssessmentFormController;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResponse;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Services\AssessmentResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AssessmentFormModuleTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);
        $assessorUser = User::factory()->create(['role' => 'employee']);
        $otherUser = User::factory()->create(['role' => 'employee']);
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $period = AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => '2026-06-16',
            'end_date' => '2026-06-29',
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);

        $assessor = Employee::create([
            'user_id' => $assessorUser->id,
            'department_id' => $department->id,
            'employee_number' => 'EMP-001',
            'name' => 'Assessor Employee',
            'email' => 'assessor@example.com',
            'employment_status' => 'active',
        ]);
        $assessee = Employee::create([
            'user_id' => $otherUser->id,
            'department_id' => $department->id,
            'employee_number' => 'EMP-002',
            'name' => 'Assessee Employee',
            'email' => 'assessee@example.com',
            'employment_status' => 'active',
        ]);
        $assignment = AssessmentAssignment::create([
            'assessment_period_id' => $period->id,
            'assessor_employee_id' => $assessor->id,
            'assessee_employee_id' => $assessee->id,
            'assessor_type' => 'peer',
            'status' => 'pending',
        ]);

        return compact('admin', 'assessorUser', 'otherUser', 'department', 'period', 'assessor', 'assessee', 'assignment');
    }

    public function test_user_sees_only_their_pending_assignments(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['assessorUser'])
            ->get('/assessment/pending')
            ->assertOk()
            ->assertSee('Assessee Employee')
            ->assertSee('Fill Assessment');

        $this->actingAs($fixture['otherUser'])
            ->get('/assessment/pending')
            ->assertOk()
            ->assertDontSee('Assessee Employee');
    }

    public function test_assessment_form_requires_all_18_indicators(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['assessorUser'])
            ->post("/assessment/assignments/{$fixture['assignment']->id}/submit", [
                'scores' => [
                    'Amanah' => [0 => 5],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_assessor_can_save_update_and_reopen_partial_draft_without_side_effects(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['assessorUser'])
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'scores' => [
                    'Amanah' => [0 => 3, 1 => 4],
                ],
            ])
            ->assertRedirect(route('assessment.fill.show', $fixture['assignment']))
            ->assertSessionHas('success', 'Draft assessment berhasil disimpan.');

        $assignment = $fixture['assignment']->fresh();
        $this->assertSame('pending', $assignment->status);
        $this->assertNull($assignment->submitted_at);
        $this->assertDatabaseCount('assessment_responses', 2);
        $this->assertDatabaseMissing('assessment_results', [
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
        ]);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $fixture['assessorUser']->id,
            'action' => 'assessment_draft_saved',
            'module' => 'assessment_forms',
        ]);

        $this->actingAs($fixture['assessorUser'])
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'scores' => [
                    'Amanah' => [0 => 5],
                ],
            ])
            ->assertRedirect(route('assessment.fill.show', $fixture['assignment']));

        $this->assertDatabaseCount('assessment_responses', 2);
        $this->assertDatabaseHas('assessment_responses', [
            'assessment_assignment_id' => $fixture['assignment']->id,
            'core_value' => 'Amanah',
            'indicator' => AssessmentFormController::INDICATORS['Amanah'][0],
            'score' => 5,
        ]);

        $this->actingAs($fixture['assessorUser'])
            ->get(route('assessment.fill.show', $fixture['assignment']))
            ->assertOk()
            ->assertSee('Draft tersimpan')
            ->assertSee('value="5"', false)
            ->assertSee('checked', false);
    }

    public function test_draft_rejects_invalid_scores_unknown_indicators_and_other_users(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['assessorUser'])
            ->from(route('assessment.fill.show', $fixture['assignment']))
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'scores' => ['Amanah' => [0 => 6]],
            ])
            ->assertSessionHasErrors('scores.Amanah.0');

        $this->actingAs($fixture['assessorUser'])
            ->from(route('assessment.fill.show', $fixture['assignment']))
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'scores' => ['Unknown' => [0 => 4]],
            ])
            ->assertSessionHasErrors('scores');

        $this->actingAs($fixture['otherUser'])
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'scores' => ['Amanah' => [0 => 4]],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('assessment_responses', 0);
    }

    public function test_submitted_assignment_and_closed_period_cannot_accept_drafts(): void
    {
        $fixture = $this->fixture();
        $fixture['assignment']->update(['status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($fixture['assessorUser'])
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'scores' => ['Amanah' => [0 => 4]],
            ])
            ->assertRedirect('/assessment/pending')
            ->assertSessionHas('warning');

        $fixture['assignment']->update(['status' => 'pending', 'submitted_at' => null]);
        $fixture['period']->update(['status' => 'closed']);

        $this->actingAs($fixture['assessorUser'])
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'scores' => ['Amanah' => [0 => 4]],
            ])
            ->assertRedirect('/assessment/pending')
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('assessment_responses', 0);
    }

    public function test_final_submit_updates_existing_draft_and_runs_calculation(): void
    {
        $fixture = $this->fixture();
        AssessmentResponse::create([
            'assessment_assignment_id' => $fixture['assignment']->id,
            'core_value' => 'Amanah',
            'indicator' => AssessmentFormController::INDICATORS['Amanah'][0],
            'score' => 2,
        ]);

        $this->mock(AssessmentResultService::class, function (MockInterface $mock) use ($fixture): void {
            $mock->shouldReceive('calculateForEmployeePeriod')
                ->once()
                ->with(
                    $fixture['assessee']->id,
                    $fixture['period']->id,
                    $fixture['assessorUser']->id,
                );
        });

        $scores = $this->completeScores();
        $scores['Amanah'][0] = 5;

        $this->actingAs($fixture['assessorUser'])
            ->post(route('assessment.submit', $fixture['assignment']), ['scores' => $scores])
            ->assertRedirect('/assessment/pending');

        $assignment = $fixture['assignment']->fresh();
        $this->assertSame('submitted', $assignment->status);
        $this->assertNotNull($assignment->submitted_at);
        $this->assertDatabaseCount('assessment_responses', 18);
        $this->assertDatabaseHas('assessment_responses', [
            'assessment_assignment_id' => $assignment->id,
            'core_value' => 'Amanah',
            'indicator' => AssessmentFormController::INDICATORS['Amanah'][0],
            'score' => 5,
        ]);
    }

    public function test_incomplete_final_submit_preserves_existing_draft(): void
    {
        $fixture = $this->fixture();
        AssessmentResponse::create([
            'assessment_assignment_id' => $fixture['assignment']->id,
            'core_value' => 'Amanah',
            'indicator' => AssessmentFormController::INDICATORS['Amanah'][0],
            'score' => 4,
        ]);

        $this->actingAs($fixture['assessorUser'])
            ->from(route('assessment.fill.show', $fixture['assignment']))
            ->post(route('assessment.submit', $fixture['assignment']), [
                'scores' => ['Amanah' => [0 => 5]],
            ])
            ->assertSessionHasErrors();

        $this->assertSame('pending', $fixture['assignment']->fresh()->status);
        $this->assertDatabaseCount('assessment_responses', 1);
        $this->assertDatabaseHas('assessment_responses', [
            'assessment_assignment_id' => $fixture['assignment']->id,
            'score' => 4,
        ]);
    }

    public function test_draft_works_for_all_assignment_types(): void
    {
        $fixture = $this->fixture();

        foreach (['self', 'supervisor', 'peer', 'subordinate'] as $type) {
            $fixture['assignment']->update(['assessor_type' => $type]);

            $this->actingAs($fixture['assessorUser'])
                ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                    'scores' => ['Amanah' => [0 => 4]],
                ])
                ->assertRedirect(route('assessment.fill.show', $fixture['assignment']));

            $this->assertDatabaseHas('assessment_responses', [
                'assessment_assignment_id' => $fixture['assignment']->id,
                'score' => 4,
            ]);
        }
    }

    public function test_feedback_field_is_only_visible_for_supervisor_and_subordinate_assignments(): void
    {
        $fixture = $this->fixture();

        foreach ([
            'supervisor' => 'Feedback untuk Bawahan',
            'subordinate' => 'Feedback untuk Atasan',
        ] as $type => $label) {
            $fixture['assignment']->update(['assessor_type' => $type]);

            $this->actingAs($fixture['assessorUser'])
                ->get(route('assessment.fill.show', $fixture['assignment']))
                ->assertOk()
                ->assertSee($label)
                ->assertSee('name="feedback"', false);
        }

        foreach (['self', 'peer'] as $type) {
            $fixture['assignment']->update(['assessor_type' => $type]);

            $this->actingAs($fixture['assessorUser'])
                ->get(route('assessment.fill.show', $fixture['assignment']))
                ->assertOk()
                ->assertDontSee('name="feedback"', false);
        }
    }

    public function test_supervisor_and_subordinate_can_save_update_and_reopen_feedback_drafts(): void
    {
        $fixture = $this->fixture();

        foreach (['supervisor', 'subordinate'] as $type) {
            $fixture['assignment']->update([
                'assessor_type' => $type,
                'feedback' => null,
            ]);
            $fixture['assignment']->responses()->delete();

            $this->actingAs($fixture['assessorUser'])
                ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                    'feedback' => "Draft feedback {$type}",
                    'scores' => ['Amanah' => [0 => 4]],
                ])
                ->assertRedirect(route('assessment.fill.show', $fixture['assignment']));

            $assignment = $fixture['assignment']->fresh();
            $this->assertSame('pending', $assignment->status);
            $this->assertNull($assignment->submitted_at);
            $this->assertSame("Draft feedback {$type}", $assignment->feedback);
            $this->assertDatabaseHas('audit_logs', [
                'action' => 'assessment_feedback_draft_saved',
                'module' => 'assessment_forms',
            ]);

            $this->actingAs($fixture['assessorUser'])
                ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                    'feedback' => "Updated feedback {$type}",
                ]);

            $this->assertSame("Updated feedback {$type}", $fixture['assignment']->fresh()->feedback);

            $this->actingAs($fixture['assessorUser'])
                ->get(route('assessment.fill.show', $fixture['assignment']))
                ->assertOk()
                ->assertSee("Updated feedback {$type}");
        }
    }

    public function test_feedback_validation_authorization_and_unsupported_types_are_enforced(): void
    {
        $fixture = $this->fixture();
        $fixture['assignment']->update(['assessor_type' => 'supervisor']);

        $this->actingAs($fixture['assessorUser'])
            ->from(route('assessment.fill.show', $fixture['assignment']))
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'feedback' => str_repeat('a', 2001),
            ])
            ->assertSessionHasErrors('feedback');

        $this->actingAs($fixture['otherUser'])
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'feedback' => 'Unauthorized feedback',
            ])
            ->assertForbidden();

        $this->assertNull($fixture['assignment']->fresh()->feedback);

        foreach (['self', 'peer'] as $type) {
            $fixture['assignment']->update(['assessor_type' => $type]);

            $this->actingAs($fixture['assessorUser'])
                ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                    'feedback' => 'Manipulated feedback',
                ])
                ->assertRedirect(route('assessment.fill.show', $fixture['assignment']));

            $this->assertNull($fixture['assignment']->fresh()->feedback);
        }
    }

    public function test_final_submit_saves_feedback_and_submitted_assignment_cannot_change_it(): void
    {
        $fixture = $this->fixture();
        $fixture['assignment']->update(['assessor_type' => 'supervisor']);

        $this->actingAs($fixture['assessorUser'])
            ->post(route('assessment.submit', $fixture['assignment']), [
                'scores' => $this->completeScores(),
                'feedback' => 'Final constructive feedback',
            ])
            ->assertRedirect('/assessment/pending');

        $assignment = $fixture['assignment']->fresh();
        $this->assertSame('submitted', $assignment->status);
        $this->assertSame('Final constructive feedback', $assignment->feedback);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assessment_feedback_submitted',
            'module' => 'assessment_forms',
        ]);

        $this->actingAs($fixture['assessorUser'])
            ->post(route('assessment.assignments.draft', $fixture['assignment']), [
                'feedback' => 'Changed after submit',
            ])
            ->assertRedirect('/assessment/pending')
            ->assertSessionHas('warning');

        $this->assertSame('Final constructive feedback', $fixture['assignment']->fresh()->feedback);
    }

    public function test_recipients_only_see_their_own_feedback_and_subordinate_identity_is_anonymous(): void
    {
        $fixture = $this->fixture();
        $fixture['assignment']->update([
            'assessor_type' => 'supervisor',
            'status' => 'submitted',
            'submitted_at' => now(),
            'feedback' => '<script>alert("xss")</script> Supervisor private feedback',
        ]);

        $this->actingAs($fixture['otherUser'])
            ->get(route('assessment.results.index'))
            ->assertOk()
            ->assertSee('Feedback dari Atasan')
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; Supervisor private feedback', false)
            ->assertDontSee('<script>alert("xss")</script>', false);

        $this->actingAs($fixture['assessorUser'])
            ->get(route('assessment.results.index'))
            ->assertOk()
            ->assertDontSee('Supervisor private feedback');

        $fixture['otherUser']->update(['role' => 'supervisor']);
        $fixture['assessor']->update(['supervisor_id' => $fixture['assessee']->id]);
        $fixture['assignment']->update([
            'assessor_type' => 'subordinate',
            'feedback' => 'Anonymous team feedback',
        ]);

        $this->actingAs($fixture['otherUser']->fresh())
            ->get(route('assessment.results.index'))
            ->assertOk()
            ->assertSee('Feedback dari Tim/Bawahan')
            ->assertSee('Anonymous team feedback')
            ->assertSee('ditampilkan secara anonim')
            ->assertDontSee('Assessor Employee');
    }

    public function test_submit_saves_responses_marks_submitted_notifies_admin_and_audits(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['assessorUser'])
            ->post("/assessment/assignments/{$fixture['assignment']->id}/submit", [
                'scores' => $this->completeScores(),
            ])
            ->assertRedirect('/assessment/pending');

        $this->assertSame('submitted', $fixture['assignment']->fresh()->status);
        $this->assertNotNull($fixture['assignment']->fresh()->submitted_at);
        $this->assertDatabaseCount('assessment_responses', 18);
        $this->assertDatabaseHas('assessment_results', [
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
            'final_score' => 4,
            'category' => 'Baik',
        ]);
        $this->assertDatabaseHas('idp_recommendations', [
            'assessment_period_id' => $fixture['period']->id,
            'employee_id' => $fixture['assessee']->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $fixture['admin']->id,
            'title' => 'Assessment Submitted',
            'type' => 'assessment_reminder',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $fixture['assessorUser']->id,
            'action' => 'submit',
            'module' => 'assessment_forms',
        ]);
    }

    public function test_duplicate_submission_is_prevented(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['assessorUser'])
            ->post("/assessment/assignments/{$fixture['assignment']->id}/submit", [
                'scores' => $this->completeScores(),
            ]);

        $this->actingAs($fixture['assessorUser'])
            ->post("/assessment/assignments/{$fixture['assignment']->id}/submit", [
                'scores' => $this->completeScores(),
            ])
            ->assertRedirect('/assessment/pending')
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('assessment_responses', 18);
    }

    public function test_other_user_cannot_fill_assignment(): void
    {
        $fixture = $this->fixture();

        $this->actingAs($fixture['otherUser'])
            ->get("/assessment/assignments/{$fixture['assignment']->id}/fill")
            ->assertForbidden();
    }

    private function completeScores(): array
    {
        $scores = [];

        foreach (AssessmentFormController::INDICATORS as $coreValue => $indicators) {
            foreach (array_keys($indicators) as $index) {
                $scores[$coreValue][$index] = 4;
            }
        }

        return $scores;
    }
}
