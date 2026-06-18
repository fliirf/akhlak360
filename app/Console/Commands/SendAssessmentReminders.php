<?php

namespace App\Console\Commands;

use App\Mail\AssessmentReminderMail;
use App\Models\AppNotification;
use App\Models\AssessmentAssignment;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAssessmentReminders extends Command
{
    protected $signature = 'assessment:send-reminders';

    protected $description = 'Send reminder notifications and email logs for pending assessments in active periods.';

    public function handle(): int
    {
        $interval = max(1, (int) config('akhlak360.reminder_interval_days', 3));
        $today = now()->startOfDay();
        $created = 0;
        $skipped = 0;
        $inAppEnabled = (bool) config('akhlak360.in_app_notifications_enabled', true);
        $emailEnabled = (bool) config('akhlak360.email_notifications_enabled', true);

        $assignments = AssessmentAssignment::query()
            ->with(['assessmentPeriod', 'assessor.user', 'assessee'])
            ->pending()
            ->whereHas('assessmentPeriod', fn ($query) => $query->active()->whereDate('end_date', '>=', $today))
            ->whereHas('assessor', fn ($query) => $query->active())
            ->get();

        foreach ($assignments as $assignment) {
            $period = $assignment->assessmentPeriod;
            $daysSinceStart = $period->start_date->startOfDay()->diffInDays($today, false);

            if ($daysSinceStart < 0 || $daysSinceStart % $interval !== 0) {
                $skipped++;

                continue;
            }

            $user = $assignment->assessor->user;

            if (! $user) {
                $skipped++;

                continue;
            }

            if (! $inAppEnabled && ! $emailEnabled) {
                $skipped++;

                continue;
            }

            $title = "Assessment Reminder #{$assignment->id}";
            $auditDescription = "Sent reminder for assessment assignment #{$assignment->id}.";
            $alreadySentToday = AuditLog::query()
                ->where('action', 'assessment_reminder_sent')
                ->where('module', 'notifications')
                ->where('description', $auditDescription)
                ->whereDate('created_at', $today)
                ->exists();

            if ($alreadySentToday) {
                $skipped++;

                continue;
            }

            $message = "Please complete your {$assignment->assessor_type} assessment for {$assignment->assessee->name} before {$period->end_date->format('d M Y')}.";

            if ($inAppEnabled) {
                AppNotification::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'message' => $message,
                    'type' => 'assessment_reminder',
                    'destination_url' => route('assessment.fill.show', $assignment, false),
                ]);
            }

            if ($emailEnabled) {
                Mail::to($user->email)->send(new AssessmentReminderMail($title, $message));
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'assessment_reminder_sent',
                'module' => 'notifications',
                'description' => $auditDescription,
                'ip_address' => null,
                'user_agent' => 'artisan assessment:send-reminders',
            ]);

            $created++;
        }

        AuditLog::create([
            'user_id' => null,
            'action' => 'send_reminders',
            'module' => 'notifications',
            'description' => "Assessment reminder command generated {$created} reminders and skipped {$skipped}.",
            'ip_address' => null,
            'user_agent' => 'artisan assessment:send-reminders',
        ]);

        $this->info("Generated {$created} reminders. Skipped {$skipped} assignments.");

        return self::SUCCESS;
    }
}
