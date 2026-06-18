<?php

namespace Tests\Feature;

use App\Mail\AssessmentReminderMail;
use App\Models\AppNotification;
use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_assessment_reminders_are_created_every_three_days_without_same_day_spam(): void
    {
        Mail::fake();

        $fixture = $this->assessmentFixture(now()->subDays(3)->toDateString());

        $this->artisan('assessment:send-reminders')
            ->expectsOutput('Generated 1 reminders. Skipped 0 assignments.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $fixture['assessorUser']->id,
            'title' => "Assessment Reminder #{$fixture['assignment']->id}",
            'type' => 'assessment_reminder',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'send_reminders',
            'module' => 'notifications',
        ]);
        Mail::assertSent(AssessmentReminderMail::class);

        $this->artisan('assessment:send-reminders')
            ->expectsOutput('Generated 0 reminders. Skipped 1 assignments.')
            ->assertExitCode(0);

        $this->assertSame(1, AppNotification::where('type', 'assessment_reminder')->count());
        Mail::assertSent(AssessmentReminderMail::class, 1);
    }

    public function test_reminder_command_skips_non_frequency_days(): void
    {
        Mail::fake();

        $this->assessmentFixture(now()->subDay()->toDateString());

        $this->artisan('assessment:send-reminders')
            ->expectsOutput('Generated 0 reminders. Skipped 1 assignments.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 0);
        Mail::assertNothingSent();
    }

    public function test_user_can_view_and_mark_notifications_as_read(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Assessment Reminder',
            'message' => 'Please complete your pending assessment.',
            'type' => 'assessment_reminder',
        ]);

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Assessment Reminder')
            ->assertSee('Unread');

        $this->actingAs($user)
            ->patch("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'mark_read',
            'module' => 'notifications',
        ]);
    }

    public function test_navbar_endpoint_returns_unread_count_and_dropdown_html(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        AppNotification::create([
            'user_id' => $user->id,
            'title' => 'New IDP',
            'message' => 'A new IDP recommendation is available.',
            'type' => 'idp',
        ]);

        $this->actingAs($user)
            ->get('/notifications/navbar')
            ->assertOk()
            ->assertJsonPath('label', 1)
            ->assertJsonPath('label_color', 'danger')
            ->assertJsonFragment(['icon_color' => 'warning']);
    }

    public function test_notification_click_marks_read_and_redirects_only_to_safe_internal_destination(): void
    {
        $user = User::factory()->create(['role' => 'employee']);
        $notification = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Open assessment',
            'message' => 'Complete the pending assessment.',
            'type' => 'assessment_reminder',
            'destination_url' => '/assessment/pending',
        ]);

        $this->actingAs($user)
            ->patch("/notifications/{$notification->id}/read")
            ->assertRedirect('/assessment/pending')
            ->assertSessionHas('success');

        $this->assertNotNull($notification->fresh()->read_at);

        $unsafe = AppNotification::create([
            'user_id' => $user->id,
            'title' => 'Unsafe',
            'message' => 'Unsafe redirect attempt.',
            'type' => 'system',
            'destination_url' => 'https://example.com',
        ]);

        $this->actingAs($user)
            ->patch("/notifications/{$unsafe->id}/read")
            ->assertRedirect('/notifications');
    }

    private function assessmentFixture(string $startDate): array
    {
        $assessorUser = User::factory()->create([
            'role' => 'employee',
            'email' => 'assessor@example.com',
        ]);
        $assesseeUser = User::factory()->create(['role' => 'employee']);
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $period = AssessmentPeriod::create([
            'name' => 'Semester 1 2026',
            'semester' => 'Semester 1',
            'year' => 2026,
            'start_date' => $startDate,
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
            'threshold_score' => 3.00,
        ]);
        $assessor = Employee::create([
            'user_id' => $assessorUser->id,
            'department_id' => $department->id,
            'employee_number' => 'EMP-REM-001',
            'name' => 'Reminder Assessor',
            'email' => $assessorUser->email,
            'employment_status' => 'active',
        ]);
        $assessee = Employee::create([
            'user_id' => $assesseeUser->id,
            'department_id' => $department->id,
            'employee_number' => 'EMP-REM-002',
            'name' => 'Reminder Assessee',
            'email' => $assesseeUser->email,
            'employment_status' => 'active',
        ]);
        $assignment = AssessmentAssignment::create([
            'assessment_period_id' => $period->id,
            'assessor_employee_id' => $assessor->id,
            'assessee_employee_id' => $assessee->id,
            'assessor_type' => 'peer',
            'status' => 'pending',
        ]);

        return compact('assessorUser', 'assesseeUser', 'department', 'period', 'assessor', 'assessee', 'assignment');
    }
}
