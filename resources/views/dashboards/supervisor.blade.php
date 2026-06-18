@extends('adminlte::page')

@section('title', 'Supervisor Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div><h1 class="m-0">Supervisor Dashboard</h1><p class="text-muted mb-0">Direct-team progress and aggregate development insight.</p></div>
        <span class="badge badge-{{ $activePeriod ? 'success' : 'secondary' }} px-3 py-2">{{ $activePeriod?->name ?? 'No Active Period' }}</span>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @if (! $supervisor)<div class="alert alert-warning">Akun supervisor belum terhubung ke profil pegawai. Hubungi Admin HR.</div>@endif
    <div class="alert alert-info">Scores are aggregated across direct reports by assessor type. Individual peer/subordinate responses and assessor identities are never displayed.</div>
    <div class="row">
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $stats['teamMembers'] }}" text="Direct Reports" icon="fas fa-users" theme="primary"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $stats['teamCompletion'] }}%" text="Team Completion" icon="fas fa-tasks" theme="success"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $stats['pendingApprovals'] }}" text="Pending Approvals" icon="fas fa-user-check" theme="warning"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $stats['myPending'] }}" text="My Pending Tasks" icon="fas fa-edit" theme="info"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $stats['myOverdue'] }}" text="My Overdue Tasks" icon="fas fa-calendar-times" theme="danger"/></div>
        <div class="col-lg-2 col-6"><x-adminlte-small-box title="{{ $stats['developmentAttention'] }}" text="Development Attention" icon="fas fa-lightbulb" theme="warning"/></div>
    </div>

    <x-adminlte-card title="Direct Report Assessment Status" theme="primary" icon="fas fa-sitemap">
        <div class="table-responsive"><table class="table table-striped mb-0">
            <thead><tr><th>Employee</th><th>Department</th><th class="text-right">Submitted</th><th class="text-right">Pending</th><th class="text-right">Completion</th><th>Deadline</th></tr></thead>
            <tbody>@forelse($teamStatus as $row)<tr><td>{{ $row['employee']->name }}</td><td>{{ $row['employee']->department?->name ?? '-' }}</td><td class="text-right">{{ $row['submitted'] }}</td><td class="text-right">{{ $row['pending'] }}</td><td class="text-right">{{ $row['completion'] }}%</td><td>{{ $row['deadline']?->format('d M Y') ?? '-' }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted">No direct reports linked.</td></tr>@endforelse</tbody>
        </table></div>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-6">
            <x-adminlte-card title="Aggregated Score by Assessor Type" theme="success" icon="fas fa-chart-bar">
                <table class="table table-sm mb-0"><thead><tr><th>Type</th><th class="text-right">Submitted</th><th class="text-right">Average</th></tr></thead><tbody>
                    @foreach($assessorTypeAggregates as $row)<tr><td>{{ ucfirst($row['type']) }}</td><td class="text-right">{{ $row['assignments'] }}</td><td class="text-right">{{ $row['average'] !== null ? number_format($row['average'], 2) : '-' }}</td></tr>@endforeach
                </tbody></table>
            </x-adminlte-card>
        </div>
        <div class="col-lg-6">
            <x-adminlte-card title="Development Summary" theme="warning" icon="fas fa-lightbulb">
                <table class="table table-sm mb-0"><thead><tr><th>Core Value</th><th>Status</th><th class="text-right">Count</th></tr></thead><tbody>
                    @forelse($developmentSummary as $row)<tr><td>{{ $row->weakest_core_value }}</td><td>{{ ucfirst(str_replace('_', ' ', $row->status)) }}</td><td class="text-right">{{ $row->total }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">No team IDP data.</td></tr>@endforelse
                </tbody></table>
            </x-adminlte-card>
        </div>
    </div>
    <div class="row"><div class="col-md-4"><a href="{{ route('assessment.pending.index') }}" class="btn btn-primary btn-block mb-3">My Assessment Tasks</a></div><div class="col-md-4"><a href="{{ route('assessment-cycle.peer-approval.index') }}" class="btn btn-outline-primary btn-block mb-3">Peer Approvals</a></div><div class="col-md-4"><a href="{{ route('assessment.results.index') }}" class="btn btn-outline-success btn-block mb-3">Team Results</a></div></div>
@stop
