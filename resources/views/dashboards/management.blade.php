@extends('adminlte::page')

@section('title', 'Management Dashboard')

@section('content_header')
    <h1 class="m-0">Management Dashboard</h1>
    <p class="text-muted mb-0">Analytical overview for AKHLAK 360 results.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('management.dashboard') }}">
            <div class="row">
                <div class="col-md-5">
                    <select name="period_id" class="form-control">
                        <option value="">All periods</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((int) $selectedPeriod === $period->id)>
                                {{ $period->name }} - {{ $period->semester }} {{ $period->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <select name="department_id" class="form-control">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) $selectedDepartment === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-search mr-1"></i> Apply</button>
                </div>
            </div>
        </form>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['companyAverage'] !== null ? number_format($summary['companyAverage'], 2) : '-' }}" text="Rata-rata Perusahaan" icon="fas fa-star" theme="primary"/></div>
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $summary['assessedEmployees'] }}" text="Pegawai Dinilai" icon="fas fa-user-check" theme="success"/></div>
        <div class="col-lg-4 col-12"><x-adminlte-small-box title="{{ $summary['belowThreshold'] }}" text="Di Bawah Threshold" icon="fas fa-exclamation-triangle" theme="danger"/></div>
    </div>

    <div class="row">
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $gapSummary['averageSelf'] ?? '-' }}" text="Average Self Score" icon="fas fa-user" theme="primary"/></div>
        <div class="col-lg-4 col-6"><x-adminlte-small-box title="{{ $gapSummary['averageOthers'] ?? '-' }}" text="Average Others Score" icon="fas fa-users" theme="info"/></div>
        <div class="col-lg-4 col-12"><x-adminlte-small-box title="{{ $gapSummary['averageGap'] ?? '-' }}" text="Self vs Others Gap" icon="fas fa-balance-scale" theme="warning"/></div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <x-adminlte-card title="Average Score per Core Value" theme="primary" icon="fas fa-chart-bar">
                @if (array_sum($coreValueChart['data']) > 0)
                    <canvas id="coreValueChart" height="150"></canvas>
                @else
                    <div class="alert alert-light mb-0">No core value data for this filter.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-6">
            <x-adminlte-card title="Distribution by Department" theme="success" icon="fas fa-building">
                @if (count($departmentChart['data']) > 0)
                    <canvas id="departmentChart" height="150"></canvas>
                @else
                    <div class="alert alert-light mb-0">No department distribution data.</div>
                @endif
            </x-adminlte-card>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <x-adminlte-card title="Semester Trend" theme="info" icon="fas fa-chart-line">
                @if (count($trendChart['data']) > 0)
                    <canvas id="trendChart" height="135"></canvas>
                @else
                    <div class="alert alert-light mb-0">No semester trend data.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-5">
            <x-adminlte-card title="Talent Mapping Category Counts" theme="warning" icon="fas fa-map">
                @if (count($talentMappingChart['data']) > 0)
                    <canvas id="talentChart" height="135"></canvas>
                @else
                    <div class="alert alert-light mb-0">No talent mapping data.</div>
                @endif
            </x-adminlte-card>
        </div>
    </div>

    <x-adminlte-card title="Employees Below Threshold" theme="danger" icon="fas fa-exclamation-triangle">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead><tr><th>Employee</th><th>Department</th><th class="text-right">Final Score</th><th>Category</th></tr></thead>
                <tbody>
                    @forelse ($belowThresholdEmployees as $result)
                        <tr>
                            <td>{{ $result->employee?->name }}</td>
                            <td>{{ $result->employee?->department?->name ?? '-' }}</td>
                            <td class="text-right">{{ $result->final_score }}</td>
                            <td><span class="badge badge-danger">{{ $result->category }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No employees below threshold for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte-card>
@stop

@section('js')
    <script>
        const coreValueData = {{ Illuminate\Support\Js::from($coreValueChart) }};
        const departmentData = {{ Illuminate\Support\Js::from($departmentChart) }};
        const trendData = {{ Illuminate\Support\Js::from($trendChart) }};
        const talentData = {{ Illuminate\Support\Js::from($talentMappingChart) }};

        const yScore = { beginAtZero: true, max: 5 };
        if (document.getElementById('coreValueChart')) new Chart(document.getElementById('coreValueChart'), {
            type: 'bar', data: { labels: coreValueData.labels, datasets: [{ label: 'Average Score', data: coreValueData.data, backgroundColor: '#007bff' }] },
            options: { maintainAspectRatio: false, scales: { y: yScore } }
        });
        if (document.getElementById('departmentChart')) new Chart(document.getElementById('departmentChart'), {
            type: 'bar', data: { labels: departmentData.labels, datasets: [{ label: 'Final Score', data: departmentData.data, backgroundColor: '#28a745' }] },
            options: { maintainAspectRatio: false, scales: { y: yScore } }
        });
        if (document.getElementById('trendChart')) new Chart(document.getElementById('trendChart'), {
            type: 'line', data: { labels: trendData.labels, datasets: [{ label: 'Average Final Score', data: trendData.data, borderColor: '#17a2b8', backgroundColor: 'rgba(23,162,184,.12)', fill: true }] },
            options: { maintainAspectRatio: false, scales: { y: yScore } }
        });
        if (document.getElementById('talentChart')) new Chart(document.getElementById('talentChart'), {
            type: 'doughnut', data: { labels: talentData.labels, datasets: [{ data: talentData.data, backgroundColor: ['#28a745', '#007bff', '#ffc107', '#dc3545'] }] },
            options: { maintainAspectRatio: false }
        });
    </script>
@stop
