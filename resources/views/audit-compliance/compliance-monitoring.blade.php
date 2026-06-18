@extends('adminlte::page')

@section('title', 'Compliance Monitoring')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="m-0">Compliance Monitoring</h1>
            <p class="text-muted mb-0">Monitor assessment completion, pending users, and overdue assignments.</p>
        </div>
        @if (auth()->user()->hasRole('admin_hr'))
            <form method="POST" action="{{ route('audit-compliance.compliance-monitoring.reminders') }}" class="mt-3 mt-md-0">
                @csrf
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-bell mr-1"></i> Send Reminders
                </button>
            </form>
        @endif
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @include('partials.flash')

    @if (! $period)
        <div class="alert alert-warning">
            Belum ada periode penilaian. Monitoring compliance akan tersedia setelah periode dan assignment dibuat.
            @if(auth()->user()->hasRole('admin_hr'))<a href="{{ route('assessment-cycle.periods.create') }}" class="btn btn-sm btn-warning ml-2">Buat Periode</a>@endif
        </div>
    @endif

    <x-adminlte-card title="Period Filter" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('audit-compliance.compliance-monitoring.index') }}">
            <div class="row">
                <div class="col-md-10">
                    <select name="period_id" class="form-control">
                        <option value="">All periods</option>
                        @foreach ($periods as $assessmentPeriod)
                            <option value="{{ $assessmentPeriod->id }}" @selected((int) $selectedPeriod === $assessmentPeriod->id)>
                                {{ $assessmentPeriod->name }} - {{ $assessmentPeriod->semester }} {{ $assessmentPeriod->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search mr-1"></i> Apply
                    </button>
                </div>
            </div>
            <a href="{{ route('audit-compliance.compliance-monitoring.index') }}" class="btn btn-sm btn-outline-secondary mt-2">Reset Filters</a>
        </form>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['total'] }}" text="Total Assignments" icon="fas fa-clipboard-list" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['submitted'] }}" text="Submitted" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['pending'] }}" text="Pending" icon="fas fa-hourglass-half" theme="warning"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['overdue'] }}" text="Overdue" icon="fas fa-exclamation-triangle" theme="danger"/></div>
    </div>
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['completionRate'] }}%" text="Completion Rate" icon="fas fa-chart-line" theme="info"/></div>
        <div class="col-lg-9">
            <x-adminlte-card title="Completion Progress" theme="info" icon="fas fa-tasks">
                <div class="progress">
                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $stats['completionRate'] }}%">
                        {{ $stats['completionRate'] }}%
                    </div>
                </div>
            </x-adminlte-card>
        </div>
    </div>

    <x-adminlte-card title="Pending Users" theme="warning" icon="fas fa-user-clock">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Assessor</th>
                        <th>Email</th>
                        <th>Assessee</th>
                        <th>Department</th>
                        <th>Assessor Type</th>
                        <th>Deadline</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingAssignments as $assignment)
                        <tr>
                            <td>{{ $assignment->assessor?->name ?? '-' }}</td>
                            <td>{{ $assignment->assessor?->user?->email ?? $assignment->assessor?->email ?? '-' }}</td>
                            <td>{{ $assignment->assessee?->name ?? '-' }}</td>
                            <td>{{ $assignment->assessee?->department?->name ?? '-' }}</td>
                            <td><span class="badge badge-info">{{ ucfirst($assignment->assessor_type) }}</span></td>
                            <td>{{ $assignment->assessmentPeriod?->end_date?->format('d M Y') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No pending assignments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $pendingAssignments->links() }}</div>
    </x-adminlte-card>

    <x-adminlte-card title="Overdue Assignments" theme="danger" icon="fas fa-calendar-times">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Assessor</th>
                        <th>Assessee</th>
                        <th>Period</th>
                        <th>Deadline</th>
                        <th>Days Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($overdueAssignments as $assignment)
                        <tr>
                            <td>{{ $assignment->assessor?->name ?? '-' }}</td>
                            <td>{{ $assignment->assessee?->name ?? '-' }}</td>
                            <td>{{ $assignment->assessmentPeriod?->name ?? '-' }}</td>
                            <td>{{ $assignment->assessmentPeriod?->end_date?->format('d M Y') ?? '-' }}</td>
                            <td>{{ $assignment->assessmentPeriod?->end_date?->diffInDays(now()) ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No overdue assignments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $overdueAssignments->links() }}</div>
    </x-adminlte-card>
@stop
