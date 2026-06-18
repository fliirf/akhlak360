@extends('adminlte::page')

@section('title', 'Admin HR Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">Admin HR Dashboard</h1>
            <p class="text-muted mb-0">Monitoring operasional penilaian AKHLAK 360.</p>
        </div>
        <span class="badge badge-{{ $activePeriod ? 'success' : 'secondary' }} px-3 py-2">
            {{ $activePeriod ? $activePeriod->name : 'No Active Period' }}
        </span>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @if (! $activePeriod)
        <div class="alert alert-warning">
            <h5><i class="icon fas fa-calendar-times"></i> Tidak ada periode aktif</h5>
            Aktifkan atau buat periode agar statistik dashboard tidak mencampur data lintas periode.
            <a class="btn btn-sm btn-warning ml-2" href="{{ route('assessment-cycle.periods.create') }}">Buat Periode</a>
        </div>
    @elseif (! $hasAssignments)
        <div class="alert alert-info">
            Periode aktif belum memiliki assignment.
            <a class="btn btn-sm btn-info ml-2" href="{{ route('assessment-cycle.assign-assessors.index') }}">Generate Assignment</a>
        </div>
    @elseif (! $hasResults)
        <div class="alert alert-info">
            Assignment tersedia, tetapi hasil belum dikalkulasikan.
            <form class="d-inline ml-2" method="POST" action="{{ route('assessment-cycle.periods.recalculate', $activePeriod) }}">@csrf<button type="submit" class="btn btn-sm btn-info">Kalkulasi Ulang Hasil</button></form>
        </div>
    @endif
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['totalEmployees'] }}" text="Total Employees" icon="fas fa-users" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['totalAssignments'] }}" text="Total Assignments" icon="fas fa-clipboard-list" theme="info"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['submittedAssignments'] }}" text="Submitted Assignments" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['pendingAssignments'] }}" text="Pending Assignments" icon="fas fa-hourglass-half" theme="warning"/></div>
    </div>
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['completionRate'] }}%" text="Completion Rate" icon="fas fa-tasks" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['averageFinalScore'] ?? '-' }}" text="Average Final Score" icon="fas fa-star-half-alt" theme="primary"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $stats['belowThreshold'] }}" text="Below Threshold" icon="fas fa-exclamation-triangle" theme="danger"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $activePeriod?->threshold_score ?? '-' }}" text="Threshold Score" icon="fas fa-bullseye" theme="secondary"/></div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <x-adminlte-card title="Average Score per AKHLAK Core Value" theme="primary" icon="fas fa-chart-bar">
                @if (array_sum($coreValueChart['data']) > 0)
                    <canvas id="coreValueChart" height="120"></canvas>
                @else
                    <div class="alert alert-light mb-0">No assessment results available yet.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-4">
            <x-adminlte-card title="Completion Progress" theme="success" icon="fas fa-chart-pie">
                @if (array_sum($completionChart['data']) > 0)
                    <canvas id="completionChart" height="220"></canvas>
                @else
                    <div class="alert alert-light mb-0">No assignments available for the active period.</div>
                @endif
            </x-adminlte-card>
        </div>
    </div>

    <x-adminlte-card title="Recent Audit Logs" theme="secondary" icon="fas fa-history">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead><tr><th>Time</th><th>User</th><th>Module</th><th>Action</th><th>Description</th></tr></thead>
                <tbody>
                    @forelse ($recentAuditLogs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td><span class="badge badge-info">{{ $log->module }}</span></td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No audit logs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte-card>
@stop

@section('js')
    <script>
        const coreValueData = {{ Illuminate\Support\Js::from($coreValueChart) }};
        const completionData = {{ Illuminate\Support\Js::from($completionChart) }};

        if (document.getElementById('coreValueChart')) {
            new Chart(document.getElementById('coreValueChart'), {
                type: 'bar',
                data: { labels: coreValueData.labels, datasets: [{ label: 'Average Score', data: coreValueData.data, backgroundColor: '#007bff' }] },
                options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 5 } } }
            });
        }
        if (document.getElementById('completionChart')) {
            new Chart(document.getElementById('completionChart'), {
                type: 'doughnut',
                data: { labels: completionData.labels, datasets: [{ data: completionData.data, backgroundColor: ['#28a745', '#ffc107'] }] },
                options: { maintainAspectRatio: false }
            });
        }
    </script>
@stop
