@extends('adminlte::page')

@section('title', 'IT Admin Dashboard')

@section('content_header')
    <h1 class="m-0">IT Admin Dashboard</h1>
    <p class="text-muted mb-0">Technical monitoring based only on configuration and recorded execution evidence.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['activeUsers30Days'] }}" text="Pengguna Aktif 30 Hari" icon="fas fa-user-clock" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['auditToday'] }}" text="Audit Events Today" icon="fas fa-shield-alt" theme="info"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['successfulHrisSyncs'] }}" text="Successful HRIS Syncs" icon="fas fa-sync" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['failedHrisSyncs'] }}" text="Failed HRIS Syncs" icon="fas fa-times-circle" theme="danger"/></div>
    </div>
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['generatedExports'] }}" text="Generated Exports" icon="fas fa-file-export" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['failedExports'] }}" text="Failed Exports" icon="fas fa-file-excel" theme="danger"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['recordedReminderActivity'] }}" text="Recorded Reminder Activity" icon="fas fa-bell" theme="warning"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['failedJobs'] }}" text="Failed Jobs" icon="fas fa-bug" theme="danger"/></div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <x-adminlte-card title="Reminder Configuration" theme="primary" icon="fas fa-cog">
                <dl class="mb-0"><dt>Configured schedule</dt><dd>{{ $reminderConfiguration['schedule'] }}</dd><dt>Interval</dt><dd>Every {{ $reminderConfiguration['intervalDays'] }} days</dd><dt>Command</dt><dd><code>{{ $reminderConfiguration['command'] }}</code></dd><dt>Channels</dt><dd>Email: {{ $reminderConfiguration['emailEnabled'] ? 'enabled' : 'disabled' }}, In-app: {{ $reminderConfiguration['inAppEnabled'] ? 'enabled' : 'disabled' }}</dd></dl>
                <small class="text-muted">Configuration does not prove that a scheduler process is currently running.</small>
            </x-adminlte-card>
        </div>
        <div class="col-lg-4">
            <x-adminlte-card title="Latest Generated Reminder" theme="warning" icon="fas fa-bell">
                @if($latestGeneratedReminder)<p><strong>{{ $latestGeneratedReminder->title }}</strong></p><p>{{ $latestGeneratedReminder->message }}</p><small>{{ $latestGeneratedReminder->created_at->format('d M Y H:i') }}</small>@else<p class="text-muted mb-0">No generated reminder is recorded.</p>@endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-4">
            <x-adminlte-card title="Latest Recorded Reminder Activity" theme="info" icon="fas fa-history">
                @if($latestReminderActivity)<p><strong>{{ $latestReminderActivity->action }}</strong></p><p>{{ $latestReminderActivity->description }}</p><small>{{ $latestReminderActivity->created_at->format('d M Y H:i') }}</small>@else<p class="text-muted mb-0">No reminder command execution is recorded.</p>@endif
            </x-adminlte-card>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6"><x-adminlte-card title="Runtime / Configuration" theme="secondary" icon="fas fa-server"><table class="table table-sm mb-0">@foreach($runtime as $label => $value)<tr><th>{{ ucfirst($label) }}</th><td>{{ $value }}</td></tr>@endforeach<tr><th>Queued jobs</th><td>{{ $stats['queuedJobs'] }}</td></tr></table></x-adminlte-card></div>
        <div class="col-lg-6"><x-adminlte-card title="Compliance Checklist" theme="success" icon="fas fa-check-double"><table class="table table-sm mb-0">@foreach($complianceChecklist as $item)<tr><td>{{ $item['label'] }}</td><td class="text-right"><span class="badge badge-{{ $item['ok'] ? 'success' : 'warning' }}">{{ $item['ok'] ? 'Recorded/Configured' : 'Attention' }}</span></td></tr>@endforeach</table></x-adminlte-card></div>
    </div>

    <x-adminlte-card title="Recent HRIS Sync History" theme="primary" icon="fas fa-database"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Time</th><th>Type</th><th>Status</th><th>Records</th><th>Message</th></tr></thead><tbody>@forelse($hrisSyncLogs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ $log->sync_type }}</td><td>{{ $log->status }}</td><td>{{ $log->success_records }}/{{ $log->total_records }}</td><td>{{ $log->message }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No HRIS history.</td></tr>@endforelse</tbody></table></div></x-adminlte-card>
    <x-adminlte-card title="Report Export History" theme="success" icon="fas fa-file-export"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Time</th><th>User</th><th>Period</th><th>Type</th><th>Status</th></tr></thead><tbody>@forelse($exportLogs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ $log->user?->name ?? '-' }}</td><td>{{ $log->assessmentPeriod?->name ?? 'All periods' }}</td><td>{{ strtoupper($log->report_type) }}</td><td>{{ ucfirst($log->status) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No export history.</td></tr>@endforelse</tbody></table></div></x-adminlte-card>
    <x-adminlte-card title="Recorded Reminder Activity" theme="warning" icon="fas fa-bell"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Time</th><th>Action</th><th>Description</th></tr></thead><tbody>@forelse($reminderLogs as $log)<tr><td>{{ $log->created_at->format('d M Y H:i') }}</td><td>{{ $log->action }}</td><td>{{ $log->description }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">No reminder activity recorded.</td></tr>@endforelse</tbody></table></div></x-adminlte-card>
    <div class="row"><div class="col-md-4"><a href="{{ route('reports.history.index') }}" class="btn btn-outline-success btn-block mb-3">Export History</a></div><div class="col-md-4"><a href="{{ route('audit-compliance.audit-logs.index') }}" class="btn btn-outline-secondary btn-block mb-3">Audit Logs</a></div><div class="col-md-4"><a href="{{ route('system-settings.index') }}" class="btn btn-outline-primary btn-block mb-3">System Settings</a></div></div>
@stop
