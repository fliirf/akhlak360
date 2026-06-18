@extends('adminlte::page')

@section('title', 'Employee Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div><h1 class="m-0">Employee Dashboard</h1><p class="text-muted mb-0">{{ $employee?->name ?? 'No employee profile linked' }}</p></div>
        <span class="badge badge-{{ $activePeriod ? 'success' : 'secondary' }} px-3 py-2">{{ $activePeriod?->name ?? 'No Active Period' }}</span>
    </div>
    @include('partials.breadcrumbs')
@stop

@section('content')
    @if (! $employee)<div class="alert alert-warning">Akun Anda belum terhubung ke profil pegawai. Hubungi Admin HR.</div>@endif

    <h4 class="mb-3"><i class="fas fa-clipboard-check mr-2"></i>Tugas Saya sebagai Assessor</h4>
    <div class="row">
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $taskStats['pending'] }}" text="Pending Tasks" icon="fas fa-hourglass-half" theme="warning"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $taskStats['overdue'] }}" text="Overdue Tasks" icon="fas fa-calendar-times" theme="danger"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $taskStats['completed'] }}" text="Completed as Assessor" icon="fas fa-check-circle" theme="success"/></div>
        <div class="col-lg-3 col-6"><x-adminlte-small-box title="{{ $taskStats['nearestDeadline']?->format('d M Y') ?? '-' }}" text="Nearest Deadline" icon="fas fa-clock" theme="info"/></div>
    </div>
    <x-adminlte-card title="Assessment Task List" theme="warning" icon="fas fa-list">
        <div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Assessee</th><th>Department</th><th>Assessor Type</th><th>Deadline</th><th></th></tr></thead><tbody>
            @forelse($pendingTasks as $task)<tr><td>{{ $task->assessee?->name ?? '-' }}</td><td>{{ $task->assessee?->department?->name ?? '-' }}</td><td>{{ ucfirst($task->assessor_type) }}</td><td>{{ $task->assessmentPeriod?->end_date?->format('d M Y') ?? '-' }}</td><td class="text-right"><a href="{{ route('assessment.fill.show', $task) }}" class="btn btn-sm btn-primary">Fill</a></td></tr>@empty<tr><td colspan="5" class="text-center text-muted">No pending assessment tasks.</td></tr>@endforelse
        </tbody></table></div>
    </x-adminlte-card>
    <x-adminlte-card title="Submission History as Assessor" theme="success" icon="fas fa-history">
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Period</th><th>Assessee</th><th>Type</th><th>Submitted At</th></tr></thead><tbody>@forelse($submissionHistory as $item)<tr><td>{{ $item->assessmentPeriod?->name ?? '-' }}</td><td>{{ $item->assessee?->name ?? '-' }}</td><td>{{ ucfirst($item->assessor_type) }}</td><td>{{ $item->submitted_at?->format('d M Y H:i') ?? '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">No submission history.</td></tr>@endforelse</tbody></table></div>
    </x-adminlte-card>

    <hr class="my-4">
    <h4 class="mb-3"><i class="fas fa-chart-line mr-2"></i>Hasil Personal Saya sebagai Assessee</h4>
    @if (! $result)<div class="alert alert-info">Personal assessment result for the active period is not available yet.</div>@endif
    <div class="row">
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $result?->final_score ?? '-' }}" text="Personal Final Score" icon="fas fa-star" theme="primary"/></div>
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $result?->gap_score ?? '-' }}" text="Self vs Others Gap" icon="fas fa-balance-scale" theme="info"/></div>
        <div class="col-lg-4 col-12"><x-adminlte-small-box title="{{ $result?->category ?? '-' }}" text="Personal Category" icon="fas fa-tag" theme="success"/></div>
    </div>
    <div class="row">
        <div class="col-lg-7"><x-adminlte-card title="Personal Core Value Result" theme="primary" icon="fas fa-chart-bar">@if($result)<canvas id="personalCoreChart" height="140"></canvas>@else<p class="text-muted mb-0">No personal result.</p>@endif</x-adminlte-card></div>
        <div class="col-lg-5"><x-adminlte-card title="Aggregated Scores by Assessor Type" theme="info" icon="fas fa-users"><table class="table table-sm mb-0"><thead><tr><th>Type</th><th class="text-right">Average</th></tr></thead><tbody>@foreach($assessorTypeAggregates as $row)<tr><td>{{ ucfirst($row['type']) }}</td><td class="text-right">{{ $row['average'] !== null ? number_format($row['average'], 2) : '-' }}</td></tr>@endforeach</tbody></table><small class="text-muted">No assessor identity or individual peer/subordinate response is shown.</small></x-adminlte-card></div>
    </div>
    <x-adminlte-card title="Historical Personal Trend" theme="secondary" icon="fas fa-chart-line">@if(count($resultTrend['data']))<canvas id="resultTrendChart" height="80"></canvas>@else<p class="text-muted mb-0">No historical results.</p>@endif</x-adminlte-card>
    <x-adminlte-card title="Personal IDP Recommendation" theme="warning" icon="fas fa-lightbulb">@if($idp)<dl class="row mb-0"><dt class="col-md-3">Weakest Core Value</dt><dd class="col-md-9">{{ $idp->weakest_core_value }}</dd><dt class="col-md-3">Recommendation</dt><dd class="col-md-9">{{ $idp->recommendation }}</dd><dt class="col-md-3">Status</dt><dd class="col-md-9">{{ ucfirst(str_replace('_', ' ', $idp->status)) }}</dd><dt class="col-md-3">Due Date</dt><dd class="col-md-9">{{ $idp->due_date?->format('d M Y') ?? '-' }}</dd></dl>@else<p class="text-muted mb-0">No personal IDP recommendation.</p>@endif</x-adminlte-card>
@stop

@section('js')
    <script>
        const personalCoreData = {{ Illuminate\Support\Js::from($personalCoreChart) }};
        const trendData = {{ Illuminate\Support\Js::from($resultTrend) }};
        const core = document.getElementById('personalCoreChart');
        if (core) new Chart(core, { type: 'bar', data: { labels: personalCoreData.labels, datasets: [{ label: 'Score', data: personalCoreData.data, backgroundColor: '#007bff' }] }, options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 5 } } } });
        const trend = document.getElementById('resultTrendChart');
        if (trend) new Chart(trend, { type: 'line', data: { labels: trendData.labels, datasets: [{ label: 'Final Score', data: trendData.data, borderColor: '#6c757d' }] }, options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 5 } } } });
    </script>
@stop
