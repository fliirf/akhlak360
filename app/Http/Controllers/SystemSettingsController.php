<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPeriod;
use App\Models\AssessmentWeight;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function index(): View
    {
        $activePeriod = AssessmentPeriod::active()->with('weights')->latest('start_date')->first();
        $configuredWeights = collect(config('akhlak360.default_weights'));
        $activeWeights = $activePeriod
            ? $activePeriod->weights->pluck('weight', 'assessor_type')->map(fn ($weight) => (float) $weight)
            : collect();

        return view('system-settings.index', [
            'settings' => [
                'applicationName' => config('akhlak360.application_name'),
                'defaultThreshold' => config('akhlak360.default_threshold_score'),
                'reminderInterval' => config('akhlak360.reminder_interval_days'),
                'emailNotifications' => config('akhlak360.email_notifications_enabled'),
                'inAppNotifications' => config('akhlak360.in_app_notifications_enabled'),
                'mailDriver' => config('mail.default'),
                'environment' => app()->environment(),
                'debug' => config('app.debug'),
                'database' => config('database.default'),
                'queue' => config('queue.default'),
            ],
            'activePeriod' => $activePeriod,
            'configuredWeights' => $configuredWeights,
            'activeWeights' => $activeWeights,
            'mvp' => config('akhlak360.mvp'),
            'system' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'pendingJobs' => DB::table('jobs')->count(),
                'failedJobs' => DB::table('failed_jobs')->count(),
                'weightRecords' => AssessmentWeight::count(),
            ],
        ]);
    }
}
