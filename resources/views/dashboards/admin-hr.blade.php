@extends('adminlte::page')

@section('title', 'Admin HR Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">Admin HR Dashboard</h1>
            <p class="text-muted mb-0">Pusat monitoring operasional penilaian AKHLAK 360.</p>
        </div>
        <span class="badge badge-{{ $activePeriod ? 'success' : 'secondary' }} px-3 py-2">{{ $activePeriod?->name ?? 'No Active Period' }}</span>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @if ($alerts['noActivePeriod'])
        <div class="alert alert-warning">Tidak ada periode aktif. <a href="{{ route('assessment-cycle.periods.create') }}" class="btn btn-sm btn-warning ml-2">Buat Periode</a></div>
    @elseif ($alerts['noAssignments'])
        <div class="alert alert-info">Periode aktif belum memiliki assignment. <a href="{{ route('assessment-cycle.assign-assessors.index') }}" class="btn btn-sm btn-info ml-2">Kelola Assignment</a></div>
    @endif

    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['activeEmployees'] }}" text="Pegawai Aktif" icon="fas fa-users" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['totalAssignments'] }}" text="Total Assignment Periode" icon="fas fa-clipboard-list" theme="info"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['submittedAssignments'] }}" text="Submitted" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['pendingAssignments'] }}" text="Pending" icon="fas fa-hourglass-half" theme="warning"/></div>
    </div>
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['overdueAssignments'] }}" text="Overdue Semua Periode" icon="fas fa-calendar-times" theme="danger"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['completionRate'] }}%" text="Completion Rate" icon="fas fa-tasks" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['belowThreshold'] }}" text="Below Threshold" icon="fas fa-exclamation-triangle" theme="danger"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $alerts['openIdp'] }}" text="Open IDP" icon="fas fa-lightbulb" theme="warning"/></div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <x-adminlte-card title="Progress per Assessor Type" theme="primary" icon="fas fa-chart-bar">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Type</th><th class="text-right">Submitted</th><th class="text-right">Pending</th><th class="text-right">Completion</th></tr></thead>
                    <tbody>@foreach($assessorProgress as $row)<tr><td>{{ ucfirst($row['type']) }}</td><td class="text-right">{{ $row['submitted'] }}</td><td class="text-right">{{ $row['pending'] }}</td><td class="text-right">{{ $row['completion'] }}%</td></tr>@endforeach</tbody>
                </table>
            </x-adminlte-card>
        </div>
        <div class="col-lg-7">
            <x-adminlte-card title="Department Completion" theme="success" icon="fas fa-building">
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th>Department</th><th class="text-right">Employees</th><th class="text-right">Assessed</th><th class="text-right">Completion</th><th class="text-right">Below Threshold</th></tr></thead>
                    <tbody>@forelse($departmentRows as $row)<tr><td>{{ $row['name'] }}</td><td class="text-right">{{ $row['employee_count'] }}</td><td class="text-right">{{ $row['assessed_count'] }}</td><td class="text-right">{{ $row['completion_percentage'] }}%</td><td class="text-right">{{ $row['below_threshold_count'] }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No department data.</td></tr>@endforelse</tbody>
                </table></div>
            </x-adminlte-card>
        </div>
    </div>

    <x-adminlte-card title="Employees Requiring Attention" theme="danger" icon="fas fa-user-clock">
        <div class="table-responsive"><table class="table table-striped mb-0">
            <thead><tr><th>Employee</th><th>Department</th><th>Category</th><th>IDP Status</th></tr></thead>
            <tbody>@forelse($attentionResults as $item)@php($idp = $item->employee?->idpRecommendations->first())<tr><td>{{ $item->employee?->name ?? '-' }}</td><td>{{ $item->employee?->department?->name ?? '-' }}</td><td>{{ $item->category ?? '-' }}</td><td>{{ $idp ? ucfirst(str_replace('_', ' ', $idp->status)) : 'Not created' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">No employees require attention.</td></tr>@endforelse</tbody>
        </table></div>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-4"><a href="{{ route('assessment-cycle.assign-assessors.index') }}" class="btn btn-primary btn-block mb-3">Kelola Assignment</a></div>
        <div class="col-lg-4"><a href="{{ route('audit-compliance.compliance-monitoring.index') }}" class="btn btn-outline-primary btn-block mb-3">Compliance Monitoring</a></div>
        <div class="col-lg-4"><a href="{{ route('audit-compliance.audit-logs.index') }}" class="btn btn-outline-secondary btn-block mb-3">Audit Logs</a></div>
    </div>

    <div class="row">
        <div class="col-lg-4"><x-adminlte-card title="Recent Submissions" theme="success" icon="fas fa-check">@forelse($recentSubmissions as $item)<p class="mb-2"><strong>{{ $item->assessee?->name ?? '-' }}</strong><br><small>{{ ucfirst($item->assessor_type) }} · {{ $item->submitted_at?->format('d M Y H:i') }}</small></p>@empty<p class="text-muted mb-0">No submissions.</p>@endforelse</x-adminlte-card></div>
        <div class="col-lg-4"><x-adminlte-card title="Recent HRIS Activity" theme="info" icon="fas fa-sync">@forelse($recentHrisSyncs as $item)<p class="mb-2"><strong>{{ ucfirst($item->status) }}</strong> · {{ $item->success_records }}/{{ $item->total_records }}<br><small>{{ $item->created_at->format('d M Y H:i') }}</small></p>@empty<p class="text-muted mb-0">No HRIS activity.</p>@endforelse</x-adminlte-card></div>
        <div class="col-lg-4"><x-adminlte-card title="Recent Audit Logs" theme="secondary" icon="fas fa-history">@forelse($recentAuditLogs as $item)<p class="mb-2"><strong>{{ $item->module }}</strong> · {{ $item->action }}<br><small>{{ $item->created_at->format('d M Y H:i') }}</small></p>@empty<p class="text-muted mb-0">No audit activity.</p>@endforelse</x-adminlte-card></div>
    </div>
@stop
