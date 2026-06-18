@extends('adminlte::page')

@section('title', 'Core Value Dashboard')

@section('content_header')
    <h1 class="m-0">Core Value Dashboard</h1>
    <p class="text-muted mb-0">Analitik rata-rata skor Core Values AKHLAK organisasi.</p>
    @include('partials.breadcrumbs')
@stop

@section('content')
    <x-adminlte-card title="Filters" theme="primary" icon="fas fa-filter">
        <form method="GET" action="{{ route('analytics.core-values.index') }}">
            <div class="row">
                <div class="col-md-5">
                    <label for="period_id">Assessment Period</label>
                    <select id="period_id" name="period_id" class="form-control">
                        @foreach ($periods as $period)
                            <option value="{{ $period->id }}" @selected((int) $selectedPeriod === $period->id)>
                                {{ $period->name }} - {{ $period->semester }} {{ $period->year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) $selectedDepartment === $department->id)>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary btn-block" type="submit">
                        <i class="fas fa-search mr-1"></i> Apply
                    </button>
                </div>
            </div>
            <a href="{{ route('analytics.core-values.index') }}" class="btn btn-sm btn-outline-secondary mt-3">Reset</a>
        </form>
    </x-adminlte-card>

    <div class="row">
        <div class="col-lg-3 col-6">
            <x-adminlte-small-box title="{{ $summary['total'] }}" text="Employees Analyzed" icon="fas fa-users" theme="primary"/>
        </div>
        <div class="col-lg-3 col-6">
            <x-adminlte-small-box title="{{ $summary['averageFinalScore'] !== null ? number_format($summary['averageFinalScore'], 2) : '-' }}" text="Overall Average Score" icon="fas fa-star-half-alt" theme="info"/>
        </div>
        <div class="col-lg-3 col-6">
            <x-adminlte-small-box title="{{ $summary['strongest']['label'] ?? '-' }}" text="Strongest Core Value" icon="fas fa-arrow-up" theme="success"/>
        </div>
        <div class="col-lg-3 col-6">
            <x-adminlte-small-box title="{{ $summary['weakest']['label'] ?? '-' }}" text="Weakest Core Value" icon="fas fa-arrow-down" theme="warning"/>
        </div>
    </div>

    @unless ($hasData)
        <div class="alert alert-light border">
            No core value assessment data available for the selected filters.
        </div>
    @endunless

    <div class="row">
        <div class="col-lg-6">
            <x-adminlte-card title="Average Score per AKHLAK Core Value" theme="primary" icon="fas fa-chart-bar">
                @if ($hasData)
                    <canvas id="coreValueBarChart" height="180"></canvas>
                @else
                    <div class="text-muted">No chart data available.</div>
                @endif
            </x-adminlte-card>
        </div>
        <div class="col-lg-6">
            <x-adminlte-card title="AKHLAK Core Value Profile" theme="info" icon="fas fa-chart-area">
                @if ($hasData)
                    <canvas id="coreValueRadarChart" height="180"></canvas>
                @else
                    <div class="text-muted">No chart data available.</div>
                @endif
            </x-adminlte-card>
        </div>
    </div>

    <x-adminlte-card title="Core Value Ranking" theme="success" icon="fas fa-table">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">Rank</th>
                        <th>Core Value</th>
                        <th class="text-right">Average Score</th>
                        <th>Interpretation</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ranking as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item['label'] }}</td>
                            <td class="text-right">{{ $item['average'] !== null ? number_format($item['average'], 2) : '-' }}</td>
                            <td>
                                <span class="badge badge-{{ match ($item['interpretation']) {
                                    'Sangat Baik' => 'success',
                                    'Baik' => 'primary',
                                    'Cukup' => 'info',
                                    'Kurang' => 'warning',
                                    'Sangat Kurang' => 'danger',
                                    default => 'secondary',
                                } }}">
                                    {{ $item['interpretation'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">No core value assessment data available for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte-card>
@stop

@section('js')
    @if ($hasData)
        <script>
            const coreValueChartData = {{ Illuminate\Support\Js::from($coreValueChart) }};

            if (document.getElementById('coreValueBarChart')) {
                new Chart(document.getElementById('coreValueBarChart'), {
                    type: 'bar',
                    data: {
                        labels: coreValueChartData.labels,
                        datasets: [{
                            label: 'Average Score',
                            data: coreValueChartData.data,
                            backgroundColor: ['#007bff', '#28a745', '#17a2b8', '#ffc107', '#6f42c1', '#20c997']
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 5
                            }
                        }
                    }
                });
            }

            if (document.getElementById('coreValueRadarChart')) {
                new Chart(document.getElementById('coreValueRadarChart'), {
                    type: 'radar',
                    data: {
                        labels: coreValueChartData.labels,
                        datasets: [{
                            label: 'Average Score',
                            data: coreValueChartData.data,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.18)',
                            pointBackgroundColor: '#007bff'
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 5
                            }
                        }
                    }
                });
            }
        </script>
    @endif
@stop
